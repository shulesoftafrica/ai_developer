<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\Milestone;
use App\Enums\TaskStatus;
use App\Enums\MilestoneStatus;
use App\Enums\AgentType;
use App\Services\AgentClient;
use App\Services\FilePatcher;
use App\Services\CommandRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Cache;

class ProcessNewTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hour timeout
    public int $tries = 2;

    protected Task $task;
    protected string $runId;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->runId = Uuid::uuid4()->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(
        AgentClient $agentClient,
        FilePatcher $filePatcher,
        CommandRunner $commandRunner
    ): void {
        Log::info('ProcessNewTask: Starting task processing', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);

        try {
            // Step 1: PM Agent - Create milestone plan
            $this->createMilestonePlan($agentClient);

            // Step 2: Execute milestones in sequence
            $this->executeMilestones($agentClient, $filePatcher, $commandRunner);

            // Step 3: Final validation and completion
            $this->completeTask($agentClient, $commandRunner);
        } catch (\Exception $e) {
            Log::error('ProcessNewTask: Task processing failed', [
                'task_id' => $this->task->id,
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            $this->task->transitionTo(TaskStatus::FAILED);
            $this->task->unlock();
            throw $e;
        }
    }

    protected function createMilestonePlan(AgentClient $agentClient): void
    {
        Log::info('ProcessNewTask: Creating milestone plan', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);

        try {
            $planResponse = $agentClient->planTask([
                'id' => $this->task->id,
                'type' => $this->task->type->value,
                'title' => $this->task->title,
                'description' => $this->task->description,
                'content' => $this->task->content,
            ], $this->runId);
        } catch (\Exception $e) {
            Log::error('Exception in planTask', [
                'task_id' => $this->task->id,
                'run_id' => $this->runId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        Log::info('PlanTask response received', [
            'task_id' => $this->task->id,
            'success' => $planResponse['success'] ?? 'not_set',
            'data_keys' => isset($planResponse['data']) ? array_keys($planResponse['data']) : 'no_data',
            'raw_content_preview' => isset($planResponse['raw_content']) ? substr($planResponse['raw_content'], 0, 200) : 'no_raw_content'
        ]);

        // Log::info('PlanTask raw response', [
        //     'task_id' => $this->task->id,
        //     'raw_content' => $planResponse['raw_content'] ?? 'no_raw_content',
        //     'data' => $planResponse['data'] ?? 'no_data'
        // ]);

        // Log::debug('Parsing milestones from response', [
        //     'task_id' => $this->task->id,
        //     'response' => $planResponse
        // ]);

        if (!$planResponse['success']) {
            throw new \Exception('Failed to create milestone plan');
        }

        // Debug logging to see what we're getting from the agent
        Log::debug('PM Agent Response', [
            'task_id' => $this->task->id,
            'response' => substr(json_encode($planResponse), 0, 200) // Log first 200 chars
        ]);

        // Try to get milestones from the response
        $milestones = [];

        // First, try to get from structured data
        if (isset($planResponse['data']['milestones']) && is_array($planResponse['data']['milestones'])) {
            $milestones = $planResponse['data']['milestones'];
            Log::debug('Found structured milestones', ['count' => count($milestones)]);
        }
        // If no structured milestones, try to parse from raw response
        elseif (isset($planResponse['data']['response'])) {
            Log::debug('Attempting to parse milestones from response text');
            $milestones = $this->parseMilestonesFromText($planResponse['data']['response']);
        }
        // Try from raw content as fallback
        elseif (isset($planResponse['raw_content'])) {
            Log::debug('Attempting to parse milestones from raw content');
            $milestones = $this->parseMilestonesFromText($planResponse['raw_content']);
        }

        if (empty($milestones)) {
            Log::warning('No milestones found in plan response, using default milestones', [
                'task_id' => $this->task->id,
                'response_keys' => array_keys($planResponse)
            ]);

            // Create a default milestone structure
            $milestones = $this->createDefaultMilestones();
        }

        Log::info('Final milestones to create', [
            'task_id' => $this->task->id,
            'milestone_count' => count($milestones),
            'milestones' => array_map(function ($m) {
                return ['title' => $m['title'], 'agent_type' => $m['agent_type']];
            }, $milestones)
        ]);

        if (empty($milestones)) {
            Log::error('Milestones array is empty before creation loop', [
                'task_id' => $this->task->id,
                'plan_response_keys' => array_keys($planResponse)
            ]);
            return;
        }

        $createdCount = 0;
        foreach ($milestones as $index => $milestoneData) {
            Log::debug('Processing milestone', [
                'task_id' => $this->task->id,
                'index' => $index,
                'milestone_data' => $milestoneData
            ]);

            try {
                Milestone::create([
                    'task_id' => $this->task->id,
                    'sequence' => $index + 1,
                    'title' => $milestoneData['title'],
                    'description' => $milestoneData['description'],
                    'agent_type' => AgentType::from($milestoneData['agent_type']),
                    'input_data' => $milestoneData['input_data'] ?? [],
                    'metadata' => $milestoneData['metadata'] ?? [],
                ]);
                $createdCount++;
                Log::debug('Created milestone', [
                    'task_id' => $this->task->id,
                    'sequence' => $index + 1,
                    'title' => $milestoneData['title'],
                    'agent_type' => $milestoneData['agent_type']
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create milestone', [
                    'task_id' => $this->task->id,
                    'milestone_data' => $milestoneData,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('ProcessNewTask: Created milestones', [
            'task_id' => $this->task->id,
            'milestone_count' => $createdCount,
        ]);
    }

    protected function executeMilestones(
        AgentClient $agentClient,
        FilePatcher $filePatcher,
        CommandRunner $commandRunner
    ): void {
        $milestones = $this->task->milestones()->orderBy('sequence')->get();

        foreach ($milestones as $milestone) {
            Log::info('ProcessNewTask: Executing milestone', [
                'task_id' => $this->task->id,
                'milestone_id' => $milestone->id,
                'sequence' => $milestone->sequence,
                'agent_type' => $milestone->agent_type->value,
            ]);

            // Locking mechanism to ensure sequential execution
            $lock = Cache::lock("milestone_{$milestone->id}_lock", 30);

            if ($lock->get()) {
                try {
                    $milestone->start();

                    $result = $this->executeMilestone($milestone, $agentClient, $filePatcher, $commandRunner);

                    $milestone->complete($result);
                } catch (\Exception $e) {
                    Log::error('ProcessNewTask: Milestone failed', [
                        'milestone_id' => $milestone->id,
                        'error' => $e->getMessage(),
                    ]);

                    $milestone->fail($e->getMessage());
                    throw $e;
                } finally {
                    $lock->release();
                }
            } else {
                Log::warning("Milestone {$milestone->id} is already being processed.");
            }
        }
    }

    protected function executeMilestone(
        Milestone $milestone,
        AgentClient $agentClient,
        FilePatcher $filePatcher,
        CommandRunner $commandRunner
    ): array {
        $context = [
            'task' => $this->task->toArray(),
            'milestone' => $milestone->toArray(),
            'input_data' => $milestone->input_data,
        ];

        switch ($milestone->agent_type) {
            case AgentType::PM:
                return $this->executePMMilestone($agentClient, $context);

            case AgentType::BA:
                return $this->executeBAMilestone($agentClient, $context);

            case AgentType::UX:
                return $this->executeUXMilestone($agentClient, $context);

            case AgentType::ARCH:
                return $this->executeArchMilestone($agentClient, $context, $filePatcher);

            case AgentType::DEV:
                return $this->executeDevMilestone($agentClient, $context, $filePatcher);

            case AgentType::QA:
                // Perform tests only if the agent is QA
                return $this->executeQAMilestone($agentClient, $context, $commandRunner, $filePatcher);

            case AgentType::DOC:
                return $this->executeDocMilestone($agentClient, $context, $filePatcher);

            default:
                throw new \Exception("Unsupported agent type: {$milestone->agent_type->value}");
        }
    }

    protected function executePMMilestone(AgentClient $agentClient, array $context): array
    {
        return $agentClient->planTask(
            $context['task'],
            $this->runId
        );
    }

    protected function executeUXMilestone(AgentClient $agentClient, array $context): array
    {
        return $agentClient->designUserExperience(
            $context['task'],
            $context['input_data'] ?? [],
            $this->runId
        );
    }

    protected function executeBAMilestone(AgentClient $agentClient, array $context): array
    {
        return $agentClient->analyzeRequirements(
            $context['task'],
            $this->runId
        );
    }

    protected function executeArchMilestone(
        AgentClient $agentClient,
        array $context,
        FilePatcher $filePatcher
    ): array {
        // Get existing file structure
        $files = $filePatcher->listFiles();
        $context['existing_files'] = $files;

        return $agentClient->designArchitecture(
            $context['task'],
            $context['input_data'] ?? [],
            $this->runId
        );
    }

    protected function executeDevMilestone(
        AgentClient $agentClient,
        array $context,
        FilePatcher $filePatcher
    ): array {
        // Use ClaudeAgentService for structured plan-based approach
        $claudeService = app(\App\Services\ClaudeAgentService::class);

        // Enhanced context with existing code structure
        $existingFiles = $filePatcher->listFiles();

        // Build comprehensive context for Claude restructuring
        $contextString = $this->buildDevContext([
            'task' => $context['task'],
            'input_data' => $context['input_data'] ?? [],
            'existing_files' => $existingFiles,
            'project_structure' => $this->getProjectStructure($filePatcher),
            'existing_code' => $this->getExistingCodeContext($filePatcher)
        ]);

        // Get structured plan from Claude
        $actions = $claudeService->getRestructurePlan($contextString);

        Log::info('DEV agent received structured plan', [
            'task_id' => $this->task->id,
            'actions_count' => count($actions),
            'run_id' => $this->runId
        ]);

        // Execute the structured plan directly
        $executionResults = $claudeService->executePlan($actions);

        Log::info('DEV agent plan execution completed', [
            'task_id' => $this->task->id,
            'execution_results' => $executionResults,
            'run_id' => $this->runId
        ]);

        return [
            'actions' => $actions,
            'execution_results' => $executionResults,
            'data' => [
                'actions_count' => count($actions),
                'successful_actions' => count(array_filter($executionResults, function ($r) {
                    return !str_contains($r, 'FAIL') && !str_contains($r, 'not found');
                }))
            ]
        ];
    }

    /**
     * Get project structure information
     */
    private function getProjectStructure(FilePatcher $filePatcher): array
    {
        try {
            $files = $filePatcher->listFiles();
            $structure = [
                'total_files' => count($files),
                'directories' => [],
                'file_types' => []
            ];

            foreach ($files as $file) {
                $dir = dirname($file);
                $ext = pathinfo($file, PATHINFO_EXTENSION);

                if (!in_array($dir, $structure['directories'])) {
                    $structure['directories'][] = $dir;
                }

                if (!isset($structure['file_types'][$ext])) {
                    $structure['file_types'][$ext] = 0;
                }
                $structure['file_types'][$ext]++;
            }

            return $structure;
        } catch (\Exception $e) {
            Log::warning('Failed to get project structure', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get existing code context for better code generation
     */
    private function getExistingCodeContext(FilePatcher $filePatcher): string
    {
        try {
            $files = $filePatcher->listFiles();
            $context = "Existing project files:\n";

            foreach (array_slice($files, 0, 30) as $file) { // Limit to first 20 files
                $context .= "- $file\n";
            }

            if (count($files) > 20) {
                $context .= "... and " . (count($files) - 20) . " more files\n";
            }

            return $context;
        } catch (\Exception $e) {
            Log::warning('Failed to get existing code context', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Build comprehensive context string for DEV agent Claude restructuring
     */
    private function buildDevContext(array $contextData): string
    {
        $context = "DEV AGENT TASK:\n";
        $context .= "================\n\n";

        // Task information
        $context .= "TASK: " . ($contextData['task']['title'] ?? 'Unknown Task') . "\n";
        $context .= "DESCRIPTION: " . ($contextData['task']['description'] ?? 'No description') . "\n";
        $context .= "TYPE: " . ($contextData['task']['type'] ?? 'Unknown') . "\n\n";

        // Input data
        if (!empty($contextData['input_data'])) {
            $context .= "INPUT DATA:\n";
            foreach ($contextData['input_data'] as $key => $value) {
                if (is_string($value)) {
                    $context .= "- $key: $value\n";
                } else {
                    $context .= "- $key: " . json_encode($value) . "\n";
                }
            }
            $context .= "\n";
        }

        // Project structure
        if (!empty($contextData['project_structure'])) {
            $context .= "EXISTING PROJECT STRUCTURE:\n";
            $structure = $contextData['project_structure'];
            if (isset($structure['directories'])) {
                $context .= "Directories: " . implode(', ', array_slice($structure['directories'], 0, 10)) . "\n";
            }
            if (isset($structure['file_types'])) {
                $context .= "File types: ";
                foreach ($structure['file_types'] as $ext => $count) {
                    $context .= "$ext($count) ";
                }
                $context .= "\n";
            }
            $context .= "\n";
        }

        // Existing files context
        if (!empty($contextData['existing_code'])) {
            $context .= $contextData['existing_code'] . "\n\n";
        }

        // Detect programming language and generate specific instructions
        $detectedLanguage = $this->detectProgrammingLanguage($contextData);
        $context .= $this->buildLanguageSpecificInstructions($detectedLanguage);

        return $context;
    }

    /**
     * Detect the primary programming language from project structure
     */
    private function detectProgrammingLanguage(array $contextData): string
    {
        $fileTypes = $contextData['project_structure']['file_types'] ?? [];
        $directories = $contextData['project_structure']['directories'] ?? [];

        // Define language detection patterns
        $languagePatterns = [
            'PHP' => [
                'file_extensions' => ['php', 'phps'],
                'directory_indicators' => ['vendor/', 'app/', 'public/'],
                'framework_indicators' => ['artisan', 'composer.json', 'bootstrap/cache']
            ],
            'JavaScript/Node.js' => [
                'file_extensions' => ['js', 'mjs', 'jsx', 'ts', 'tsx'],
                'directory_indicators' => ['node_modules/', 'public/', 'src/'],
                'framework_indicators' => ['package.json', 'yarn.lock', 'webpack.config.js']
            ],
            'Python' => [
                'file_extensions' => ['py', 'pyc', 'pyo', 'pyw'],
                'directory_indicators' => ['venv/', '__pycache__/', 'src/'],
                'framework_indicators' => ['requirements.txt', 'Pipfile', 'setup.py', 'manage.py']
            ],
            'Java' => [
                'file_extensions' => ['java', 'class', 'jar', 'war'],
                'directory_indicators' => ['src/main/', 'src/test/', 'target/'],
                'framework_indicators' => ['pom.xml', 'build.gradle', 'application.properties']
            ],
            'C#' => [
                'file_extensions' => ['cs', 'csx', 'csproj'],
                'directory_indicators' => ['bin/', 'obj/', 'Properties/'],
                'framework_indicators' => ['packages.config', 'project.json', '*.csproj']
            ],
            'Go' => [
                'file_extensions' => ['go'],
                'directory_indicators' => ['vendor/', 'pkg/', 'src/'],
                'framework_indicators' => ['go.mod', 'go.sum', 'main.go']
            ],
            'Ruby' => [
                'file_extensions' => ['rb', 'erb'],
                'directory_indicators' => ['lib/', 'app/', 'config/'],
                'framework_indicators' => ['Gemfile', 'config.ru', 'Rakefile']
            ],
            'Rust' => [
                'file_extensions' => ['rs', 'rlib'],
                'directory_indicators' => ['src/', 'target/', 'cargo/'],
                'framework_indicators' => ['Cargo.toml', 'Cargo.lock']
            ],
            'TypeScript' => [
                'file_extensions' => ['ts', 'tsx', 'd.ts'],
                'directory_indicators' => ['src/', 'dist/', 'build/'],
                'framework_indicators' => ['tsconfig.json', 'package.json']
            ],
            'HTML/CSS' => [
                'file_extensions' => ['html', 'htm', 'css', 'scss', 'sass', 'less'],
                'directory_indicators' => ['css/', 'js/', 'img/'],
                'framework_indicators' => ['index.html']
            ]
        ];

        // Count language matches
        $languageScores = [];

        foreach ($languagePatterns as $language => $pattern) {
            $score = 0;

            // Check file extensions (weighted heavily)
            foreach ($pattern['file_extensions'] as $ext) {
                if (isset($fileTypes[$ext])) {
                    $score += $fileTypes[$ext] * 2; // Weight file counts more
                }
            }

            // Check directory indicators
            foreach ($pattern['directory_indicators'] as $dir) {
                if (in_array($dir, $directories)) {
                    $score += 1;
                }
            }

            // Check framework-specific files (highest weight)
            if (!empty($contextData['existing_code'])) {
                foreach ($pattern['framework_indicators'] as $indicator) {
                    if (strpos($contextData['existing_code'], $indicator) !== false) {
                        $score += 5;
                    }
                }
            }

            if ($score > 0) {
                $languageScores[$language] = $score;
            }
        }

        // Special detection for Laravel PHP
        if (isset($languageScores['PHP'])) {
            // Laravel indicators
            $laravelIndicators = ['artisan', 'composer.json', 'bootstrap/', 'app/', 'routes/'];
            $laravelScore = 0;

            foreach ($laravelIndicators as $indicator) {
                if (strpos($contextData['existing_code'] ?? '', $indicator) !== false) {
                    $laravelScore += 1;
                }
                if (in_array(rtrim($indicator, '/'), $directories)) {
                    $laravelScore += 2;
                }
            }

            if ($laravelScore >= 3) {
                return 'PHP/Laravel';
            }
        }

        // Special detection for React.js
        if (isset($languageScores['JavaScript/Node.js'])) {
            $reactIndicators = ['src/components/', 'public/index.html', 'src/App.js', 'src/index.js'];
            $reactScore = 0;

            foreach ($reactIndicators as $indicator) {
                if (strpos($contextData['existing_code'] ?? '', $indicator) !== false) {
                    $reactScore += 1;
                }
            }

            if ($reactScore >= 2) {
                return 'JavaScript/React';
            }
        }

        // Return the language with the highest score, fallback to 'Unknown'
        if (empty($languageScores)) {
            return 'Unknown';
        }

        arsort($languageScores);
        $primaryLanguage = key($languageScores);

        // Add framework detection for known patterns
        $frameworkMap = [
            'JavaScript/Node.js' => 'Node.js',
            'Python' => 'Python',
            'Java' => 'Java',
            'C#' => 'C#',
            'Go' => 'Go',
            'Ruby' => 'Ruby',
            'Rust' => 'Rust',
            'TypeScript' => 'TypeScript',
            'HTML/CSS' => 'HTML/CSS',
            'PHP' => 'PHP'
        ];

        return $frameworkMap[$primaryLanguage] ?? $primaryLanguage;
    }

    /**
     * Build comprehensive context string for QA agent testing
     */
    private function buildTestingContext(array $contextData): string
    {
        $context = "QA AGENT TESTING TASK:\n";
        $context .= "========================\n\n";

        // Detect programming language
        $detectedLanguage = $contextData['programming_language'] ?? 'Unknown';

        // Task information
        $context .= "TASK: " . ($contextData['task']['title'] ?? 'Unknown Task') . "\n";
        $context .= "DESCRIPTION: " . ($contextData['task']['description'] ?? 'No description') . "\n";
        $context .= "PROGRAMMING LANGUAGE: " . $detectedLanguage . "\n\n";

        // Input data
        if (!empty($contextData['input_data'])) {
            $context .= "INPUT DATA:\n";
            foreach ($contextData['input_data'] as $key => $value) {
                if (is_string($value)) {
                    $context .= "- $key: $value\n";
                } else {
                    $context .= "- $key: " . json_encode($value) . "\n";
                }
            }
            $context .= "\n";
        }

        // Existing files context
        if (!empty($contextData['existing_code'])) {
            $context .= $contextData['existing_code'] . "\n\n";
        }

        // Project structure
        if (!empty($contextData['project_structure'])) {
            $context .= "PROJECT STRUCTURE:\n";
            $structure = $contextData['project_structure'];
            if (isset($structure['directories'])) {
                $context .= "Directories: " . implode(', ', array_slice($structure['directories'], 0, 10)) . "\n";
            }
            if (isset($structure['file_types'])) {
                $context .= "File types: ";
                foreach ($structure['file_types'] as $ext => $count) {
                    $context .= "$ext($count) ";
                }
                $context .= "\n";
            }
            $context .= "\n";
        }

        // Instructions for QA agent based on detected language
        $context .= $this->buildTestingInstructions($detectedLanguage);

        return $context;
    }

    /**
     * Build language-specific testing instructions for QA agent
     */
    private function buildTestingInstructions(string $language): string
    {
        $context = "INSTRUCTIONS:\n";
        $context .= "Create comprehensive testing features and unit tests for the implemented code.\n\n";

        switch ($language) {
            case 'PHP':
            case 'PHP/Laravel':
                $context .= "PHP/LARAVEL TESTING REQUIREMENTS:\n";
                $context .= "- Use PHPUnit as the testing framework\n";
                $context .= "- Create unit tests for all classes and methods\n";
                $context .= "- Use data providers for comprehensive test coverage\n";
                $context .= "- Test edge cases, null values, and exception handling\n";
                $context .= "- Create feature tests for integration testing\n";
                $context .= "- Use Laravel's testing helpers (TestCase, factories, etc.)\n";
                $context .= "- Test database operations with refreshDatabase trait\n";
                $context .= "- Create test database seeders for consistent test data\n";
                $context .= "- Use appropriate file structure: tests/Feature/ and tests/Unit/\n";
                $context .= "- Include code coverage reports\n\n";
                break;

            case 'JavaScript/React':
            case 'JavaScript/Node.js':
                $context .= "JAVASCRIPT TESTING REQUIREMENTS:\n";
                $context .= "- Use Jest as the testing framework\n";
                $context .= "- Use React Testing Library for component testing\n";
                $context .= "- Create unit tests for utilities and services\n";
                $context .= "- Mock external dependencies and APIs\n";
                $context .= "- Test async operations with proper awaits\n";
                $context .= "- Use describe/it blocks for test organization\n";
                $context .= "- Create integration tests for critical user flows\n";
                $context .= "- Test error boundaries and error handling\n";
                $context .= "- Include snapshot tests for UI components\n";
                $context .= "- Generate coverage reports with Jest coverage\n\n";
                break;

            case 'Python':
                $context .= "PYTHON TESTING REQUIREMENTS:\n";
                $context .= "- Use pytest as the testing framework\n";
                $context .= "- Create comprehensive unit tests with proper naming\n";
                $context .= "- Use fixtures for test data setup\n";
                $context .= "- Implement parametrization for multiple test cases\n";
                $context .= "- Test exception handling with pytest.raises\n";
                $context .= "- Create mock objects for external dependencies\n";
                $context .= "- Use test coverage tools (pytest-cov)\n";
                $context .= "- Follow test directory structure conventions\n";
                $context .= "- Include integration tests for complex workflows\n\n";
                break;

            case 'Java':
                $context .= "JAVA TESTING REQUIREMENTS:\n";
                $context .= "- Use JUnit 5 as the testing framework\n";
                $context .= "- Use Mockito for mocking dependencies\n";
                $context .= "- Create comprehensive unit tests\n";
                $context .= "- Use @ParameterizedTest for multiple inputs\n";
                $context .= "- Test exception scenarios with Assertions.assertThrows\n";
                $context .= "- Use Maven Surefire plugin for test execution\n";
                $context .= "- Create integration tests with Testcontainers if needed\n";
                $context .= "- Follow Maven/Gradle test directory structure\n";
                $context .= "- Include code coverage with JaCoCo\n\n";
                break;

            case 'C#':
                $context .= "C# TESTING REQUIREMENTS:\n";
                $context .= "- Use xUnit or NUnit testing framework\n";
                $context .= "- Use Moq for mocking dependencies\n";
                $context .= "- Create comprehensive unit tests with Theory attributes\n";
                $context .= "- Test async methods properly\n";
                $context .= "- Use FluentAssertions for better assertions\n";
                $context .= "- Create integration tests for data access\n";
                $context .= "- Use coverlet for code coverage\n";
                $context .= "- Follow test project naming conventions\n\n";
                break;

            case 'Go':
                $context .= "GO TESTING REQUIREMENTS:\n";
                $context .= "- Use Go's built-in testing package\n";
                $context .= "- Create table-driven tests for comprehensive coverage\n";
                $context .= "- Use subtests for organizing related test cases\n";
                $context .= "- Mock dependencies using interfaces\n";
                $context .= "- Test error scenarios thoroughly\n";
                $context .= "- Use testdata directories for test assets\n";
                $context .= "- Follow Go testing conventions (TestXxx naming)\n";
                $context .= "- Include benchmarks for performance testing\n";
                $context .= "- Use race detection for concurrent code\n\n";
                break;

            case 'Ruby':
                $context .= "RUBY TESTING REQUIREMENTS:\n";
                $context .= "- Use RSpec as the testing framework\n";
                $context .= "- Create comprehensive example blocks\n";
                $context .= "- Use contexts and describe blocks for organization\n";
                $context .= "- Test Rails models, controllers, and views\n";
                $context .= "- Use Factory Bot for test data\n";
                $context .= "- Include request specs for API testing\n";
                $context .= "- Use shoulda-matchers for common validations\n";
                $context .= "- Include Capybara for integration testing\n";
                $context .= "- Generate coverage reports with SimpleCov\n\n";
                break;

            case 'Rust':
                $context .= "RUST TESTING REQUIREMENTS:\n";
                $context .= "- Use Rust's built-in testing framework\n";
                $context .= "- Write unit tests with #[test] attribute\n";
                $context .= "- Create integration tests in tests/ directory\n";
                $context .= "- Write doc tests in documentation comments\n";
                $context .= "- Test Result<T, E> and Option<T> properly\n";
                $context .= "- Use assert_eq!, assert_ne!, and custom assertions\n";
                $context .= "- Test error conditions and edge cases\n";
                $context .= "- Include benchmark tests with #[bench]\n";
                $context .= "- Generate test coverage reports\n\n";
                break;

            case 'TypeScript':
                $context .= "TYPESCRIPT TESTING REQUIREMENTS:\n";
                $context .= "- Use Jest as the primary testing framework\n";
                $context .= "- Leverage TypeScript for type-safe testing\n";
                $context .= "- Create comprehensive unit and integration tests\n";
                $context .= "- Mock external dependencies properly\n";
                $context .= "- Test async operations with await/resolves\n";
                $context .= "- Use @types/jest for Jest TypeScript support\n";
                $context .= "- Create coverage reports with coverageReporters\n";
                $context .= "- Test error scenarios and type safety\n\n";
                break;

            default:
                $context .= "GENERAL TESTING REQUIREMENTS:\n";
                $context .= "- Create comprehensive unit tests for all functions/methods\n";
                $context .= "- Write appropriate integration tests\n";
                $context .= "- Test edge cases and error conditions\n";
                $context .= "- Use mocks/stubs for external dependencies\n";
                $context .= "- Generate code coverage reports\n";
                $context .= "- Follow the language's testing conventions\n\n";
                break;
        }

        $context .= "GENERAL TESTING PRINCIPLES:\n";
        $context .= "- Test both positive and negative scenarios\n";
        $context .= "- Ensure proper isolation between tests\n";
        $context .= "- Write descriptive test names that explain what is being tested\n";
        $context .= "- Include comments explaining complex test setups\n";
        $context .= "- Test boundary conditions and invalid inputs\n";
        $context .= "- Verify error messages and exception handling\n";
        $context .= "- Include performance tests where applicable\n";
        $context .= "- Ensure tests are maintainable and readable\n\n";

        $context .= "Use appropriate file operations to create test files in the correct directories.\n";
        $context .= "Ensure all necessary test directories are created before creating test files.\n";

        return $context;
    }

    /**
     * Build language-specific instructions for the DEV agent
     */
    private function buildLanguageSpecificInstructions(string $language): string
    {
        $context = "INSTRUCTIONS:\n";
        $default_pattern = "Use appropriate file operations (create_file, update_file, create_folder, etc.).\n";
        $default_pattern .= "Ensure all necessary directories are created before files.\n";

        switch ($language) {
            case 'PHP':
                $context .= "Create a structured plan to implement this task as PHP code.\n";
                $context .= "Focus on creating clean, maintainable code following PHP best practices.\n";
                $context .= $default_pattern;
                $context .= "Before creating a file check if it exists, if exists, create a replace especial in migrations folder\n";
                $context .= "Follow PSR-12 coding standards and PHP best practices.\n";
                break;

            case 'PHP/Laravel':
                $context .= "Create a structured plan to implement this task as PHP/Laravel code.\n";
                $context .= "Focus on creating clean, maintainable code following Laravel conventions.\n";
                $context .= $default_pattern;
                $context .= "Before creating a file check if it exists, if exists, create a replace especial in migrations folder\n";
                $context .= "Follow PSR-12 coding standards and Laravel best practices.\n";
                break;

            case 'JavaScript/React':
                $context .= "Create a structured plan to implement this task as JavaScript/React code.\n";
                $context .= "Focus on creating clean, maintainable code following React best practices.\n";
                $context .= "Use modern JavaScript (ES6+) and React hooks when appropriate.\n";
                $context .= $default_pattern;
                $context .= "Follow ESLint and Prettier conventions for code formatting.\n";
                break;

            case 'JavaScript/Node.js':
                $context .= "Create a structured plan to implement this task as JavaScript/Node.js code.\n";
                $context .= "Focus on creating clean, maintainable code following Node.js best practices.\n";
                $context .= "Use modern JavaScript (ES6+) features and CommonJS/ES6 modules as appropriate.\n";
                $context .= $default_pattern;
                $context .= "Follow ESLint and Prettier conventions for code formatting.\n";
                break;

            case 'Python':
                $context .= "Create a structured plan to implement this task as Python code.\n";
                $context .= "Focus on creating clean, maintainable code following PEP 8 standards.\n";
                $context .= "Use type hints and docstrings for better code documentation.\n";
                $context .= $default_pattern;
                $context .= "Follow Python best practices and conventions.\n";
                break;

            case 'Java':
                $context .= "Create a structured plan to implement this task as Java code.\n";
                $context .= "Focus on creating clean, maintainable code following Java naming conventions.\n";
                $context .= "Use appropriate package structure and object-oriented principles.\n";
                $context .= $default_pattern;
                $context .= "Follow standard Java coding conventions and best practices.\n";
                break;

            case 'C#':
                $context .= "Create a structured plan to implement this task as C# code.\n";
                $context .= "Focus on creating clean, maintainable code following C# coding conventions.\n";
                $context .= "Use .NET naming conventions and object-oriented principles.\n";
                $context .= $default_pattern;
                $context .= "Follow Microsoft C# coding guidelines and best practices.\n";
                break;

            case 'Go':
                $context .= "Create a structured plan to implement this task as Go code.\n";
                $context .= "Focus on creating clean, idiomatic Go code following Go conventions.\n";
                $context .= "Use Go's standard library effectively and follow the Go project layout.\n";
                $context .= $default_pattern;
                $context .= "Follow effective Go programming patterns and conventions.\n";
                break;

            case 'Ruby':
                $context .= "Create a structured plan to implement this task as Ruby code.\n";
                $context .= "Focus on creating clean, maintainable code following Ruby style guide.\n";
                $context .= "Use Ruby's object-oriented features and follow Rails conventions if applicable.\n";
                $context .= $default_pattern;
                $context .= "Follow Ruby coding conventions and best practices.\n";
                break;

            case 'Rust':
                $context .= "Create a structured plan to implement this task as Rust code.\n";
                $context .= "Focus on creating safe, performant code following Rust ownership principles.\n";
                $context .= "Use Rust's type system and borrow checker effectively.\n";
                $context .= $default_pattern;
                $context .= "Follow Rust coding conventions and best practices.\n";
                break;

            case 'TypeScript':
                $context .= "Create a structured plan to implement this task as TypeScript code.\n";
                $context .= "Focus on creating strongly-typed, maintainable code with TypeScript.\n";
                $context .= "Use TypeScript's type system effectively for better code reliability.\n";
                $context .= $default_pattern;
                $context .= "Follow TypeScript and modern JavaScript coding conventions.\n";
                break;

            case 'HTML/CSS':
                $context .= "Create a structured plan to implement this task as HTML/CSS/JavaScript.\n";
                $context .= "Focus on creating semantic HTML, maintainable CSS, and clean JavaScript.\n";
                $context .= "Use responsive design principles and modern CSS features.\n";
                $context .= $default_pattern;
                $context .= "Follow web development best practices and accessibility standards.\n";
                break;

            default:
                $context .= "Create a structured plan to implement this task as clean, maintainable code.\n";
                $context .= "Analyze the project structure and use appropriate technology stack conventions.\n";
                $context .= $default_pattern;
                $context .= "Follow the project's existing coding standards and best practices.\n";
                break;
        }

        return $context . "\n\n";
    }

    protected function executeQAMilestone(
        AgentClient $agentClient,
        array $context,
        CommandRunner $commandRunner,
        FilePatcher $filePatcher
    ): array {
        // Enhance context with testing information
        $testContext = $this->buildTestingContext([
            'task' => $context['task'],
            'input_data' => $context['input_data'] ?? [],
            'existing_files' => $filePatcher->listFiles(),
            'project_structure' => $this->getProjectStructure($filePatcher),
            'programming_language' => $this->detectProgrammingLanguage([
                'project_structure' => $this->getProjectStructure($filePatcher),
                'existing_code' => $this->getExistingCodeContext($filePatcher)
            ])
        ]);

        // Generate testing features and unit tests using Claude
        $claudeService = app(\App\Services\ClaudeAgentService::class);
        $testActions = $claudeService->getTestPlan($testContext);

        Log::info('QA agent received test plan', [
            'task_id' => $this->task->id,
            'test_actions_count' => count($testActions),
            'run_id' => $this->runId
        ]);

        // Execute test file generation
        $testExecutionResults = [];
        if (!empty($testActions)) {
            $testExecutionResults = $claudeService->executePlan($testActions);

            Log::info('QA agent test files generation completed', [
                'task_id' => $this->task->id,
                'test_execution_results' => $testExecutionResults,
                'run_id' => $this->runId
            ]);
        }

        // Run existing tests
        $testResult = $commandRunner->runTests();

        // Return combined results
        return [
            'success' => $testResult['success'] ?? false,
            'test_generation' => [
                'actions' => $testActions,
                'execution_results' => $testExecutionResults,
                'data' => [
                    'test_actions_count' => count($testActions),
                    'successful_test_actions' => count(array_filter($testExecutionResults, function ($r) {
                        return !str_contains($r, 'FAIL') && !str_contains($r, 'not found');
                    }))
                ]
            ],
            'command_results' => $testResult,
            'raw_content' => json_encode([
                'test_execution' => $testResult,
                'test_generation' => [
                    'actions_count' => count($testActions),
                    'successful_actions' => count(array_filter($testExecutionResults, function ($r) {
                        return !str_contains($r, 'FAIL') && !str_contains($r, 'not found');
                    }))
                ]
            ])
        ];
    }

    protected function executeDocMilestone(
        AgentClient $agentClient,
        array $context,
        FilePatcher $filePatcher
    ): array {
        $result = $agentClient->generateDocumentation(
            $context['task'],
            $context['input_data'] ?? [],
            $this->runId
        );

        // Update documentation files
        if (isset($result['data']['documentation'])) {
            foreach ($result['data']['documentation'] as $doc) {
                if (isset($doc['path']) && isset($doc['content'])) {
                    $filePatcher->writeFile($doc['path'], $doc['content']);
                }
            }
        }

        return $result;
    }

    protected function completeTask(AgentClient $agentClient, CommandRunner $commandRunner): void
    {
        // Run final tests
        $finalTestResult = $commandRunner->runTests();

        if (!$finalTestResult['success']) {
            throw new \Exception('Final tests failed: ' . $finalTestResult['stderr']);
        }

        // Mark task as completed
        $this->task->transitionTo(TaskStatus::COMPLETED);
        $this->task->unlock();

        Log::info('ProcessNewTask: Task completed successfully', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNewTask: Job failed', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        $this->task->transitionTo(TaskStatus::FAILED);
        $this->task->unlock();
    }



    private function parseMilestonesFromText(string $text): array
    {
        $milestones = [];

        $normalize = function (array $ms): array {
            $out = [];
            foreach ($ms as $m) {
                if (!is_array($m)) continue;
                $title = $m['title'] ?? '';
                $out[] = [
                    'title'       => $title,
                    'description' => $m['description'] ?? '',
                    'agent_type'  => $m['agent_type'] ?? (method_exists($this, 'detectAgentType') ? $this->detectAgentType($title) : null),
                    'input_data'  => $m['input_data'] ?? [],
                    'metadata'    => $m['metadata'] ?? [],
                ];
            }
            return $out;
        };

        // Strip zero-width & BOM chars that often break JSON
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);

        // 1) Try decoding the whole string as JSON
        try {
            $data = json_decode($text, true, 65535, JSON_THROW_ON_ERROR);
            if (isset($data['milestones']) && is_array($data['milestones'])) {
                return $normalize($data['milestones']);
            }
            // If it's a wrapper with the JSON inside a string
            foreach (['raw_content', 'response', 'content', 'message', 'text'] as $k) {
                if (!empty($data[$k]) && is_string($data[$k]) && $data[$k] !== $text) {
                    $inner = $this->parseMilestonesFromText($data[$k]);
                    if (!empty($inner)) return $inner;
                }
            }
        } catch (\JsonException $e) {
            // ignore, we’ll try other strategies
        }

        // 2) Extract JSON from fenced code blocks ```json ... ```
        if (preg_match_all('/```(?:json|javascript)?\s*(\{.*?\})\s*```/si', $text, $m)) {
            foreach ($m[1] as $block) {
                try {
                    $obj = json_decode($block, true, 65535, JSON_THROW_ON_ERROR);
                    if (isset($obj['milestones']) && is_array($obj['milestones'])) {
                        return $normalize($obj['milestones']);
                    }
                    // If the block itself is an array of milestones without a parent object
                    if (is_array($obj) && isset($obj[0]['title'])) {
                        return $normalize($obj);
                    }
                } catch (\JsonException $e) { /* continue */
                }
            }
        }

        // 3) Try balanced-brace object that contains "milestones" (PCRE recursive)
        if (preg_match_all('/\{(?:[^{}]|(?R))*"milestones"(?:[^{}]|(?R))*\}/s', $text, $m)) {
            foreach ($m[0] as $cand) {
                try {
                    $obj = json_decode($cand, true, 65535, JSON_THROW_ON_ERROR);
                    if (isset($obj['milestones']) && is_array($obj['milestones'])) {
                        return $normalize($obj['milestones']);
                    }
                } catch (\JsonException $e) { /* continue */
                }
            }
        }

        // 4) Fenced array form: ```json [ {..}, {..} ] ```
        if (preg_match_all('/```(?:json|javascript)?\s*(\[\s*\{.*?\}\s*\])\s*```/si', $text, $m)) {
            foreach ($m[1] as $arrBlock) {
                try {
                    $arr = json_decode($arrBlock, true, 65535, JSON_THROW_ON_ERROR);
                    if (is_array($arr) && isset($arr[0]['title'])) {
                        return $normalize($arr);
                    }
                } catch (\JsonException $e) { /* continue */
                }
            }
        }

        // 5) Less-greedy single-pass capture as a last JSON attempt
        if (preg_match('/\{[\s\S]*?"milestones"[\s\S]*?\}/', $text, $matches)) {
            try {
                $data = json_decode($matches[0], true, 65535, JSON_THROW_ON_ERROR);
                if (isset($data['milestones']) && is_array($data['milestones'])) {
                    return $normalize($data['milestones']);
                }
            } catch (\JsonException $e) { /* continue */
            }
        }

        // 6) Fallback: your existing markdown/text parser
        $lines = explode("\n", $text);
        $currentMilestone = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Look for milestone headers (## Milestone, ### Step, etc.)
            if (preg_match('/^#+\s*(?:milestone|step)\s*\d*:?:?\s*(.*)/i', $line, $matches)) {
                if ($currentMilestone) {
                    $milestones[] = $currentMilestone;
                }
                $title = trim($matches[1]);
                $currentMilestone = [
                    'title'       => $title,
                    'description' => '',
                    'agent_type'  => method_exists($this, 'detectAgentType') ? $this->detectAgentType($title) : null,
                    'input_data'  => [],
                    'metadata'    => [],
                ];
            } elseif ($currentMilestone && !empty($line) && !preg_match('/^#+/', $line)) {
                $currentMilestone['description'] .= $line . "\n";
            }
        }

        if ($currentMilestone) {
            $milestones[] = $currentMilestone;
        }

        if (empty($milestones)) {
            Log::warning('No milestones parsed from text', ['text_preview' => substr($text, 0, 500)]);
        }

        return $milestones;
    }


    /**
     * Detect agent type from milestone title
     */
    private function detectAgentType(string $title): string
    {
        $title = strtolower($title);

        if (strpos($title, 'plan') !== false || strpos($title, 'manage') !== false) {
            return 'pm';
        } elseif (strpos($title, 'requirement') !== false || strpos($title, 'analyze') !== false) {
            return 'ba';
        } elseif (strpos($title, 'design') !== false || strpos($title, 'ui') !== false || strpos($title, 'ux') !== false) {
            return 'ux';
        } elseif (strpos($title, 'architecture') !== false || strpos($title, 'architect') !== false) {
            return 'arch';
        } elseif (strpos($title, 'code') !== false || strpos($title, 'implement') !== false || strpos($title, 'develop') !== false) {
            return 'dev';
        } elseif (strpos($title, 'test') !== false || strpos($title, 'qa') !== false || strpos($title, 'quality') !== false) {
            return 'qa';
        } elseif (strpos($title, 'document') !== false || strpos($title, 'doc') !== false) {
            return 'doc';
        }

        return 'dev'; // Default fallback
    }

    /**
     * Create default milestones when parsing fails
     */
    private function createDefaultMilestones(): array
    {
        return [
            [
                'title' => 'Requirements Analysis',
                'description' => 'Analyze and clarify the task requirements',
                'agent_type' => 'ba',
                'input_data' => [],
                'metadata' => []
            ],
            [
                'title' => 'Implementation',
                'description' => 'Implement the required functionality',
                'agent_type' => 'dev',
                'input_data' => [],
                'metadata' => []
            ],
            [
                'title' => 'Testing',
                'description' => 'Test the implemented functionality',
                'agent_type' => 'qa',
                'input_data' => [],
                'metadata' => []
            ]
        ];
    }
}

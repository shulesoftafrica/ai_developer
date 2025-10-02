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

        Log::info('PlanTask raw response', [
            'task_id' => $this->task->id,
            'raw_content' => $planResponse['raw_content'] ?? 'no_raw_content',
            'data' => $planResponse['data'] ?? 'no_data'
        ]);

        Log::debug('Parsing milestones from response', [
            'task_id' => $this->task->id,
            'response' => $planResponse
        ]);

        if (!$planResponse['success']) {
            throw new \Exception('Failed to create milestone plan');
        }

        // Debug logging to see what we're getting from the agent
        Log::debug('PM Agent Response', [
            'task_id' => $this->task->id,
            'response' => $planResponse
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
            'milestones' => array_map(function($m) { return ['title' => $m['title'], 'agent_type' => $m['agent_type']]; }, $milestones)
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
            $lock = Cache::lock("milestone_{$milestone->id}_lock", 10);

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
                return $this->executeQAMilestone($agentClient, $context, $commandRunner);
            
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
                'successful_actions' => count(array_filter($executionResults, function($r) {
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
            
            foreach (array_slice($files, 0, 20) as $file) { // Limit to first 20 files
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
        
        // Instructions for DEV agent
        $context .= "INSTRUCTIONS:\n";
        $context .= "Create a structured plan to implement this task as PHP/Laravel code.\n";
        $context .= "Focus on creating clean, maintainable code following Laravel conventions.\n";
        $context .= "Use appropriate file operations (create_file, update_file, create_folder, etc.).\n";
        $context .= "Ensure all necessary directories are created before files.\n";
        $context .= "Follow PSR-12 coding standards and Laravel best practices.\n\n";
        
        return $context;
    }

    protected function executeQAMilestone(
        AgentClient $agentClient,
        array $context,
        CommandRunner $commandRunner
    ): array {
        // Run tests
        $testResult = $commandRunner->runTests();
        
        return $agentClient->runTests(
            $context['task'],
            json_encode($testResult),
            $this->runId
        );
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
        foreach (['raw_content','response','content','message','text'] as $k) {
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
            } catch (\JsonException $e) { /* continue */ }
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
            } catch (\JsonException $e) { /* continue */ }
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
            } catch (\JsonException $e) { /* continue */ }
        }
    }

    // 5) Less-greedy single-pass capture as a last JSON attempt
    if (preg_match('/\{[\s\S]*?"milestones"[\s\S]*?\}/', $text, $matches)) {
        try {
            $data = json_decode($matches[0], true, 65535, JSON_THROW_ON_ERROR);
            if (isset($data['milestones']) && is_array($data['milestones'])) {
                return $normalize($data['milestones']);
            }
        } catch (\JsonException $e) { /* continue */ }
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
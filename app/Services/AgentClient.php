<?php

namespace App\Services;

use App\Enums\AgentType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AgentClient
{
    private LlmClient $llmClient;
    private ClaudeAgentService $claudeService;
    private string $workspacePath;

    public function __construct(LlmClient $llmClient, ClaudeAgentService $claudeService)
    {
        $this->llmClient = $llmClient;
        $this->claudeService = $claudeService;
        $this->workspacePath = config('agent.workspace_path');
    }

    public function executeAgent(
        AgentType $agentType,
        array $context,
        ?int $taskId = null,
        ?int $milestoneId = null,
        ?string $runId = null
    ): array {
        Log::info("TEST LOG: executeAgent called for {$agentType->value}", [
            'task_id' => $taskId,
            'milestone_id' => $milestoneId,
            'run_id' => $runId,
        ]);

        Log::info("Executing {$agentType->value} agent", [
            'task_id' => $taskId,
            'milestone_id' => $milestoneId,
            'run_id' => $runId,
        ]);

        $systemPrompt = $this->loadPromptTemplate($agentType);
        $userMessage = $this->buildUserMessage($context);

        $messages = $this->llmClient->formatMessages($systemPrompt, $userMessage);

        $response = $this->llmClient->chat(
            $messages,
            $agentType,
            $taskId,
            $milestoneId,
            $runId
        );

        $content = $this->llmClient->extractContent($response);

        Log::info("AgentClient: Raw response from {$agentType->value} agent", [
            'task_id' => $taskId,
            'milestone_id' => $milestoneId,
            'run_id' => $runId,
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 500)
        ]);

        return $this->parseAgentResponse($content, $agentType);
    }

    private function loadPromptTemplate(AgentType $agentType): string
    {
        $templatePath = $agentType->getPromptTemplate();

        if (!File::exists($templatePath)) {
            throw new \Exception("Prompt template not found: {$templatePath}");
        }

        return File::get($templatePath);
    }

    private function buildUserMessage(array $context): string
    {
        $message = "CONTEXT:\n";

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $message .= strtoupper($key) . ":\n" . $value . "\n\n";
        }

        return $message;
    }

    private function parseAgentResponse(string $content, AgentType $agentType): array
    {
        // Enhanced JSON parsing for code generation
        $parsedData = $this->parseJsonFromContent($content);
        if ($parsedData !== null) {
            return [
                'success' => true,
                'data' => $parsedData,
                'raw_content' => $content,
            ];
        }

        // Special handling for DEV agent - extract file changes
        if ($agentType === AgentType::DEV) {
            $fileChanges = $this->extractFileChanges($content);
            if (!empty($fileChanges)) {
                return [
                    'success' => true,
                    'data' => ['file_changes' => $fileChanges],
                    'raw_content' => $content,
                ];
            }
        }

        // Try to extract code blocks for certain agent types
        if (in_array($agentType, [AgentType::DEV, AgentType::DOC]) && preg_match_all('/```(\w+)?\s*(.*?)```/s', $content, $matches, PREG_SET_ORDER)) {
            $files = [];
            foreach ($matches as $match) {
                $files[] = [
                    'language' => $match[1] ?? 'text',
                    'content' => $match[2],
                ];
            }
            
            if (!empty($files)) {
                return [
                    'success' => true,
                    'data' => ['files' => $files],
                    'raw_content' => $content,
                ];
            }
        }

        // Fallback to plain text response
        return [
            'success' => true,
            'data' => ['response' => $content],
            'raw_content' => $content,
        ];
    }

    /**
     * Enhanced JSON parsing from content using multiple strategies
     */
    private function parseJsonFromContent(string $content): ?array
    {
        // Clean control characters that can cause JSON parsing issues
        $cleanContent = $this->cleanJsonContent($content);
        
        // Strategy 1: JSON in code blocks
        if (preg_match('/```(?:json|javascript)?\s*(\{[\s\S]*?\})\s*```/i', $cleanContent, $matches)) {
            try {
                $jsonContent = $this->cleanJsonContent($matches[1]);
                return json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::debug('Failed to parse JSON from code block', ['error' => $e->getMessage()]);
            }
        }

        // Strategy 2: Entire content as JSON
        try {
            $data = json_decode($cleanContent, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data)) {
                return $data;
            }
        } catch (\JsonException $e) {
            // Try with unescaping if direct decode fails
            try {
                $unescapedContent = stripslashes($cleanContent);
                $data = json_decode($unescapedContent, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data)) {
                    return $data;
                }
            } catch (\JsonException $e2) {
                // Continue to other strategies
            }
        }

        // Strategy 3: Extract JSON object from mixed content
        if (preg_match('/\{[\s\S]*?"(?:file_changes|milestones|actions)"[\s\S]*?\}/i', $cleanContent, $matches)) {
            try {
                $jsonContent = $this->cleanJsonContent($matches[0]);
                return json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::debug('Failed to parse extracted JSON object', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Clean JSON content by removing control characters and fixing common issues
     */
    private function cleanJsonContent(string $content): string
    {
        // Remove BOM if present (both actual BOM and unicode escape)
        $content = ltrim($content, "\xef\xbb\xbf");
        $content = preg_replace('/\\\\uFEFF/', '', $content);
        
        // Remove null bytes and other control characters (both actual and unicode escapes)
        $content = preg_replace('/[\x00-\x1f\x7f]/', '', $content);
        $content = preg_replace('/\\\\u000[0-9A-Fa-f]/', '', $content);
        
        return trim($content);
    }

    /**
     * Extract file changes from content for DEV agent
     */
    private function extractFileChanges(string $content): array
    {
        $fileChanges = [];

        // Pattern 1: Look for file path followed by code block
        preg_match_all('/(?:File|Path|Update|Create):\s*([^\n]+)\n```(?:\w+)?\n([\s\S]*?)```/i', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $path = trim($match[1]);
            $content = trim($match[2]);
            $fileChanges[] = [
                'path' => $path,
                'content' => $content,
                'type' => 'create_or_update'
            ];
        }

        // Pattern 2: JSON-like file changes
        if (preg_match('/"file_changes"\s*:\s*\[([\s\S]*?)\]/i', $content, $matches)) {
            try {
                $jsonContent = '{"file_changes":[' . $matches[1] . ']}';
                $cleanedJson = $this->cleanJsonContent($jsonContent);
                $data = json_decode($cleanedJson, true, 512, JSON_THROW_ON_ERROR);
                if (isset($data['file_changes'])) {
                    return $data['file_changes'];
                }
            } catch (\JsonException $e) {
                Log::debug('Failed to parse file_changes JSON', ['error' => $e->getMessage()]);
            }
        }

        return $fileChanges;
    }

    public function planTask(array $taskData, ?string $runId = null): array
    {
        $result = $this->executeAgent(
            AgentType::PM,
            [
                'task' => $taskData,
                'instruction' => 'Break this task into concrete milestones with clear deliverables.',
            ],
            $taskData['id'] ?? null,
            null,
            $runId
        );
        
        Log::info('AgentClient: PM planTask result', [
            'task_id' => $taskData['id'] ?? null,
            'run_id' => $runId,
            'success' => $result['success'],
            'data_keys' => isset($result['data']) ? array_keys($result['data']) : null,
            'raw_content_length' => isset($result['raw_content']) ? strlen($result['raw_content']) : null,
        ]);
        
        return $result;
    }

    public function analyzeRequirements(array $taskData, ?string $runId = null): array
    {
        return $this->executeAgent(
            AgentType::BA,
            [
                'task' => $taskData,
                'instruction' => 'Analyze requirements and clarify specifications.',
            ],
            $taskData['id'] ?? null,
            null,
            $runId
        );
    }

    public function designUserExperience(array $taskData, array $requirements, ?string $runId = null): array
    {
        return $this->executeAgent(
            AgentType::UX,
            [
                'task' => $taskData,
                'requirements' => $requirements,
                'instruction' => 'Design user experience and interface mockups.',
            ],
            $taskData['id'] ?? null,
            null,
            $runId
        );
    }

    public function designArchitecture(array $taskData, array $requirements, ?string $runId = null): array
    {
        return $this->executeAgent(
            AgentType::ARCH,
            [
                'task' => $taskData,
                'requirements' => $requirements,
                'instruction' => 'Design technical architecture and identify files to modify.',
            ],
            $taskData['id'] ?? null,
            null,
            $runId
        );
    }

    public function generateCode(
        array $taskData,
        array $context,
        string $existingCode,
        ?string $runId = null
    ): array {
        // Enhanced context with workspace information
        $enhancedContext = array_merge($context, [
            'task' => $taskData,
            'existing_code' => $existingCode,
            'workspace_path' => $this->workspacePath,
            'instruction' => 'Generate structured code changes with file paths and content. Use JSON format with file_changes array.',
            'format_requirements' => [
                'Use JSON format with file_changes array',
                'Include relative file paths from project root',
                'Provide complete file content or patches',
                'Specify action type: create, update, or patch'
            ]
        ]);

        $result = $this->executeAgent(
            AgentType::DEV,
            $enhancedContext,
            $taskData['id'] ?? null,
            null,
            $runId
        );

        // Post-process the result to ensure consistent structure
        return $this->postProcessCodeGeneration($result, $taskData, $runId);
    }

    /**
     * Post-process code generation results for consistency
     */
    private function postProcessCodeGeneration(array $result, array $taskData, ?string $runId): array
    {
        if (!$result['success'] || !isset($result['data'])) {
            return $result;
        }

        $data = $result['data'];

        // Ensure file_changes structure exists
        if (!isset($data['file_changes']) && isset($data['files'])) {
            // Convert files format to file_changes format
            $fileChanges = [];
            foreach ($data['files'] as $file) {
                $fileChanges[] = [
                    'path' => $file['path'] ?? 'unknown.php',
                    'content' => $file['content'] ?? '',
                    'type' => $file['type'] ?? 'create'
                ];
            }
            $data['file_changes'] = $fileChanges;
        }

        // If no structured file changes found, try to generate them using Claude service
        if (empty($data['file_changes']) && isset($result['raw_content'])) {
            Log::info('Attempting to restructure unstructured code response', [
                'task_id' => $taskData['id'] ?? null,
                'run_id' => $runId
            ]);

            try {
                $restructuredActions = $this->claudeService->getRestructurePlan($result['raw_content']);
                if (!empty($restructuredActions)) {
                    $data['file_changes'] = $this->convertActionsToFileChanges($restructuredActions);
                    Log::info('Successfully restructured code response', [
                        'file_changes_count' => count($data['file_changes'])
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to restructure code response', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $result['data'] = $data;
        return $result;
    }

    /**
     * Convert Claude actions to file_changes format
     */
    private function convertActionsToFileChanges(array $actions): array
    {
        $fileChanges = [];

        foreach ($actions as $action) {
            $actionType = $action['action'] ?? null;

            if (in_array($actionType, ['create_file', 'update_file'])) {
                $fileChanges[] = [
                    'path' => $action['path'] ?? 'unknown.php',
                    'content' => $action['content'] ?? '',
                    'type' => $actionType === 'create_file' ? 'create' : 'update'
                ];
            }
        }

        return $fileChanges;
    }

    public function runTests(array $taskData, string $testResults, ?string $runId = null): array
    {
        return $this->executeAgent(
            AgentType::QA,
            [
                'task' => $taskData,
                'test_results' => $testResults,
                'instruction' => 'Analyze test results and determine if the task is complete.',
            ],
            $taskData['id'] ?? null,
            null,
            $runId
        );
    }

    public function generateDocumentation(array $taskData, array $changes, ?string $runId = null): array
    {
        return $this->executeAgent(
            AgentType::DOC,
            [
                'task' => $taskData,
                'changes' => $changes,
                'instruction' => 'Generate documentation for the implemented changes.',
            ],
            $taskData['id'] ?? null,
            null,
            $runId
        );
    }
}

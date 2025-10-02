<?php

namespace App\Services;

use App\Models\AiLog;
use App\Enums\AgentType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class LlmClient
{
    private Client $httpClient;
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => 'https://api.anthropic.com/',
            'timeout' => 120,
        ]);
        
        $this->apiKey = config('app.llm.anthropic_api_key');
        $this->model = config('app.llm.model');
        $this->maxTokens = config('app.llm.max_tokens');
        $this->temperature = config('app.llm.temperature');
    }

    public function chat(
        array $messages,
        AgentType $agentType,
        ?int $taskId = null,
        ?int $milestoneId = null,
        ?string $runId = null
    ): array {
        $runId = $runId ?? Uuid::uuid4()->toString();
        $startTime = microtime(true);

        // Create cache key for identical requests
        $cacheKey = 'llm_' . md5(json_encode($messages) . $this->model . $this->temperature);
        
        try {
            // Check cache first (for identical requests within 1 hour)
            if ($cached = Cache::get($cacheKey)) {
                Log::info('LLM cache hit', ['cache_key' => $cacheKey, 'run_id' => $runId]);
                
                $this->logInteraction(
                    $messages,
                    $cached,
                    $agentType,
                    $runId,
                    $taskId,
                    $milestoneId,
                    microtime(true) - $startTime,
                    'success',
                    'cache_hit'
                );
                
                return $cached;
            }

            $response = $this->httpClient->post('v1/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                    'system' => $this->extractSystemPrompt($messages),
                    'messages' => $this->extractUserMessages($messages),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $executionTime = microtime(true) - $startTime;

            // Cache successful responses for 1 hour
            Cache::put($cacheKey, $data, 3600);

            $this->logInteraction(
                $messages,
                $data,
                $agentType,
                $runId,
                $taskId,
                $milestoneId,
                $executionTime,
                'success'
            );

            return $data;

        } catch (RequestException $e) {
            $executionTime = microtime(true) - $startTime;
            $errorMessage = $e->getMessage();
            
            if ($e->hasResponse()) {
                $errorMessage .= ' Response: ' . $e->getResponse()->getBody()->getContents();
            }

            Log::error('LLM API error', [
                'error' => $errorMessage,
                'run_id' => $runId,
                'agent_type' => $agentType->value,
            ]);

            $this->logInteraction(
                $messages,
                [],
                $agentType,
                $runId,
                $taskId,
                $milestoneId,
                $executionTime,
                'error',
                $errorMessage
            );

            throw new \Exception("LLM API error: {$errorMessage}");
        }
    }

    private function logInteraction(
        array $prompt,
        array $response,
        AgentType $agentType,
        string $runId,
        ?int $taskId,
        ?int $milestoneId,
        float $executionTime,
        string $status,
        ?string $errorMessage = null
    ): void {
        $tokensUsed = $response['usage']['total_tokens'] ?? 0;

        AiLog::create([
            'task_id' => $taskId,
            'milestone_id' => $milestoneId,
            'run_id' => $runId,
            'agent_type' => $agentType,
            'prompt' => $prompt,
            'response' => $response,
            'model' => $this->model,
            'tokens_used' => $tokensUsed,
            'execution_time_ms' => round($executionTime * 1000),
            'status' => $status,
            'error_message' => $errorMessage,
            'metadata' => [
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'cached' => $status === 'cache_hit',
            ],
        ]);
    }

    public function extractContent(array $response): string
    {
        return $response['content'][0]['text'] ?? '';
    }

    public function formatMessages(string $systemPrompt, string $userMessage): array
    {
        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user', 
                'content' => $userMessage,
            ],
        ];
    }

    /**
     * Extract system prompt from messages array
     */
    private function extractSystemPrompt(array $messages): string
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                return $message['content'];
            }
        }
        return '';
    }

    /**
     * Extract non-system messages for Claude API
     */
    private function extractUserMessages(array $messages): array
    {
        return array_values(array_filter($messages, function($message) {
            return $message['role'] !== 'system';
        }));
    }
}
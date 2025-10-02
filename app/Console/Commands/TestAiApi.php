<?php

namespace App\Console\Commands;

use App\Services\LlmClient;
use App\Enums\AgentType;
use Illuminate\Console\Command;

class TestAiApi extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kudos:test-ai';

    /**
     * The console command description.
     */
    protected $description = 'Test AI API connection and functionality';

    /**
     * Execute the console command.
     */
    public function handle(LlmClient $llmClient): int
    {
        $this->info('Testing AI API connection...');
        
        try {
            $messages = [
                [
                    'role' => 'user',
                    'content' => 'Hello! Please respond with a simple greeting to confirm the API is working.'
                ]
            ];
            
            $this->info('Sending test message to AI API...');
            
            $response = $llmClient->chat(
                $messages,
                AgentType::DEV,
                null,
                null,
                'test-' . time()
            );
            
            $this->info('✅ AI API Response received successfully!');
            $this->line('Response: ' . json_encode($response, JSON_PRETTY_PRINT));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ AI API Test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
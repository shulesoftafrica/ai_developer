<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GitService;
use App\Services\LlmClient;
use App\Services\AgentClient;

class TestServices extends Command
{
    protected $signature = 'kudos:test-services';
    protected $description = 'Test all application services';

    public function handle()
    {
        $this->info('🧪 Testing Application Services');
        $this->newLine();

        // Test GitService
        try {
            $gitService = app(GitService::class);
            $this->line('GitService: <fg=green>✅ OK</fg=green>');
        } catch (\Exception $e) {
            $this->line('GitService: <fg=red>❌ FAIL</fg=red> - ' . $e->getMessage());
        }

        // Test LlmClient
        try {
            $llmClient = app(LlmClient::class);
            $this->line('LlmClient: <fg=green>✅ OK</fg=green>');
        } catch (\Exception $e) {
            $this->line('LlmClient: <fg=red>❌ FAIL</fg=red> - ' . $e->getMessage());
        }

        // Test AgentClient
        try {
            $agentClient = app(AgentClient::class);
            $this->line('AgentClient: <fg=green>✅ OK</fg=green>');
        } catch (\Exception $e) {
            $this->line('AgentClient: <fg=red>❌ FAIL</fg=red> - ' . $e->getMessage());
        }

        // Test Git repository validation
        try {
            $gitService = app(GitService::class);
            $isValid = $gitService->validateRepository();
            $this->line('Git Repository: ' . ($isValid ? '<fg=green>✅ Valid</fg=green>' : '<fg=yellow>⚠️ Invalid</fg=yellow>'));
        } catch (\Exception $e) {
            $this->line('Git Repository: <fg=red>❌ Error</fg=red> - ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('Service testing complete!');
    }
}
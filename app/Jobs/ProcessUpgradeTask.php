<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\Milestone;
use App\Enums\TaskStatus;
use App\Enums\AgentType;
use App\Services\AgentClient;
use App\Services\CommandRunner;
use App\Services\FilePatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class ProcessUpgradeTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 10800; // 3 hour timeout for upgrades
    public int $tries = 1; // Upgrades are complex, don't retry

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
        Log::info('ProcessUpgradeTask: Starting upgrade processing', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);

        try {
            // Step 1: Analyze current state and upgrade requirements
            $this->analyzeUpgrade($agentClient, $filePatcher, $commandRunner);

            // Step 2: Plan upgrade strategy
            $this->planUpgrade($agentClient);

            // Step 3: Backup current state
            $this->backupCurrentState($commandRunner);

            // Step 4: Execute upgrade steps
            $this->executeUpgrade($agentClient, $filePatcher, $commandRunner);

            // Step 5: Validate upgrade
            $this->validateUpgrade($agentClient, $commandRunner);

            // Step 6: Complete upgrade
            $this->completeUpgrade($commandRunner);

        } catch (\Exception $e) {
            Log::error('ProcessUpgradeTask: Upgrade failed', [
                'task_id' => $this->task->id,
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            $this->task->transitionTo(TaskStatus::FAILED);
            $this->task->unlock();
            throw $e;
        }
    }

    protected function analyzeUpgrade(
        AgentClient $agentClient,
        FilePatcher $filePatcher,
        CommandRunner $commandRunner
    ): void {
        Log::info('ProcessUpgradeTask: Analyzing current state', [
            'task_id' => $this->task->id,
        ]);

        // Get current package versions
        $composerInfo = $commandRunner->runComposer(['show', '--format=json']);
        $packageJson = $filePatcher->fileExists('package.json') 
            ? json_decode($filePatcher->readFile('package.json'), true) 
            : null;

        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 1,
            'title' => 'Upgrade Analysis',
            'description' => 'Analyze current versions and upgrade requirements',
            'agent_type' => AgentType::ARCH,
            'input_data' => [
                'composer_packages' => $composerInfo,
                'package_json' => $packageJson,
                'upgrade_target' => $this->task->content,
            ],
        ]);

        $milestone->start();

        $analysis = $agentClient->designArchitecture(
            $this->task->toArray(),
            $milestone->input_data,
            $this->runId
        );

        $milestone->complete($analysis);
    }

    protected function planUpgrade(AgentClient $agentClient): void
    {
        Log::info('ProcessUpgradeTask: Planning upgrade strategy', [
            'task_id' => $this->task->id,
        ]);

        $analysisData = $this->task->milestones()
            ->where('agent_type', AgentType::ARCH)
            ->first()
            ->output_data ?? [];

        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 2,
            'title' => 'Upgrade Planning',
            'description' => 'Create detailed upgrade execution plan',
            'agent_type' => AgentType::PM,
            'input_data' => $analysisData,
        ]);

        $milestone->start();

        $plan = $agentClient->planTask([
            'id' => $this->task->id,
            'type' => $this->task->type->value,
            'title' => $this->task->title,
            'description' => $this->task->description,
            'content' => array_merge($this->task->content, $analysisData),
        ], $this->runId);

        $milestone->complete($plan);
    }

    protected function backupCurrentState(CommandRunner $commandRunner): void
    {
        Log::info('ProcessUpgradeTask: Creating backup', [
            'task_id' => $this->task->id,
        ]);

        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 3,
            'title' => 'Backup Creation',
            'description' => 'Create backup of current state before upgrade',
            'agent_type' => AgentType::DEV,
        ]);

        $milestone->start();

        // Create git branch for backup
        $backupBranch = 'backup/upgrade-' . $this->task->id . '-' . date('Y-m-d-H-i-s');
        $branchResult = $commandRunner->run('git', ['checkout', '-b', $backupBranch]);

        if (!$branchResult['success']) {
            throw new \Exception('Failed to create backup branch: ' . $branchResult['stderr']);
        }

        $milestone->complete([
            'backup_branch' => $backupBranch,
            'git_result' => $branchResult,
        ]);
    }

    protected function executeUpgrade(
        AgentClient $agentClient,
        FilePatcher $filePatcher,
        CommandRunner $commandRunner
    ): void {
        Log::info('ProcessUpgradeTask: Executing upgrade', [
            'task_id' => $this->task->id,
        ]);

        $planData = $this->task->milestones()
            ->where('agent_type', AgentType::PM)
            ->first()
            ->output_data ?? [];

        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 4,
            'title' => 'Upgrade Execution',
            'description' => 'Execute the planned upgrade steps',
            'agent_type' => AgentType::DEV,
            'input_data' => $planData,
        ]);

        $milestone->start();

        // Execute upgrade commands based on plan
        $upgradeSteps = $planData['data']['upgrade_steps'] ?? [];
        $results = [];

        foreach ($upgradeSteps as $step) {
            Log::info('ProcessUpgradeTask: Executing upgrade step', [
                'step' => $step['title'] ?? 'Unknown step',
            ]);

            if (isset($step['command'])) {
                $result = $commandRunner->run($step['command'], $step['args'] ?? []);
                $results[] = [
                    'step' => $step,
                    'result' => $result,
                ];

                if (!$result['success']) {
                    throw new \Exception("Upgrade step failed: {$step['title']} - {$result['stderr']}");
                }
            }

            if (isset($step['file_changes'])) {
                foreach ($step['file_changes'] as $fileChange) {
                    if (isset($fileChange['path']) && isset($fileChange['content'])) {
                        $filePatcher->writeFile($fileChange['path'], $fileChange['content']);
                    }
                }
            }
        }

        $milestone->complete(['upgrade_results' => $results]);
    }

    protected function validateUpgrade(AgentClient $agentClient, CommandRunner $commandRunner): void
    {
        Log::info('ProcessUpgradeTask: Validating upgrade', [
            'task_id' => $this->task->id,
        ]);

        // Run tests to validate upgrade
        $testResult = $commandRunner->runTests();

        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 5,
            'title' => 'Upgrade Validation',
            'description' => 'Validate that upgrade completed successfully',
            'agent_type' => AgentType::QA,
            'input_data' => [
                'test_results' => $testResult,
            ],
        ]);

        $milestone->start();

        $validation = $agentClient->runTests(
            $this->task->toArray(),
            json_encode($testResult),
            $this->runId
        );

        if (!$testResult['success']) {
            throw new \Exception('Upgrade validation failed: tests are failing');
        }

        $milestone->complete($validation);
    }

    protected function completeUpgrade(CommandRunner $commandRunner): void
    {
        // Final test run
        $finalTestResult = $commandRunner->runTests();
        
        if (!$finalTestResult['success']) {
            throw new \Exception('Final tests failed after upgrade');
        }

        $this->task->transitionTo(TaskStatus::COMPLETED);
        $this->task->unlock();

        Log::info('ProcessUpgradeTask: Upgrade completed successfully', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessUpgradeTask: Job failed', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        $this->task->transitionTo(TaskStatus::FAILED);
        $this->task->unlock();
    }
}
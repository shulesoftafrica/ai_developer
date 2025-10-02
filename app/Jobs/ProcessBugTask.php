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

class ProcessBugTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout for bugs
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
        Log::info('ProcessBugTask: Starting bug fix processing', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);

        try {
            // Step 1: Analyze the bug
            $this->analyzeBug($agentClient, $commandRunner);

            // Step 2: Generate and apply fix
            $this->generateFix($agentClient, $filePatcher);

            // Step 3: Test the fix
            $this->testFix($agentClient, $commandRunner);

            // Step 4: Complete task
            $this->completeBugFix($commandRunner);

        } catch (\Exception $e) {
            Log::error('ProcessBugTask: Bug fix failed', [
                'task_id' => $this->task->id,
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            $this->task->transitionTo(TaskStatus::FAILED);
            $this->task->unlock();
            throw $e;
        }
    }

    protected function analyzeBug(AgentClient $agentClient, CommandRunner $commandRunner): void
    {
        Log::info('ProcessBugTask: Analyzing bug', [
            'task_id' => $this->task->id,
        ]);

        // Run tests to see current failures
        $testResult = $commandRunner->runTests();

        // Create analysis milestone
        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 1,
            'title' => 'Bug Analysis',
            'description' => 'Analyze the bug and identify root cause',
            'agent_type' => AgentType::QA,
            'input_data' => [
                'test_results' => $testResult,
                'bug_description' => $this->task->description,
            ],
        ]);

        $milestone->start();

        $analysis = $agentClient->runTests(
            $this->task->toArray(),
            json_encode($testResult),
            $this->runId
        );

        $milestone->complete($analysis);
    }

    protected function generateFix(AgentClient $agentClient, FilePatcher $filePatcher): void
    {
        Log::info('ProcessBugTask: Generating bug fix', [
            'task_id' => $this->task->id,
        ]);

        // Get the analysis results
        $analysisData = $this->task->milestones()
            ->where('agent_type', AgentType::QA)
            ->first()
            ->output_data ?? [];

        // Create development milestone
        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 2,
            'title' => 'Bug Fix Implementation',
            'description' => 'Generate and apply code fix for the bug',
            'agent_type' => AgentType::DEV,
            'input_data' => $analysisData,
        ]);

        $milestone->start();

        $fix = $agentClient->generateCode(
            $this->task->toArray(),
            $analysisData,
            '',
            $this->runId
        );

        // Apply the fix
        if (isset($fix['data']['file_changes'])) {
            foreach ($fix['data']['file_changes'] as $fileChange) {
                $filePath = $fileChange['path'];
                
                if (isset($fileChange['content'])) {
                    $filePatcher->writeFile($filePath, $fileChange['content']);
                } elseif (isset($fileChange['patches'])) {
                    $filePatcher->patchFile($filePath, $fileChange['patches']);
                }
            }
        }

        $milestone->complete($fix);
    }

    protected function testFix(AgentClient $agentClient, CommandRunner $commandRunner): void
    {
        Log::info('ProcessBugTask: Testing bug fix', [
            'task_id' => $this->task->id,
        ]);

        // Run tests again
        $testResult = $commandRunner->runTests();

        // Create testing milestone
        $milestone = Milestone::create([
            'task_id' => $this->task->id,
            'sequence' => 3,
            'title' => 'Bug Fix Verification',
            'description' => 'Verify that the bug fix works correctly',
            'agent_type' => AgentType::QA,
            'input_data' => [
                'post_fix_test_results' => $testResult,
            ],
        ]);

        $milestone->start();

        $verification = $agentClient->runTests(
            $this->task->toArray(),
            json_encode($testResult),
            $this->runId
        );

        if (!$testResult['success']) {
            throw new \Exception('Bug fix failed verification tests');
        }

        $milestone->complete($verification);
    }

    protected function completeBugFix(CommandRunner $commandRunner): void
    {
        // Final test run
        $finalTestResult = $commandRunner->runTests();
        
        if (!$finalTestResult['success']) {
            throw new \Exception('Final tests failed after bug fix');
        }

        $this->task->transitionTo(TaskStatus::COMPLETED);
        $this->task->unlock();

        Log::info('ProcessBugTask: Bug fix completed successfully', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessBugTask: Job failed', [
            'task_id' => $this->task->id,
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        $this->task->transitionTo(TaskStatus::FAILED);
        $this->task->unlock();
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Jobs\ProcessNewTask;
use App\Enums\TaskStatus;

class TestTaskProcessing extends Command
{
    protected $signature = 'kudos:test-task {task_id? : Specific task ID to test}';
    protected $description = 'Test task processing manually';

    public function handle()
    {
        $taskId = $this->argument('task_id');
        
        if ($taskId) {
            $task = Task::find($taskId);
            if (!$task) {
                $this->error("Task {$taskId} not found");
                return 1;
            }
        } else {
            // Get first pending task
            $task = Task::where('status', TaskStatus::PENDING)->first();
            if (!$task) {
                $this->error("No pending tasks found");
                return 1;
            }
        }

        $this->info("Testing task processing for Task #{$task->id}: {$task->title}");
        $this->newLine();

        try {
            // Reset task status if it was failed
            if ($task->status === TaskStatus::FAILED) {
                $task->status = TaskStatus::PENDING;
                $task->save();
                $this->line("Reset task status to pending");
            }

            // Process the task directly (not via queue)
            $this->line("Starting task processing...");
            
            $job = new ProcessNewTask($task);
            
            // We'll catch any exceptions to see what's failing
            $agentClient = app(\App\Services\AgentClient::class);
            $filePatcher = app(\App\Services\FilePatcher::class);
            $commandRunner = app(\App\Services\CommandRunner::class);
            
            $job->handle($agentClient, $filePatcher, $commandRunner);
            
            $this->info("Task processed successfully!");
            
        } catch (\Exception $e) {
            $this->error("Task processing failed:");
            $this->line("Error: " . $e->getMessage());
            $this->line("File: " . $e->getFile());
            $this->line("Line: " . $e->getLine());
            
            if ($e->getPrevious()) {
                $this->line("Previous error: " . $e->getPrevious()->getMessage());
            }
            
            return 1;
        }

        return 0;
    }
}
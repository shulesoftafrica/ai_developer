<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\Milestone;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Services\AgentClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class ProcessNextTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout
    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ProcessNextTask: Looking for available tasks');

        // Find next available task
        $task = Task::where('status', TaskStatus::PENDING)
            ->where(function ($query) {
                $query->whereNull('locked_at')
                    ->orWhere('locked_at', '<', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at')
            ->first();

        if (!$task) {
            Log::info('ProcessNextTask: No available tasks found');
            return;
        }

        // Lock the task for 1 hour
        $workerId = 'worker-' . gethostname() . '-' . getmypid();
        if (!$task->lock($workerId, 3600)) {
            Log::warning('ProcessNextTask: Failed to lock task', ['task_id' => $task->id]);
            return;
        }

        Log::info('ProcessNextTask: Processing task', [
            'task_id' => $task->id,
            'type' => $task->type->value,
            'title' => $task->title,
            'worker_id' => $workerId,
        ]);

        try {
            // Update task status
            $task->transitionTo(TaskStatus::IN_PROGRESS);

            // Dispatch type-specific job
            $jobClass = $task->type->getJobClass();
            dispatch(new $jobClass($task));

            Log::info('ProcessNextTask: Dispatched task to specific handler', [
                'task_id' => $task->id,
                'job_class' => $jobClass,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessNextTask: Failed to process task', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark task as failed and unlock
            $task->transitionTo(TaskStatus::FAILED);
            $task->unlock();

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNextTask: Job failed completely', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
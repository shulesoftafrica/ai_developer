<?php

namespace App\Console\Commands;

use App\Jobs\ProcessNextTask;
use App\Models\Task;
use App\Enums\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kudos:dispatch-tasks
                            {--limit=1 : Maximum number of tasks to dispatch}
                            {--force : Force dispatch even if tasks are locked}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending tasks to the queue for processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $force = $this->option('force');

        $this->info("Dispatching up to {$limit} pending tasks...");

        $query = Task::where('status', TaskStatus::PENDING);

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('locked_at')
                  ->orWhere('locked_at', '<', now());
            });
        }

        $tasks = $query->orderBy('priority')
                      ->orderBy('created_at')
                      ->limit($limit)
                      ->get();

        if ($tasks->isEmpty()) {
            $this->info('No pending tasks found.');
            return 0;
        }

        $dispatched = 0;
        $errors = 0;

        foreach ($tasks as $task) {
            try {
                ProcessNextTask::dispatch($task);
                $dispatched++;
                
                $this->line("✓ Dispatched task #{$task->id}: {$task->title}");
                
                Log::info('Task dispatched via command', [
                    'task_id' => $task->id,
                    'title' => $task->title,
                ]);

            } catch (\Exception $e) {
                $errors++;
                $this->error("✗ Failed to dispatch task #{$task->id}: {$e->getMessage()}");
                
                Log::error('Failed to dispatch task via command', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Dispatch complete: {$dispatched} successful, {$errors} errors");

        return $errors > 0 ? 1 : 0;
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\AiLog;
use App\Models\Milestone;

class DebugTasks extends Command
{
    protected $signature = 'kudos:debug-tasks {--recent=10 : Number of recent tasks to show}';
    protected $description = 'Debug task processing issues';

    public function handle()
    {
        $this->info('🔍 Debugging Task Processing');
        $this->newLine();

        $recent = (int) $this->option('recent');

        // Show recent tasks with details
        $this->line('<fg=cyan>📋 Recent Tasks</fg=cyan>');
        $this->line('─────────────────');
        
        $tasks = Task::orderBy('updated_at', 'desc')
            ->limit($recent)
            ->get();

        foreach ($tasks as $task) {
            $status = $task->status->value ?? $task->status;
            $statusColor = match($status) {
                'completed' => 'green',
                'failed' => 'red',
                'in_progress' => 'yellow',
                default => 'white'
            };
            
            $this->line(sprintf(
                '[%d] %s - <fg=%s>%s</fg=%s> (%s)',
                $task->id,
                $task->title,
                $statusColor,
                $status,
                $statusColor,
                $task->updated_at->diffForHumans()
            ));
        }

        $this->newLine();

        // Show failed tasks with more details
        $failedTasks = Task::where('status', \App\Enums\TaskStatus::FAILED)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        if ($failedTasks->count() > 0) {
            $this->line('<fg=red>❌ Failed Tasks Details</fg=red>');
            $this->line('─────────────────');
            
            foreach ($failedTasks as $task) {
                $this->line("Task ID: {$task->id}");
                $this->line("Title: {$task->title}");
                $this->line("Type: {$task->type->value}");
                $this->line("Updated: {$task->updated_at}");
                
                // Check for related AI logs
                $aiLogs = AiLog::where('task_id', $task->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(3)
                    ->get();
                
                if ($aiLogs->count() > 0) {
                    $this->line("Recent AI Activity:");
                    foreach ($aiLogs as $log) {
                        $this->line("  • {$log->agent_type->value} - {$log->status} ({$log->created_at->diffForHumans()})");
                        if ($log->status === 'error' && $log->response) {
                            $response = is_array($log->response) ? json_encode($log->response) : $log->response;
                            $this->line("    Error: " . substr($response, 0, 100) . "...");
                        }
                    }
                } else {
                    $this->line("No AI logs found for this task");
                }
                
                $this->newLine();
            }
        }

        // Show recent AI logs
        $this->line('<fg=cyan>🤖 Recent AI Activity</fg=cyan>');
        $this->line('─────────────────');
        
        $aiLogs = AiLog::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($aiLogs as $log) {
            $statusColor = match($log->status) {
                'success' => 'green',
                'error' => 'red',
                'cache_hit' => 'blue',
                default => 'white'
            };
            
            $this->line(sprintf(
                '%s - <fg=%s>%s</fg=%s> - Task #%s (%s)',
                $log->agent_type->value,
                $statusColor,
                $log->status,
                $statusColor,
                $log->task_id ?? 'N/A',
                $log->created_at->diffForHumans()
            ));
        }

        $this->newLine();

        // Show system stats
        $this->line('<fg=cyan>📊 System Statistics</fg=cyan>');
        $this->line('─────────────────');
        
        $stats = [
            'Total Tasks' => Task::count(),
            'Pending Tasks' => Task::where('status', \App\Enums\TaskStatus::PENDING)->count(),
            'In Progress' => Task::where('status', \App\Enums\TaskStatus::IN_PROGRESS)->count(),
            'Completed' => Task::where('status', \App\Enums\TaskStatus::COMPLETED)->count(),
            'Failed' => Task::where('status', \App\Enums\TaskStatus::FAILED)->count(),
            'Total Milestones' => Milestone::count(),
            'AI Requests Today' => AiLog::whereDate('created_at', today())->count(),
        ];

        foreach ($stats as $label => $count) {
            $this->line("{$label}: {$count}");
        }
    }
}
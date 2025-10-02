<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\Milestone;
use App\Models\AiLog;
use App\Enums\TaskStatus;

class MonitorSystem extends Command
{
    protected $signature = 'kudos:monitor {--refresh=5 : Refresh interval in seconds}';
    protected $description = 'Monitor system status and queue activity';

    public function handle()
    {
        $refresh = (int) $this->option('refresh');
        
        $this->info('🤖 Kudos Orchestrator System Monitor');
        $this->info('Press Ctrl+C to stop monitoring');
        $this->newLine();

        while (true) {
            $this->clearScreen();
            $this->displayHeader();
            $this->displaySystemStatus();
            $this->displayQueueStatus();
            $this->displayRecentActivity();
            
            sleep($refresh);
        }
    }

    private function clearScreen()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }

    private function displayHeader()
    {
        $this->info('🤖 Kudos AI Development Orchestrator - System Monitor');
        $this->info('Time: ' . now()->format('Y-m-d H:i:s'));
        $this->info('Refresh: ' . $this->option('refresh') . 's');
        $this->newLine();
    }

    private function displaySystemStatus()
    {
        $this->line('<fg=cyan>📊 System Status</>');
        $this->line('─────────────────');

        // Database connection
        try {
            DB::connection()->getPdo();
            $this->line('Database: <fg=green>✅ Connected</fg=green>');
        } catch (\Exception $e) {
            $this->line('Database: <fg=red>❌ Disconnected</fg=red>');
        }

        // Redis connection
        try {
            Redis::ping();
            $this->line('Redis: <fg=green>✅ Connected</fg=green>');
        } catch (\Exception $e) {
            $this->line('Redis: <fg=red>❌ Disconnected</fg=red>');
        }

        // Git repository
        try {
            $gitService = app(\App\Services\GitService::class);
            $valid = $gitService->validateRepository();
            $this->line('Git Repository: ' . ($valid ? '<fg=green>✅ Valid</fg=green>' : '<fg=yellow>⚠️ Invalid</fg=yellow>'));
        } catch (\Exception $e) {
            $this->line('Git Repository: <fg=red>❌ Error</fg=red>');
        }

        $this->newLine();
    }

    private function displayQueueStatus()
    {
        $this->line('<fg=cyan>🔄 Queue Status</>');
        $this->line('─────────────────');

        try {
            // Get queue sizes
            $defaultQueue = Redis::llen('queues:default') ?? 0;
            $failedJobs = DB::table('failed_jobs')->count();

            $this->line("Pending Jobs: <fg=yellow>{$defaultQueue}</fg=yellow>");
            $this->line("Failed Jobs: " . ($failedJobs > 0 ? "<fg=red>{$failedJobs}</fg=red>" : "<fg=green>{$failedJobs}</fg=green>"));

            // Recent job processing
            $recentJobs = DB::table('jobs')
                ->select('queue', 'payload')
                ->orderBy('id', 'desc')
                ->limit(3)
                ->get();

            if ($recentJobs->count() > 0) {
                $this->line('Recent Jobs:');
                foreach ($recentJobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobName = $payload['displayName'] ?? 'Unknown';
                    $this->line("  • {$jobName} (Queue: {$job->queue})");
                }
            }

        } catch (\Exception $e) {
            $this->line('<fg=red>❌ Queue status unavailable</fg=red>');
        }

        $this->newLine();
    }

    private function displayRecentActivity()
    {
        $this->line('<fg=cyan>📈 Recent Activity</>');
        $this->line('─────────────────');

        // Task statistics
        $tasks = [
            'total' => Task::count(),
            'pending' => Task::where('status', TaskStatus::PENDING)->count(),
            'in_progress' => Task::where('status', TaskStatus::IN_PROGRESS)->count(),
            'completed' => Task::where('status', TaskStatus::COMPLETED)->count(),
            'failed' => Task::where('status', TaskStatus::FAILED)->count(),
        ];

        $this->line("Tasks: Total {$tasks['total']} | Pending {$tasks['pending']} | In Progress {$tasks['in_progress']} | Completed {$tasks['completed']} | Failed {$tasks['failed']}");

        // Milestone statistics
        $milestones = [
            'total' => Milestone::count(),
            'pending' => Milestone::where('status', 'pending')->count(),
            'completed' => Milestone::where('status', 'completed')->count(),
        ];

        $this->line("Milestones: Total {$milestones['total']} | Pending {$milestones['pending']} | Completed {$milestones['completed']}");

        // AI activity
        $aiToday = AiLog::whereDate('created_at', today())->count();
        $aiSuccessRate = $this->getAiSuccessRate();

        $this->line("AI Activity: {$aiToday} requests today | Success rate: {$aiSuccessRate}%");

        // Recent tasks
        $recentTasks = Task::orderBy('updated_at', 'desc')->limit(3)->get(['id', 'title', 'status', 'updated_at']);
        
        if ($recentTasks->count() > 0) {
            $this->newLine();
            $this->line('Recent Tasks:');
            foreach ($recentTasks as $task) {
                $status = $task->status->value ?? $task->status; // Handle enum
                $statusColor = match($status) {
                    'completed' => 'green',
                    'failed' => 'red',
                    'in_progress' => 'yellow',
                    default => 'white'
                };
                $this->line("  • [{$task->id}] {$task->title} - <fg={$statusColor}>{$status}</fg={$statusColor}> ({$task->updated_at->diffForHumans()})");
            }
        }

        $this->newLine();
        $this->line('<fg=gray>Press Ctrl+C to exit | Refresh every ' . $this->option('refresh') . ' seconds</fg=gray>');
    }

    private function getAiSuccessRate(): float
    {
        $total = AiLog::count();
        if ($total === 0) {
            return 100.0;
        }

        $successful = AiLog::whereIn('status', ['success', 'cache_hit'])->count();
        return round(($successful / $total) * 100, 1);
    }
}
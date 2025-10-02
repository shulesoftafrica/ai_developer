<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Sprint;
use App\Models\AiLog;
use App\Enums\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class SystemStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kudos:status
                            {--json : Output in JSON format}
                            {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show system status and health information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $json = $this->option('json');
        $detailed = $this->option('detailed');

        $status = $this->gatherSystemStatus($detailed);

        if ($json) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));
        } else {
            $this->displayStatus($status, $detailed);
        }

        return 0;
    }

    private function gatherSystemStatus(bool $detailed): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'tasks' => $this->getTaskStatus($detailed),
            'sprints' => $this->getSprintStatus($detailed),
            'queue' => $this->getQueueStatus($detailed),
            'ai_logs' => $this->getAiLogStatus($detailed),
            'system' => $this->getSystemStatus($detailed),
        ];
    }

    private function getTaskStatus(bool $detailed): array
    {
        $tasks = [
            'total' => Task::count(),
            'by_status' => [
                'pending' => Task::where('status', TaskStatus::PENDING)->count(),
                'in_progress' => Task::where('status', TaskStatus::IN_PROGRESS)->count(),
                'completed' => Task::where('status', TaskStatus::COMPLETED)->count(),
                'failed' => Task::where('status', TaskStatus::FAILED)->count(),
                'cancelled' => Task::where('status', TaskStatus::CANCELLED)->count(),
            ],
            'locked_tasks' => Task::whereNotNull('locked_at')
                                 ->where('locked_at', '>', now())
                                 ->count(),
        ];

        if ($detailed) {
            $tasks['recent_activity'] = Task::with('milestones')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status->value,
                        'type' => $task->type->value,
                        'updated_at' => $task->updated_at->toISOString(),
                        'milestone_count' => $task->milestones->count(),
                    ];
                });
        }

        return $tasks;
    }

    private function getSprintStatus(bool $detailed): array
    {
        $sprints = [
            'total' => Sprint::count(),
            'active' => Sprint::where('status', 'active')->count(),
            'planning' => Sprint::where('status', 'planning')->count(),
            'completed' => Sprint::where('status', 'completed')->count(),
        ];

        if ($detailed) {
            $activeSprint = Sprint::where('status', 'active')->first();
            if ($activeSprint) {
                $sprints['current_sprint'] = [
                    'id' => $activeSprint->id,
                    'name' => $activeSprint->name,
                    'start_date' => $activeSprint->start_date?->toDateString(),
                    'end_date' => $activeSprint->end_date?->toDateString(),
                    'progress' => $activeSprint->progress,
                ];
            }
        }

        return $sprints;
    }

    private function getQueueStatus(bool $detailed): array
    {
        $queue = [
            'pending_jobs' => $this->getQueueSize('default'),
            'failed_jobs' => $this->getQueueSize('failed'),
        ];

        if ($detailed) {
            $queue['workers'] = $this->getWorkerStatus();
        }

        return $queue;
    }

    private function getQueueSize(string $queue): int
    {
        try {
            return Redis::llen("queues:{$queue}") ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getWorkerStatus(): array
    {
        // This is a simplified worker status check
        // In production, you might want to use Laravel Horizon
        return [
            'redis_connected' => $this->checkRedisConnection(),
            'queue_connection' => config('queue.default'),
        ];
    }

    private function checkRedisConnection(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getAiLogStatus(bool $detailed): array
    {
        $logs = [
            'total' => AiLog::count(),
            'today' => AiLog::whereDate('created_at', today())->count(),
            'success_rate' => $this->getAiSuccessRate(),
        ];

        if ($detailed) {
            $logs['by_agent'] = AiLog::selectRaw('agent_type, count(*) as count')
                ->groupBy('agent_type')
                ->pluck('count', 'agent_type')
                ->toArray();

            $logs['recent_errors'] = AiLog::where('status', 'error')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get(['agent_type', 'error_message', 'created_at'])
                ->toArray();
        }

        return $logs;
    }

    private function getAiSuccessRate(): float
    {
        $total = AiLog::count();
        if ($total === 0) return 100.0;

        $successful = AiLog::where('status', 'success')->count();
        return round(($successful / $total) * 100, 2);
    }

    private function getSystemStatus(bool $detailed): array
    {
        $system = [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
        ];

        if ($detailed) {
            $system['memory_usage'] = [
                'current' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            ];
            
            $system['disk_space'] = [
                'free' => $this->formatBytes(disk_free_space(base_path())),
                'total' => $this->formatBytes(disk_total_space(base_path())),
            ];
        }

        return $system;
    }

    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    private function displayStatus(array $status, bool $detailed): void
    {
        $this->info('Kudos Orchestrator System Status');
        $this->info('Generated: ' . $status['timestamp']);
        $this->newLine();

        // Tasks
        $this->info('📋 Tasks');
        $this->line("  Total: {$status['tasks']['total']}");
        $this->line("  Pending: {$status['tasks']['by_status']['pending']}");
        $this->line("  In Progress: {$status['tasks']['by_status']['in_progress']}");
        $this->line("  Completed: {$status['tasks']['by_status']['completed']}");
        $this->line("  Failed: {$status['tasks']['by_status']['failed']}");
        $this->line("  Locked: {$status['tasks']['locked_tasks']}");
        $this->newLine();

        // Sprints
        $this->info('🏃 Sprints');
        $this->line("  Total: {$status['sprints']['total']}");
        $this->line("  Active: {$status['sprints']['active']}");
        $this->line("  Planning: {$status['sprints']['planning']}");
        $this->line("  Completed: {$status['sprints']['completed']}");
        $this->newLine();

        // Queue
        $this->info('⚡ Queue');
        $this->line("  Pending Jobs: {$status['queue']['pending_jobs']}");
        $this->line("  Failed Jobs: {$status['queue']['failed_jobs']}");
        if (isset($status['queue']['workers']['redis_connected'])) {
            $redisStatus = $status['queue']['workers']['redis_connected'] ? '✓ Connected' : '✗ Disconnected';
            $this->line("  Redis: {$redisStatus}");
        }
        $this->newLine();

        // AI Logs
        $this->info('🤖 AI Logs');
        $this->line("  Total: {$status['ai_logs']['total']}");
        $this->line("  Today: {$status['ai_logs']['today']}");
        $this->line("  Success Rate: {$status['ai_logs']['success_rate']}%");
        $this->newLine();

        // System
        $this->info('💻 System');
        $this->line("  Laravel: {$status['system']['laravel_version']}");
        $this->line("  PHP: {$status['system']['php_version']}");
        $this->line("  Environment: {$status['system']['environment']}");
        
        if (isset($status['system']['memory_usage'])) {
            $this->line("  Memory: {$status['system']['memory_usage']['current']} (peak: {$status['system']['memory_usage']['peak']})");
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\AiLog;
use App\Models\Milestone;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\GitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    /**
     * Display the main dashboard
     */
    public function index(): View
    {
        $stats = $this->getSystemStats();
        $recentTasks = $this->getRecentTasks();
        $activeSprints = $this->getActiveSprints();
        $recentLogs = $this->getRecentAiLogs();
        $gitStatus = $this->getGitStatus();

        return view('dashboard.index', compact(
            'stats',
            'recentTasks', 
            'activeSprints',
            'recentLogs',
            'gitStatus'
        ));
    }

    /**
     * API endpoint for system statistics
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->getSystemStats());
    }

    /**
     * API endpoint for tasks data
     */
    public function tasks(Request $request): JsonResponse
    {
        $query = Task::with(['sprint', 'milestones'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        $tasks = $query->paginate(20);

        return response()->json($tasks);
    }

    /**
     * API endpoint for milestones data
     */
    public function milestones(Request $request): JsonResponse
    {
        $query = Milestone::with('task')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('agent_type')) {
            $query->where('agent_type', $request->get('agent_type'));
        }

        $milestones = $query->paginate(20);

        return response()->json($milestones);
    }

    /**
     * API endpoint for AI logs data
     */
    public function aiLogs(Request $request): JsonResponse
    {
        $query = AiLog::orderBy('created_at', 'desc');

        if ($request->has('agent_type')) {
            $query->where('agent_type', $request->get('agent_type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }

    /**
     * Get system statistics
     */
    private function getSystemStats(): array
    {
        return [
            'tasks' => [
                'total' => Task::count(),
                'pending' => Task::where('status', \App\Enums\TaskStatus::PENDING)->count(),
                'in_progress' => Task::where('status', \App\Enums\TaskStatus::IN_PROGRESS)->count(),
                'completed' => Task::where('status', \App\Enums\TaskStatus::COMPLETED)->count(),
                'failed' => Task::where('status', \App\Enums\TaskStatus::FAILED)->count(),
            ],
            'sprints' => [
                'total' => Sprint::count(),
                'active' => Sprint::where('status', 'active')->count(),
                'completed' => Sprint::where('status', 'completed')->count(),
            ],
            'milestones' => [
                'total' => Milestone::count(),
                'pending' => Milestone::where('status', \App\Enums\MilestoneStatus::PENDING)->count(),
                'in_progress' => Milestone::where('status', \App\Enums\MilestoneStatus::IN_PROGRESS)->count(),
                'completed' => Milestone::where('status', \App\Enums\MilestoneStatus::COMPLETED)->count(),
                'failed' => Milestone::where('status', \App\Enums\MilestoneStatus::FAILED)->count(),
            ],
            'ai_logs' => [
                'total' => AiLog::count(),
                'today' => AiLog::whereDate('created_at', Carbon::today())->count(),
                'success_rate' => $this->getAiSuccessRate(),
                'avg_response_time' => AiLog::avg('execution_time_ms'),
            ],
            'queue' => $this->getQueueStats(),
        ];
    }

    /**
     * Get recent tasks
     */
    private function getRecentTasks(): array
    {
        return Task::with(['sprint', 'milestones'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get active sprints
     */
    private function getActiveSprints(): array
    {
        return Sprint::with(['tasks'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get recent AI logs
     */
    private function getRecentAiLogs(): array
    {
        return AiLog::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get Git status
     */
    private function getGitStatus(): array
    {
        try {
            return [
                'repository_valid' => $this->gitService->validateRepository(),
                'status' => $this->gitService->getStatus(),
                'repository_info' => $this->gitService->getRepositoryInfo(),
            ];
        } catch (\Exception $e) {
            return [
                'repository_valid' => false,
                'status' => [],
                'repository_info' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get AI success rate
     */
    private function getAiSuccessRate(): float
    {
        $total = AiLog::count();
        if ($total === 0) {
            return 100.0;
        }

        $successful = AiLog::whereIn('status', ['success', 'cache_hit'])->count();
        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get queue statistics
     */
    private function getQueueStats(): array
    {
        // This would typically require Redis connection to get queue stats
        // For now, return basic structure
        return [
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'processed_jobs' => 0,
        ];
    }

    /**
     * Task performance analytics
     */
    public function analytics(): JsonResponse
    {
        $tasksByType = Task::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $tasksByStatus = Task::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        $milestonesByAgent = Milestone::select('agent_type', DB::raw('count(*) as count'))
            ->groupBy('agent_type')
            ->get();
            

        $dailyActivity = AiLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total_requests'),
                DB::raw('AVG(execution_time_ms) as avg_response_time'),
                DB::raw('SUM(CASE WHEN status IN (\'success\', \'cache_hit\') THEN 1 ELSE 0 END) as successful_requests')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
            $res =[
            'tasks_by_type' => $tasksByType,
            'tasks_by_status' => $tasksByStatus,
            'milestones_by_agent' => $milestonesByAgent,
            'daily_activity' => $dailyActivity,
            ];
            $json = response()->json($res);
    

        return $json;
    }
}
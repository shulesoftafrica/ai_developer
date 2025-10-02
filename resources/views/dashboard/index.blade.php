@extends('layouts.app')

@section('title', 'Dashboard - Kudos Orchestrator')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">System Dashboard</h2>
        <p class="text-gray-600">Monitor AI development orchestrator performance and activity</p>
    </div>

    <!-- System Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Tasks Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Tasks</h3>
                    <div class="mt-2">
                        <div class="text-3xl font-bold text-blue-600" id="total-tasks">{{ $stats['tasks']['total'] }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            <span class="text-green-600">{{ $stats['tasks']['completed'] }} completed</span> • 
                            <span class="text-yellow-600">{{ $stats['tasks']['in_progress'] }} in progress</span>
                        </div>
                    </div>
                </div>
                <div class="ml-4">
                    <i class="fas fa-tasks text-2xl text-blue-500"></i>
                </div>
            </div>
        </div>

        <!-- Sprints Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Sprints</h3>
                    <div class="mt-2">
                        <div class="text-3xl font-bold text-green-600" id="total-sprints">{{ $stats['sprints']['total'] }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            <span class="text-green-600">{{ $stats['sprints']['active'] }} active</span> • 
                            <span class="text-gray-600">{{ $stats['sprints']['completed'] }} completed</span>
                        </div>
                    </div>
                </div>
                <div class="ml-4">
                    <i class="fas fa-calendar-alt text-2xl text-green-500"></i>
                </div>
            </div>
        </div>

        <!-- Milestones Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Milestones</h3>
                    <div class="mt-2">
                        <div class="text-3xl font-bold text-purple-600" id="total-milestones">{{ $stats['milestones']['total'] }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            <span class="text-green-600">{{ $stats['milestones']['completed'] }} completed</span> • 
                            <span class="text-yellow-600">{{ $stats['milestones']['in_progress'] }} in progress</span>
                        </div>
                    </div>
                </div>
                <div class="ml-4">
                    <i class="fas fa-flag-checkered text-2xl text-purple-500"></i>
                </div>
            </div>
        </div>

        <!-- AI Logs Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">AI Performance</h3>
                    <div class="mt-2">
                        <div class="text-3xl font-bold text-orange-600" id="ai-success-rate">{{ number_format($stats['ai_logs']['success_rate'], 1) }}%</div>
                        <div class="text-sm text-gray-500 mt-1">
                            <span class="text-blue-600">{{ $stats['ai_logs']['today'] }} requests today</span>
                        </div>
                    </div>
                </div>
                <div class="ml-4">
                    <i class="fas fa-brain text-2xl text-orange-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Data Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tasks by Status Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tasks by Status</h3>
            <canvas id="tasksChart" width="400" height="200"></canvas>
        </div>

        <!-- AI Agents Performance -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">AI Agents Activity</h3>
            <canvas id="agentsChart" width="400" height="200"></canvas>
        </div>
    </div>

    <!-- Git Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fab fa-git-alt mr-2"></i>Git Repository Status
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center">
                <div class="flex-1">
                    <div class="text-sm text-gray-500">Repository Status</div>
                    @if($gitStatus['repository_valid'])
                        <div class="text-green-600 font-semibold">
                            <i class="fas fa-check-circle mr-1"></i>Valid
                        </div>
                    @else
                        <div class="text-red-600 font-semibold">
                            <i class="fas fa-times-circle mr-1"></i>Invalid
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex items-center">
                <div class="flex-1">
                    <div class="text-sm text-gray-500">Current Branch</div>
                    <div class="font-semibold">{{ $gitStatus['status']['current_branch'] ?? 'master' }}</div>
                </div>
            </div>
            <div class="flex items-center">
                <div class="flex-1">
                    <div class="text-sm text-gray-500">Pending Changes</div>
                    @if(isset($gitStatus['status']['has_changes']) && $gitStatus['status']['has_changes'])
                        <div class="text-yellow-600 font-semibold">
                            <i class="fas fa-edit mr-1"></i>Yes
                        </div>
                    @else
                        <div class="text-green-600 font-semibold">
                            <i class="fas fa-check mr-1"></i>Clean
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks and Logs -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Tasks -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Tasks</h3>
                <button onclick="refreshTasks()" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-refresh"></i>
                </button>
            </div>
            <div class="space-y-3" id="recent-tasks">
                @if(count($recentTasks) > 0)
                    @foreach($recentTasks as $task)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div class="flex-1">
                            <div class="font-medium">{{ $task['title'] }}</div>
                            <div class="text-sm text-gray-500">
                                {{ ucfirst($task['type']) }} • 
                                <span class="text-xs">{{ \Carbon\Carbon::parse($task['created_at'])->diffForHumans() }}</span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <span class="px-2 py-1 text-xs rounded-full 
                                @if($task['status'] === 'completed') bg-green-100 text-green-800
                                @elseif($task['status'] === 'in_progress') bg-yellow-100 text-yellow-800
                                @elseif($task['status'] === 'failed') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $task['status'])) }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="text-center text-gray-500 py-4">
                        <i class="fas fa-tasks text-3xl mb-2"></i>
                        <div>No tasks found</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent AI Logs -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent AI Activity</h3>
                <button onclick="refreshLogs()" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-refresh"></i>
                </button>
            </div>
            <div class="space-y-3" id="recent-logs">
                @if(count($recentLogs) > 0)
                    @foreach(array_slice($recentLogs, 0, 8) as $log)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div class="flex-1">
                            <div class="font-medium text-sm">{{ ucfirst($log['agent_type']) }} Agent</div>
                            <div class="text-xs text-gray-500">
                                {{ \Carbon\Carbon::parse($log['created_at'])->format('H:i:s') }} • 
                                {{ $log['execution_time_ms'] }}ms
                            </div>
                        </div>
                        <div class="ml-4">
                            @if($log['status'] === 'success' || $log['status'] === 'cache_hit')
                                <i class="fas fa-check-circle text-green-500"></i>
                            @else
                                <i class="fas fa-times-circle text-red-500"></i>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="text-center text-gray-500 py-4">
                        <i class="fas fa-robot text-3xl mb-2"></i>
                        <div>No AI activity yet</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')


<script>
    // Initialize charts
    let tasksChart, agentsChart;

    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        loadAnalytics();
    });

    function initializeCharts() {
        // Tasks by Status Chart
        const tasksCtx = document.getElementById('tasksChart').getContext('2d');
        tasksChart = new Chart(tasksCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Failed'],
                datasets: [{
                    data: [{{ $stats['tasks']['pending'] }}, {{ $stats['tasks']['in_progress'] }}, {{ $stats['tasks']['completed'] }}, {{ $stats['tasks']['failed'] }}],
                    backgroundColor: ['#9CA3AF', '#F59E0B', '#10B981', '#EF4444']
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false
            }
        });

        // AI Agents Chart
        const agentsCtx = document.getElementById('agentsChart').getContext('2d');
        agentsChart = new Chart(agentsCtx, {
            type: 'bar',
            data: {
                labels: ['PM', 'BA', 'UX', 'Arch', 'Dev', 'QA', 'Doc'],
                datasets: [{
                    label: 'Milestones',
                    data: [0, 0, 0, 0, 0, 0, 0], // Will be populated by loadAnalytics()
                    backgroundColor: '#3B82F6'
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function loadAnalytics() {
        axios.get('/api/dashboard/analytics')
            .then(function(response) {
                const data = response.data;
                
                // Update agents chart
                if (data.milestones_by_agent) {
                    const agentData = [0, 0, 0, 0, 0, 0, 0];
                    const agentMap = {
                        'pm': 0, 'ba': 1, 'ux': 2, 'arch': 3, 'dev': 4, 'qa': 5, 'doc': 6
                    };
                    
                    data.milestones_by_agent.forEach(function(item) {
                        if (agentMap[item.agent_type] !== undefined) {
                            agentData[agentMap[item.agent_type]] = item.count;
                        }
                    });
                    
                    agentsChart.data.datasets[0].data = agentData;
                    agentsChart.update();
                }
            })
            .catch(function(error) {
                console.error('Failed to load analytics:', error);
            });
    }

    function refreshData() {
        location.reload();
    }

    function refreshTasks() {
        // Implement AJAX refresh for tasks
        console.log('Refreshing tasks...');
    }

    function refreshLogs() {
        // Implement AJAX refresh for logs
        console.log('Refreshing logs...');
    }
</script>
@endsection
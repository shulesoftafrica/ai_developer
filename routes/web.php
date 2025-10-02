<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

// Dashboard routes
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
Route::get('/api/dashboard/tasks', [DashboardController::class, 'tasks'])->name('dashboard.tasks');
Route::get('/api/dashboard/milestones', [DashboardController::class, 'milestones'])->name('dashboard.milestones');
Route::get('/api/dashboard/ai-logs', [DashboardController::class, 'aiLogs'])->name('dashboard.ai-logs');
Route::get('/api/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');
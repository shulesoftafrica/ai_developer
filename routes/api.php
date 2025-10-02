<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::middleware(['api'])->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok', 'timestamp' => now()]);
    });
    
    // API v1 routes using existing DashboardController
    Route::prefix('v1')->group(function () {
        // System statistics
        Route::get('/stats', [DashboardController::class, 'stats']);
        
        // Tasks endpoints
        Route::get('/tasks', [DashboardController::class, 'tasks']);
        
        // Milestones endpoints  
        Route::get('/milestones', [DashboardController::class, 'milestones']);
        
        // AI logs endpoints
        Route::get('/ai-logs', [DashboardController::class, 'aiLogs']);
        
        // Analytics endpoints
        Route::get('/analytics', [DashboardController::class, 'analytics']);
    });
    
    // Legacy dashboard API routes (maintained for backward compatibility)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/tasks', [DashboardController::class, 'tasks']);
        Route::get('/milestones', [DashboardController::class, 'milestones']);
        Route::get('/ai-logs', [DashboardController::class, 'aiLogs']);
        Route::get('/analytics', [DashboardController::class, 'analytics']);
    });
});
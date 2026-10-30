<?php

use Illuminate\Support\Facades\Route;
use Modules\TaskCenter\Http\Controllers\SystemAlertController;
use Modules\TaskCenter\Http\Controllers\TaskRunController;

/*
 * Route API module TaskCenter — prefix /api/v1/admin/task-center
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/task-center')->name('task-center.')->group(function () {
    // Tiến trình (system_task_runs).
    Route::middleware('permission:task-center.view')->group(function () {
        Route::get('tasks', [TaskRunController::class, 'index'])->name('tasks.index');
        Route::get('tasks/active', [TaskRunController::class, 'active'])->name('tasks.active');
        Route::get('adapters', [TaskRunController::class, 'adapters'])->name('adapters');
    });
    Route::middleware('permission:task-center.manage')->group(function () {
        Route::post('tasks', [TaskRunController::class, 'store'])->name('tasks.store');
        Route::post('tasks/{id}/cancel', [TaskRunController::class, 'cancel'])->name('tasks.cancel');
    });

    // Cảnh báo (system_alerts).
    Route::middleware('permission:task-center.view')->group(function () {
        Route::get('alerts', [SystemAlertController::class, 'index'])->name('alerts.index');
        Route::get('alerts/unread-count', [SystemAlertController::class, 'unreadCount'])->name('alerts.unread-count');
    });
    Route::middleware('permission:task-center.manage')->group(function () {
        Route::post('alerts/{id}/read', [SystemAlertController::class, 'markRead'])->name('alerts.read');
        Route::post('alerts/read-all', [SystemAlertController::class, 'markAllRead'])->name('alerts.read-all');
    });
});

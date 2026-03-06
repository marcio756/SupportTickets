<?php

use App\Enums\RoleEnum;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkSessionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    /**
     * Authentication & Session Management
     */
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    /**
     * Profile Management & Account Deletion
     */
    Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
    Route::put('/me', [ProfileController::class, 'update'])->name('me.update');
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->name('me.password');
    Route::delete('/me', [ProfileController::class, 'destroy'])->name('me.destroy');

    /**
     * Dashboard Statistics
     */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    /**
     * Notifications System
     */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-bulk', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('/notifications/clear', [NotificationController::class, 'destroyAll'])->name('notifications.clear');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    /**
     * FCM Tokens (Push Notifications)
     */
    Route::post('/fcm-token', [FcmTokenController::class, 'store'])->name('fcm-token.store');

    /**
     * Discovery Endpoints for UI Selection
     */
    Route::get('/customers', function () {
        $customers = User::where('role', RoleEnum::CUSTOMER->value)->select('id', 'name', 'email')->get();
        return response()->json(['data' => $customers]);
    })->name('customers.index');

    Route::get('/supporters', function () {
        $supporters = User::where('role', RoleEnum::SUPPORTER->value)->select('id', 'name', 'email')->get();
        return response()->json(['data' => $supporters]);
    })->name('supporters.index');

    /**
     * Work Sessions (Attendance Tracking & Reports)
     */
    Route::prefix('work-sessions')->name('work-sessions.')->group(function () {
        Route::get('/', [WorkSessionController::class, 'index'])->name('index');
        Route::get('/reports', [WorkSessionController::class, 'reports'])->name('reports');
        Route::get('/current', [WorkSessionController::class, 'current'])->name('current');
        Route::post('/start', [WorkSessionController::class, 'start'])->name('start');
        Route::post('/end', [WorkSessionController::class, 'end'])->name('end');
        Route::post('/pause', [WorkSessionController::class, 'pause'])->name('pause');
        Route::post('/resume', [WorkSessionController::class, 'resume'])->name('resume');
        Route::delete('/{workSession}', [WorkSessionController::class, 'destroy'])->name('destroy');
    });

    /**
     * Administrative User Management
     */
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

    /**
     * System Activity Logs
     */
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    /**
     * Tag Taxonomy Management
     */
    Route::apiResource('tags', TagController::class)->except(['create', 'show', 'edit']);

    /**
     * Email Synchronization Service
     */
    Route::post('/emails/fetch', [EmailController::class, 'fetch'])->name('emails.fetch');

    /**
     * Core Ticket Operations & State Management
     */
    Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.updateStatus');
    Route::post('/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/tick', [TicketController::class, 'tickTime'])->name('tickets.tickTime');
    Route::put('/tickets/{ticket}/tags', [TicketController::class, 'syncTags'])->name('tickets.tags.sync');
});
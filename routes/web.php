<?php

use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\WorkSessionController;
use App\Http\Middleware\EnsureActiveWorkSession;
use App\Http\Controllers\WorkSessionReportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    /**
     * User profile management.
     */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /**
     * Work Session & Attendance Endpoints.
     */
    Route::post('/work-sessions/start', [WorkSessionController::class, 'start'])->name('work-sessions.start');
    Route::post('/work-sessions/pause', [WorkSessionController::class, 'pause'])->name('work-sessions.pause');
    Route::post('/work-sessions/resume', [WorkSessionController::class, 'resume'])->name('work-sessions.resume');
    Route::post('/work-sessions/end', [WorkSessionController::class, 'end'])->name('work-sessions.end');
    Route::get('/work-sessions/reports', [WorkSessionReportController::class, 'index'])->name('work-sessions.index');
    Route::delete('/work-sessions/{workSession}', [WorkSessionController::class, 'destroy'])->name('work-sessions.destroy');

    /**
     * Admin: Teams Management
     */
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');

    /**
     * Supporters/Admin: Vacations Map
     */
    Route::get('/vacations', [VacationController::class, 'index'])->name('vacations.index');

    /**
     * Ticket Viewing.
     */
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

    /**
     * Writing operations protected by Active Session.
     */
    Route::middleware([EnsureActiveWorkSession::class])->group(function () {
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
        Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
        Route::post('/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
        Route::post('/tickets/{ticket}/tick-time', [TicketController::class, 'tickTime'])->name('tickets.tick-time');
        Route::put('/tickets/{ticket}/tags', [TicketController::class, 'syncTags'])->name('tickets.tags.sync');
    });

    Route::resource('tags', TagController::class)->except(['create', 'show', 'edit']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-bulk', [NotificationController::class, 'markBulkAsRead'])->name('notifications.read-bulk');
    Route::post('/notifications/clear', [NotificationController::class, 'destroyAll'])->name('notifications.clear');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();
    
    Route::post('/fcm-token', [FcmTokenController::class, 'store'])->name('web.fcm-token.store');
});

require __DIR__.'/auth.php';
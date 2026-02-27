<?php

use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ActivityLogController;
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
     * Controls user profile updates and deletion.
     */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /**
     * Core ticketing system endpoints including status tracking and agent assignments.
     */
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
    
    /**
     * Handles live chat interactions and time tracking within a specific ticket.
     */
    Route::post('/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/tick-time', [TicketController::class, 'tickTime'])->name('tickets.tick-time');

    /**
     * Global tagging system for ticket categorization and filtering.
     */
    Route::put('/tickets/{ticket}/tags', [TicketController::class, 'syncTags'])->name('tickets.tags.sync');
    Route::resource('tags', TagController::class)->except(['create', 'show', 'edit']);

    /**
     * Centralized audit logging for administrative oversight.
     */
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    /**
     * Notification endpoints adjusted to prevent collision with core API routes.
     */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-bulk', [NotificationController::class, 'markBulkAsRead'])->name('notifications.read-bulk');
    Route::post('/notifications/clear', [NotificationController::class, 'destroyAll'])->name('notifications.clear');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    /**
     * User administration panel for role and access management.
     */
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

    /**
     * Persists Firebase Cloud Messaging tokens via standard web session authentication.
     */
    Route::post('/fcm-token', [FcmTokenController::class, 'store'])->name('web.fcm-token.store');
});

require __DIR__.'/auth.php';
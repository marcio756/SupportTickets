<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TicketController;
// Arquiteto: Adicionado o TicketMessageController em falta
use App\Http\Controllers\Api\TicketMessageController; 
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkSessionController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\VacationController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Rate limiting rigoroso para evitar brute-force (ex: 5 tentativas por minuto)
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    /**
     * Autenticação e Recuperação de Password (Acesso Público)
     */
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1')
        ->name('password.email');
        
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.update');

    // Proteção global da API contra abusos (ex: 60 pedidos por minuto por utilizador)
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
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
        Route::get('/customers', [DiscoveryController::class, 'customers'])->name('customers.index');
        Route::get('/supporters', [DiscoveryController::class, 'supporters'])->name('supporters.index');

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
         * Teams Management (Admin)
         */
        Route::apiResource('teams', TeamController::class)->except(['create', 'show', 'edit']);
        Route::get('teams/{team}/members', [TeamController::class, 'members'])->name('teams.members');
        Route::post('teams/{team}/members', [TeamController::class, 'assignMembers'])->name('teams.assign-members');

        /**
         * Vacations Management
         */
        Route::get('/vacations/calendar', [VacationController::class, 'calendar'])->name('vacations.calendar');
        Route::get('/vacations/supporter/{supporter}', [VacationController::class, 'showBySupporter'])->name('vacations.supporter');
        Route::patch('/vacations/{vacation}/status', [VacationController::class, 'updateStatus'])->name('vacations.updateStatus');
        Route::apiResource('vacations', VacationController::class)->except(['create', 'show', 'edit']); // update removido do except para permitir edição

        /**
         * Administrative User Management
         */
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/users/search', [UserController::class, 'search'])->name('users.search'); // Para suportar menções (@)
        Route::patch('/users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');

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
         * Announcements
         */
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

        /**
         * Core Ticket Operations & State Management
         */
        Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.updateStatus');
        Route::post('/tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->name('tickets.messages.store');
        
        // Arquiteto: Rota adicionada com a nomenclatura exata que o Vue (Ziggy) procura.
        // Removi também o prefixo "api." porque o route provider já lhe dá o prefixo name('v1.') e o Laravel o prefixo de ficheiro.
        Route::get('/tickets/{ticket}/messages', [TicketMessageController::class, 'index'])->name('tickets.messages'); 
        
        Route::post('/tickets/{ticket}/tick', [TicketController::class, 'tickTime'])->name('tickets.tickTime');
        Route::put('/tickets/{ticket}/tags', [TicketController::class, 'syncTags'])->name('tickets.tags.sync');
    });
});
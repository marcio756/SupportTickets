<?php

use App\Enums\RoleEnum;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Profile
    Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
    Route::put('/me', [ProfileController::class, 'update'])->name('me.update');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');

    // FCM Tokens Route
    Route::post('/fcm-token', [FcmTokenController::class, 'store'])->name('fcm-token.store');

    Route::get('/customers', function () {
        $customers = User::where('role', RoleEnum::CUSTOMER->value)->select('id', 'name', 'email')->get();
        return response()->json(['data' => $customers]);
    })->name('customers.index');

    // Users (User Management)
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

    // Tickets
    Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show']);
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.updateStatus');
    Route::post('/tickets/{ticket}/messages', [TicketController::class, 'sendMessage'])->name('tickets.messages');
    Route::post('/tickets/{ticket}/tick', [TicketController::class, 'tickTime'])->name('tickets.tickTime');
});
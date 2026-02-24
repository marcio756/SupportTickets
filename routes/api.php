<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Profile
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Tickets
    Route::apiResource('tickets', TicketController::class)->only(['index', 'show']);
    Route::post('/tickets/{ticket}/messages', [TicketController::class, 'sendMessage']);
    Route::post('/tickets/{ticket}/tick', [TicketController::class, 'tickTime']);
});
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Architect Note: Denormalization migration.
 * By storing the active tickets count and online status directly on the user, 
 * we eliminate massive JOINs and COUNT() aggregates during the auto-assignment process.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('active_tickets_count')->default(0);
            $table->boolean('is_online')->default(false);
            
            // Composite index designed specifically for the findAvailableSupporter query
            $table->index(['role', 'is_online', 'active_tickets_count'], 'idx_user_assignment');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_user_assignment');
            $table->dropColumn(['active_tickets_count', 'is_online']);
        });
    }
};
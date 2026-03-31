<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add Database Indexes for high-volume environments.
 * Architect Note: Crucial for the Hyper-Seeder. Without these B-Tree indexes, 
 * querying millions of tickets or messages would result in full table scans 
 * and immediately crash the server memory.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index('customer_id');
            $table->index('assigned_to');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
            $table->index('started_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['assigned_to']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['started_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['team_id']);
        });
    }
};
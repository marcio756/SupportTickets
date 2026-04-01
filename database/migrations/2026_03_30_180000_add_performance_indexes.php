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
     * This method adds all user-defined indexes plus composite optimizations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // User's original indexes
            $table->index(['status', 'created_at'], 'idx_tickets_status_created');
            $table->index('customer_id', 'idx_tickets_customer_id');
            $table->index('assigned_to', 'idx_tickets_assigned_to');
            
            // Optimization for the Controller filters (Composite indexes)
            // This speeds up queries that filter by customer and status simultaneously.
            $table->index(['customer_id', 'status'], 'idx_tickets_cust_status_comp');
            $table->index(['assigned_to', 'status'], 'idx_tickets_assig_status_comp');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            // User's original indexes
            $table->index('ticket_id', 'idx_messages_ticket_id');
            $table->index('user_id', 'idx_messages_user_id');
            $table->index('created_at', 'idx_messages_created_at');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            // User's original indexes
            $table->index('user_id', 'idx_sessions_user_id');
            $table->index('status', 'idx_sessions_status');
            $table->index('started_at', 'idx_sessions_started_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // User's original indexes
            $table->index('role', 'idx_users_role');
            $table->index('team_id', 'idx_users_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('idx_tickets_status_created');
            $table->dropIndex('idx_tickets_customer_id');
            $table->dropIndex('idx_tickets_assigned_to');
            $table->dropIndex('idx_tickets_cust_status_comp');
            $table->dropIndex('idx_tickets_assig_status_comp');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_ticket_id');
            $table->dropIndex('idx_messages_user_id');
            $table->dropIndex('idx_messages_created_at');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sessions_user_id');
            $table->dropIndex('idx_sessions_status');
            $table->dropIndex('idx_sessions_started_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_team_id');
        });
    }
};
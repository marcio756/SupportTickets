<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Architect Note: Critical performance indexes required to handle 
 * millions of records in SQLite/MySQL without triggering Full Table Scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status');
            $table->index('customer_id');
            $table->index('assigned_to');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['assigned_to']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'started_at']);
        });
    }
};
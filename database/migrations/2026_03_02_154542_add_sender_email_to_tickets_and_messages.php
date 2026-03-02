<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('sender_email')->nullable()->after('customer_id');
            // Garantir que a chave forasteira permite valores nulos
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->string('sender_email')->nullable()->after('user_id');
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('sender_email');
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropColumn('sender_email');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
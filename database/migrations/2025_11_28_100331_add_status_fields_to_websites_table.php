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
        Schema::table('websites', function (Blueprint $table) {
            $table->enum('nginx_status', ['pending', 'active', 'failed', 'inactive'])->default('pending')->after('is_active');
            $table->enum('ssl_status', ['none', 'pending', 'active', 'failed'])->default('none')->after('nginx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn(['nginx_status', 'ssl_status']);
        });
    }
};

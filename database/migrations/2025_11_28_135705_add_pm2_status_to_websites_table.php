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
            $table->enum('pm2_status', ['stopped', 'running', 'error', 'unknown'])
                  ->default('stopped')
                  ->after('nginx_status')
                  ->comment('PM2 process status for Node.js projects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('pm2_status');
        });
    }
};

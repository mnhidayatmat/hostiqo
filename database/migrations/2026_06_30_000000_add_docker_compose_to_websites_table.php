<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores the raw docker-compose.yml for the 'custom' template, so users can
     * deploy any app by pasting a compose file instead of needing a hardcoded
     * template generator in DockerService.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->longText('docker_compose')->nullable()->after('docker_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('docker_compose');
        });
    }
};

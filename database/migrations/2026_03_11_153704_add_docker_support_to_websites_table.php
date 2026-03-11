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
            // Change project_type enum to include 'docker'
            $table->enum('project_type', ['php', 'node', 'docker'])->default('php')->change();

            // Docker-specific fields
            $table->string('docker_compose_path')->nullable()->after('working_directory');
            $table->string('docker_image')->nullable()->after('docker_compose_path');
            $table->string('docker_tag')->nullable()->after('docker_image');
            $table->string('docker_status')->nullable()->after('pm2_status');
            $table->json('docker_env')->nullable()->after('docker_status');
            $table->json('docker_ports')->nullable()->after('docker_env');
            $table->json('docker_volumes')->nullable()->after('docker_ports');
            $table->string('docker_template')->nullable()->after('docker_volumes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            // Revert project_type enum
            $table->enum('project_type', ['php', 'node'])->default('php')->change();

            // Remove Docker-specific fields
            $table->dropColumn([
                'docker_compose_path',
                'docker_image',
                'docker_tag',
                'docker_status',
                'docker_env',
                'docker_ports',
                'docker_volumes',
                'docker_template',
            ]);
        });
    }
};

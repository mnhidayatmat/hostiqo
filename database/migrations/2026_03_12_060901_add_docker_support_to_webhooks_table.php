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
        Schema::table('webhooks', function (Blueprint $table) {
            // Add project_type field (php, node, docker)
            $table->string('project_type', 10)->default('php')->after('is_active');

            // Docker-specific fields
            $table->string('docker_compose_path')->nullable()->after('post_deploy_script');
            $table->enum('docker_action', ['build', 'pull', 'restart'])->default('restart')->after('docker_compose_path');
            $table->string('docker_image_name')->nullable()->after('docker_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn([
                'project_type',
                'docker_compose_path',
                'docker_action',
                'docker_image_name',
            ]);
        });
    }
};

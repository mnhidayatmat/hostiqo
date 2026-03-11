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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('git_provider')->default('github'); // github, gitlab
            $table->text('repository_url');
            $table->string('branch')->default('main');
            $table->text('local_path');
            $table->string('secret_token');
            $table->boolean('is_active')->default(true);
            $table->text('pre_deploy_script')->nullable();
            $table->text('post_deploy_script')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};

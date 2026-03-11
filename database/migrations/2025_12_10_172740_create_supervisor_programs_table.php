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
        Schema::create('supervisor_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Program name (e.g., laravel-queue, laravel-scheduler)
            $table->text('description')->nullable();
            $table->string('command'); // Command to run (e.g., php artisan queue:work)
            $table->string('directory'); // Working directory
            $table->integer('numprocs')->default(1); // Number of processes
            $table->string('user')->default('www-data'); // User to run as
            $table->boolean('autostart')->default(true);
            $table->boolean('autorestart')->default(true);
            $table->integer('startsecs')->default(1);
            $table->integer('stopwaitsecs')->default(10);
            $table->string('stdout_logfile')->nullable(); // Log file path
            $table->integer('stdout_logfile_maxbytes')->default(50 * 1024 * 1024); // 50MB
            $table->integer('stdout_logfile_backups')->default(10);
            $table->string('redirect_stderr')->default('true');
            $table->integer('stopasgroup')->default(1);
            $table->integer('killasgroup')->default(1);
            $table->text('environment')->nullable(); // JSON environment variables
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_programs');
    }
};

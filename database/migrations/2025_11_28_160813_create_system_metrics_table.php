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
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->decimal('cpu_usage', 5, 2); // 0-100%
            $table->decimal('memory_usage', 5, 2); // 0-100%
            $table->decimal('disk_usage', 5, 2); // 0-100%
            $table->bigInteger('memory_total')->nullable(); // bytes
            $table->bigInteger('memory_used')->nullable(); // bytes
            $table->bigInteger('disk_total')->nullable(); // bytes
            $table->bigInteger('disk_used')->nullable(); // bytes
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};

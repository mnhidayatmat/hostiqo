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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('metric', ['cpu', 'memory', 'disk', 'service']); // what to monitor
            $table->string('condition'); // >, <, ==, !=
            $table->decimal('threshold', 8, 2)->nullable(); // threshold value
            $table->string('service_name')->nullable(); // for service monitoring
            $table->integer('duration')->default(5); // minutes before alerting
            $table->enum('channel', ['email', 'slack', 'both'])->default('email');
            $table->string('email')->nullable();
            $table->string('slack_webhook')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};

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
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('action', ['allow', 'deny'])->default('allow');
            $table->string('direction')->default('in'); // in, out, both
            $table->string('port')->nullable(); // 80, 443, 22, etc
            $table->string('protocol')->nullable(); // tcp, udp, any
            $table->string('from_ip')->nullable(); // source IP
            $table->string('to_ip')->nullable(); // dest IP
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System rules can't be deleted
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};

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
        Schema::table('system_metrics', function (Blueprint $table) {
            $table->bigInteger('disk_read_bytes')->nullable()->after('disk_used');
            $table->bigInteger('disk_write_bytes')->nullable()->after('disk_read_bytes');
            $table->bigInteger('network_rx_bytes')->nullable()->after('disk_write_bytes');
            $table->bigInteger('network_tx_bytes')->nullable()->after('network_rx_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_metrics', function (Blueprint $table) {
            $table->dropColumn(['disk_read_bytes', 'disk_write_bytes', 'network_rx_bytes', 'network_tx_bytes']);
        });
    }
};

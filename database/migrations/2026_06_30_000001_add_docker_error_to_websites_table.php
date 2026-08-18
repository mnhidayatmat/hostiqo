<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores the last Docker bring-up error (failed pull, failed `up`, unhealthy
     * service, …) so failures are visible in the panel instead of being silently
     * swallowed. Cleared on a successful deploy.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->text('docker_error')->nullable()->after('docker_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('docker_error');
        });
    }
};

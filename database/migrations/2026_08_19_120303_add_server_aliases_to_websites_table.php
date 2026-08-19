<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra server names for a site, comma-separated (e.g. "*.example.com").
     *
     * Multi-tenant apps serve each tenant on its own subdomain and need a
     * wildcard alongside the apex. The generator previously hard-coded
     * "{domain} www.{domain}", so regenerating such a vhost dropped every
     * tenant subdomain from nginx.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('server_aliases')->nullable()->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('server_aliases');
        });
    }
};

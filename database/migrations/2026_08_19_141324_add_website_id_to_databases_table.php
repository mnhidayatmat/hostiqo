<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a database to the website that uses it.
     *
     * Databases and websites were tracked independently, so the panel could not
     * answer "which site does this database belong to" -- the association only
     * existed in each app's .env. Nullable: shared databases, or ones whose site
     * is not managed here, stay unlinked. nullOnDelete so removing a website
     * never deletes the database record.
     */
    public function up(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            $table->foreignId('website_id')->nullable()->after('id')
                ->constrained('websites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_id');
        });
    }
};

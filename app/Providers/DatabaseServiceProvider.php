<?php

namespace App\Providers;

use App\Services\DatabaseService;
use App\Services\MySqlDatabaseService;
use App\Services\PostgreSqlDatabaseService;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DatabaseService::class);
        $this->app->singleton(MySqlDatabaseService::class);
        $this->app->singleton(PostgreSqlDatabaseService::class);

        // Register an alias for easy access
        $this->app->alias(DatabaseService::class, 'db.service');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

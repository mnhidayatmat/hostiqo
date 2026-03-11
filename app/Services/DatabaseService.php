<?php

namespace App\Services;

use App\Services\Contracts\DatabaseServiceInterface;
use App\Services\MySqlDatabaseService;
use App\Services\PostgreSqlDatabaseService;
use Illuminate\Support\Facades\Cache;

class DatabaseService
{
    /**
     * Get the appropriate database service based on type.
     *
     * @param string $type The database type ('mysql' or 'postgresql')
     * @return DatabaseServiceInterface
     * @throws \InvalidArgumentException If type is invalid
     */
    public function connection(string $type = 'mysql'): DatabaseServiceInterface
    {
        return match ($type) {
            'mysql' => app(MySqlDatabaseService::class),
            'postgresql' => app(PostgreSqlDatabaseService::class),
            default => throw new \InvalidArgumentException("Unsupported database type: {$type}"),
        };
    }

    /**
     * Get the MySQL database service.
     *
     * @return MySqlDatabaseService
     */
    public function mysql(): MySqlDatabaseService
    {
        return app(MySqlDatabaseService::class);
    }

    /**
     * Get the PostgreSQL database service.
     *
     * @return PostgreSqlDatabaseService
     */
    public function postgresql(): PostgreSqlDatabaseService
    {
        return app(PostgreSqlDatabaseService::class);
    }

    /**
     * Clear all permission caches.
     *
     * @return void
     */
    public function clearAllPermissionCaches(): void
    {
        try {
            app(MySqlDatabaseService::class)->clearPermissionCache();
        } catch (\Exception $e) {
            // Ignore MySQL errors
        }

        try {
            app(PostgreSqlDatabaseService::class)->clearPermissionCache();
        } catch (\Exception $e) {
            // Ignore PostgreSQL errors
        }
    }
}

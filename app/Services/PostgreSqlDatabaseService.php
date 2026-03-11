<?php

namespace App\Services;

use App\Services\Contracts\DatabaseServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;

class PostgreSqlDatabaseService implements DatabaseServiceInterface
{
    /**
     * Get the PostgreSQL superuser connection name.
     *
     * @return string
     */
    protected function getConnectionName(): string
    {
        return config('database.connections.pgsql_superuser') ? 'pgsql_superuser' : 'pgsql';
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'postgresql';
    }

    /**
     * {@inheritdoc}
     */
    public function canCreateDatabase(): array
    {
        try {
            $connection = $this->getConnectionName();

            // Check if PostgreSQL extension is available
            if (!extension_loaded('pdo_pgsql')) {
                return [
                    'can_create' => false,
                    'has_createdb' => false,
                    'has_create_role' => false,
                    'current_user' => null,
                    'missing_privileges' => ['PostgreSQL PHP extension (pdo_pgsql) is not installed'],
                    'message' => 'PostgreSQL PHP extension (pdo_pgsql) is not installed. Please install it to enable PostgreSQL support.',
                    'not_available' => true,
                ];
            }

            $currentUser = DB::connection($connection)->selectOne("SELECT current_user as user")->user;

            // Create cache key based on current user
            $cacheKey = 'pgsql_permissions_' . md5($currentUser);

            // Check cache first (valid for 10 minutes)
            return Cache::remember($cacheKey, 600, function () use ($currentUser, $connection) {
                return $this->testDatabasePermissions($currentUser, $connection);
            });
        } catch (Exception $e) {
            $message = $e->getMessage();

            // Check for connection refused or connection error
            if (stripos($message, 'connection refused') !== false ||
                stripos($message, 'SQLSTATE[08006]') !== false ||
                stripos($message, 'could not connect') !== false) {
                return [
                    'can_create' => false,
                    'has_createdb' => false,
                    'has_create_role' => false,
                    'current_user' => null,
                    'missing_privileges' => ['PostgreSQL is not installed or not running'],
                    'message' => 'PostgreSQL is not installed or not running on this server. Install PostgreSQL to enable PostgreSQL database support.',
                    'not_available' => true,
                ];
            }

            return [
                'can_create' => false,
                'has_createdb' => false,
                'has_create_role' => false,
                'current_user' => null,
                'missing_privileges' => ['Error: ' . $message],
                'message' => 'Failed to check permissions: ' . $message,
            ];
        }
    }

    /**
     * Test database permissions for PostgreSQL.
     *
     * @param string $currentUser The current PostgreSQL user
     * @param string $connection The connection name
     * @return array Permission test results
     */
    protected function testDatabasePermissions(string $currentUser, string $connection): array
    {
        try {
            $hasCreateDb = false;
            $hasCreateRole = false;

            // Check if user has CREATEDB privilege
            $result = DB::connection($connection)->selectOne("
                SELECT rolcreatedb as can_create
                FROM pg_roles
                WHERE rolname = current_user
            ");

            if ($result && $result->can_create) {
                $hasCreateDb = true;
            }

            // Check if user has CREATEROLE privilege
            $result = DB::connection($connection)->selectOne("
                SELECT rolcreaterole as can_create_role
                FROM pg_roles
                WHERE rolname = current_user
            ");

            if ($result && $result->can_create_role) {
                $hasCreateRole = true;
            }

            // Test by actually creating a test database
            $testDbName = '_test_permission_check_' . time();

            try {
                DB::connection($connection)->statement("CREATE DATABASE \"{$testDbName}\"");
                $hasCreateDb = true;
                DB::connection($connection)->statement("DROP DATABASE \"{$testDbName}\"");
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'permission') !== false) {
                    $hasCreateDb = false;
                }
            }

            // Test role creation
            $testRole = '_test_role_' . time();
            try {
                DB::connection($connection)->statement("CREATE ROLE \"{$testRole}\"");
                $hasCreateRole = true;
                DB::connection($connection)->statement("DROP ROLE \"{$testRole}\"");
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'permission') !== false) {
                    $hasCreateRole = false;
                }
            }

            $missingPrivileges = [];
            if (!$hasCreateDb) $missingPrivileges[] = 'CREATEDB';
            if (!$hasCreateRole) $missingPrivileges[] = 'CREATEROLE';

            return [
                'can_create' => $hasCreateDb && $hasCreateRole,
                'has_createdb' => $hasCreateDb,
                'has_create_role' => $hasCreateRole,
                'current_user' => $currentUser,
                'missing_privileges' => $missingPrivileges,
                'message' => $hasCreateDb && $hasCreateRole
                    ? 'You have permission to create databases and roles.'
                    : 'Missing privileges: ' . implode(', ', $missingPrivileges),
                'cached' => false,
            ];
        } catch (Exception $e) {
            return [
                'can_create' => false,
                'has_createdb' => false,
                'has_create_role' => false,
                'current_user' => $currentUser,
                'missing_privileges' => ['Error: ' . $e->getMessage()],
                'message' => 'Failed to test permissions: ' . $e->getMessage(),
                'cached' => false,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearPermissionCache(): void
    {
        try {
            $connection = $this->getConnectionName();
            $currentUser = DB::connection($connection)->selectOne("SELECT current_user as user")->user;
            $cacheKey = 'pgsql_permissions_' . md5($currentUser);
            Cache::forget($cacheKey);
        } catch (Exception $e) {
            // Ignore errors
        }
    }

    /**
     * {@inheritdoc}
     */
    public function listDatabases(): array
    {
        $connection = $this->getConnectionName();
        $databases = DB::connection($connection)->select("
            SELECT datname as name
            FROM pg_database
            WHERE datistemplate = false
            AND datname != 'postgres'
        ");

        $result = [];
        foreach ($databases as $db) {
            $result[] = $db->name;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase(string $dbName, string $username, string $password, string $host = 'localhost'): void
    {
        try {
            $connection = $this->getConnectionName();

            // Create user/role with password (must be done first in some PostgreSQL versions)
            DB::connection($connection)->statement("CREATE ROLE \"{$username}\" WITH LOGIN PASSWORD '{$password}'");

            // Create database with UTF8 encoding and set owner
            DB::connection($connection)->statement("CREATE DATABASE \"{$dbName}\" ENCODING 'UTF8' OWNER \"{$username}\"");

            // Grant all privileges on the database to the user
            DB::connection($connection)->statement("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$username}\"");

            // Grant schema privileges on public schema
            // Note: We connect to the new database to grant schema privileges
            DB::connection($connection)->statement("SET search_path TO \"{$dbName}\"");
            DB::connection($connection)->statement("GRANT ALL ON SCHEMA public TO \"{$username}\"");
        } catch (Exception $e) {
            throw new Exception("Failed to create PostgreSQL database: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function changeUserPassword(string $username, string $newPassword, string $host = 'localhost'): void
    {
        try {
            $connection = $this->getConnectionName();
            DB::connection($connection)->statement("ALTER ROLE \"{$username}\" WITH PASSWORD '{$newPassword}'");
        } catch (Exception $e) {
            throw new Exception("Failed to change PostgreSQL password: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDatabase(string $dbName): void
    {
        try {
            $connection = $this->getConnectionName();

            // First, disconnect all connections to the database
            DB::connection($connection)->statement("
                SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = '{$dbName}'
                AND pid <> pg_backend_pid()
            ");

            DB::connection($connection)->statement("DROP DATABASE IF EXISTS \"{$dbName}\"");
        } catch (Exception $e) {
            throw new Exception("Failed to delete PostgreSQL database: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteUser(string $username, string $host = 'localhost'): void
    {
        try {
            $connection = $this->getConnectionName();

            // Revoke privileges on all databases first
            $databases = $this->listDatabases();
            foreach ($databases as $dbName) {
                try {
                    DB::connection($connection)->statement("REVOKE ALL PRIVILEGES ON DATABASE \"{$dbName}\" FROM \"{$username}\"");
                } catch (Exception $e) {
                    // Ignore if database doesn't exist or user doesn't have privileges
                }
            }

            // Revoke schema public privileges (in postgres database)
            try {
                DB::connection($connection)->statement("REVOKE ALL ON SCHEMA public FROM \"{$username}\"");
            } catch (Exception $e) {
                // Ignore if no privileges
            }

            // Drop the role with IF EXISTS to avoid errors
            DB::connection($connection)->statement("DROP ROLE IF EXISTS \"{$username}\"");
        } catch (Exception $e) {
            throw new Exception("Failed to delete PostgreSQL role: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function databaseExists(string $dbName): bool
    {
        try {
            $connection = $this->getConnectionName();
            $result = DB::connection($connection)->selectOne("
                SELECT 1 FROM pg_database WHERE datname = ?
            ", [$dbName]);

            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function userExists(string $username, string $host = 'localhost'): bool
    {
        try {
            $connection = $this->getConnectionName();
            $result = DB::connection($connection)->selectOne("
                SELECT 1 FROM pg_roles WHERE rolname = ?
            ", [$username]);

            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseSize(string $dbName): float
    {
        try {
            $connection = $this->getConnectionName();
            $result = DB::connection($connection)->selectOne("
                SELECT ROUND(pg_database_size(?) / 1024 / 1024, 2) as size_mb
            ", [$dbName]);

            return $result->size_mb ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTableCount(string $dbName): int
    {
        try {
            $connection = $this->getConnectionName();
            // Get table count by querying information_schema which is accessible from any database
            $result = DB::connection($connection)->selectOne("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = 'public'
                AND table_type = 'BASE TABLE'
                AND table_catalog = ?
            ", [$dbName]);

            return $result->count ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

<?php

namespace App\Services\Contracts;

interface DatabaseServiceInterface
{
    /**
     * Check if current user has permission to create databases and users.
     *
     * @return array{can_create: bool, message: string, current_user: string|null, missing_privileges: array}
     */
    public function canCreateDatabase(): array;

    /**
     * Clear cached permission check to force retest.
     *
     * @return void
     */
    public function clearPermissionCache(): void;

    /**
     * Get list of all databases (excluding system databases).
     *
     * @return array<int, string> List of database names
     */
    public function listDatabases(): array;

    /**
     * Create a new database and user with privileges.
     *
     * @param string $dbName The database name
     * @param string $username The username to create
     * @param string $password The user password
     * @param string $host The host for the user
     * @return void
     * @throws \Exception If creation fails
     */
    public function createDatabase(string $dbName, string $username, string $password, string $host = 'localhost'): void;

    /**
     * Change password for a database user.
     *
     * @param string $username The username
     * @param string $newPassword The new password
     * @param string $host The host for the user
     * @return void
     * @throws \Exception If password change fails
     */
    public function changeUserPassword(string $username, string $newPassword, string $host = 'localhost'): void;

    /**
     * Delete a database.
     *
     * @param string $dbName The database name to delete
     * @return void
     * @throws \Exception If deletion fails
     */
    public function deleteDatabase(string $dbName): void;

    /**
     * Delete a database user.
     *
     * @param string $username The username to delete
     * @param string $host The host for the user
     * @return void
     * @throws \Exception If deletion fails
     */
    public function deleteUser(string $username, string $host = 'localhost'): void;

    /**
     * Check if a database exists.
     *
     * @param string $dbName The database name
     * @return bool True if database exists
     */
    public function databaseExists(string $dbName): bool;

    /**
     * Check if a user exists.
     *
     * @param string $username The username
     * @param string $host The host for the user
     * @return bool True if user exists
     */
    public function userExists(string $username, string $host = 'localhost'): bool;

    /**
     * Get database size in MB.
     *
     * @param string $dbName The database name
     * @return float Size in megabytes
     */
    public function getDatabaseSize(string $dbName): float;

    /**
     * Get table count for a database.
     *
     * @param string $dbName The database name
     * @return int Number of tables
     */
    public function getTableCount(string $dbName): int;

    /**
     * Get the database type identifier.
     *
     * @return string
     */
    public function getType(): string;
}

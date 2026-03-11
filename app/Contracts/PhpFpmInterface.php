<?php

namespace App\Contracts;

use App\Models\Website;

interface PhpFpmInterface
{
    /**
     * Get OS family.
     *
     * @return string The OS family (e.g., 'debian', 'rhel')
     */
    public function getOsFamily(): string;

    /**
     * Get pool directory path for a specific PHP version.
     *
     * @param string $phpVersion The PHP version
     * @return string The pool directory path
     */
    public function getPoolDirectoryPath(string $phpVersion): string;

    /**
     * Get socket path for a PHP-FPM pool.
     *
     * @param string $phpVersion The PHP version
     * @param string $poolName The pool name
     * @return string The socket path
     */
    public function getSocketPath(string $phpVersion, string $poolName): string;

    /**
     * Get log directory path.
     *
     * @param string $phpVersion The PHP version
     * @return string The log directory path
     */
    public function getLogPath(string $phpVersion): string;

    /**
     * Generate PHP-FPM pool configuration for a website.
     *
     * @param Website $website The website model
     * @return string The generated pool configuration
     */
    public function generatePoolConfig(Website $website): string;

    /**
     * Write PHP-FPM pool configuration.
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function writePoolConfig(Website $website): array;

    /**
     * Delete PHP-FPM pool configuration.
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deletePoolConfig(Website $website): array;

    /**
     * Test PHP-FPM configuration.
     *
     * @param string $phpVersion The PHP version
     * @param string|null $poolConfigPath Optional pool config path
     * @return array{success: bool, output?: string, error?: string}
     */
    public function testConfig(string $phpVersion, ?string $poolConfigPath = null): array;

    /**
     * Restart PHP-FPM service.
     *
     * @param string $phpVersion The PHP version
     * @return array{success: bool, message?: string, error?: string}
     */
    public function restart(string $phpVersion): array;

    /**
     * Reload PHP-FPM service.
     *
     * @param string $phpVersion The PHP version
     * @return array{success: bool, message?: string, error?: string}
     */
    public function reload(string $phpVersion): array;

    /**
     * Get web server user.
     *
     * @return string The web server user
     */
    public function getWebServerUser(): string;

    /**
     * Get web server group.
     *
     * @return string The web server group
     */
    public function getWebServerGroup(): string;
}

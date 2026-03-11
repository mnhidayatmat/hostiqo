<?php

namespace App\Contracts;

use App\Models\Website;

interface NginxInterface
{
    /**
     * Get OS family.
     *
     * @return string The OS family (e.g., 'debian', 'rhel')
     */
    public function getOsFamily(): string;

    /**
     * Generate Nginx configuration for a website.
     *
     * @param Website $website The website model
     * @return string The generated configuration
     */
    public function generateConfig(Website $website): string;

    /**
     * Write Nginx configuration file.
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function writeConfig(Website $website): array;

    /**
     * Delete Nginx configuration file.
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deleteConfig(Website $website): array;

    /**
     * Enable a site (create symlink).
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function enableSite(Website $website): array;

    /**
     * Disable a site (remove symlink).
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function disableSite(Website $website): array;

    /**
     * Test Nginx configuration.
     *
     * @return array{success: bool, output?: string, error?: string}
     */
    public function testConfig(): array;

    /**
     * Reload Nginx.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function reload(): array;

    /**
     * Deploy website configuration (write, enable, test, reload).
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deploy(Website $website): array;

    /**
     * Get PHP-FPM socket path.
     *
     * @param string $phpVersion The PHP version
     * @param string $poolName The pool name
     * @param string|null $customPool Optional custom pool name
     * @return string The socket path
     */
    public function getPhpFpmSocketPath(string $phpVersion, string $poolName, ?string $customPool = null): string;
}

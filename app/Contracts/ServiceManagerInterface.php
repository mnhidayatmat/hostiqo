<?php

namespace App\Contracts;

interface ServiceManagerInterface
{
    /**
     * Get OS family.
     *
     * @return string The OS family (e.g., 'debian', 'rhel')
     */
    public function getOsFamily(): string;

    /**
     * Get list of supported services.
     *
     * @return array<string, array> List of supported services
     */
    public function getSupportedServices(): array;

    /**
     * Get available services (installed on system).
     *
     * @return array<string, array> List of available services with status
     */
    public function getAvailableServices(): array;

    /**
     * Get status of a specific service.
     *
     * @param string $serviceKey The service key
     * @return array{running: bool, enabled: bool, status: string, error?: string}
     */
    public function getServiceStatus(string $serviceKey): array;

    /**
     * Start a service.
     *
     * @param string $serviceKey The service key
     * @return array{success: bool, message: string, error?: string}
     */
    public function startService(string $serviceKey): array;

    /**
     * Stop a service.
     *
     * @param string $serviceKey The service key
     * @return array{success: bool, message: string, error?: string}
     */
    public function stopService(string $serviceKey): array;

    /**
     * Restart a service.
     *
     * @param string $serviceKey The service key
     * @return array{success: bool, message: string, error?: string}
     */
    public function restartService(string $serviceKey): array;

    /**
     * Reload a service.
     *
     * @param string $serviceKey The service key
     * @return array{success: bool, message: string, error?: string}
     */
    public function reloadService(string $serviceKey): array;

    /**
     * Get service logs.
     *
     * @param string $serviceKey The service key
     * @param int $lines Number of log lines to retrieve
     * @return string The log content
     */
    public function getServiceLogs(string $serviceKey, int $lines = 100): string;
}

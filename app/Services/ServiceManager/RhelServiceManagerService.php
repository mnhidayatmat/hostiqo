<?php

namespace App\Services\ServiceManager;

class RhelServiceManagerService extends AbstractServiceManagerService
{
    /**
     * Create a new RhelServiceManagerService instance.
     */
    public function __construct()
    {
        $this->supportedServices = $this->buildServiceList();
    }

    /**
     * {@inheritdoc}
     */
    public function getOsFamily(): string
    {
        return 'rhel';
    }

    /**
     * Build the list of supported services for RHEL/Rocky/Alma.
     *
     * @return array<string, array> List of supported services
     */
    protected function buildServiceList(): array
    {
        return [
            // Web Server
            'nginx' => [
                'name' => 'Nginx',
                'service' => 'nginx',
                'supports_reload' => true,
                'icon' => 'hexagon'
            ],
            
            // PHP-FPM versions (Remi)
            'php84-fpm' => [
                'name' => 'PHP 8.4 FPM',
                'service' => 'php84-php-fpm',
                'supports_reload' => true,
                'icon' => 'code'
            ],
            'php83-fpm' => [
                'name' => 'PHP 8.3 FPM',
                'service' => 'php83-php-fpm',
                'supports_reload' => true,
                'icon' => 'code'
            ],
            'php82-fpm' => [
                'name' => 'PHP 8.2 FPM',
                'service' => 'php82-php-fpm',
                'supports_reload' => true,
                'icon' => 'code'
            ],
            'php81-fpm' => [
                'name' => 'PHP 8.1 FPM',
                'service' => 'php81-php-fpm',
                'supports_reload' => true,
                'icon' => 'code'
            ],
            
            // Database
            'mariadb' => [
                'name' => 'MariaDB',
                'service' => 'mariadb',
                'supports_reload' => true,
                'icon' => 'database'
            ],
            'mysql' => [
                'name' => 'MySQL',
                'service' => 'mysqld',
                'supports_reload' => true,
                'icon' => 'database'
            ],
            
            // Cache
            'redis' => [
                'name' => 'Redis',
                'service' => 'redis',
                'supports_reload' => false,
                'icon' => 'layers'
            ],
            
            // Process Manager
            'supervisor' => [
                'name' => 'Supervisor',
                'service' => 'supervisord',
                'supports_reload' => true,
                'icon' => 'activity'
            ],
            
            // Firewall
            'firewalld' => [
                'name' => 'Firewalld',
                'service' => 'firewalld',
                'supports_reload' => true,
                'icon' => 'shield'
            ],
            
            // SSH
            'ssh' => [
                'name' => 'SSH Server',
                'service' => 'sshd',
                'supports_reload' => true,
                'icon' => 'terminal'
            ],
            
            // Cron
            'cron' => [
                'name' => 'Cron',
                'service' => 'crond',
                'supports_reload' => true,
                'icon' => 'clock'
            ],
        ];
    }
}

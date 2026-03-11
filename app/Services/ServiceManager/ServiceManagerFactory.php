<?php

namespace App\Services\ServiceManager;

use App\Contracts\ServiceManagerInterface;

class ServiceManagerFactory
{
    /**
     * Create service manager based on OS.
     *
     * @return ServiceManagerInterface The appropriate service manager
     */
    public static function create(): ServiceManagerInterface
    {
        $osFamily = self::detectOsFamily();
        
        if ($osFamily === 'rhel') {
            return new RhelServiceManagerService();
        }
        
        return new DebianServiceManagerService();
    }

    /**
     * Detect OS family.
     *
     * @return string The OS family (rhel or debian)
     */
    protected static function detectOsFamily(): string
    {
        if (file_exists('/etc/redhat-release')) {
            return 'rhel';
        }
        
        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            if (preg_match('/ID_LIKE=.*rhel|ID_LIKE=.*fedora|ID=.*rocky|ID=.*alma|ID=.*centos/i', $content)) {
                return 'rhel';
            }
        }
        
        return 'debian';
    }
}

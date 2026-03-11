<?php

namespace App\Services\Firewall;

use App\Contracts\FirewallInterface;

class FirewallFactory
{
    /**
     * Create firewall service based on OS.
     *
     * @return FirewallInterface The appropriate firewall service
     */
    public static function create(): FirewallInterface
    {
        $osFamily = self::detectOsFamily();
        
        if ($osFamily === 'rhel') {
            return new FirewalldService();
        }
        
        return new UfwService();
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

<?php

namespace App\Services\PhpFpm;

use App\Contracts\PhpFpmInterface;

class PhpFpmFactory
{
    /**
     * Create PHP-FPM service based on OS.
     *
     * @return PhpFpmInterface The appropriate PHP-FPM service
     */
    public static function create(): PhpFpmInterface
    {
        $osFamily = self::detectOsFamily();
        $isLocal = in_array(config('app.env'), ['local', 'dev', 'development']);
        
        if ($isLocal) {
            return new LocalPhpFpmService();
        }
        
        if ($osFamily === 'rhel') {
            return new RhelPhpFpmService();
        }
        
        return new DebianPhpFpmService();
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

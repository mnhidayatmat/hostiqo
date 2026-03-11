<?php

namespace App\Services\Nginx;

class RhelNginxService extends AbstractNginxService
{
    /**
     * Create a new RhelNginxService instance.
     */
    public function __construct()
    {
        $this->sitesAvailable = '/etc/nginx/conf.d';
        $this->sitesEnabled = '/etc/nginx/conf.d'; // RHEL doesn't use symlinks
        $this->configTestCmd = 'sudo /usr/sbin/nginx -t';
        $this->reloadCmd = 'sudo /bin/systemctl reload nginx';
    }

    /**
     * {@inheritdoc}
     */
    public function getOsFamily(): string
    {
        return 'rhel';
    }

    /**
     * {@inheritdoc}
     */
    public function getPhpFpmSocketPath(string $phpVersion, string $poolName, ?string $customPool = null): string
    {
        $phpVer = str_replace('.', '', $phpVersion); // 8.4 -> 84
        
        if ($customPool) {
            return "/var/opt/remi/php{$phpVer}/run/php-fpm/{$poolName}.sock";
        }
        return "/var/opt/remi/php{$phpVer}/run/php-fpm/www.sock";
    }

    /**
     * {@inheritdoc}
     */
    protected function getFastcgiConfig(): string
    {
        // RHEL doesn't have snippets/fastcgi-php.conf, use inline config
        return <<<'FASTCGI'
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
FASTCGI;
    }
}

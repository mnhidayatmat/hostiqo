<?php

namespace App\Services\PhpFpm;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RhelPhpFpmService extends AbstractPhpFpmService
{
    /**
     * Create a new RhelPhpFpmService instance.
     */
    public function __construct()
    {
        $this->webServerUser = 'nginx';
        $this->webServerGroup = 'nginx';
    }

    /**
     * {@inheritdoc}
     */
    public function getOsFamily(): string
    {
        return 'rhel';
    }

    /**
     * Convert PHP version to RHEL format (8.4 -> 84).
     *
     * @param string $version The PHP version
     * @return string The RHEL format version
     */
    protected function phpVersionToRhel(string $version): string
    {
        return str_replace('.', '', $version);
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolDirectoryPath(string $phpVersion): string
    {
        $phpVer = $this->phpVersionToRhel($phpVersion);
        return "/etc/opt/remi/php{$phpVer}/php-fpm.d";
    }

    /**
     * {@inheritdoc}
     */
    public function getSocketPath(string $phpVersion, string $poolName): string
    {
        $phpVer = $this->phpVersionToRhel($phpVersion);
        return "/var/opt/remi/php{$phpVer}/run/php-fpm/{$poolName}.sock";
    }

    /**
     * {@inheritdoc}
     */
    public function getLogPath(string $phpVersion): string
    {
        $phpVer = $this->phpVersionToRhel($phpVersion);
        return "/var/opt/remi/php{$phpVer}/log/php-fpm";
    }

    /**
     * {@inheritdoc}
     */
    public function getWebServerUser(): string
    {
        return $this->webServerUser;
    }

    /**
     * {@inheritdoc}
     */
    public function getWebServerGroup(): string
    {
        return $this->webServerGroup;
    }

    /**
     * Write PHP-FPM pool configuration for a website.
     *
     * @param Website $website The website model
     * @return array{success: bool, filepath?: string, pool_name?: string, socket_path?: string, message?: string, error?: string}
     */
    public function writePoolConfig(Website $website): array
    {
        try {
            if ($website->project_type !== 'php') {
                return [
                    'success' => true,
                    'message' => 'Not a PHP project, skipping PHP-FPM pool configuration'
                ];
            }

            if (empty($website->php_version)) {
                throw new \InvalidArgumentException('PHP version is required for PHP projects');
            }

            $poolName = $website->php_pool_name ?? $this->generatePoolName($website);
            $config = $this->generatePoolConfig($website);
            
            $poolDir = $this->getPoolDirectoryPath($website->php_version);
            $filepath = "{$poolDir}/{$poolName}.conf";
            $logDir = $this->getLogPath($website->php_version);
            $socketPath = $this->getSocketPath($website->php_version, $poolName);

            // Create log directory
            Process::run("sudo /bin/mkdir -p {$logDir}");
            Process::run("sudo /bin/chown {$this->webServerUser}:{$this->webServerGroup} {$logDir}");

            // Write config
            $tempFile = tempnam(sys_get_temp_dir(), 'phpfpm_');
            File::put($tempFile, $config);
            
            $result = Process::run("sudo /bin/cp {$tempFile} {$filepath}");
            @unlink($tempFile);
            
            if ($result->failed()) {
                throw new \Exception("Failed to write pool config: " . $result->errorOutput());
            }

            Process::run("sudo /bin/chmod 644 {$filepath}");

            // Update pool name in database
            if (!$website->php_pool_name) {
                $website->update(['php_pool_name' => $poolName]);
            }

            return [
                'success' => true,
                'filepath' => $filepath,
                'pool_name' => $poolName,
                'socket_path' => $socketPath,
                'message' => 'PHP-FPM pool configuration created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to write PHP-FPM pool config', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete PHP-FPM pool configuration for a website.
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deletePoolConfig(Website $website): array
    {
        try {
            if ($website->project_type !== 'php' || !$website->php_pool_name) {
                return [
                    'success' => true,
                    'message' => 'No PHP-FPM pool to delete'
                ];
            }

            if (empty($website->php_version)) {
                throw new \InvalidArgumentException('PHP version is required for PHP projects');
            }

            $poolDir = $this->getPoolDirectoryPath($website->php_version);
            $filepath = "{$poolDir}/{$website->php_pool_name}.conf";

            $result = Process::run("sudo /bin/rm -f {$filepath}");
            
            if ($result->failed()) {
                throw new \Exception("Failed to delete pool config: " . $result->errorOutput());
            }

            return [
                'success' => true,
                'message' => 'PHP-FPM pool configuration deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete PHP-FPM pool config', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function testConfig(string $phpVersion, ?string $poolConfigPath = null): array
    {
        $phpVer = $this->phpVersionToRhel($phpVersion);
        $result = Process::run("sudo /opt/remi/php{$phpVer}/root/usr/sbin/php-fpm -t");

        return [
            'success' => $result->successful(),
            'output' => $result->output() . $result->errorOutput(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function restart(string $phpVersion): array
    {
        $phpVer = $this->phpVersionToRhel($phpVersion);
        $service = "php{$phpVer}-php-fpm";
        $result = Process::run("sudo /bin/systemctl restart {$service}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "PHP-FPM {$phpVersion} restarted" : "Failed to restart PHP-FPM",
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function reload(string $phpVersion): array
    {
        $phpVer = $this->phpVersionToRhel($phpVersion);
        $service = "php{$phpVer}-php-fpm";
        $result = Process::run("sudo /bin/systemctl reload {$service}");

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? "PHP-FPM {$phpVersion} reloaded" : "Failed to reload PHP-FPM",
            'error' => $result->errorOutput(),
        ];
    }
}

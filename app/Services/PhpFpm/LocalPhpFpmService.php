<?php

namespace App\Services\PhpFpm;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LocalPhpFpmService extends AbstractPhpFpmService
{
    /**
     * Base directory for local PHP-FPM storage.
     *
     * @var string
     */
    protected string $baseDir;

    /**
     * Create a new LocalPhpFpmService instance.
     */
    public function __construct()
    {
        $this->baseDir = storage_path('server/php');
        $this->webServerUser = get_current_user();
        $this->webServerGroup = $this->webServerUser;
        
        $this->ensureDirectories();
    }

    /**
     * {@inheritdoc}
     */
    public function getOsFamily(): string
    {
        return 'local';
    }

    /**
     * {@inheritdoc}
     */
    public function getPoolDirectoryPath(string $phpVersion): string
    {
        return "{$this->baseDir}/{$phpVersion}/pool.d";
    }

    /**
     * {@inheritdoc}
     */
    public function getSocketPath(string $phpVersion, string $poolName): string
    {
        return storage_path("server/php/php{$phpVersion}-fpm-{$poolName}.sock");
    }

    /**
     * {@inheritdoc}
     */
    public function getLogPath(string $phpVersion): string
    {
        return storage_path("server/logs/php{$phpVersion}-fpm");
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
     * Ensure local storage directories exist.
     *
     * @return void
     */
    protected function ensureDirectories(): void
    {
        if (!File::exists($this->baseDir)) {
            File::makeDirectory($this->baseDir, 0755, true);
        }
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

            // Create directories
            if (!File::exists($poolDir)) {
                File::makeDirectory($poolDir, 0755, true);
            }
            if (!File::exists($logDir)) {
                File::makeDirectory($logDir, 0755, true);
            }

            // Write config directly
            File::put($filepath, $config);

            // Update pool name in database
            if (!$website->php_pool_name) {
                $website->update(['php_pool_name' => $poolName]);
            }

            Log::info('[LOCAL] PHP-FPM pool config written to storage', [
                'filepath' => $filepath,
                'website_id' => $website->id
            ]);

            return [
                'success' => true,
                'filepath' => $filepath,
                'pool_name' => $poolName,
                'socket_path' => $socketPath,
                'message' => '[LOCAL] PHP-FPM pool configuration created successfully'
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

            if (File::exists($filepath)) {
                File::delete($filepath);
            }

            Log::info('[LOCAL] PHP-FPM pool config deleted', ['filepath' => $filepath]);

            return [
                'success' => true,
                'message' => '[LOCAL] PHP-FPM pool configuration deleted successfully'
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
        Log::info("[LOCAL] PHP-FPM {$phpVersion} config test (skipped)");
        
        return [
            'success' => true,
            'output' => "[LOCAL] PHP-FPM config test (skipped)",
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function restart(string $phpVersion): array
    {
        Log::info("[LOCAL] PHP-FPM {$phpVersion} restart (skipped)");
        
        return [
            'success' => true,
            'message' => "[LOCAL] PHP-FPM {$phpVersion} restart (skipped)",
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function reload(string $phpVersion): array
    {
        Log::info("[LOCAL] PHP-FPM {$phpVersion} reload (skipped)");
        
        return [
            'success' => true,
            'message' => "[LOCAL] PHP-FPM {$phpVersion} reload (skipped)",
        ];
    }
}

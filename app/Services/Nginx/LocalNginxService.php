<?php

namespace App\Services\Nginx;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LocalNginxService extends AbstractNginxService
{
    /**
     * Create a new LocalNginxService instance.
     */
    public function __construct()
    {
        $storageRoot = storage_path('server');
        $this->sitesAvailable = "{$storageRoot}/nginx/sites-available";
        $this->sitesEnabled = "{$storageRoot}/nginx/sites-enabled";
        $this->configTestCmd = 'echo "[LOCAL] Nginx config test (skipped)"';
        $this->reloadCmd = 'echo "[LOCAL] Nginx reload (skipped)"';
        
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
    public function getPhpFpmSocketPath(string $phpVersion, string $poolName, ?string $customPool = null): string
    {
        if ($customPool) {
            return storage_path("server/php/php{$phpVersion}-fpm-{$poolName}.sock");
        }
        return storage_path("server/php/php{$phpVersion}-fpm.sock");
    }

    /**
     * {@inheritdoc}
     */
    protected function getFastcgiConfig(): string
    {
        return '        include snippets/fastcgi-php.conf;';
    }

    /**
     * Ensure local storage directories exist.
     *
     * @return void
     */
    protected function ensureDirectories(): void
    {
        $dirs = [
            $this->sitesAvailable,
            $this->sitesEnabled,
            storage_path('server/logs/nginx'),
        ];

        foreach ($dirs as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
    }

    /**
     * Write Nginx configuration file (local mode - direct file operations).
     *
     * @param Website $website The website model
     * @return array{success: bool, filepath?: string, message?: string, error?: string}
     */
    public function writeConfig(Website $website): array
    {
        try {
            $config = $this->generateConfig($website);
            $filename = $website->domain . '.conf';
            $filepath = "{$this->sitesAvailable}/{$filename}";

            File::put($filepath, $config);

            Log::info('[LOCAL] Nginx config written to storage', [
                'filepath' => $filepath,
                'website_id' => $website->id
            ]);

            return [
                'success' => true,
                'filepath' => $filepath,
                'message' => '[LOCAL] Nginx configuration written to storage'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to write Nginx config', [
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
     * Delete Nginx configuration file (local mode).
     *
     * @param Website $website The website model
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deleteConfig(Website $website): array
    {
        try {
            $filename = $website->domain . '.conf';
            $filepath = "{$this->sitesAvailable}/{$filename}";
            $enabledPath = "{$this->sitesEnabled}/{$filename}";

            if (File::exists($enabledPath)) {
                File::delete($enabledPath);
            }
            
            if (File::exists($filepath)) {
                File::delete($filepath);
            }

            return [
                'success' => true,
                'message' => '[LOCAL] Nginx configuration deleted'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable a site (local mode - copy file).
     *
     * @param Website $website The website model
     * @return array{success: bool, message: string}
     */
    public function enableSite(Website $website): array
    {
        $filename = $website->domain . '.conf';
        $source = "{$this->sitesAvailable}/{$filename}";
        $target = "{$this->sitesEnabled}/{$filename}";

        if (File::exists($source)) {
            File::copy($source, $target);
        }

        return ['success' => true, 'message' => '[LOCAL] Site enabled'];
    }

    /**
     * Disable a site (local mode).
     *
     * @param Website $website The website model
     * @return array{success: bool, message: string}
     */
    public function disableSite(Website $website): array
    {
        $filename = $website->domain . '.conf';
        $target = "{$this->sitesEnabled}/{$filename}";

        if (File::exists($target)) {
            File::delete($target);
        }

        return ['success' => true, 'message' => '[LOCAL] Site disabled'];
    }
}

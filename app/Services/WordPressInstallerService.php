<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WordPressInstallerService
{
    protected NginxService $nginxService;
    protected PhpFpmService $phpFpmService;

    public function __construct(NginxService $nginxService, PhpFpmService $phpFpmService)
    {
        $this->nginxService = $nginxService;
        $this->phpFpmService = $phpFpmService;
    }

    /**
     * Install WordPress with optimized configuration.
     *
     * @param Website $website The website to install WordPress on
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     * @param string $dbPrefix Table prefix
     * @param string $adminUser WordPress admin username
     * @param string $adminPassword WordPress admin password
     * @param string $adminEmail WordPress admin email
     * @param string $siteTitle Site title
     * @param bool $enableCache Enable FastCGI cache
     * @param bool $installPlugins Install recommended plugins
     * @return array{success: bool, message: string, admin_url?: string, admin_user?: string, steps: array}
     */
    public function install(
        Website $website,
        string $dbName,
        string $dbUser,
        string $dbPassword,
        string $dbHost = 'localhost',
        string $dbPrefix = 'wp_',
        string $adminUser,
        string $adminPassword,
        string $adminEmail,
        string $siteTitle,
        bool $enableCache = true,
        bool $installPlugins = true
    ): array {
        $steps = [];
        
        try {
            // Step 1: Check if WP-CLI is installed
            $steps[] = ['step' => 'Check WP-CLI', 'status' => 'running'];
            if (!$this->checkWpCli()) {
                return [
                    'success' => false,
                    'message' => 'WP-CLI is not installed. Please run: curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp',
                    'steps' => $steps
                ];
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 2: Create database
            $steps[] = ['step' => 'Create database', 'status' => 'running'];
            $dbResult = $this->createDatabase($dbName, $dbUser, $dbPassword, $dbHost);
            if (!$dbResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Database creation failed: ' . $dbResult['message'],
                    'steps' => $steps
                ];
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 3: Download WordPress
            $steps[] = ['step' => 'Download WordPress', 'status' => 'running'];
            $downloadResult = $this->downloadWordPress($website->root_path);
            if (!$downloadResult['success']) {
                return [
                    'success' => false,
                    'message' => 'WordPress download failed',
                    'steps' => $steps
                ];
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 4: Configure wp-config.php
            $steps[] = ['step' => 'Configure WordPress', 'status' => 'running'];
            $configResult = $this->configureWordPress(
                $website->root_path,
                $dbName,
                $dbUser,
                $dbPassword,
                $dbHost,
                $dbPrefix
            );
            if (!$configResult['success']) {
                return [
                    'success' => false,
                    'message' => 'WordPress configuration failed',
                    'steps' => $steps
                ];
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 5: Install WordPress
            $steps[] = ['step' => 'Install WordPress core', 'status' => 'running'];
            $installResult = $this->installWordPressCore(
                $website->root_path,
                $website->domain,
                $siteTitle,
                $adminUser,
                $adminPassword,
                $adminEmail
            );
            if (!$installResult['success']) {
                return [
                    'success' => false,
                    'message' => 'WordPress core installation failed',
                    'steps' => $steps
                ];
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 6: Install recommended plugins
            if ($installPlugins) {
                $steps[] = ['step' => 'Install recommended plugins', 'status' => 'running'];
                $this->installRecommendedPlugins($website->root_path, $enableCache);
                $steps[count($steps) - 1]['status'] = 'completed';
            }

            // Step 7: Generate optimized Nginx config
            $steps[] = ['step' => 'Generate optimized Nginx config', 'status' => 'running'];
            $nginxResult = $this->generateOptimizedNginxConfig($website, $enableCache);
            if (!$nginxResult['success']) {
                Log::warning('Nginx config generation failed', $nginxResult);
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 8: Generate optimized PHP-FPM pool
            $steps[] = ['step' => 'Generate optimized PHP-FPM pool', 'status' => 'running'];
            $phpFpmResult = $this->generateOptimizedPhpFpmPool($website);
            if (!$phpFpmResult['success']) {
                Log::warning('PHP-FPM pool generation failed', $phpFpmResult);
            }
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 9: Set permissions
            $steps[] = ['step' => 'Set file permissions', 'status' => 'running'];
            $this->setWordPressPermissions($website->root_path);
            $steps[count($steps) - 1]['status'] = 'completed';

            // Step 10: Create cache directory if enabled
            if ($enableCache) {
                $steps[] = ['step' => 'Setup FastCGI cache', 'status' => 'running'];
                $this->setupFastCgiCache($website->domain);
                $steps[count($steps) - 1]['status'] = 'completed';
            }

            return [
                'success' => true,
                'message' => 'WordPress installed successfully!',
                'admin_url' => 'https://' . $website->domain . '/wp-admin',
                'admin_user' => $adminUser,
                'steps' => $steps
            ];

        } catch (\Exception $e) {
            Log::error('WordPress installation error', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
                'steps' => $steps
            ];
        }
    }

    /**
     * Check if WP-CLI is installed.
     *
     * @return bool True if WP-CLI is available
     */
    protected function checkWpCli(): bool
    {
        $result = Process::run('which wp');
        return $result->successful();
    }

    /**
     * Create database and user.
     *
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     * @return array{success: bool, message?: string}
     */
    protected function createDatabase(string $dbName, string $dbUser, string $dbPassword, string $dbHost): array
    {
        try {
            // Read MySQL root password
            $rootPassword = trim(file_get_contents('/root/.mysql_root_password'));
            
            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                    CREATE USER IF NOT EXISTS '{$dbUser}'@'{$dbHost}' IDENTIFIED BY '{$dbPassword}';
                    GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$dbHost}';
                    FLUSH PRIVILEGES;";

            $result = Process::run("sudo mysql -u root -p'{$rootPassword}' -e \"{$sql}\"");

            if ($result->successful()) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => $result->errorOutput()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Download WordPress using WP-CLI.
     *
     * @param string $path The installation path
     * @return array{success: bool, output: string}
     */
    protected function downloadWordPress(string $path): array
    {
        $result = Process::path($path)->run('sudo -u www-data wp core download --force');

        return [
            'success' => $result->successful(),
            'output' => $result->output()
        ];
    }

    /**
     * Configure wp-config.php.
     *
     * @param string $path The installation path
     * @param string $dbName Database name
     * @param string $dbUser Database username
     * @param string $dbPassword Database password
     * @param string $dbHost Database host
     * @param string $dbPrefix Table prefix
     * @return array{success: bool, output: string}
     */
    protected function configureWordPress(
        string $path,
        string $dbName,
        string $dbUser,
        string $dbPassword,
        string $dbHost,
        string $dbPrefix
    ): array {
        $result = Process::path($path)->run(
            "sudo -u www-data wp config create " .
            "--dbname={$dbName} " .
            "--dbuser={$dbUser} " .
            "--dbpass='{$dbPassword}' " .
            "--dbhost={$dbHost} " .
            "--dbprefix={$dbPrefix} " .
            "--force"
        );

        return [
            'success' => $result->successful(),
            'output' => $result->output()
        ];
    }

    /**
     * Install WordPress core.
     *
     * @param string $path The installation path
     * @param string $domain The domain name
     * @param string $title Site title
     * @param string $adminUser Admin username
     * @param string $adminPassword Admin password
     * @param string $adminEmail Admin email
     * @return array{success: bool, output: string}
     */
    protected function installWordPressCore(
        string $path,
        string $domain,
        string $title,
        string $adminUser,
        string $adminPassword,
        string $adminEmail
    ): array {
        $url = 'https://' . $domain;
        
        $result = Process::path($path)->run(
            "sudo -u www-data wp core install " .
            "--url='{$url}' " .
            "--title='" . addslashes($title) . "' " .
            "--admin_user={$adminUser} " .
            "--admin_password='{$adminPassword}' " .
            "--admin_email={$adminEmail} " .
            "--skip-email"
        );

        return [
            'success' => $result->successful(),
            'output' => $result->output()
        ];
    }

    /**
     * Install recommended plugins.
     *
     * @param string $path The installation path
     * @param bool $enableCache Whether cache is enabled
     * @return void
     */
    protected function installRecommendedPlugins(string $path, bool $enableCache): void
    {
        $plugins = [
            'wordfence',           // Security
            'wp-optimize',         // Optimization
            'updraftplus',         // Backup
        ];

        if ($enableCache) {
            $plugins[] = 'nginx-helper';  // FastCGI cache purging
        }

        foreach ($plugins as $plugin) {
            Process::path($path)->run("sudo -u www-data wp plugin install {$plugin} --activate");
        }

        // Configure Nginx Helper for cache purging
        if ($enableCache) {
            Process::path($path)->run(
                "sudo -u www-data wp option update rt_wp_nginx_helper_options '" .
                json_encode([
                    'enable_purge' => 1,
                    'cache_method' => 'enable_fastcgi',
                    'purge_method' => 'get_request',
                    'enable_map' => 0,
                    'enable_log' => 0,
                    'log_level' => 'INFO',
                    'log_filesize' => 5,
                    'enable_stamp' => 0,
                    'purge_homepage_on_new' => 1,
                    'purge_homepage_on_edit' => 1,
                    'purge_homepage_on_del' => 1,
                    'purge_archive_on_new' => 1,
                    'purge_archive_on_edit' => 0,
                    'purge_archive_on_del' => 1,
                    'purge_page_on_mod' => 1,
                ]) .
                "' --format=json"
            );
        }
    }

    /**
     * Generate optimized Nginx configuration for WordPress.
     *
     * @param Website $website The website
     * @param bool $enableCache Whether cache is enabled
     * @return array{success: bool, config?: string, message?: string}
     */
    protected function generateOptimizedNginxConfig(Website $website, bool $enableCache): array
    {
        try {
            // Use the existing NginxService but with WordPress-optimized template
            $config = $this->nginxService->generateWordPressConfig($website, $enableCache);
            
            return [
                'success' => true,
                'config' => $config
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate optimized PHP-FPM pool for WordPress.
     *
     * @param Website $website The website
     * @return array{success: bool, message?: string}
     */
    protected function generateOptimizedPhpFpmPool(Website $website): array
    {
        try {
            // Override PHP settings for WordPress
            $wordpressSettings = [
                'memory_limit' => '256M',
                'max_execution_time' => '300',
                'upload_max_filesize' => '64M',
                'post_max_size' => '64M',
                'max_input_vars' => '3000',  // WordPress needs high value
                'disable_functions' => 'exec,passthru,shell_exec,system,proc_open,popen',  // Keep some for WordPress
                'opcache.enable' => 'On',
                'opcache.memory_consumption' => '256',
                'opcache.interned_strings_buffer' => '16',
                'opcache.max_accelerated_files' => '20000',
                'opcache.revalidate_freq' => '2',
            ];

            $website->php_settings = array_merge($website->php_settings ?? [], $wordpressSettings);
            $website->save();

            // Generate pool config
            $config = $this->phpFpmService->generatePoolConfig($website);
            $this->phpFpmService->deployPoolConfig($website, $config);

            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Set WordPress file permissions.
     *
     * @param string $path The installation path
     * @return void
     */
    protected function setWordPressPermissions(string $path): void
    {
        // Directories: 755
        Process::run("sudo /usr/bin/find {$path} -type d -exec /bin/chmod 755 {} \\;");
        
        // Files: 644
        Process::run("sudo /usr/bin/find {$path} -type f -exec /bin/chmod 644 {} \\;");
        
        // wp-config.php: 600 (more secure)
        if (file_exists("{$path}/wp-config.php")) {
            Process::run("sudo /bin/chmod 600 {$path}/wp-config.php");
        }
        
        // Set owner
        Process::run("sudo /bin/chown -R www-data:www-data {$path}");
    }

    /**
     * Setup FastCGI cache directory.
     *
     * @param string $domain The domain name
     * @return void
     */
    protected function setupFastCgiCache(string $domain): void
    {
        $cacheDir = "/var/cache/nginx/wordpress-{$domain}";
        
        Process::run("sudo /bin/mkdir -p {$cacheDir}");
        Process::run("sudo /bin/chown -R www-data:www-data {$cacheDir}");
        Process::run("sudo /bin/chmod -R 755 {$cacheDir}");
    }

    /**
     * Check if WordPress is installed.
     *
     * @param Website $website The website to check
     * @return array{installed: bool, message: string, admin_url?: string}
     */
    public function checkInstallation(Website $website): array
    {
        $wpConfigPath = $website->root_path . '/wp-config.php';
        
        if (!file_exists($wpConfigPath)) {
            return [
                'installed' => false,
                'message' => 'WordPress not installed'
            ];
        }

        // Check if WordPress is configured
        $result = Process::path($website->root_path)->run('sudo -u www-data wp core is-installed');

        return [
            'installed' => $result->successful(),
            'message' => $result->successful() ? 'WordPress is installed' : 'WordPress files exist but not configured',
            'admin_url' => 'https://' . $website->domain . '/wp-admin'
        ];
    }
}

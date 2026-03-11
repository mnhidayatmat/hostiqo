<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DockerService
{
    protected bool $isLocal;
    protected string $dockerProjectsPath;

    /**
     * Available Docker templates with their configurations.
     */
    protected array $templates = [
        'affine' => [
            'name' => 'AFFiNE',
            'description' => 'Next-gen knowledge base that brings planning, sorting and creating all together',
            'image' => 'ghcr.io/toeverything/affine',
            'tag' => 'stable',
            'compose' => 'affine',
            'services' => ['affine', 'postgres', 'redis'],
            'default_port' => 3010,
        ],
        'wordpress' => [
            'name' => 'WordPress',
            'description' => 'WordPress with MySQL and phpMyAdmin',
            'image' => 'wordpress',
            'tag' => 'latest',
            'compose' => 'wordpress',
            'services' => ['wordpress', 'db', 'phpmyadmin'],
            'default_port' => 8080,
        ],
        'nextcloud' => [
            'name' => 'Nextcloud',
            'description' => 'Self-hosted file sync and share platform',
            'image' => 'nextcloud',
            'tag' => 'latest',
            'compose' => 'nextcloud',
            'services' => ['nextcloud', 'db', 'redis'],
            'default_port' => 8081,
        ],
        'plausible' => [
            'name' => 'Plausible Analytics',
            'description' => 'Simple, privacy-friendly alternative to Google Analytics',
            'image' => 'plausible/analytics',
            'tag' => 'latest',
            'compose' => 'plausible',
            'services' => ['plausible', 'db', 'clickhouse', 'geoip'],
            'default_port' => 8000,
        ],
        'vaultwarden' => [
            'name' => 'Vaultwarden',
            'description' => 'Unofficial Bitwarden server implementation',
            'image' => 'vaultwarden/server',
            'tag' => 'latest',
            'compose' => 'vaultwarden',
            'services' => ['vaultwarden'],
            'default_port' => 8082,
        ],
        'gitea' => [
            'name' => 'Gitea',
            'description' => 'Lightweight self-hosted Git service',
            'image' => 'gitea/gitea',
            'tag' => 'latest',
            'compose' => 'gitea',
            'services' => ['gitea', 'db'],
            'default_port' => 3000,
        ],
        'uptime-kuma' => [
            'name' => 'Uptime Kuma',
            'description' => 'Self-hosted monitoring tool',
            'image' => 'louislam/uptime-kuma',
            'tag' => 'latest',
            'compose' => 'uptime-kuma',
            'services' => ['uptime-kuma'],
            'default_port' => 3001,
        ],
    ];

    public function __construct()
    {
        $this->isLocal = in_array(config('app.env'), ['local', 'dev', 'development']);

        // Environment-aware paths
        if ($this->isLocal) {
            $this->dockerProjectsPath = storage_path('server/docker');
        } else {
            $this->dockerProjectsPath = '/var/www/docker';
        }
    }

    /**
     * Get all available Docker templates.
     *
     * @return array
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Get a specific template by key.
     *
     * @param string $key
     * @return array|null
     */
    public function getTemplate(string $key): ?array
    {
        return $this->templates[$key] ?? null;
    }

    /**
     * Generate docker-compose.yml content for a template.
     *
     * @param string $template
     * @param Website $website
     * @return string
     */
    public function generateComposeFile(string $template, Website $website): string
    {
        $projectName = str_replace(['.', '-'], '_', $website->domain);
        $port = $website->port ?? $this->getTemplate($template)['default_port'] ?? 8080;

        return match($template) {
            'affine' => $this->generateAffineCompose($website, $projectName, $port),
            'wordpress' => $this->generateWordPressCompose($website, $projectName, $port),
            'nextcloud' => $this->generateNextcloudCompose($website, $projectName, $port),
            'vaultwarden' => $this->generateVaultwardenCompose($website, $projectName, $port),
            'gitea' => $this->generateGiteaCompose($website, $projectName, $port),
            'uptime-kuma' => $this->generateUptimeKumaCompose($website, $projectName, $port),
            default => throw new \InvalidArgumentException("Unknown template: {$template}"),
        };
    }

    /**
     * Generate docker-compose.yml for AFFiNE.
     */
    protected function generateAffineCompose(Website $website, string $projectName, int $port): string
    {
        $dbPassword = $website->docker_env['DB_PASSWORD'] ?? 'changeme';
        $dbUser = $website->docker_env['DB_USERNAME'] ?? 'affine';
        $dbDatabase = $website->docker_env['DB_DATABASE'] ?? 'affine';

        return <<<YAML
name: {$projectName}
services:
  affine:
    image: ghcr.io/toeverything/affine:stable
    container_name: {$projectName}_affine
    ports:
      - '{$port}:3010'
    depends_on:
      redis:
        condition: service_healthy
      postgres:
        condition: service_healthy
      affine_migration:
        condition: service_completed_successfully
    volumes:
      - ./storage:/root/.affine/storage
      - ./config:/root/.affine/config
    environment:
      - REDIS_SERVER_HOST=redis
      - DATABASE_URL=postgresql://{$dbUser}:{$dbPassword}@postgres:5432/{$dbDatabase}
      - AFFINE_INDEXER_ENABLED=false
    restart: unless-stopped

  affine_migration:
    image: ghcr.io/toeverything/affine:stable
    container_name: {$projectName}_migration
    volumes:
      - ./storage:/root/.affine/storage
      - ./config:/root/.affine/config
    command: ['sh', '-c', 'node ./scripts/self-host-predeploy.js']
    environment:
      - REDIS_SERVER_HOST=redis
      - DATABASE_URL=postgresql://{$dbUser}:{$dbPassword}@postgres:5432/{$dbDatabase}
      - AFFINE_INDEXER_ENABLED=false
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy

  redis:
    image: redis:alpine
    container_name: {$projectName}_redis
    healthcheck:
      test: ['CMD', 'redis-cli', '--raw', 'incr', 'ping']
      interval: 10s
      timeout: 5s
      retries: 5
    restart: unless-stopped

  postgres:
    image: pgvector/pgvector:pg16
    container_name: {$projectName}_postgres
    volumes:
      - ./postgres:/var/lib/postgresql/data
    environment:
      POSTGRES_USER: {$dbUser}
      POSTGRES_PASSWORD: {$dbPassword}
      POSTGRES_DB: {$dbDatabase}
      POSTGRES_INITDB_ARGS: '--data-checksums'
      POSTGRES_HOST_AUTH_METHOD: trust
    healthcheck:
      test: ['CMD', 'pg_isready', '-U', '{$dbUser}', '-d', '{$dbDatabase}']
      interval: 10s
      timeout: 5s
      retries: 5
    restart: unless-stopped

YAML;
    }

    /**
     * Generate docker-compose.yml for WordPress.
     */
    protected function generateWordPressCompose(Website $website, string $projectName, int $port): string
    {
        $dbPassword = $website->docker_env['DB_PASSWORD'] ?? 'wordpress';
        $dbUser = $website->docker_env['DB_USERNAME'] ?? 'wordpress';
        $dbDatabase = $website->docker_env['DB_DATABASE'] ?? 'wordpress';

        return <<<YAML
name: {$projectName}
services:
  wordpress:
    image: wordpress:latest
    container_name: {$projectName}_wordpress
    ports:
      - '{$port}:80'
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: {$dbUser}
      WORDPRESS_DB_PASSWORD: {$dbPassword}
      WORDPRESS_DB_NAME: {$dbDatabase}
    volumes:
      - ./wordpress:/var/www/html
    restart: unless-stopped
    depends_on:
      - db

  db:
    image: mysql:8.0
    container_name: {$projectName}_db
    environment:
      MYSQL_DATABASE: {$dbDatabase}
      MYSQL_USER: {$dbUser}
      MYSQL_PASSWORD: {$dbPassword}
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - ./mysql:/var/lib/mysql
    restart: unless-stopped

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: {$projectName}_phpmyadmin
    ports:
      - '{$port}1:80'
    environment:
      PMA_HOST: db
      PMA_PORT: 3306
      UPLOAD_LIMIT: 100M
    restart: unless-stopped
    depends_on:
      - db

YAML;
    }

    /**
     * Generate docker-compose.yml for Nextcloud.
     */
    protected function generateNextcloudCompose(Website $website, string $projectName, int $port): string
    {
        $dbPassword = $website->docker_env['DB_PASSWORD'] ?? 'nextcloud';
        $dbUser = $website->docker_env['DB_USERNAME'] ?? 'nextcloud';
        $dbDatabase = $website->docker_env['DB_DATABASE'] ?? 'nextcloud';

        return <<<YAML
name: {$projectName}
services:
  nextcloud:
    image: nextcloud:latest
    container_name: {$projectName}_nextcloud
    ports:
      - '{$port}:80'
    volumes:
      - ./nextcloud:/var/www/html
    environment:
      - MYSQL_HOST=db
      - MYSQL_USER={$dbUser}
      - MYSQL_PASSWORD={$dbPassword}
      - MYSQL_DATABASE={$dbDatabase}
      - REDIS_HOST=redis
    restart: unless-stopped
    depends_on:
      - db
      - redis

  db:
    image: mysql:8.0
    container_name: {$projectName}_db
    environment:
      MYSQL_DATABASE: {$dbDatabase}
      MYSQL_USER: {$dbUser}
      MYSQL_PASSWORD: {$dbPassword}
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - ./mysql:/var/lib/mysql
    restart: unless-stopped

  redis:
    image: redis:alpine
    container_name: {$projectName}_redis
    restart: unless-stopped

YAML;
    }

    /**
     * Generate docker-compose.yml for Vaultwarden.
     */
    protected function generateVaultwardenCompose(Website $website, string $projectName, int $port): string
    {
        return <<<YAML
name: {$projectName}
services:
  vaultwarden:
    image: vaultwarden/server:latest
    container_name: {$projectName}_vaultwarden
    ports:
      - '{$port}:80'
    volumes:
      - ./vaultwarden:/data
    environment:
      - ROCKET_PORT=80
    restart: unless-stopped

YAML;
    }

    /**
     * Generate docker-compose.yml for Gitea.
     */
    protected function generateGiteaCompose(Website $website, string $projectName, int $port): string
    {
        $dbPassword = $website->docker_env['DB_PASSWORD'] ?? 'gitea';
        $dbUser = $website->docker_env['DB_USERNAME'] ?? 'gitea';
        $dbDatabase = $website->docker_env['DB_DATABASE'] ?? 'gitea';

        return <<<YAML
name: {$projectName}
services:
  gitea:
    image: gitea/gitea:latest
    container_name: {$projectName}_gitea
    ports:
      - '{$port}:3000'
      - '222:22'
    volumes:
      - ./gitea:/data
      - /etc/timezone:/etc/timezone:ro
      - /etc/localtime:/etc/localtime:ro
    environment:
      - USER_UID=1000
      - USER_GID=1000
      - GITEA__database__DB_TYPE=mysql
      - GITEA__database__HOST=db:3306
      - GITEA__database__NAME={$dbDatabase}
      - GITEA__database__USER={$dbUser}
      - GITEA__database__PASSWD={$dbPassword}
    restart: unless-stopped
    depends_on:
      - db

  db:
    image: mysql:8
    container_name: {$projectName}_db
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_USER: {$dbUser}
      MYSQL_PASSWORD: {$dbPassword}
      MYSQL_DATABASE: {$dbDatabase}
    volumes:
      - ./mysql:/var/lib/mysql
    restart: unless-stopped

YAML;
    }

    /**
     * Generate docker-compose.yml for Uptime Kuma.
     */
    protected function generateUptimeKumaCompose(Website $website, string $projectName, int $port): string
    {
        return <<<YAML
name: {$projectName}
services:
  uptime-kuma:
    image: louislam/uptime-kuma:latest
    container_name: {$projectName}_uptime_kuma
    ports:
      - '{$port}:3001'
    volumes:
      - ./uptime-kuma:/app/data
    restart: unless-stopped

YAML;
    }

    /**
     * Create docker-compose.yml file for a website.
     *
     * @param Website $website
     * @return array{success: bool, filepath?: string, message?: string, error?: string}
     */
    public function createComposeFile(Website $website): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $template = $website->docker_template ?? 'custom';
            $projectDir = $this->getProjectDirectory($website);
            $composeFile = "{$projectDir}/docker-compose.yml";

            // Create project directory
            if ($this->isLocal) {
                if (!File::exists($projectDir)) {
                    File::makeDirectory($projectDir, 0755, true);
                }
            } else {
                Process::run("sudo /bin/mkdir -p {$projectDir}");
                Process::run("sudo /bin/chmod 755 {$projectDir}");
            }

            // Generate compose file content
            $content = $this->generateComposeFile($template, $website);

            // Write compose file
            if ($this->isLocal) {
                File::put($composeFile, $content);
            } else {
                $tempFile = tempnam(sys_get_temp_dir(), 'docker_');
                File::put($tempFile, $content);
                Process::run("sudo /bin/cp {$tempFile} {$composeFile}");
                Process::run("sudo /bin/chmod 644 {$composeFile}");
                @unlink($tempFile);
            }

            // Create .env file if environment variables are provided
            if (!empty($website->docker_env)) {
                $envContent = '';
                foreach ($website->docker_env as $key => $value) {
                    $envContent .= "{$key}={$value}\n";
                }

                if ($this->isLocal) {
                    File::put("{$projectDir}/.env", $envContent);
                } else {
                    $tempEnvFile = tempnam(sys_get_temp_dir(), 'docker_env_');
                    File::put($tempEnvFile, $envContent);
                    Process::run("sudo /bin/cp {$tempEnvFile} {$projectDir}/.env");
                    Process::run("sudo /bin/chmod 600 {$projectDir}/.env");
                    @unlink($tempEnvFile);
                }
            }

            // Create necessary subdirectories
            $subdirs = match($template) {
                'affine' => ['storage', 'config', 'postgres'],
                'wordpress' => ['wordpress', 'mysql'],
                'nextcloud' => ['nextcloud', 'mysql'],
                'vaultwarden' => ['vaultwarden'],
                'gitea' => ['gitea', 'mysql'],
                'uptime-kuma' => ['uptime-kuma'],
                default => [],
            };

            foreach ($subdirs as $subdir) {
                $fullPath = "{$projectDir}/{$subdir}";
                if ($this->isLocal) {
                    if (!File::exists($fullPath)) {
                        File::makeDirectory($fullPath, 0755, true);
                    }
                } else {
                    Process::run("sudo /bin/mkdir -p {$fullPath}");
                    Process::run("sudo /bin/chmod 755 {$fullPath}");
                }
            }

            // Store the compose file path in the database
            $website->update(['docker_compose_path' => $composeFile]);

            Log::info('Docker compose file created', [
                'website_id' => $website->id,
                'filepath' => $composeFile
            ]);

            return [
                'success' => true,
                'filepath' => $composeFile,
                'message' => 'Docker compose file created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Docker compose file', [
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
     * Start Docker containers for a website.
     *
     * @param Website $website
     * @return array{success: bool, message?: string, error?: string, output?: string}
     */
    public function startContainers(Website $website): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            // Update status to pending
            $website->update(['docker_status' => 'pending']);

            $result = Process::path($projectDir)->run('docker compose up -d');

            if ($result->successful()) {
                $website->update(['docker_status' => 'running']);

                Log::info('Docker containers started', [
                    'website_id' => $website->id,
                    'output' => $result->output()
                ]);

                return [
                    'success' => true,
                    'message' => 'Docker containers started successfully',
                    'output' => $result->output()
                ];
            }

            $website->update(['docker_status' => 'error']);

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: 'Failed to start containers'
            ];
        } catch (\Exception $e) {
            $website->update(['docker_status' => 'error']);

            Log::error('Failed to start Docker containers', [
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
     * Stop Docker containers for a website.
     *
     * @param Website $website
     * @return array{success: bool, message?: string, error?: string, output?: string}
     */
    public function stopContainers(Website $website): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            $result = Process::path($projectDir)->run('docker compose down');

            if ($result->successful()) {
                $website->update(['docker_status' => 'stopped']);

                Log::info('Docker containers stopped', [
                    'website_id' => $website->id
                ]);

                return [
                    'success' => true,
                    'message' => 'Docker containers stopped successfully',
                    'output' => $result->output()
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: 'Failed to stop containers'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to stop Docker containers', [
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
     * Restart Docker containers for a website.
     *
     * @param Website $website
     * @return array{success: bool, message?: string, error?: string, output?: string}
     */
    public function restartContainers(Website $website): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            $website->update(['docker_status' => 'restarting']);

            $result = Process::path($projectDir)->run('docker compose restart');

            if ($result->successful()) {
                $website->update(['docker_status' => 'running']);

                Log::info('Docker containers restarted', [
                    'website_id' => $website->id
                ]);

                return [
                    'success' => true,
                    'message' => 'Docker containers restarted successfully',
                    'output' => $result->output()
                ];
            }

            $website->update(['docker_status' => 'error']);

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: 'Failed to restart containers'
            ];
        } catch (\Exception $e) {
            $website->update(['docker_status' => 'error']);

            Log::error('Failed to restart Docker containers', [
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
     * Get Docker container status for a website.
     *
     * @param Website $website
     * @return array{success: bool, status?: string, containers?: array, error?: string}
     */
    public function getContainerStatus(Website $website): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            $result = Process::path($projectDir)->run('docker compose ps --format json');

            if ($result->failed()) {
                return [
                    'success' => false,
                    'error' => 'Failed to get container status'
                ];
            }

            $containers = json_decode($result->output(), true);

            // Determine overall status
            $status = 'stopped';
            if (!empty($containers)) {
                $allRunning = collect($containers)->every(fn($c) => ($c['State'] ?? '') === 'running');
                $anyRunning = collect($containers)->contains(fn($c) => ($c['State'] ?? '') === 'running');

                if ($allRunning) {
                    $status = 'running';
                } elseif ($anyRunning) {
                    $status = 'partial';
                } else {
                    $status = 'stopped';
                }
            }

            return [
                'success' => true,
                'status' => $status,
                'containers' => $containers ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Docker container status', [
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
     * Get Docker container logs for a website.
     *
     * @param Website $website
     * @param string|null $service
     * @param int $lines
     * @return array{success: bool, logs?: string, error?: string}
     */
    public function getLogs(Website $website, ?string $service = null, int $lines = 100): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            $command = 'docker compose logs --tail ' . $lines;
            if ($service) {
                $command .= ' ' . escapeshellarg($service);
            }

            $result = Process::path($projectDir)->run($command);

            return [
                'success' => true,
                'logs' => $result->output()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Docker logs', [
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
     * Pull latest Docker images for a website.
     *
     * @param Website $website
     * @return array{success: bool, message?: string, error?: string, output?: string}
     */
    public function pullImages(Website $website): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            $result = Process::path($projectDir)->run('docker compose pull');

            if ($result->successful()) {
                Log::info('Docker images pulled', [
                    'website_id' => $website->id
                ]);

                return [
                    'success' => true,
                    'message' => 'Docker images pulled successfully',
                    'output' => $result->output()
                ];
            }

            return [
                'success' => false,
                'error' => $result->errorOutput() ?: 'Failed to pull images'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to pull Docker images', [
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
     * Delete Docker project (compose file and volumes).
     *
     * @param Website $website
     * @param bool $deleteVolumes
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deleteProject(Website $website, bool $deleteVolumes = false): array
    {
        try {
            if ($website->project_type !== 'docker') {
                return [
                    'success' => false,
                    'error' => 'Not a Docker project'
                ];
            }

            $projectDir = $this->getProjectDirectory($website);

            // Stop and remove containers
            $command = 'docker compose down';
            if ($deleteVolumes) {
                $command .= ' -v';
            }

            Process::path($projectDir)->run($command);

            // Delete project directory
            if ($this->isLocal) {
                if (File::exists($projectDir)) {
                    File::deleteDirectory($projectDir);
                }
            } else {
                Process::run("sudo /bin/rm -rf {$projectDir}");
            }

            Log::info('Docker project deleted', [
                'website_id' => $website->id,
                'delete_volumes' => $deleteVolumes
            ]);

            return [
                'success' => true,
                'message' => 'Docker project deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete Docker project', [
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
     * Get the project directory for a website.
     *
     * @param Website $website
     * @return string
     */
    protected function getProjectDirectory(Website $website): string
    {
        $projectName = str_replace(['.', '-'], '_', $website->domain);
        return "{$this->dockerProjectsPath}/{$projectName}";
    }

    /**
     * Check if Docker is available on the server.
     *
     * @return array{success: bool, installed?: bool, version?: string, error?: string}
     */
    public function checkDockerInstallation(): array
    {
        try {
            $result = Process::run('docker --version');

            if ($result->successful()) {
                $version = trim(str_replace('Docker version ', '', $result->output()));

                // Check docker compose
                $composeResult = Process::run('docker compose version');

                return [
                    'success' => true,
                    'installed' => true,
                    'version' => $version,
                    'compose_available' => $composeResult->successful()
                ];
            }

            return [
                'success' => true,
                'installed' => false,
                'error' => 'Docker is not installed'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

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
        'n8n' => [
            'name' => 'n8n',
            'description' => 'Workflow automation tool - automate without limits',
            'image' => 'n8nio/n8n',
            'tag' => 'latest',
            'compose' => 'n8n',
            'services' => ['n8n'],
            'default_port' => 5678,
        ],
        'appflowy' => [
            'name' => 'AppFlowy',
            'description' => 'Open-source AI workspace - the Notion alternative (self-hosted AppFlowy Cloud)',
            'image' => 'appflowyinc/appflowy_cloud',
            'tag' => 'latest',
            'compose' => 'appflowy',
            // AppFlowy ships its own internal nginx that path-routes to every
            // backend, so hostiqo's outer proxy only needs to target the nginx
            // service. All other services stay on the internal compose network.
            'services' => [
                'nginx', 'postgres', 'redis', 'minio', 'gotrue',
                'appflowy_cloud', 'admin_frontend', 'appflowy_web',
                'ai', 'appflowy_search', 'appflowy_worker',
            ],
            'default_port' => 8083,
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
            'n8n' => $this->generateN8nCompose($website, $projectName, $port),
            'appflowy' => $this->generateAppFlowyCompose($website, $projectName, $port),
            'custom' => $this->generateCustomCompose($website, $projectName, $port),
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
     * Generate docker-compose.yml for n8n.
     */
    protected function generateN8nCompose(Website $website, string $projectName, int $port): string
    {
        $webhookUrl = "https://{$website->domain}/";
        $genericTimezone = $website->docker_env['GENERIC_TIMEZONE'] ?? 'Asia/Kuala_Lumpur';

        return <<<YAML
name: {$projectName}
services:
  n8n:
    image: n8nio/n8n:latest
    container_name: {$projectName}_n8n
    ports:
      - '{$port}:5678'
    volumes:
      - ./n8n-data:/home/node/.n8n
    environment:
      - N8N_HOST={$website->domain}
      - N8N_PORT=5678
      - N8N_PROTOCOL=https
      - WEBHOOK_URL={$webhookUrl}
      - GENERIC_TIMEZONE={$genericTimezone}
      - TZ={$genericTimezone}
    restart: unless-stopped

YAML;
    }

    /**
     * "Custom" template: deploy any app from a user-supplied docker-compose.yml.
     *
     * The compose content is stored verbatim on the website (docker_compose) and
     * written as-is. The user is responsible for the YAML, including publishing a
     * host port that matches the website's configured port so the outer nginx
     * reverse proxy can reach it.
     */
    protected function generateCustomCompose(Website $website, string $projectName, int $port): string
    {
        $compose = trim((string) ($website->docker_compose ?? ''));

        if ($compose === '') {
            throw new \InvalidArgumentException('Custom Docker project has no docker-compose.yml content.');
        }

        return $compose . "\n";
    }

    /**
     * Derive a stable, per-site secret. Stable across regenerations (so the
     * postgres password / JWT secret never change out from under existing
     * containers) yet unique per domain. Overridable via docker_env.
     */
    protected function appflowySecret(Website $website, string $purpose, int $length = 32): string
    {
        return substr(hash('sha256', $purpose . '|' . $website->domain . '|' . config('app.key')), 0, $length);
    }

    /**
     * Generate docker-compose.yml for AppFlowy (self-hosted AppFlowy Cloud).
     *
     * This is a multi-service stack. AppFlowy's own nginx container terminates
     * the internal path routing (/, /ws, /gotrue, /api, /minio-api, /console, ...)
     * and is the single entrypoint that hostiqo's outer per-domain nginx proxies
     * to. TLS is handled by the outer nginx, so the internal nginx is HTTP-only.
     * The internal nginx.conf is written separately via getTemplateExtraFiles().
     */
    protected function generateAppFlowyCompose(Website $website, string $projectName, int $port): string
    {
        $env = $website->docker_env ?? [];

        // Public-facing URLs. hostiqo terminates TLS, so the public scheme is https/wss.
        $domain = $website->domain;
        $baseUrl = "https://{$domain}";
        $wsBaseUrl = "wss://{$domain}/ws/v2";
        $apiExternalUrl = "{$baseUrl}/gotrue";
        $presignedEndpoint = "{$baseUrl}/minio-api";

        // Credentials & secrets (overridable via docker_env, otherwise derived & stable).
        $pgUser = $env['DB_USERNAME'] ?? 'postgres';
        $pgPass = $env['DB_PASSWORD'] ?? $this->appflowySecret($website, 'postgres', 24);
        $pgDb = $env['DB_DATABASE'] ?? 'postgres';
        $jwtSecret = $env['GOTRUE_JWT_SECRET'] ?? $this->appflowySecret($website, 'jwt', 48);
        $adminEmail = $env['GOTRUE_ADMIN_EMAIL'] ?? "admin@{$domain}";
        $adminPass = $env['GOTRUE_ADMIN_PASSWORD'] ?? $this->appflowySecret($website, 'gotrue-admin', 20);
        $minioKey = $env['AWS_ACCESS_KEY'] ?? $this->appflowySecret($website, 'minio-key', 20);
        $minioSecret = $env['AWS_SECRET'] ?? $this->appflowySecret($website, 'minio-secret', 32);
        $openaiKey = $env['AI_OPENAI_API_KEY'] ?? '';

        // Optional SMTP. With autoconfirm enabled, signup works without email.
        $smtpHost = $env['SMTP_HOST'] ?? '';
        $smtpPort = $env['SMTP_PORT'] ?? '465';
        $smtpUser = $env['SMTP_USER'] ?? '';
        $smtpPass = $env['SMTP_PASS'] ?? '';
        $smtpEmail = $env['SMTP_EMAIL'] ?? $smtpUser;

        // Internal connection strings (resolved over the compose network).
        $dbUrl = "postgres://{$pgUser}:{$pgPass}@postgres:5432/{$pgDb}";
        $gotrueDbUrl = "{$dbUrl}?search_path=auth";
        $redisUri = 'redis://redis:6379';
        $minioUrl = 'http://minio:9000';

        return <<<YAML
name: {$projectName}
services:
  nginx:
    image: nginx:latest
    container_name: {$projectName}_nginx
    restart: unless-stopped
    ports:
      - '{$port}:80'
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
    depends_on:
      - appflowy_cloud
      - appflowy_web
      - gotrue
      - minio

  postgres:
    image: pgvector/pgvector:pg16
    container_name: {$projectName}_postgres
    restart: unless-stopped
    environment:
      POSTGRES_USER: {$pgUser}
      POSTGRES_PASSWORD: {$pgPass}
      POSTGRES_DB: {$pgDb}
      POSTGRES_HOST_AUTH_METHOD: trust
    volumes:
      - ./postgres:/var/lib/postgresql/data
    healthcheck:
      test: ['CMD', 'pg_isready', '-U', '{$pgUser}', '-d', '{$pgDb}']
      interval: 5s
      timeout: 5s
      retries: 12

  redis:
    image: redis:latest
    container_name: {$projectName}_redis
    restart: unless-stopped

  minio:
    image: minio/minio
    container_name: {$projectName}_minio
    restart: unless-stopped
    command: server /data --console-address ":9001"
    environment:
      - MINIO_BROWSER_REDIRECT_URL={$baseUrl}/minio
      - MINIO_ROOT_USER={$minioKey}
      - MINIO_ROOT_PASSWORD={$minioSecret}
    volumes:
      - minio_data:/data
    healthcheck:
      test: ['CMD', 'curl', '-f', 'http://localhost:9000/minio/health/live']
      interval: 30s
      timeout: 20s
      retries: 3

  gotrue:
    image: appflowyinc/gotrue:latest
    container_name: {$projectName}_gotrue
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      - GOTRUE_ADMIN_EMAIL={$adminEmail}
      - GOTRUE_ADMIN_PASSWORD={$adminPass}
      - GOTRUE_DISABLE_SIGNUP=false
      - GOTRUE_SITE_URL=appflowy-flutter://
      - GOTRUE_URI_ALLOW_LIST=**
      - GOTRUE_JWT_SECRET={$jwtSecret}
      - GOTRUE_JWT_EXP=604800
      - GOTRUE_JWT_ADMIN_GROUP_NAME=supabase_admin
      - GOTRUE_DB_DRIVER=postgres
      - API_EXTERNAL_URL={$apiExternalUrl}
      - DATABASE_URL={$gotrueDbUrl}
      - PORT=9999
      - GOTRUE_SMTP_HOST={$smtpHost}
      - GOTRUE_SMTP_PORT={$smtpPort}
      - GOTRUE_SMTP_USER={$smtpUser}
      - GOTRUE_SMTP_PASS={$smtpPass}
      - GOTRUE_SMTP_ADMIN_EMAIL={$smtpEmail}
      - GOTRUE_MAILER_URLPATHS_CONFIRMATION=/gotrue/verify
      - GOTRUE_MAILER_URLPATHS_INVITE=/gotrue/verify
      - GOTRUE_MAILER_URLPATHS_RECOVERY=/gotrue/verify
      - GOTRUE_MAILER_URLPATHS_EMAIL_CHANGE=/gotrue/verify
      - GOTRUE_MAILER_AUTOCONFIRM=true
      - GOTRUE_RATE_LIMIT_EMAIL_SENT=100
    healthcheck:
      test: 'curl --fail http://127.0.0.1:9999/health || exit 1'
      interval: 5s
      timeout: 5s
      retries: 12
      start_period: 40s

  appflowy_cloud:
    image: appflowyinc/appflowy_cloud:latest
    container_name: {$projectName}_cloud
    restart: unless-stopped
    depends_on:
      gotrue:
        condition: service_healthy
    environment:
      - RUST_LOG=info
      - APPFLOWY_ENVIRONMENT=production
      - APPFLOWY_DATABASE_URL={$dbUrl}
      - APPFLOWY_REDIS_URI={$redisUri}
      - APPFLOWY_GOTRUE_JWT_SECRET={$jwtSecret}
      - APPFLOWY_GOTRUE_BASE_URL=http://gotrue:9999
      - APPFLOWY_S3_CREATE_BUCKET=true
      - APPFLOWY_S3_USE_MINIO=true
      - APPFLOWY_S3_MINIO_URL={$minioUrl}
      - APPFLOWY_S3_ACCESS_KEY={$minioKey}
      - APPFLOWY_S3_SECRET_KEY={$minioSecret}
      - APPFLOWY_S3_BUCKET=appflowy
      - APPFLOWY_S3_REGION=us-east-1
      - APPFLOWY_S3_PRESIGNED_URL_ENDPOINT={$presignedEndpoint}
      - APPFLOWY_MAILER_SMTP_HOST={$smtpHost}
      - APPFLOWY_MAILER_SMTP_PORT={$smtpPort}
      - APPFLOWY_MAILER_SMTP_USERNAME={$smtpUser}
      - APPFLOWY_MAILER_SMTP_EMAIL={$smtpEmail}
      - APPFLOWY_MAILER_SMTP_PASSWORD={$smtpPass}
      - APPFLOWY_MAILER_SMTP_TLS_KIND=wrapper
      - APPFLOWY_ACCESS_CONTROL=true
      - APPFLOWY_DATABASE_MAX_CONNECTIONS=40
      - AI_SERVER_HOST=ai
      - AI_SERVER_PORT=5001
      - AI_OPENAI_API_KEY={$openaiKey}
      - APPFLOWY_WEB_URL={$baseUrl}
      - APPFLOWY_BASE_URL={$baseUrl}
      - APPFLOWY_SEARCH_SERVICE_URL=http://appflowy_search:4002
      - AI_ENABLED=true
    healthcheck:
      test: 'curl --fail http://127.0.0.1:8000/api/health || exit 1'
      interval: 5s
      timeout: 5s
      retries: 12

  admin_frontend:
    image: appflowyinc/admin_frontend:latest
    container_name: {$projectName}_admin
    restart: unless-stopped
    depends_on:
      gotrue:
        condition: service_healthy
      appflowy_cloud:
        condition: service_healthy
    environment:
      - APPFLOWY_GOTRUE_BASE_URL=http://gotrue:9999
      - APPFLOWY_BASE_URL=http://appflowy_cloud:8000

  appflowy_web:
    image: appflowyinc/appflowy_web:latest
    container_name: {$projectName}_web
    restart: unless-stopped
    depends_on:
      appflowy_cloud:
        condition: service_healthy
    environment:
      - APPFLOWY_BASE_URL={$baseUrl}
      - APPFLOWY_GOTRUE_BASE_URL={$baseUrl}/gotrue
      - APPFLOWY_WS_BASE_URL={$wsBaseUrl}

  ai:
    image: appflowyinc/appflowy_ai:latest
    container_name: {$projectName}_ai
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
      appflowy_cloud:
        condition: service_healthy
    environment:
      - AI_SERVER_PORT=5001
      - OPENAI_API_KEY={$openaiKey}
      - DEFAULT_AI_MODEL=gpt-4.1-mini
      - DEFAULT_AI_COMPLETION_MODEL=gpt-4.1-mini
      - APPFLOWY_S3_ACCESS_KEY={$minioKey}
      - APPFLOWY_S3_SECRET_KEY={$minioSecret}
      - APPFLOWY_S3_BUCKET=appflowy
      - APPFLOWY_S3_REGION=us-east-1
      - AI_DATABASE_URL={$dbUrl}
      - AI_REDIS_URL={$redisUri}
      - AI_USE_MINIO=true
      - AI_MINIO_URL={$minioUrl}
      - AI_APPFLOWY_HOST={$baseUrl}
      - APPFLOWY_GOTRUE_JWT_SECRET={$jwtSecret}
    healthcheck:
      test: ['CMD', 'curl', '-f', 'http://localhost:5001/health']
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  appflowy_search:
    image: appflowyinc/appflowy_search:latest
    container_name: {$projectName}_search
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      - RUST_LOG=info
      - APPFLOWY_SEARCH_HOST=[::]
      - APPFLOWY_SEARCH_PORT=4002
      - APPFLOWY_SEARCH_DATABASE_URL={$dbUrl}
      - APPFLOWY_SEARCH_REDIS_URL={$redisUri}
      - AI_OPENAI_API_KEY={$openaiKey}
      - APPFLOWY_S3_USE_MINIO=true
      - APPFLOWY_S3_MINIO_URL={$minioUrl}
      - APPFLOWY_S3_ACCESS_KEY={$minioKey}
      - APPFLOWY_S3_SECRET_KEY={$minioSecret}
      - APPFLOWY_S3_BUCKET=appflowy
      - APPFLOWY_S3_REGION=us-east-1
      - APPFLOWY_BACKGROUND_INDEXER_ENABLED=true
      - APPFLOWY_KEYWORD_SEARCH_ENABLED=true
      - APPFLOWY_KEYWORD_WORKER_ENABLED=true
      - APPFLOWY_KEYWORD_INDEX_DIR=/var/lib/appflowy/keyword_index
      - APPFLOWY_GOTRUE_JWT_SECRET={$jwtSecret}
    volumes:
      - keyword_index_data:/var/lib/appflowy/keyword_index

  appflowy_worker:
    image: appflowyinc/appflowy_worker:latest
    container_name: {$projectName}_worker
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
      appflowy_cloud:
        condition: service_healthy
    environment:
      - RUST_LOG=info
      - APPFLOWY_ENVIRONMENT=production
      - APPFLOWY_WORKER_REDIS_URL={$redisUri}
      - APPFLOWY_WORKER_ENVIRONMENT=production
      - APPFLOWY_WORKER_DATABASE_URL={$dbUrl}
      - APPFLOWY_WORKER_DATABASE_NAME={$pgDb}
      - APPFLOWY_WORKER_IMPORT_TICK_INTERVAL=30
      - APPFLOWY_S3_USE_MINIO=true
      - APPFLOWY_S3_MINIO_URL={$minioUrl}
      - APPFLOWY_S3_ACCESS_KEY={$minioKey}
      - APPFLOWY_S3_SECRET_KEY={$minioSecret}
      - APPFLOWY_S3_BUCKET=appflowy
      - APPFLOWY_S3_REGION=us-east-1
      - APPFLOWY_MAILER_SMTP_HOST={$smtpHost}
      - APPFLOWY_MAILER_SMTP_PORT={$smtpPort}
      - APPFLOWY_MAILER_SMTP_USERNAME={$smtpUser}
      - APPFLOWY_MAILER_SMTP_EMAIL={$smtpEmail}
      - APPFLOWY_MAILER_SMTP_PASSWORD={$smtpPass}
      - APPFLOWY_MAILER_SMTP_TLS_KIND=wrapper

volumes:
  minio_data:
  keyword_index_data:

YAML;
    }

    /**
     * Internal nginx config for AppFlowy. Mirrors AppFlowy-Cloud's reference
     * nginx.conf but HTTP-only (the outer hostiqo nginx terminates TLS) and with
     * the optional pgadmin block removed. This is the single entrypoint that the
     * outer per-domain proxy targets; it path-routes to each backend service.
     */
    protected function generateAppFlowyNginxConf(Website $website): string
    {
        return <<<'NGINX'
events {
    worker_connections 1024;
}

http {
    # docker dns resolver
    resolver 127.0.0.11 valid=10s;

    map $http_upgrade $connection_upgrade {
        default upgrade;
        '' close;
    }

    server {
        listen 80;
        client_max_body_size 2G;

        underscores_in_headers on;
        set $appflowy_cloud_backend "http://appflowy_cloud:8000";
        set $gotrue_backend "http://gotrue:9999";
        set $admin_frontend_backend "http://admin_frontend:3000";
        set $appflowy_web_backend "http://appflowy_web:80";
        set $minio_backend "http://minio:9001";
        set $minio_api_backend "http://minio:9000";
        set $minio_internal_host "minio:9000";

        # GoTrue
        location /gotrue/ {
            proxy_pass $gotrue_backend;
            rewrite ^/gotrue(/.*)$ $1 break;
            proxy_set_header Host $http_host;
            proxy_pass_request_headers on;
        }

        # WebSocket
        location /ws {
            proxy_pass $appflowy_cloud_backend;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_read_timeout 86400s;
        }

        location /api {
            proxy_pass $appflowy_cloud_backend;
            proxy_set_header X-Request-Id $request_id;
            proxy_set_header Host $http_host;

            location ~* ^/api/workspace/([a-zA-Z0-9_-]+)/publish$ {
                proxy_pass $appflowy_cloud_backend;
                proxy_request_buffering off;
                client_max_body_size 256M;
            }

            location /api/chat {
                proxy_pass $appflowy_cloud_backend;
                proxy_http_version 1.1;
                proxy_set_header Connection "";
                chunked_transfer_encoding on;
                proxy_buffering off;
                proxy_cache off;
                proxy_read_timeout 600s;
                proxy_connect_timeout 600s;
                proxy_send_timeout 600s;
            }

            location /api/import {
                proxy_pass $appflowy_cloud_backend;
                proxy_set_header X-Request-Id $request_id;
                proxy_set_header Host $http_host;
                proxy_read_timeout 600s;
                proxy_connect_timeout 600s;
                proxy_send_timeout 600s;
                proxy_request_buffering off;
                proxy_buffering off;
                proxy_cache off;
                client_max_body_size 2G;
            }
        }

        # MinIO Web UI
        location /minio/ {
            proxy_pass $minio_backend;
            rewrite ^/minio/(.*) /$1 break;
            proxy_set_header Host $http_host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_set_header X-NginX-Proxy true;
            real_ip_header X-Real-IP;
            proxy_connect_timeout 300s;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            chunked_transfer_encoding off;
        }

        # MinIO API (presigned URLs)
        location /minio-api/ {
            proxy_pass $minio_api_backend;
            proxy_set_header Host $minio_internal_host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            rewrite ^/minio-api/(.*) /$1 break;
            proxy_connect_timeout 300s;
            proxy_read_timeout 600s;
            proxy_send_timeout 600s;
            proxy_request_buffering off;
            proxy_http_version 1.1;
            proxy_set_header Connection "";
            chunked_transfer_encoding off;
            client_max_body_size 0;
        }

        # AI
        location /ai/ {
            proxy_pass $appflowy_cloud_backend;
            proxy_set_header X-Request-Id $request_id;
            proxy_set_header Host $http_host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # Admin Frontend
        location /console {
            proxy_pass $admin_frontend_backend;
            proxy_set_header X-Forwarded-Host $http_host;
            proxy_set_header Host $http_host;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Scheme $scheme;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection 'upgrade';
            proxy_cache_bypass $http_upgrade;
        }

        # AppFlowy Web (catch-all)
        location / {
            proxy_pass $appflowy_web_backend;
            proxy_set_header X-Scheme $scheme;
            proxy_set_header Host $host;
        }
    }
}
NGINX;
    }

    /**
     * Extra non-compose files a template needs written into its project dir,
     * keyed by path relative to the project directory.
     *
     * @return array<string, string>
     */
    protected function getTemplateExtraFiles(Website $website): array
    {
        return match($website->docker_template) {
            'appflowy' => ['nginx/nginx.conf' => $this->generateAppFlowyNginxConf($website)],
            default => [],
        };
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
                'n8n' => ['n8n-data'],
                'appflowy' => ['postgres', 'nginx'],
                default => [],
            };

            // Templates that run as non-root user inside container (need UID ownership)
            $uidOwnership = match($template) {
                'n8n' => 1000,       // n8n runs as 'node' user (UID 1000)
                'gitea' => 1000,     // gitea runs as UID 1000
                default => null,
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
                    if ($uidOwnership) {
                        Process::run("sudo /bin/chown -R {$uidOwnership}:{$uidOwnership} {$fullPath}");
                    }
                }
            }

            // Write any template-specific support files (e.g. AppFlowy's internal
            // nginx.conf). Their parent dirs were created via $subdirs above.
            foreach ($this->getTemplateExtraFiles($website) as $relativePath => $fileContent) {
                $fullPath = "{$projectDir}/{$relativePath}";

                if ($this->isLocal) {
                    File::ensureDirectoryExists(dirname($fullPath));
                    File::put($fullPath, $fileContent);
                } else {
                    Process::run('sudo /bin/mkdir -p ' . dirname($fullPath));
                    $tempExtraFile = tempnam(sys_get_temp_dir(), 'docker_extra_');
                    File::put($tempExtraFile, $fileContent);
                    Process::run("sudo /bin/cp {$tempExtraFile} {$fullPath}");
                    Process::run("sudo /bin/chmod 644 {$fullPath}");
                    @unlink($tempExtraFile);
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

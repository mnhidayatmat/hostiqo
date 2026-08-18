<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DockerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full bring-up of a Docker-template website in the background,
 * so the web request returns immediately and slow image pulls don't hit the
 * PHP-FPM / browser timeout.
 *
 * Sequence: write compose files -> pull images -> start containers -> wait until
 * healthy -> deploy the outer nginx reverse-proxy vhost -> request SSL. Each step
 * records status/errors on the website (docker_status / docker_error) so failures
 * are visible in the panel instead of being silently swallowed.
 *
 * Runs with tries=1 (a half-finished bring-up must not be blindly retried) and a
 * long timeout; retry_after in config/queue.php is kept above this timeout so the
 * job is never re-reserved mid-run.
 */
class DeployDockerProject implements ShouldQueue
{
    use Queueable;

    public $tries = 1;
    public $timeout = 1800; // 30 min — cold image pulls for large stacks

    /**
     * @param Website $website The Docker-template website to bring up.
     * @param bool $withSsl Whether to request an SSL certificate once healthy.
     */
    public function __construct(
        public Website $website,
        public bool $withSsl = false,
    ) {}

    /**
     * Prevent two overlapping bring-ups of the same project (e.g. a double
     * click, or a redeploy fired while the first is still pulling).
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('docker-deploy-' . $this->website->id))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    /**
     * Execute the job.
     *
     * @param DockerService $docker
     * @return void
     */
    public function handle(DockerService $docker): void
    {
        $website = $this->website;

        Log::info('Docker project deployment started', [
            'website_id' => $website->id,
            'domain' => $website->domain,
            'template' => $website->docker_template,
        ]);

        $website->update(['docker_status' => 'deploying', 'docker_error' => null]);

        // 1. Write docker-compose.yml (+ any template support files, e.g. AppFlowy nginx.conf).
        $compose = $docker->createComposeFile($website);
        if (empty($compose['success'])) {
            $this->fail($website, 'Compose file creation failed: ' . ($compose['error'] ?? 'unknown error'));
            return;
        }

        // 2. Pull images explicitly first, so a slow/failed pull is reported as
        //    its own step (distinct from a container that starts but crashes).
        $pull = $docker->pullImages($website);
        if (empty($pull['success'])) {
            $this->fail($website, 'Image pull failed: ' . ($pull['error'] ?? 'unknown error'));
            return;
        }

        // 3. Start the stack.
        $start = $docker->startContainers($website);
        if (empty($start['success'])) {
            // startContainers already set docker_status/docker_error.
            Log::warning('Docker containers failed to start', [
                'website_id' => $website->id,
                'error' => $start['error'] ?? 'unknown error',
            ]);
            return;
        }

        // 4. Wait until the stack is healthy before exposing it, so nginx doesn't
        //    502 and the ACME challenge isn't run against a not-yet-ready backend.
        $health = $docker->waitForHealthy($website);
        if (!$health['ready']) {
            // Not fatal — containers may just be slow. Record it and continue;
            // the reverse proxy will start serving once they finish coming up.
            $website->update([
                'docker_status' => 'running',
                'docker_error' => 'Started, but some services were not healthy in time: '
                    . implode(', ', $health['unready']),
            ]);
            Log::warning('Docker stack not fully healthy before proxy deploy', [
                'website_id' => $website->id,
                'unready' => $health['unready'],
            ]);
        }

        // 5. Deploy the outer nginx reverse-proxy vhost.
        dispatch(new DeployNginxConfig($website));

        // 6. Request SSL only once the stack is up and only when the caller asked.
        //    Chained after nginx so the vhost (and ACME challenge root) exists.
        if ($this->withSsl && $website->ssl_enabled) {
            dispatch(new RequestSslCertificate($website));
        }

        Log::info('Docker project deployment finished', [
            'website_id' => $website->id,
            'healthy' => $health['ready'],
        ]);
    }

    /**
     * Record a terminal failure on the website.
     *
     * @param Website $website
     * @param string $message
     * @return void
     */
    protected function fail(Website $website, string $message): void
    {
        $website->update(['docker_status' => 'error', 'docker_error' => $message]);
        Log::error('Docker project deployment failed', [
            'website_id' => $website->id,
            'error' => $message,
        ]);
    }

    /**
     * Handle an uncaught job failure (timeout, fatal error).
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed(\Throwable $e): void
    {
        $this->website->update([
            'docker_status' => 'error',
            'docker_error' => 'Deployment job failed: ' . $e->getMessage(),
        ]);
    }
}

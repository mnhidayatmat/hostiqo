<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Services\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDeployment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param Webhook $webhook The webhook to process
     * @param array $payload The webhook payload data
     */
    public function __construct(
        public Webhook $webhook,
        public array $payload = []
    ) {
    }

    /**
     * Execute the job.
     *
     * @param DeploymentService $deploymentService The deployment service
     * @return void
     */
    public function handle(DeploymentService $deploymentService): void
    {
        $deploymentService->deploy($this->webhook, $this->payload);
    }
}

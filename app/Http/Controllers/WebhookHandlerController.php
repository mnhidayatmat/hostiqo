<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployment;
use App\Models\Webhook;
use App\Services\DeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookHandlerController extends Controller
{
    public function __construct(
        protected DeploymentService $deploymentService
    ) {
    }

    /**
     * Handle incoming webhook from git providers.
     */
    public function handle(Request $request, int $webhook, string $token)
    {
        $webhookModel = Webhook::findOrFail($webhook);

        // Verify secret token
        if ($token !== $webhookModel->secret_token) {
            Log::warning('Invalid webhook token', [
                'webhook_id' => $webhook,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if webhook is active
        if (!$webhookModel->is_active) {
            return response()->json(['error' => 'Webhook is not active'], 403);
        }

        // Verify webhook signature based on provider
        if (!$this->verifySignature($request, $webhookModel)) {
            Log::warning('Invalid webhook signature', [
                'webhook_id' => $webhook,
                'provider' => $webhookModel->git_provider,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Parse payload based on provider
        $payload = $this->parsePayload($request, $webhookModel);

        // Dispatch deployment job
        ProcessDeployment::dispatch($webhookModel, $payload);

        Log::info('Webhook received and deployment queued', [
            'webhook_id' => $webhook,
            'provider' => $webhookModel->git_provider,
            'commit' => $payload['commit_hash'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deployment queued',
        ]);
    }

    /**
     * Verify webhook signature based on provider.
     */
    protected function verifySignature(Request $request, Webhook $webhook): bool
    {
        if ($webhook->git_provider === 'github') {
            return $this->verifyGithubSignature($request, $webhook);
        }

        if ($webhook->git_provider === 'gitlab') {
            return $this->verifyGitlabSignature($request, $webhook);
        }

        return true;
    }

    /**
     * Verify GitHub webhook signature.
     */
    protected function verifyGithubSignature(Request $request, Webhook $webhook): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return true; // Allow if no signature provided (optional)
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhook->secret_token);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify GitLab webhook signature.
     */
    protected function verifyGitlabSignature(Request $request, Webhook $webhook): bool
    {
        $token = $request->header('X-Gitlab-Token');

        if (!$token) {
            return true; // Allow if no token provided (optional)
        }

        return hash_equals($webhook->secret_token, $token);
    }

    /**
     * Parse payload based on provider.
     */
    protected function parsePayload(Request $request, Webhook $webhook): array
    {
        $payload = $request->json()->all();

        if ($webhook->git_provider === 'github') {
            return $this->deploymentService->parseGithubPayload($payload);
        }

        if ($webhook->git_provider === 'gitlab') {
            return $this->deploymentService->parseGitlabPayload($payload);
        }

        return [];
    }
}

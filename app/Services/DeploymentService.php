<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Webhook;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeploymentService
{
    public function __construct(
        protected SshKeyService $sshKeyService
    ) {
    }

    /**
     * Execute deployment for a webhook.
     *
     * @param Webhook $webhook The webhook to deploy
     * @param array $payload Optional payload data from webhook
     * @return Deployment The deployment record
     */
    public function deploy(Webhook $webhook, array $payload = []): Deployment
    {
        $deployment = Deployment::create([
            'webhook_id' => $webhook->id,
            'status' => 'processing',
            'commit_hash' => $payload['commit_hash'] ?? null,
            'commit_message' => $payload['commit_message'] ?? null,
            'author' => $payload['author'] ?? null,
            'started_at' => now(),
        ]);

        try {
            $output = $this->executeDeployment($webhook);

            $deployment->update([
                'status' => 'completed',
                'output' => $output,
                'completed_at' => now(),
            ]);

            $webhook->update(['last_deployed_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Deployment failed: ' . $e->getMessage(), [
                'webhook_id' => $webhook->id,
                'deployment_id' => $deployment->id,
            ]);

            $deployment->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $deployment;
    }

    /**
     * Prepare command to run as specific user if configured.
     *
     * @param array $command The command array
     * @param string|null $deployUser The user to run command as
     * @return array The prepared command array
     */
    protected function prepareCommandAsUser(array $command, ?string $deployUser = null): array
    {
        // If no deploy user specified, or same as current user, run as-is
        if (!$deployUser || $deployUser === get_current_user()) {
            return $command;
        }

        // Prepend sudo -u to run as different user
        return array_merge(['sudo', '-u', $deployUser], $command);
    }

    /**
     * Execute the deployment process.
     *
     * @param Webhook $webhook The webhook to deploy
     * @return string The deployment output
     * @throws \Exception If deployment fails
     */
    protected function executeDeployment(Webhook $webhook): string
    {
        // Handle Docker deployments differently
        if ($webhook->project_type === 'docker') {
            return $this->executeDockerDeployment($webhook);
        }

        return $this->executeGitDeployment($webhook);
    }

    /**
     * Execute Docker deployment.
     *
     * @param Webhook $webhook The webhook to deploy
     * @return string The deployment output
     * @throws \Exception If deployment fails
     */
    protected function executeDockerDeployment(Webhook $webhook): string
    {
        $localPath = $webhook->local_path;
        $branch = $webhook->branch;
        $deployUser = $webhook->deploy_user;
        $dockerAction = $webhook->docker_action ?? 'restart';
        $composePath = $webhook->docker_compose_path;
        $imageName = $webhook->docker_image_name;
        $output = [];

        // Setup SSH key if available
        $sshKey = $webhook->sshKey;
        $keyPath = null;
        $gitSshCommand = '';

        if ($sshKey) {
            $keyPath = $this->sshKeyService->saveTempPrivateKey($sshKey);
            $gitSshCommand = "ssh -i {$keyPath} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null";
        }

        try {
            $output[] = "=== Docker Deployment ===";
            $output[] = "Action: {$dockerAction}";

            // Pull code if repository is configured
            if ($webhook->repository_url) {
                $output[] = "\n--- Pulling Code from Git ---";

                // Check if directory exists
                if (File::isDirectory($localPath)) {
                    // Pull latest changes
                    $output[] = "Pulling latest changes...";

                    $command = $this->prepareCommandAsUser([
                        'git', 'fetch', 'origin', $branch
                    ], $deployUser);

                    $result = Process::path($localPath)
                        ->env(['GIT_SSH_COMMAND' => $gitSshCommand])
                        ->run($command);

                    $output[] = $result->output();

                    if ($result->failed()) {
                        throw new \Exception("Git fetch failed: " . $result->errorOutput());
                    }

                    // Reset to origin
                    $command = $this->prepareCommandAsUser([
                        'git',
                        'reset',
                        '--hard',
                        "origin/{$branch}",
                    ], $deployUser);

                    $result = Process::path($localPath)->run($command);

                    $output[] = $result->output();

                    if ($result->failed()) {
                        throw new \Exception("Git reset failed: " . $result->errorOutput());
                    }
                } else {
                    // Clone repository
                    $output[] = "Cloning repository...";
                    $command = $this->prepareCommandAsUser([
                        'git',
                        'clone',
                        '-b', $branch,
                        $webhook->repository_url,
                        $localPath,
                    ], $deployUser);

                    $result = Process::env([
                        'GIT_SSH_COMMAND' => $gitSshCommand,
                    ])->run($command);

                    $output[] = $result->output();

                    if ($result->failed()) {
                        throw new \Exception("Git clone failed: " . $result->errorOutput());
                    }
                }
            }

            // Run pre-deploy script
            if ($webhook->pre_deploy_script) {
                $output[] = "\n--- Running Pre-Deploy Script ---";

                $normalizedScript = str_replace(["\r\n", "\r"], "\n", $webhook->pre_deploy_script);
                $cleanedScript = implode("\n", array_map('trim', explode("\n", $normalizedScript)));

                $command = $this->prepareCommandAsUser([
                    'bash', '-c', $cleanedScript
                ], $deployUser);

                $result = Process::path($localPath)
                    ->timeout(300)
                    ->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    $output[] = "Warning: Pre-deploy script failed: " . $result->errorOutput();
                }
            }

            // Docker actions
            $output[] = "\n--- Docker Deployment ---";

            // Determine compose file path
            $composeFile = $composePath ?: ($localPath . '/docker-compose.yml');

            if (!File::exists($composeFile)) {
                throw new \Exception("Docker compose file not found: {$composeFile}");
            }

            $output[] = "Using compose file: {$composeFile}";

            // Build Docker image if action is 'build'
            if ($dockerAction === 'build') {
                $output[] = "\nBuilding Docker image...";

                $command = $this->prepareCommandAsUser([
                    'docker', 'compose', '-f', $composeFile, 'build'
                ], $deployUser);

                $result = Process::path($localPath)
                    ->timeout(600)
                    ->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    throw new \Exception("Docker build failed: " . $result->errorOutput());
                }
            }

            // Pull Docker image if action is 'pull'
            if ($dockerAction === 'pull' && $imageName) {
                $output[] = "\nPulling Docker image: {$imageName}";

                $command = $this->prepareCommandAsUser([
                    'docker', 'pull', $imageName
                ], $deployUser);

                $result = Process::run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    throw new \Exception("Docker pull failed: " . $result->errorOutput());
                }
            }

            // Down existing containers
            $output[] = "\nStopping existing containers...";

            $command = $this->prepareCommandAsUser([
                'docker', 'compose', '-f', $composeFile, 'down'
            ], $deployUser);

            $result = Process::path($localPath)
                ->timeout(120)
                ->run($command);

            $output[] = $result->output();

            // Start containers
            $output[] = "\nStarting containers...";

            $command = $this->prepareCommandAsUser([
                'docker', 'compose', '-f', $composeFile, 'up', '-d', '--build'
            ], $deployUser);

            $result = Process::path($localPath)
                ->timeout(300)
                ->run($command);

            $output[] = $result->output();

            if ($result->failed()) {
                throw new \Exception("Docker compose up failed: " . $result->errorOutput());
            }

            // Run post-deploy script
            if ($webhook->post_deploy_script) {
                $output[] = "\n--- Running Post-Deploy Script ---";

                $normalizedScript = str_replace(["\r\n", "\r"], "\n", $webhook->post_deploy_script);
                $cleanedScript = implode("\n", array_map('trim', explode("\n", $normalizedScript)));

                $command = $this->prepareCommandAsUser([
                    'bash', '-c', $cleanedScript
                ], $deployUser);

                $homeDir = $deployUser === 'www-data' ? '/var/www' : ('/home/' . $deployUser);

                $result = Process::path($localPath)
                    ->timeout(300)
                    ->env([
                        'HOME' => $homeDir,
                        'COMPOSER_HOME' => $homeDir . '/.composer',
                        'PATH' => '/usr/local/bin:/usr/bin:/bin',
                    ])
                    ->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    $output[] = "Warning: Post-deploy script failed: " . $result->errorOutput();
                }
            }

            // Show running containers
            $output[] = "\n--- Running Containers ---";
            $result = Process::run(['docker', 'compose', '-f', $composeFile, 'ps']);
            $output[] = $result->output();

            $output[] = "\n✓ Docker deployment completed successfully!";
        } finally {
            // Clean up temporary key
            if ($keyPath) {
                $this->sshKeyService->deleteTempPrivateKey($keyPath);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Execute Git-based deployment (PHP/Node.js).
     *
     * @param Webhook $webhook The webhook to deploy
     * @return string The deployment output
     * @throws \Exception If deployment fails
     */
    protected function executeGitDeployment(Webhook $webhook): string
    {
        $localPath = $webhook->local_path;
        $branch = $webhook->branch;
        $deployUser = $webhook->deploy_user;
        $output = [];

        // Setup SSH key if available
        $sshKey = $webhook->sshKey;
        $keyPath = null;
        $gitSshCommand = '';

        if ($sshKey) {
            $keyPath = $this->sshKeyService->saveTempPrivateKey($sshKey);
            $gitSshCommand = "ssh -i {$keyPath} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null";
        }

        try {
            // Log deploy user if specified
            if ($deployUser) {
                $output[] = "Running deployment as user: {$deployUser}\n";
            }

            // Check if directory exists
            if (File::isDirectory($localPath)) {
                // Check if it's a git repository
                if (!File::isDirectory($localPath . '/.git')) {
                    // Directory exists but not a git repo - check if empty
                    $files = File::allFiles($localPath);
                    $directories = File::directories($localPath);
                    
                    if (empty($files) && empty($directories)) {
                        // Empty directory - delete it so we can clone fresh
                        $output[] = "Removing empty directory: {$localPath}";
                        $result = Process::run(['sudo', 'rm', '-rf', $localPath]);
                        
                        if ($result->failed()) {
                            throw new \Exception("Failed to remove directory: " . $result->errorOutput());
                        }
                    } else {
                        throw new \Exception("Directory {$localPath} exists but is not a git repository and contains files. Please remove it manually.");
                    }
                }
            }
            
            // Clone or pull repository
            if (!File::isDirectory($localPath)) {
                // Clone repository
                $output[] = "Cloning repository...";
                $command = $this->prepareCommandAsUser([
                    'git',
                    'clone',
                    '-b', $branch,
                    $webhook->repository_url,
                    $localPath,
                ], $deployUser);

                $result = Process::env([
                    'GIT_SSH_COMMAND' => $gitSshCommand,
                ])->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    throw new \Exception("Git clone failed: " . $result->errorOutput());
                }
            } else {
                // Pull latest changes
                $output[] = "Pulling latest changes...";

                // Fetch
                $command = $this->prepareCommandAsUser([
                    'git', 'fetch', 'origin', $branch
                ], $deployUser);

                $result = Process::path($localPath)
                    ->env(['GIT_SSH_COMMAND' => $gitSshCommand])
                    ->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    throw new \Exception("Git fetch failed: " . $result->errorOutput());
                }

                // Reset to origin
                $command = $this->prepareCommandAsUser([
                    'git',
                    'reset',
                    '--hard',
                    "origin/{$branch}",
                ], $deployUser);

                $result = Process::path($localPath)->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    throw new \Exception("Git reset failed: " . $result->errorOutput());
                }
            }

            // Run pre-deploy script
            if ($webhook->pre_deploy_script) {
                $output[] = "\nRunning pre-deploy script...";
                
                // Normalize line endings and trim each line to remove trailing spaces
                $normalizedScript = str_replace(["\r\n", "\r"], "\n", $webhook->pre_deploy_script);
                $cleanedScript = implode("\n", array_map('trim', explode("\n", $normalizedScript)));
                
                $command = $this->prepareCommandAsUser([
                    'bash', '-c', $cleanedScript
                ], $deployUser);

                $result = Process::path($localPath)
                    ->timeout(300)
                    ->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    $output[] = "Warning: Pre-deploy script failed: " . $result->errorOutput();
                }
            }

            // Run post-deploy script
            if ($webhook->post_deploy_script) {
                $output[] = "\nRunning post-deploy script...";
                
                // Normalize line endings and trim each line to remove trailing spaces
                $normalizedScript = str_replace(["\r\n", "\r"], "\n", $webhook->post_deploy_script);
                $cleanedScript = implode("\n", array_map('trim', explode("\n", $normalizedScript)));
                
                $command = $this->prepareCommandAsUser([
                    'bash', '-c', $cleanedScript
                ], $deployUser);

                // Set required environment variables for Composer and other tools
                $homeDir = $deployUser === 'www-data' ? '/var/www' : ('/home/' . $deployUser);
                
                $result = Process::path($localPath)
                    ->timeout(300)
                    ->env([
                        'HOME' => $homeDir,
                        'COMPOSER_HOME' => $homeDir . '/.composer',
                        'PATH' => '/usr/local/bin:/usr/bin:/bin',
                    ])
                    ->run($command);

                $output[] = $result->output();

                if ($result->failed()) {
                    $output[] = "Warning: Post-deploy script failed: " . $result->errorOutput();
                }
            }

            $output[] = "\n✓ Deployment completed successfully!";
        } finally {
            // Clean up temporary key
            if ($keyPath) {
                $this->sshKeyService->deleteTempPrivateKey($keyPath);
            }
        }

        return implode("\n", $output);
    }

    /**
     * Parse GitHub webhook payload.
     *
     * @param array $payload The GitHub webhook payload
     * @return array{commit_hash: string|null, commit_message: string|null, author: string|null}
     */
    public function parseGithubPayload(array $payload): array
    {
        return [
            'commit_hash' => $payload['after'] ?? null,
            'commit_message' => $payload['head_commit']['message'] ?? null,
            'author' => $payload['head_commit']['author']['name'] ?? null,
        ];
    }

    /**
     * Parse GitLab webhook payload.
     *
     * @param array $payload The GitLab webhook payload
     * @return array{commit_hash: string|null, commit_message: string|null, author: string|null}
     */
    public function parseGitlabPayload(array $payload): array
    {
        return [
            'commit_hash' => $payload['checkout_sha'] ?? $payload['after'] ?? null,
            'commit_message' => $payload['commits'][0]['message'] ?? null,
            'author' => $payload['commits'][0]['author']['name'] ?? $payload['user_name'] ?? null,
        ];
    }
}

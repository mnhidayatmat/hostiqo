<?php

namespace App\Services;

use App\Models\SshKey;
use App\Models\Webhook;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class SshKeyService
{
    /**
     * Generate SSH key pair for a webhook.
     *
     * @param Webhook $webhook The webhook to generate key pair for
     * @return SshKey The created SSH key model
     */
    public function generateKeyPair(Webhook $webhook): SshKey
    {
        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $keyName = 'webhook_' . $webhook->id . '_' . Str::random(8);
        $keyPath = $tempDir . '/' . $keyName;

        // Generate SSH key pair using ssh-keygen
        Process::run([
            'ssh-keygen',
            '-t', 'ed25519',
            '-f', $keyPath,
            '-N', '', // No passphrase
            '-C', "webhook_{$webhook->id}@hostiqo",
        ]);

        $publicKey = File::get($keyPath . '.pub');
        $privateKey = File::get($keyPath);

        // Get fingerprint
        $fingerprintOutput = Process::run([
            'ssh-keygen',
            '-lf',
            $keyPath . '.pub',
        ]);

        $fingerprint = $this->extractFingerprint($fingerprintOutput->output());

        // Clean up temporary files
        File::delete($keyPath);
        File::delete($keyPath . '.pub');

        // Delete existing SSH key if any
        $webhook->sshKey?->delete();

        // Create new SSH key record
        return SshKey::create([
            'webhook_id' => $webhook->id,
            'public_key' => trim($publicKey),
            'private_key' => $privateKey,
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * Extract fingerprint from ssh-keygen output.
     *
     * @param string $output The ssh-keygen output
     * @return string|null The extracted fingerprint or null
     */
    protected function extractFingerprint(string $output): ?string
    {
        if (preg_match('/SHA256:([^\s]+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Save private key to temporary file for git operations.
     *
     * @param SshKey $sshKey The SSH key model
     * @return string The path to the temporary key file
     */
    public function saveTempPrivateKey(SshKey $sshKey): string
    {
        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $keyPath = $tempDir . '/temp_key_' . $sshKey->webhook_id . '_' . time();
        File::put($keyPath, $sshKey->private_key);
        chmod($keyPath, 0600);

        return $keyPath;
    }

    /**
     * Delete temporary private key file.
     *
     * @param string $keyPath The path to the temporary key file
     * @return void
     */
    public function deleteTempPrivateKey(string $keyPath): void
    {
        if (File::exists($keyPath)) {
            File::delete($keyPath);
        }
    }
}

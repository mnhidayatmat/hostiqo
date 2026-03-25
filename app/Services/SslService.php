<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SslService
{
    /**
     * Request SSL certificate using certbot.
     *
     * @param Website $website The website to request certificate for
     * @return array{success: bool, message?: string, error?: string}
     */
    public function requestCertificate(Website $website): array
    {
        try {
            $domain = $website->domain;

            // For Docker projects, use root_path directory for ACME challenge (not the Docker project dir)
            // For PHP/Node projects, use root_path + working_directory
            if ($website->project_type === 'docker') {
                $webroot = $website->root_path;
            } else {
                $webroot = rtrim($website->root_path, '/') . '/' . ltrim($website->working_directory ?? '/', '/');
            }
            $webroot = rtrim($webroot, '/');

            // Ensure webroot and .well-known/acme-challenge directory exists with proper permissions
            Process::run("sudo /bin/mkdir -p {$webroot}");
            $acmeDir = $webroot . '/.well-known/acme-challenge';
            Process::run("sudo /bin/mkdir -p {$acmeDir}");
            Process::run("sudo /bin/chmod 755 {$webroot}/.well-known");
            Process::run("sudo /bin/chmod 755 {$acmeDir}");
            Process::run("sudo /bin/chown -R www-data:www-data {$webroot}/.well-known");

            // Build domain list - only add www if www_redirect is configured
            $domains = [$domain];
            if ($website->www_redirect === 'to_www') {
                $domains[] = "www.{$domain}";
            }
            
            $domainArgs = implode(' -d ', $domains);

            // Use certbot with webroot plugin
            $command = "sudo /usr/bin/certbot certonly --webroot -w {$webroot} -d {$domainArgs} --non-interactive --agree-tos --email admin@{$domain} --expand";
            
            Log::info('Requesting SSL certificate', [
                'domain' => $domain,
                'webroot' => $webroot,
                'domains' => $domains,
                'command' => $command
            ]);
            
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info('SSL certificate obtained successfully', [
                    'domain' => $domain,
                    'output' => $result->output()
                ]);

                return [
                    'success' => true,
                    'message' => 'SSL certificate obtained successfully',
                ];
            }

            Log::error('SSL certificate request failed', [
                'domain' => $domain,
                'error' => $result->errorOutput(),
                'output' => $result->output()
            ]);

            return [
                'success' => false,
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to request SSL certificate', [
                'domain' => $website->domain,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete SSL certificate.
     *
     * @param Website $website The website whose certificate to delete
     * @return array{success: bool, message?: string, error?: string}
     */
    public function deleteCertificate(Website $website): array
    {
        try {
            $domain = $website->domain;
            
            $result = Process::run("sudo /usr/bin/certbot delete --cert-name {$domain} --non-interactive");

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'SSL certificate deleted' : 'Failed to delete certificate',
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Renew all SSL certificates.
     *
     * @return array{success: bool, output?: string, error?: string}
     */
    public function renewCertificates(): array
    {
        try {
            $result = Process::run('sudo /usr/bin/certbot renew --non-interactive');

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

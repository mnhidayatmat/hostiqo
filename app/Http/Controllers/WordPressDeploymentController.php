<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\WordPressInstallerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WordPressDeploymentController extends Controller
{
    protected WordPressInstallerService $wordpressInstaller;

    public function __construct(WordPressInstallerService $wordpressInstaller)
    {
        $this->wordpressInstaller = $wordpressInstaller;
    }

    /**
     * Show WordPress installation form
     */
    public function show(Website $website)
    {
        // Only allow for PHP projects
        if ($website->project_type !== 'php') {
            return redirect()
                ->route('websites.show', $website)
                ->with('error', 'WordPress installation is only available for PHP projects');
        }

        return view('websites.wordpress-deploy', compact('website'));
    }

    /**
     * Install WordPress
     */
    public function install(Request $request, Website $website)
    {
        // Validate project type
        if ($website->project_type !== 'php') {
            return response()->json([
                'success' => false,
                'message' => 'WordPress can only be installed on PHP projects'
            ], 400);
        }

        // Validate request
        $validated = $request->validate([
            'db_name' => 'required|string|max:64|regex:/^[a-zA-Z0-9_]+$/',
            'db_user' => 'required|string|max:32|regex:/^[a-zA-Z0-9_]+$/',
            'db_password' => 'required|string|min:8',
            'db_host' => 'nullable|string|max:255',
            'db_prefix' => 'nullable|string|max:20|regex:/^[a-zA-Z0-9_]+$/',
            'admin_user' => 'required|string|max:60|regex:/^[a-zA-Z0-9_]+$/',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email|max:100',
            'site_title' => 'required|string|max:255',
            'enable_cache' => 'boolean',
            'install_plugins' => 'boolean',
        ]);

        try {
            // Install WordPress
            $result = $this->wordpressInstaller->install(
                website: $website,
                dbName: $validated['db_name'],
                dbUser: $validated['db_user'],
                dbPassword: $validated['db_password'],
                dbHost: $validated['db_host'] ?? 'localhost',
                dbPrefix: $validated['db_prefix'] ?? 'wp_',
                adminUser: $validated['admin_user'],
                adminPassword: $validated['admin_password'],
                adminEmail: $validated['admin_email'],
                siteTitle: $validated['site_title'],
                enableCache: $validated['enable_cache'] ?? true,
                installPlugins: $validated['install_plugins'] ?? true,
            );

            if ($result['success']) {
                // Update website status and mark as WordPress
                $website->update([
                    'status' => 'active',
                    'framework' => 'wordpress',
                    'notes' => 'WordPress installed via 1-Click Deployment'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'WordPress installed successfully!',
                    'data' => $result
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Installation failed',
                'errors' => $result['errors'] ?? []
            ], 500);

        } catch (\Exception $e) {
            Log::error('WordPress installation failed', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check installation status
     */
    public function checkStatus(Website $website)
    {
        $status = $this->wordpressInstaller->checkInstallation($website);

        return response()->json($status);
    }
}

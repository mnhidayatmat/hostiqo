<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ArtisanController extends Controller
{
    /**
     * Display artisan commands interface
     */
    public function index()
    {
        return view('artisan.index');
    }

    /**
     * Run optimize command
     */
    public function optimize()
    {
        try {
            $exitCode = Artisan::call('optimize');
            $output = Artisan::output();
            
            Log::info('Artisan optimize executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            $message = 'Application optimized successfully!';
            if (!empty(trim($output))) {
                $message .= ' Check output below for details.';
            }
            
            return redirect()->route('artisan.index')
                ->with('success', $message)
                ->with('output', $output ?: 'Command executed successfully with no output.');
                
        } catch (\Exception $e) {
            Log::error('Artisan optimize failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Optimization failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear all caches
     */
    public function cacheClear()
    {
        try {
            $exitCode = Artisan::call('cache:clear');
            $output = Artisan::output();
            
            Log::info('Artisan cache:clear executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Application cache cleared successfully!')
                ->with('output', $output ?: 'Cache cleared successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan cache:clear failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Cache clear failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear config cache
     */
    public function configClear()
    {
        try {
            $exitCode = Artisan::call('config:clear');
            $output = Artisan::output();
            
            Log::info('Artisan config:clear executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Configuration cache cleared successfully!')
                ->with('output', $output ?: 'Config cache cleared successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan config:clear failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Config clear failed: ' . $e->getMessage());
        }
    }

    /**
     * Cache config
     */
    public function configCache()
    {
        try {
            $exitCode = Artisan::call('config:cache');
            $output = Artisan::output();
            
            Log::info('Artisan config:cache executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Configuration cached successfully!')
                ->with('output', $output ?: 'Config cached successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan config:cache failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Config cache failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear route cache
     */
    public function routeClear()
    {
        try {
            $exitCode = Artisan::call('route:clear');
            $output = Artisan::output();
            
            Log::info('Artisan route:clear executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Route cache cleared successfully!')
                ->with('output', $output ?: 'Route cache cleared successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan route:clear failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Route clear failed: ' . $e->getMessage());
        }
    }

    /**
     * Cache routes
     */
    public function routeCache()
    {
        try {
            $exitCode = Artisan::call('route:cache');
            $output = Artisan::output();
            
            Log::info('Artisan route:cache executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Routes cached successfully!')
                ->with('output', $output ?: 'Routes cached successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan route:cache failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Route cache failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear view cache
     */
    public function viewClear()
    {
        try {
            $exitCode = Artisan::call('view:clear');
            $output = Artisan::output();
            
            Log::info('Artisan view:clear executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Compiled views cleared successfully!')
                ->with('output', $output ?: 'View cache cleared successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan view:clear failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'View clear failed: ' . $e->getMessage());
        }
    }

    /**
     * Cache views
     */
    public function viewCache()
    {
        try {
            $exitCode = Artisan::call('view:cache');
            $output = Artisan::output();
            
            Log::info('Artisan view:cache executed', [
                'exit_code' => $exitCode,
                'output' => $output
            ]);
            
            return redirect()->route('artisan.index')
                ->with('success', 'Views cached successfully!')
                ->with('output', $output ?: 'Views cached successfully.');
                
        } catch (\Exception $e) {
            Log::error('Artisan view:cache failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'View cache failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear all caches at once
     */
    public function clearAll()
    {
        try {
            $commands = [
                'cache:clear',
                'config:clear',
                'route:clear',
                'view:clear',
            ];

            $outputs = [];
            foreach ($commands as $command) {
                $exitCode = Artisan::call($command);
                $output = trim(Artisan::output());
                $outputs[] = $command . ': ' . ($output ?: 'Success');
            }
            
            Log::info('Artisan clear all executed');
            
            return redirect()->route('artisan.index')
                ->with('success', 'All caches cleared successfully!')
                ->with('output', implode("\n", $outputs));
                
        } catch (\Exception $e) {
            Log::error('Artisan clear all failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Clear all failed: ' . $e->getMessage());
        }
    }

    /**
     * Optimize for production
     */
    public function optimizeProduction()
    {
        try {
            $commands = [
                'config:cache',
                'route:cache',
                'view:cache',
                'optimize',
            ];

            $outputs = [];
            foreach ($commands as $command) {
                $exitCode = Artisan::call($command);
                $output = trim(Artisan::output());
                $outputs[] = $command . ': ' . ($output ?: 'Success');
            }
            
            Log::info('Artisan optimize production executed');
            
            return redirect()->route('artisan.index')
                ->with('success', 'Application optimized for production!')
                ->with('output', implode("\n", $outputs));
                
        } catch (\Exception $e) {
            Log::error('Artisan optimize production failed', ['error' => $e->getMessage()]);
            return redirect()->route('artisan.index')
                ->with('error', 'Production optimization failed: ' . $e->getMessage());
        }
    }
}

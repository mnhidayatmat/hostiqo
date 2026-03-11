<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Simple ping endpoint for uptime monitoring.
     * Returns plain text "pong" response.
     */
    public function ping(): string
    {
        return 'pong';
    }

    /**
     * Health check endpoint returning system status.
     * Checks database, cache, and queue connectivity.
     */
    public function health(): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'app' => $this->checkApp(),
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'redis' => $this->checkRedis(),
            ],
        ];

        // Determine overall status
        $allHealthy = collect($checks['services'])->every(fn($service) => $service['status'] === 'ok');
        $checks['status'] = $allHealthy ? 'ok' : 'degraded';

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    /**
     * Check application status.
     */
    private function checkApp(): array
    {
        return [
            'status' => 'ok',
            'version' => config('app.version', '1.0.0'),
        ];
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            
            return [
                'status' => 'ok',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, true, 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === true) {
                return [
                    'status' => 'ok',
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Cache read/write failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache connection failed',
            ];
        }
    }

    /**
     * Check Redis connectivity.
     */
    private function checkRedis(): array
    {
        try {
            if (config('database.redis.client') === 'phpredis' || config('database.redis.client') === 'predis') {
                Redis::ping();
                
                return [
                    'status' => 'ok',
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'Redis not configured',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Redis connection failed',
            ];
        }
    }
}

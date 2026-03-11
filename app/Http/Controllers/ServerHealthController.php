<?php

namespace App\Http\Controllers;

use App\Models\SystemMetric;
use App\Services\SystemMonitorService;
use Illuminate\Http\Request;

class ServerHealthController extends Controller
{
    /**
     * Display server health metrics.
     */
    public function index(Request $request)
    {
        // Get hours filter from request, default to config value
        $chartHours = $request->get('hours', config('monitoring.chart_hours', 6));
        
        // Validate hours (only allow specific values)
        $allowedHours = [1, 3, 6, 12];
        if (!in_array($chartHours, $allowedHours)) {
            $chartHours = 6; // Default to 6 if invalid
        }
        
        $systemMetrics = SystemMetric::getRecentMetrics($chartHours);
        $latestMetric = SystemMetric::getLatest();
        $cpuCores = app(SystemMonitorService::class)->getCpuCores();

        return view('server-health', compact(
            'systemMetrics',
            'latestMetric',
            'cpuCores',
            'chartHours'
        ));
    }
}

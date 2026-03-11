<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertRule;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Display all alerts and alert rules
     */
    public function index()
    {
        $alerts = Alert::with('alertRule')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $alertRules = AlertRule::orderBy('created_at', 'desc')->get();

        return view('alerts.index', compact('alerts', 'alertRules'));
    }

    /**
     * Show create alert rule form
     */
    public function create()
    {
        return view('alerts.create');
    }

    /**
     * Store a new alert rule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'metric' => 'required|in:cpu,memory,disk,service',
            'condition' => 'required|in:>,<,==,!=',
            'threshold' => 'nullable|numeric',
            'service_name' => 'nullable|string|max:255',
            'duration' => 'required|integer|min:1',
            'channel' => 'required|in:email,slack,both',
            'email' => 'nullable|email',
            'slack_webhook' => 'nullable|url',
        ]);

        AlertRule::create($validated);

        return redirect()->route('alerts.index')
            ->with('success', 'Alert rule created successfully');
    }

    /**
     * Show edit alert rule form
     */
    public function edit(AlertRule $alertRule)
    {
        return view('alerts.edit', compact('alertRule'));
    }

    /**
     * Update alert rule
     */
    public function update(Request $request, AlertRule $alertRule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'metric' => 'required|in:cpu,memory,disk,service',
            'condition' => 'required|in:>,<,==,!=',
            'threshold' => 'nullable|numeric',
            'service_name' => 'nullable|string|max:255',
            'duration' => 'required|integer|min:1',
            'channel' => 'required|in:email,slack,both',
            'email' => 'nullable|email',
            'slack_webhook' => 'nullable|url',
        ]);

        $alertRule->update($validated);

        return redirect()->route('alerts.index')
            ->with('success', 'Alert rule updated successfully');
    }

    /**
     * Delete alert rule
     */
    public function destroy(AlertRule $alertRule)
    {
        $alertRule->delete();

        return back()->with('success', 'Alert rule deleted successfully');
    }

    /**
     * Toggle alert rule active status
     */
    public function toggle(AlertRule $alertRule)
    {
        $alertRule->update([
            'is_active' => !$alertRule->is_active
        ]);

        return back()->with('success', 'Alert rule status updated');
    }

    /**
     * Resolve an alert
     */
    public function resolve(Alert $alert)
    {
        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Alert resolved');
    }

    /**
     * Delete an alert
     */
    public function deleteAlert(Alert $alert)
    {
        $alert->delete();

        return back()->with('success', 'Alert deleted');
    }
}

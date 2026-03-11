<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployment;
use App\Models\Deployment;
use App\Models\Webhook;

class DeploymentController extends Controller
{
    /**
     * Display a listing of the deployments.
     */
    public function index()
    {
        $deployments = Deployment::with('webhook')
            ->latest()
            ->paginate(20);

        return view('deployments.index', compact('deployments'));
    }

    /**
     * Display the specified deployment.
     */
    public function show(Deployment $deployment)
    {
        $deployment->load('webhook');

        return view('deployments.show', compact('deployment'));
    }

    /**
     * Trigger manual deployment for a webhook.
     */
    public function trigger(Webhook $webhook)
    {
        if (!$webhook->is_active) {
            return redirect()
                ->back()
                ->with('error', 'Webhook is not active!');
        }

        ProcessDeployment::dispatch($webhook);

        return redirect()
            ->route('webhooks.show', $webhook)
            ->with('success', 'Deployment queued successfully!');
    }
}

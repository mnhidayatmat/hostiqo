<?php

namespace App\Http\Controllers;

use App\Contracts\ServiceManagerInterface;
use Illuminate\Http\Request;
use Exception;

class ServiceManagerController extends Controller
{
    public function __construct(
        protected ServiceManagerInterface $serviceManager
    ) {}

    /**
     * Service manager index.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        try {
            $services = $this->serviceManager->getAvailableServices();

            return view('services.index', compact('services'));

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get service status (AJAX).
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        $service = $request->input('service');

        if (!$service) {
            return response()->json(['error' => 'Service name is required'], 400);
        }

        try {
            $status = $this->serviceManager->getServiceStatus($service);

            return response()->json($status);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start service.
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function start(Request $request)
    {
        $service = $request->input('service');

        if (!$service) {
            return back()->with('error', 'Service name is required');
        }

        try {
            $result = $this->serviceManager->startService($service);

            if ($result['success']) {
                return back()->with('success', $result['message']);
            } else {
                return back()->with('error', $result['message']);
            }

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Stop service.
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function stop(Request $request)
    {
        $service = $request->input('service');

        if (!$service) {
            return back()->with('error', 'Service name is required');
        }

        try {
            $result = $this->serviceManager->stopService($service);

            if ($result['success']) {
                return back()->with('success', $result['message']);
            } else {
                return back()->with('error', $result['message']);
            }

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Restart service.
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restart(Request $request)
    {
        $service = $request->input('service');

        if (!$service) {
            return back()->with('error', 'Service name is required');
        }

        try {
            $result = $this->serviceManager->restartService($service);

            if ($result['success']) {
                return back()->with('success', $result['message']);
            } else {
                return back()->with('error', $result['message']);
            }

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reload service.
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reload(Request $request)
    {
        $service = $request->input('service');

        if (!$service) {
            return back()->with('error', 'Service name is required');
        }

        try {
            $result = $this->serviceManager->reloadService($service);

            if ($result['success']) {
                return back()->with('success', $result['message']);
            } else {
                return back()->with('error', $result['message']);
            }

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * View service logs.
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function logs(Request $request)
    {
        $service = $request->input('service');
        $lines = $request->input('lines', 100);

        if (!$service) {
            return back()->with('error', 'Service name is required');
        }

        try {
            $logs = $this->serviceManager->getServiceLogs($service, $lines);

            return view('services.logs', compact('service', 'logs'));

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Services\DatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {
    }

    /**
     * Clear permission cache and recheck.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function recheckPermissions()
    {
        $this->databaseService->clearAllPermissionCaches();

        return redirect()
            ->route('databases.index')
            ->with('success', 'Permissions rechecked successfully!');
    }

    /**
     * Display a listing of the databases.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $databases = Database::latest()->paginate(15);

        // Enhance database records with real-time info
        foreach ($databases as $database) {
            $service = $database->getService();
            $dbList = $service->listDatabases();
            $database->exists_in_server = in_array($database->name, $dbList);

            if ($database->exists_in_server) {
                $database->size_mb = $service->getDatabaseSize($database->name);
                $database->table_count = $service->getTableCount($database->name);
            }
        }

        // Check permissions for both database types
        $permissions = [
            'mysql' => $this->databaseService->mysql()->canCreateDatabase(),
            'postgresql' => $this->databaseService->postgresql()->canCreateDatabase(),
        ];

        return view('databases.index', compact('databases', 'permissions'));
    }

    /**
     * Show the form for creating a new database.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        // Check permissions for both database types
        $permissions = [
            'mysql' => $this->databaseService->mysql()->canCreateDatabase(),
            'postgresql' => $this->databaseService->postgresql()->canCreateDatabase(),
        ];

        return view('databases.create', compact('permissions'));
    }

    /**
     * Store a newly created database in storage.
     *
     * @param Request $request The HTTP request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:mysql,postgresql'],
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:databases,name'],
            'username' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'host' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $host = $validated['host'] ?? 'localhost';
        $type = $validated['type'];

        // Get the appropriate service
        $service = $this->databaseService->connection($type);

        try {
            // Check permissions
            $permissions = $service->canCreateDatabase();
            if (!$permissions['can_create']) {
                return back()
                    ->withInput()
                    ->withErrors(['permission' => $permissions['message']]);
            }

            // Check if database already exists
            if ($service->databaseExists($validated['name'])) {
                $typeName = $type === 'postgresql' ? 'PostgreSQL' : 'MySQL';
                return back()
                    ->withInput()
                    ->withErrors(['name' => "Database already exists in {$typeName}."]);
            }

            // Create database and user
            $service->createDatabase(
                $validated['name'],
                $validated['username'],
                $validated['password'],
                $host
            );

            // Save to tracking table
            $database = Database::create([
                'type' => $type,
                'name' => $validated['name'],
                'username' => $validated['username'],
                'host' => $host,
                'description' => $validated['description'] ?? null,
            ]);

            return redirect()
                ->route('databases.index')
                ->with('success', 'Database and user created successfully!');
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create database: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified database.
     *
     * @param Database $database The database model
     * @return \Illuminate\View\View
     */
    public function show(Database $database)
    {
        $service = $database->getService();
        $database->exists_in_server = $service->databaseExists($database->name);

        if ($database->exists_in_server) {
            $database->size_mb = $service->getDatabaseSize($database->name);
            $database->table_count = $service->getTableCount($database->name);
        }

        return view('databases.show', compact('database'));
    }

    /**
     * Show the form for editing the specified database.
     *
     * @param Database $database The database model
     * @return \Illuminate\View\View
     */
    public function edit(Database $database)
    {
        return view('databases.edit', compact('database'));
    }

    /**
     * Update the specified database in storage (metadata only).
     *
     * @param Request $request The HTTP request
     * @param Database $database The database model
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Database $database)
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $database->update($validated);

        return redirect()
            ->route('databases.show', $database)
            ->with('success', 'Database information updated successfully!');
    }

    /**
     * Show form to change user password.
     *
     * @param Database $database The database model
     * @return \Illuminate\View\View
     */
    public function showChangePasswordForm(Database $database)
    {
        return view('databases.change-password', compact('database'));
    }

    /**
     * Change password for database user.
     *
     * @param Request $request The HTTP request
     * @param Database $database The database model
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changePassword(Request $request, Database $database)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $service = $database->getService();
            $service->changeUserPassword(
                $database->username,
                $validated['password'],
                $database->host
            );

            return redirect()
                ->route('databases.show', $database)
                ->with('success', 'Password changed successfully!');
        } catch (Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to change password: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified database from storage.
     *
     * @param Database $database The database model
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Database $database)
    {
        try {
            $service = $database->getService();

            // Delete database from server
            if ($service->databaseExists($database->name)) {
                $service->deleteDatabase($database->name);
            }

            // Delete user from server
            if ($service->userExists($database->username, $database->host)) {
                $service->deleteUser($database->username, $database->host);
            }

            // Delete from tracking table
            $database->delete();

            return redirect()
                ->route('databases.index')
                ->with('success', 'Database and user deleted successfully!');
        } catch (Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete database: ' . $e->getMessage()]);
        }
    }
}

@extends('layouts.app')

@section('title', 'Create Database - Hostiqo')
@section('page-title', 'Create New Database')
@section('page-description', 'Create a new MySQL or PostgreSQL database and user')

@section('page-actions')
    <a href="{{ route('databases.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Databases
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <!-- Permission Status -->
            @if(isset($permissions))
                @foreach($permissions as $type => $perm)
                    @if($perm['can_create'])
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>{{ ucfirst($type) }} Permissions Verified:</strong> You have all required privileges to create {{ $type }} databases.
                            @if(isset($perm['current_user']))
                                <br>
                                <small class="mt-1 d-block">
                                    <strong>{{ ucfirst($type) }} User:</strong> <code>{{ $perm['current_user'] }}</code>
                                </small>
                            @endif
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                @endforeach
            @endif

            <form action="{{ route('databases.store') }}" method="POST">
                @csrf

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-database me-2"></i> Database Configuration
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="type" class="form-label">
                                Database Type <span class="text-danger">*</span>
                            </label>
                            <select
                                class="form-select @error('type') is-invalid @enderror"
                                id="type"
                                name="type"
                                required
                                onchange="updateDatabaseTypeUI()"
                            >
                                <option value="mysql" selected>MySQL</option>
                                @if(!($permissions['postgresql']['not_available'] ?? false))
                                    <option value="postgresql">PostgreSQL</option>
                                @endif
                            </select>
                            <div class="form-text">
                                Choose the database engine type.
                                @if($permissions['postgresql']['not_available'] ?? false)
                                    <span class="text-warning">PostgreSQL is not available on this server.</span>
                                @endif
                            </div>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Database Name <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control font-monospace @error('name') is-invalid @enderror"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                placeholder="my_database"
                                pattern="[a-zA-Z0-9_]+"
                            >
                            <div class="form-text">Only letters, numbers, and underscores allowed. No spaces or special characters.</div>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">
                                Database Username <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control font-monospace @error('username') is-invalid @enderror"
                                id="username"
                                name="username"
                                value="{{ old('username') }}"
                                required
                                placeholder="db_user"
                                pattern="[a-zA-Z0-9_]+"
                            >
                            <div class="form-text">Username for the database user. Only letters, numbers, and underscores.</div>
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Password <span class="text-danger">*</span>
                            </label>
                            <input
                                type="password"
                                class="form-control @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                required
                                minlength="8"
                            >
                            <div class="form-text">Minimum 8 characters. Use a strong password.</div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                minlength="8"
                            >
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-hdd-network me-2"></i> Connection Settings
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="host" class="form-label">Host</label>
                            <input
                                type="text"
                                class="form-control @error('host') is-invalid @enderror"
                                id="host"
                                name="host"
                                value="{{ old('host', 'localhost') }}"
                                placeholder="localhost"
                            >
                            <div class="form-text">Default is 'localhost'. Use '%' for any host (not recommended for security).</div>
                            @error('host')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea
                                class="form-control @error('description') is-invalid @enderror"
                                id="description"
                                name="description"
                                rows="3"
                                placeholder="Optional description for this database"
                            >{{ old('description') }}</textarea>
                            <div class="form-text">Optional description for this database</div>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="alert alert-info" id="mysql-note">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> This will create a new MySQL database and user with full privileges on the database.
                    Make sure to save the credentials securely.
                </div>

                <div class="alert alert-info d-none" id="postgresql-note">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> This will create a new PostgreSQL database and role with full privileges on the database.
                    PostgreSQL uses UTF8 encoding by default. Make sure to save the credentials securely.
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Create Database
                    </button>
                    <a href="{{ route('databases.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-header">
                    <i class="bi bi-lightbulb me-2"></i> Quick Tips
                </div>
                <div class="card-body">
                    <h6>Database Type Selection</h6>
                    <p class="small">Choose <strong>MySQL</strong> for traditional web applications. Choose <strong>PostgreSQL</strong> for advanced features like JSONB, full-text search, and complex queries.</p>

                    <h6 class="mt-3">Database Naming</h6>
                    <p class="small">Use descriptive names like <code>projectname_db</code> or <code>app_production</code> to easily identify databases.</p>

                    <h6 class="mt-3">Strong Passwords</h6>
                    <p class="small">Use a mix of uppercase, lowercase, numbers, and special characters. Consider using a password generator.</p>

                    <h6 class="mt-3">Host Access</h6>
                    <p class="small">Use <code>localhost</code> for local access only (most secure). Use specific IPs for remote access instead of <code>%</code> (any host).</p>

                    <h6 class="mt-3">User Privileges</h6>
                    <p class="small">The created user/role will have FULL privileges (SELECT, INSERT, UPDATE, DELETE, etc.) on the database only.</p>

                    <h6 class="mt-3">Credentials Storage</h6>
                    <p class="small">Save the database credentials securely. Store them in your application's <code>.env</code> file and never commit to version control.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateDatabaseTypeUI() {
            const type = document.getElementById('type').value;
            const mysqlNote = document.getElementById('mysql-note');
            const postgresqlNote = document.getElementById('postgresql-note');

            if (type === 'postgresql') {
                mysqlNote.classList.add('d-none');
                postgresqlNote.classList.remove('d-none');
            } else {
                mysqlNote.classList.remove('d-none');
                postgresqlNote.classList.add('d-none');
            }
        }
    </script>
@endsection

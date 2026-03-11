@extends('layouts.app')

@section('title', 'Create Webhook - Hostiqo')
@section('page-title', 'Create Webhook')
@section('page-description', 'Configure a new Git webhook for automated deployments')

@section('page-actions')
    <a href="{{ route('webhooks.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Webhooks
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('webhooks.store') }}" method="POST">
                @csrf

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i> Basic Information
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Webhook Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            <div class="form-text">A descriptive name for this webhook</div>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="domain" class="form-label">Domain / Website Reference</label>
                            <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain') }}" placeholder="example.com">
                            <div class="form-text">Optional website domain - will auto-generate local path (e.g., example.com → /var/www/example_com)</div>
                            @error('domain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Active (Enable webhook to receive deployment triggers)
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="project_type" class="form-label">Project Type</label>
                            <select class="form-select @error('project_type') is-invalid @enderror" id="project_type" name="project_type" required onchange="toggleDockerFields()">
                                <option value="php" selected>PHP</option>
                                <option value="node">Node.js</option>
                                <option value="docker">Docker</option>
                            </select>
                            @error('project_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card" id="docker-config-card" style="display: none;">
                    <div class="card-header">
                        <i class="bi bi-box-seam me-2"></i> Docker Configuration
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="docker_action" class="form-label">Docker Action</label>
                            <select class="form-select @error('docker_action') is-invalid @enderror" id="docker_action" name="docker_action">
                                <option value="restart" selected>Restart Only</option>
                                <option value="build">Build Image</option>
                                <option value="pull">Pull Image</option>
                            </select>
                            <div class="form-text">
                                <strong>Restart Only:</strong> Just restart containers (fastest)<br>
                                <strong>Build Image:</strong> Rebuild Docker image from Dockerfile<br>
                                <strong>Pull Image:</strong> Pull latest image from registry
                            </div>
                            @error('docker_action')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="docker_image_name_field" style="display: none;">
                            <label for="docker_image_name" class="form-label">Docker Image Name</label>
                            <input type="text" class="form-control @error('docker_image_name') is-invalid @enderror" id="docker_image_name" name="docker_image_name" value="{{ old('docker_image_name') }}" placeholder="myregistry.com/myimage:latest">
                            <div class="form-text">Required when using "Pull Image" action. Example: <code>ghcr.io/username/myapp:latest</code></div>
                            @error('docker_image_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="docker_compose_path" class="form-label">Docker Compose Path</label>
                            <input type="text" class="form-control @error('docker_compose_path') is-invalid @enderror" id="docker_compose_path" name="docker_compose_path" value="{{ old('docker_compose_path') }}" placeholder="/var/www/myapp/docker-compose.yml">
                            <div class="form-text">Path to docker-compose.yml file. Leave empty to use <code>{local_path}/docker-compose.yml</code></div>
                            @error('docker_compose_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-git me-2"></i> Repository Configuration
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="git_provider" class="form-label">Git Provider <span class="text-danger">*</span></label>
                            <select class="form-select @error('git_provider') is-invalid @enderror" id="git_provider" name="git_provider" required>
                                <option value="github" {{ old('git_provider', 'github') == 'github' ? 'selected' : '' }}>
                                    <i class="bi bi-github"></i> GitHub
                                </option>
                                <option value="gitlab" {{ old('git_provider') == 'gitlab' ? 'selected' : '' }}>
                                    GitLab
                                </option>
                            </select>
                            @error('git_provider')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="repository_url" class="form-label">Repository URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('repository_url') is-invalid @enderror" id="repository_url" name="repository_url" value="{{ old('repository_url') }}" placeholder="git@github.com:username/repository.git" required>
                            <div class="form-text">SSH or HTTPS URL of your Git repository</div>
                            @error('repository_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="branch" class="form-label">Branch <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('branch') is-invalid @enderror" id="branch" name="branch" value="{{ old('branch', 'main') }}" required>
                            <div class="form-text">Git branch to deploy (e.g., main, master, develop)</div>
                            @error('branch')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="local_path" class="form-label">Local Path <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('local_path') is-invalid @enderror" id="local_path" name="local_path" value="{{ old('local_path') }}" placeholder="/var/www/example_com" required>
                            <div class="form-text">Absolute path where the repository will be cloned/deployed (auto-generated from domain)</div>
                            @error('local_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="deploy_user" class="form-label">Deploy User</label>
                            <input type="text" class="form-control @error('deploy_user') is-invalid @enderror" id="deploy_user" name="deploy_user" value="{{ old('deploy_user', 'www-data') }}" placeholder="www-data">
                            <div class="form-text">
                                User that will execute deployment commands. Ensure this user has permissions for the deployment path.
                                <br>Common users: <code>www-data</code>, <code>www</code>, <code>nginx</code>, <code>apache</code>, <code>http</code>
                            </div>
                            @error('deploy_user')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-key me-2"></i> SSH Key Configuration
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="generate_ssh_key" value="0">
                            <input class="form-check-input" type="checkbox" id="generate_ssh_key" name="generate_ssh_key" value="1" {{ old('generate_ssh_key', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="generate_ssh_key">
                                <strong>Auto-generate SSH Key Pair</strong>
                                <div class="form-text mt-1">Automatically generate a unique SSH key for this webhook. You'll need to add the public key to your Git provider.</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i> Deploy Scripts (Optional)
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="pre_deploy_script" class="form-label">Pre-Deploy Script</label>
                            <textarea class="form-control font-monospace @error('pre_deploy_script') is-invalid @enderror" id="pre_deploy_script" name="pre_deploy_script" rows="4" placeholder="Commands to run BEFORE pulling code (e.g., backups, maintenance mode)">{{ old('pre_deploy_script') }}</textarea>
                            <div class="form-text" id="pre_deploy_help">Script to run before deployment (e.g., backup, maintenance mode)</div>
                            @error('pre_deploy_script')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="post_deploy_script" class="form-label">Post-Deploy Script</label>
                            <textarea class="form-control font-monospace @error('post_deploy_script') is-invalid @enderror" id="post_deploy_script" name="post_deploy_script" rows="5" placeholder="Commands to run AFTER deployment (e.g., composer install, npm install, migrate)">{{ old('post_deploy_script') }}</textarea>
                            <div class="form-text" id="post_deploy_help">Script to run after deployment (e.g., composer install, migrations, build assets)</div>
                            @error('post_deploy_script')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Create Webhook
                    </button>
                    <a href="{{ route('webhooks.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i> Quick Tips
                </div>
                <div class="card-body">
                    <h6>SSH vs HTTPS URLs</h6>
                    <p class="small">For private repositories, use SSH URLs and generate an SSH key. For public repos, HTTPS URLs work fine.</p>

                    <h6 class="mt-3">Deploy Scripts</h6>
                    <p class="small">Use post-deploy scripts to automate tasks like:</p>
                    <ul class="small">
                        <li>Installing dependencies</li>
                        <li>Running migrations</li>
                        <li>Building assets</li>
                        <li>Clearing cache</li>
                        <li>Restarting services</li>
                    </ul>

                    <h6 class="mt-3">PHP Paths by Version</h6>
                    <p class="small">Use specific PHP version in deploy scripts:</p>
                    <ul class="small mb-0" style="font-family: monospace; font-size: 0.8rem;">
                        <li>/usr/bin/php7.4</li>
                        <li>/usr/bin/php8.0</li>
                        <li>/usr/bin/php8.1</li>
                        <li>/usr/bin/php8.2</li>
                        <li>/usr/bin/php8.3</li>
                        <li>/usr/bin/php8.4</li>
                    </ul>
                    <p class="small mt-2 mb-0">Laravel Example:</p>
                    <code class="small d-block bg-white p-2 rounded mb-2">/usr/bin/php8.3 artisan migrate</code>
                    <p class="small mt-2 mb-0">Composer Example:</p>
                    <code class="small d-block bg-white p-2 rounded">/usr/bin/php8.3 /usr/local/bin/composer install</code>

                    <h6 class="mt-3">Security</h6>
                    <p class="small">Each webhook gets a unique secret token for verification. Never share your webhook URLs publicly.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function() {
    var $domainInput = $('#domain');
    var $localPathInput = $('#local_path');
    var manuallyEdited = false;

    // Track if user manually edited local path
    $localPathInput.on('input', function() {
        if ($(this).val() !== '') {
            manuallyEdited = true;
        }
    });

    // Auto-generate local path from domain
    $domainInput.on('input', function() {
        if (!manuallyEdited || $localPathInput.val() === '') {
            var domain = $(this).val().trim();
            if (domain) {
                // Remove www. prefix if exists
                domain = domain.replace(/^www\./, '');

                // Replace dots with underscores
                var path = domain.replace(/\./g, '_');

                // Generate full path
                $localPathInput.val('/var/www/' + path);
            } else {
                $localPathInput.val('');
            }
        }
    });

    // Reset manual edit flag if user clears the field
    $localPathInput.on('keydown', function(e) {
        if ((e.key === 'Backspace' || e.key === 'Delete') && $(this).val().length <= 1) {
            manuallyEdited = false;
        }
    });

    // Docker action change handler
    $('#docker_action').on('change', function() {
        if ($(this).val() === 'pull') {
            $('#docker_image_name_field').show();
        } else {
            $('#docker_image_name_field').hide();
        }
    });

    // Project type change handler
    $('#project_type').on('change', function() {
        updateScriptPlaceholders($(this).val());
    });
});

function toggleDockerFields() {
    const projectType = document.getElementById('project_type').value;
    const dockerCard = document.getElementById('docker-config-card');

    if (projectType === 'docker') {
        dockerCard.style.display = 'block';
    } else {
        dockerCard.style.display = 'none';
    }

    updateScriptPlaceholders(projectType);
}

function updateScriptPlaceholders(projectType) {
    const preScript = document.getElementById('pre_deploy_script');
    const postScript = document.getElementById('post_deploy_script');
    const preHelp = document.getElementById('pre_deploy_help');
    const postHelp = document.getElementById('post_deploy_help');

    if (projectType === 'docker') {
        // Docker-specific placeholders
        preScript.placeholder = "docker compose exec -T app php artisan down --maintenance";
        preHelp.textContent = "Script to run BEFORE restarting containers (e.g., maintenance mode, backups)";

        postScript.placeholder = "docker compose exec -T app php artisan migrate --force\\ndocker compose exec -T app npm run build\\ndocker compose exec -T app php artisan cache:clear";
        postHelp.textContent = "Script to run AFTER containers are running (run inside container, use docker compose exec)";
    } else if (projectType === 'node') {
        // Node.js-specific placeholders
        preScript.placeholder = "pm2 stop app || true";
        preHelp.textContent = "Script to run BEFORE pulling code (e.g., stop processes)";

        postScript.placeholder = "npm install\\nnpm run build\\npm2 restart app";
        postHelp.textContent = "Script to run AFTER deployment (e.g., install dependencies, build, restart)";
    } else {
        // PHP-specific placeholders (default)
        preScript.placeholder = "npm install\\nnpm run build";
        preHelp.textContent = "Script to run before deployment (e.g., install npm dependencies, build assets)";

        postScript.placeholder = "#!/bin/bash\\n/usr/bin/php8.4 /usr/local/bin/composer install --no-dev --optimize-autoloader\\nnpm run build\\n/usr/bin/php8.4 artisan migrate --force\\n/usr/bin/php8.4 artisan config:clear\\n/usr/bin/php8.4 artisan cache:clear\\n/usr/bin/php8.4 artisan route:clear\\n/usr/bin/php8.4 artisan view:clear";
        postHelp.textContent = "Script to run after deployment (composer install, migrations, clear caches)";
    }
}
</script>
@endpush

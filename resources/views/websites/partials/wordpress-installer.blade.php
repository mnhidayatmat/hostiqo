<div class="row">
    <div class="col-12">
        <!-- Installation Status Card -->
        <div class="card mb-4" id="installationStatusCard" style="display: none;">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-hourglass-split me-2"></i> Installation in Progress
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         id="installProgress"
                         role="progressbar"
                         style="width: 0%">
                        0%
                    </div>
                </div>

                <div id="installationSteps">
                    <!-- Steps will be populated dynamically -->
                </div>

                <div id="installationResult" class="mt-3" style="display: none;">
                    <!-- Result will be shown here -->
                </div>
            </div>
        </div>

        <!-- Installation Form -->
        <form id="wordpressInstallForm">
            @csrf

            <!-- Database Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-database me-2"></i> Database Configuration
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Database Name <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="db_name"
                                       name="db_name"
                                       value="{{ str_replace(['.', '-'], '_', $website->domain) }}_wp"
                                       pattern="[a-zA-Z0-9_]+"
                                       required>
                                <div class="form-text">Alphanumeric and underscores only</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Database User <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="db_user"
                                       name="db_user"
                                       value="{{ str_replace(['.', '-'], '_', $website->domain) }}_user"
                                       pattern="[a-zA-Z0-9_]+"
                                       maxlength="32"
                                       required>
                                <div class="form-text">Max 32 characters</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_password" class="form-label">Database Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       class="form-control"
                                       id="db_password"
                                       name="db_password"
                                       minlength="8"
                                       required>
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Database Host</label>
                                <input type="text"
                                       class="form-control"
                                       id="db_host"
                                       name="db_host"
                                       value="localhost"
                                       placeholder="localhost">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="db_prefix" class="form-label">Table Prefix</label>
                                <input type="text"
                                       class="form-control"
                                       id="db_prefix"
                                       name="db_prefix"
                                       value="wp_"
                                       pattern="[a-zA-Z0-9_]+"
                                       maxlength="20">
                                <div class="form-text">Default: wp_</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WordPress Admin Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-person-lock me-2"></i> WordPress Admin Account
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admin_user" class="form-label">Admin Username <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="admin_user"
                                       name="admin_user"
                                       pattern="[a-zA-Z0-9_]+"
                                       maxlength="60"
                                       required>
                                <div class="form-text">Avoid using "admin" for security</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Admin Email <span class="text-danger">*</span></label>
                                <input type="email"
                                       class="form-control"
                                       id="admin_email"
                                       name="admin_email"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admin_password" class="form-label">Admin Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       class="form-control"
                                       id="admin_password"
                                       name="admin_password"
                                       minlength="8"
                                       required>
                                <div class="form-text">Strong password recommended</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_title" class="form-label">Site Title <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="site_title"
                                       name="site_title"
                                       value="{{ ucwords(str_replace(['.', '-', '_'], ' ', explode('.', $website->domain)[0])) }}"
                                       required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optimization Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-speedometer2 me-2"></i> Optimization & Features
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input"
                               type="checkbox"
                               id="enable_cache"
                               name="enable_cache"
                               value="1"
                               checked>
                        <label class="form-check-label" for="enable_cache">
                            <strong>Enable FastCGI Cache</strong>
                            <br><small class="text-muted">High-performance page caching (Recommended)</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input"
                               type="checkbox"
                               id="install_plugins"
                               name="install_plugins"
                               value="1"
                               checked>
                        <label class="form-check-label" for="install_plugins">
                            <strong>Install Recommended Plugins</strong>
                            <br><small class="text-muted">Wordfence (Security), WP-Optimize, UpdraftPlus (Backup), Nginx Helper</small>
                        </label>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>What will be configured:</strong>
                        <ul class="mb-0 mt-2">
                            <li>✅ WordPress core (latest version)</li>
                            <li>✅ Optimized Nginx configuration with security headers</li>
                            <li>✅ Optimized PHP-FPM pool (256MB, OPcache enabled)</li>
                            <li>✅ FastCGI cache (60min TTL, 1GB max)</li>
                            <li>✅ Rate limiting on wp-login.php</li>
                            <li>✅ Block xmlrpc.php (DDoS protection)</li>
                            <li>✅ Secure file permissions</li>
                            <li>✅ Path isolation (open_basedir)</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg" id="installButton">
                    <i class="bi bi-wordpress me-2"></i> Install WordPress
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
$('#wordpressInstallForm').on('submit', function(e) {
    e.preventDefault();

    var $form = $(this);
    var $installButton = $('#installButton');
    var $statusCard = $('#installationStatusCard');
    var $stepsContainer = $('#installationSteps');
    var $resultContainer = $('#installationResult');
    var $progressBar = $('#installProgress');

    // Disable form and show status card
    $installButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Installing...');
    $form.find('input, button').prop('disabled', true);
    $statusCard.show();
    $resultContainer.hide();

    // Scroll to status card
    $('html, body').animate({
        scrollTop: $statusCard.offset().top
    }, 500);

    // Prepare form data
    var data = {};
    $form.serializeArray().forEach(function(item) {
        data[item.name] = item.value;
    });

    $.ajax({
        url: '{{ route("websites.wordpress.install", $website) }}',
        method: 'POST',
        contentType: 'application/json',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        data: JSON.stringify(data)
    }).done(function(result) {
        if (result.success) {
            // Show success
            $progressBar.css('width', '100%').text('100%')
                .removeClass('progress-bar-animated')
                .addClass('bg-success');

            // Display installation steps
            if (result.data.steps) {
                var stepsHtml = result.data.steps.map(function(step) {
                    return '<div class="d-flex align-items-center mb-2">' +
                        '<i class="bi bi-check-circle-fill text-success me-2"></i>' +
                        '<span>' + step.step + '</span>' +
                    '</div>';
                }).join('');
                $stepsContainer.html(stepsHtml);
            }

            // Show success message
            $resultContainer.html(
                '<div class="alert alert-success">' +
                    '<h5 class="alert-heading">' +
                        '<i class="bi bi-check-circle me-2"></i> WordPress Installed Successfully!' +
                    '</h5>' +
                    '<hr>' +
                    '<p class="mb-2"><strong>Admin URL:</strong> <a href="' + result.data.admin_url + '" target="_blank">' + result.data.admin_url + '</a></p>' +
                    '<p class="mb-2"><strong>Admin Username:</strong> ' + result.data.admin_user + '</p>' +
                    '<p class="mb-0"><strong>Admin Password:</strong> [As entered in the form]</p>' +
                    '<hr>' +
                    '<div class="mt-3">' +
                        '<a href="' + result.data.admin_url + '" target="_blank" class="btn btn-success">' +
                            '<i class="bi bi-box-arrow-up-right me-1"></i> Open WordPress Admin' +
                        '</a> ' +
                        '<button type="button" class="btn btn-secondary" onclick="location.reload()">' +
                            '<i class="bi bi-arrow-clockwise me-1"></i> Refresh Page' +
                        '</button>' +
                    '</div>' +
                '</div>'
            ).show();

        } else {
            showWpInstallError(result.message, result.errors, $form, $installButton, $progressBar, $resultContainer);
        }
    }).fail(function(xhr) {
        var message = xhr.responseJSON ? xhr.responseJSON.message : 'An unexpected error occurred. Please check the logs or try again.';
        showWpInstallError(message, null, $form, $installButton, $progressBar, $resultContainer);
    });
});

function showWpInstallError(message, errors, $form, $installButton, $progressBar, $resultContainer) {
    $progressBar.removeClass('progress-bar-animated').addClass('bg-danger');

    var errorsHtml = '';
    if (errors) {
        errorsHtml = '<hr><ul class="mb-0">';
        $.each(errors, function(key, err) {
            errorsHtml += '<li>' + err + '</li>';
        });
        errorsHtml += '</ul>';
    }

    $resultContainer.html(
        '<div class="alert alert-danger">' +
            '<h5 class="alert-heading">' +
                '<i class="bi bi-x-circle me-2"></i> Installation Failed' +
            '</h5>' +
            '<p class="mb-0">' + message + '</p>' +
            errorsHtml +
        '</div>'
    ).show();

    // Re-enable form
    $form.find('input, button').prop('disabled', false);
    $installButton.prop('disabled', false).html('<i class="bi bi-wordpress me-2"></i> Retry Installation');
}
</script>
@endpush

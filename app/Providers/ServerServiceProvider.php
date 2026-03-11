<?php

namespace App\Providers;

use App\Contracts\FirewallInterface;
use App\Contracts\NginxInterface;
use App\Contracts\PhpFpmInterface;
use App\Contracts\ServiceManagerInterface;
use App\Services\Firewall\FirewallFactory;
use App\Services\Nginx\NginxFactory;
use App\Services\PhpFpm\PhpFpmFactory;
use App\Services\ServiceManager\ServiceManagerFactory;
use Illuminate\Support\ServiceProvider;

class ServerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Firewall Interface
        $this->app->singleton(FirewallInterface::class, function () {
            return FirewallFactory::create();
        });

        // Bind Nginx Interface
        $this->app->singleton(NginxInterface::class, function () {
            return NginxFactory::create();
        });

        // Bind PHP-FPM Interface
        $this->app->singleton(PhpFpmInterface::class, function () {
            return PhpFpmFactory::create();
        });

        // Bind Service Manager Interface
        $this->app->singleton(ServiceManagerInterface::class, function () {
            return ServiceManagerFactory::create();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use App\Services\LicenseManagerClient;
use Illuminate\Support\ServiceProvider;

class LicenseManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/license-manager.php', 'license-manager');

        $this->app->singleton(LicenseManagerClient::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/license-manager.php' => config_path('license-manager.php'),
            ], 'license-manager-config');

            $this->commands([
                \App\Console\Commands\LicenseActivateCommand::class,
                \App\Console\Commands\LicenseVerifyCommand::class,
                \App\Console\Commands\LicenseDeactivateCommand::class,
                \App\Console\Commands\LicenseStatusCommand::class,
            ]);
        }
    }
}

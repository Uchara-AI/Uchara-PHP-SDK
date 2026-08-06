<?php

namespace Uchara\SDK\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class UcharaServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/uchara.php',
            'uchara'
        );

        $this->app->singleton('uchara', function (Application $app) {
            return new UcharaManager($app['config']['uchara']);
        });

        $this->app->alias('uchara', UcharaManager::class);
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/uchara.php' => config_path('uchara.php'),
            ], 'uchara-config');
        }
    }
}

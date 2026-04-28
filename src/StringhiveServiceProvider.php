<?php

declare(strict_types=1);

namespace Stringhive;

use Illuminate\Support\ServiceProvider;

class StringhiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/stringhive.php', 'stringhive');

        $this->app->singleton(StringHive::class, function ($app) {
            /** @var array{base_url: string, token: string|null, timeout: int} $config */
            $config = $app['config']['stringhive'];

            return new StringHive(
                baseUrl: $config['base_url'],
                token: $config['token'] ?? '',
                timeout: $config['timeout'],
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/stringhive.php' => config_path('stringhive.php'),
            ], 'stringhive-config');
        }
    }
}

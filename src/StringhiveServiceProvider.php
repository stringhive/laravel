<?php

declare(strict_types=1);

namespace Stringhive;

use Illuminate\Support\ServiceProvider;
use Stringhive\Commands\PullCommand;
use Stringhive\Commands\PushCommand;
use Stringhive\Lang\LangLoader;

class StringhiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stringhive.php', 'stringhive');

        $this->app->singleton(Stringhive::class, function ($app) {
            /** @var array{base_url: string, token: string|null, timeout: int} $config */
            $config = $app['config']['stringhive'];

            return new Stringhive(
                baseUrl: $config['base_url'],
                token: $config['token'] ?? '',
                timeout: $config['timeout'],
            );
        });

        $this->app->singleton(LangLoader::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stringhive.php' => config_path('stringhive.php'),
            ], 'stringhive-config');

            $this->commands([
                PushCommand::class,
                PullCommand::class,
            ]);
        }
    }
}

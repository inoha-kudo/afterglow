<?php

declare(strict_types=1);

namespace Afterglow;

use Illuminate\Support\ServiceProvider;

final class AfterglowServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/afterglow.php',
            'afterglow',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/afterglow.php' => $this->app->configPath('afterglow.php'),
            ], 'afterglow-config');
        }
    }
}

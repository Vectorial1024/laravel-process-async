<?php

namespace Vectorial1024\LaravelProcessAsync;

use Illuminate\Support\ServiceProvider;

class BackgroundAsyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // pass
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->commands([
            AsyncTaskRunnerCommand::class,
        ]);
    }
}

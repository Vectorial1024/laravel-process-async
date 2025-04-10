<?php

namespace Vectorial1024\LaravelProcessAsync;

use Illuminate\Support\ServiceProvider;

class ProcessAsyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // load/reset our secret key
        AsyncTask::loadSecretKey();
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

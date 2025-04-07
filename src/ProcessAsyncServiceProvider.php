<?php

namespace Vectorial1024\LaravelProcessAsync;

use function Opis\Closure\init;
use Illuminate\Support\ServiceProvider;

class ProcessAsyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // read from the env file for the secret key (if exists) to verify our identity
        $secretKey = env("PROCESS_ASYNC_SECRET_KEY");
        if ($secretKey != null && strlen($secretKey) > 0) {
            // we can set the secret key
            init($secretKey);
        }
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

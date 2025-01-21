<?php

namespace Vectorial1024\LaravelProcessAsync\Tests\Tasks;

use Vectorial1024\LaravelProcessAsync\AsyncTaskInterface;

class SleepingAsyncTask implements AsyncTaskInterface
{
    // just sleeps for 2 seconds, and finish

    public function __construct()
    {
    }

    public function execute(): void
    {
        sleep(2);
    }

    public function handleTimeout(): void
    {
        // nothing for now
    }
}

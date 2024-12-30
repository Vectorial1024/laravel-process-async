<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use Illuminate\Support\Facades\Log;
use Vectorial1024\LaravelProcessAsync\AsyncTaskInterface;

class TestTimeoutNormalTask implements AsyncTaskInterface
{
    // when timeout, write a message to a file

    public function __construct(
        private string $message,
        private string $targetFilePath
    ) {
    }

    public function execute(): void
    {
        // pass
        Log::info("Starting!");
    }

    public function handleTimeout(): void
    {
        Log::info("Starting!");
        $fp = fopen($this->targetFilePath, "w");
        fwrite($fp, $this->message);
        fflush($fp);
        fclose($fp);
    }
}

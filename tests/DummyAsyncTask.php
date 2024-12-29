<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use Vectorial1024\LaravelProcessAsync\AsyncTaskInterface;

class DummyAsyncTask implements AsyncTaskInterface
{
    // write a message to a file

    public function __construct(
        private string $message,
        private string $targetFilePath
    ) {
    }

    public function execute(): void
    {
        $fp = fopen($this->targetFilePath, "w");
        fwrite($fp, $this->message);
        fflush($fp);
        fclose($fp);
    }

    public function handleTimeout(): void
    {
        // nothing for now
    }
}

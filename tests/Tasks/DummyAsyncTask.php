<?php

namespace Vectorial1024\LaravelProcessAsync\Tests\Tasks;

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
        file_put_contents($this->targetFilePath, $this->message);
    }

    public function handleTimeout(): void
    {
        // nothing for now
    }
}

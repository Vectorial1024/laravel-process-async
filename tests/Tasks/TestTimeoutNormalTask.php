<?php

namespace Vectorial1024\LaravelProcessAsync\Tests\Tasks;

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
        // we have to sleep a bit to trigger the timeout
        // the test has a timeout of 1s, so we try to sleep for slightly longer than 1 seconds
        // this is currently 1.2 seconds
        usleep(300000);
        usleep(300000);
        usleep(300000);
        usleep(300000);
    }

    public function handleTimeout(): void
    {
        file_put_contents($this->targetFilePath, $this->message);
    }
}

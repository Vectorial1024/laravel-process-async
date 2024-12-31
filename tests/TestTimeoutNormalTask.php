<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

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
        // the test has a timeout of 1s, so we try to sleep for 1.5s
        sleep(1);
        usleep(500000);
    }

    public function handleTimeout(): void
    {
        $fp = fopen($this->targetFilePath, "w");
        fwrite($fp, $this->message);
        fflush($fp);
        fclose($fp);
    }
}

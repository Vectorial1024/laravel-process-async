<?php

namespace Vectorial1024\LaravelProcessAsync\Tests\Tasks;

use Vectorial1024\LaravelProcessAsync\AsyncTaskInterface;

class TestTimeoutNoOpTask implements AsyncTaskInterface
{
    // quite literally does nothing; the timeout handler is therefore only for decoration

    public function __construct(
        private string $message,
        private string $targetFilePath
    ) {
    }

    public function execute(): void
    {
        // goodbye!
        exit();
    }

    public function handleTimeout(): void
    {
        $fp = fopen($this->targetFilePath, "w");
        fwrite($fp, $this->message);
        fflush($fp);
        fclose($fp);
    }
}
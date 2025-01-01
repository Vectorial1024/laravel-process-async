<?php

namespace Vectorial1024\LaravelProcessAsync\Tests\Tasks;

use Vectorial1024\LaravelProcessAsync\AsyncTaskInterface;

class TestTimeoutErrorTask implements AsyncTaskInterface
{
    // has a timeout handler, but throws a runtime exception (the timeout handler is not triggered)

    public function __construct(
        private string $message,
        private string $targetFilePath
    ) {
    }

    public function execute(): void
    {
        // boom!
        1 / 0;
    }

    public function handleTimeout(): void
    {
        $fp = fopen($this->targetFilePath, "w");
        fwrite($fp, $this->message);
        fflush($fp);
        fclose($fp);
    }
}

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
        file_put_contents($this->targetFilePath, $this->message);
    }
}

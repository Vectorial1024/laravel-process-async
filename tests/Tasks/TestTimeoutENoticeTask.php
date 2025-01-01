<?php

namespace Vectorial1024\LaravelProcessAsync\Tests\Tasks;

use Vectorial1024\LaravelProcessAsync\AsyncTaskInterface;

class TestTimeoutENoticeTask implements AsyncTaskInterface
{
    // triggers an E_NOTICE error; the timeout handler should NOT trigger! this is because script execution can still continue (at least in PHP 8)

    public function __construct(
        private string $message,
        private string $targetFilePath
    ) {
    }

    public function execute(): void
    {
        // eh eh
        $context;
    }

    public function handleTimeout(): void
    {
        $fp = fopen($this->targetFilePath, "w");
        fwrite($fp, $this->message);
        fflush($fp);
        fclose($fp);
    }
}

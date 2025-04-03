<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

use InvalidArgumentException;
use loophp\phposinfo\OsInfo;
use RuntimeException;

/**
 * The fake AsyncTaskStatus class for testing. Fake async tasks are presumed to be running by default.
 */
class FakeAsyncTaskStatus extends AsyncTaskStatus
{
    private bool $fakeIsRunning = true;

    /**
     * Constructs a fake status object. Fake async tasks are presumed to be running by default.
     * @param string $fakeTaskID The task ID of the fake async task.
     */
    public function __construct(string $fakeTaskID) {
        parent::__construct($fakeTaskID);
    }

    /**
     * Returns whether the fake task is currently "running".
     * @return bool The faked "task is running" status.
     */
    public function isRunning(): bool
    {
        return $this->fakeIsRunning;
    }


}

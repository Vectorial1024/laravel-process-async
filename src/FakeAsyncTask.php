<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

use Closure;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use loophp\phposinfo\OsInfo;
use RuntimeException;

use function Opis\Closure\{serialize, unserialize};

/**
 * The fake AsyncTask class for testing.
 */
class FakeAsyncTask extends AsyncTask
{
    /**
     * Creates a FakeAsyncTask instance.
     * 
     * @param \Closure|AsyncTaskInterface $theTask The task to be executed in the background.
     * @param string|null $taskID (optional) The user-specified task ID of this AsyncTask. Should be unique.
     * @see AsyncTask::fake() an alternative way of creating FakeAsyncTask instances.
     */
    public function __construct(Closure|AsyncTaskInterface $theTask, string|null $taskID = null)
    {
        parent::__construct($theTask, taskID: $taskID);
    }

    /**
     * Dummy overriding method to prevent the FakeAsyncTask object from actually running the specified background task.
     * @return void
     */
    public function run(): void
    {
        // don't do anything!
        return;
    }

    /**
     * Fakes the AsyncTask being started in the background, but does not actually start the task.
     * @return AsyncTaskStatus The status object for the fake-started FakeAsyncTask.
     */
    public function start(): AsyncTaskStatus
    {
        // todo fake version
        $taskID = $this->taskID ?? Str::ulid()->toString();
        return new AsyncTaskStatus($taskID);
    }
}

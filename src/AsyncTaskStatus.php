<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

use InvalidArgumentException;

/**
 * Represents the status of an async task: "running" or "stopped".
 * 
 * This does not tell you whether it was a success/failure, since it depends on the user's custom result checking.
 */
class AsyncTaskStatus
{
    /**
     * The cached task ID for quick ID reusing. We will most probably reuse this ID many times.
     * @var string|null
     */
    private string|null $encodedTaskID = null;

    /**
     * Indicates whether the task is stopped.
     * 
     * Note: the criteria is "pretty sure it is stopped"; once the task is stopped, it stays stopped.
     * @var bool
     */
    private bool $isStopped = false;

    /**
     * The last known PID of the task runner.
     * @var int|null If null, it means the PID is unknown or expired.
     */
    private int|null $lastKnownPID = null;

    /**
     * Constructs a status object.
     * @param string $taskID The task ID of the async task so to check its status.
     */
    public function __construct(
        public readonly string $taskID
    ) {
        if ($taskID === "") {
            // why no blank IDs? because this will produce blank output via base64 encode.
            throw new InvalidArgumentException("AsyncTask IDs cannot be blank");
        }
    }

    /**
     * Returns the task ID encoded in base64, mainly for result checking.
     * @return string The encoded task ID.
     */
    public function getEncodedTaskID(): string
    {
        if ($this->encodedTaskID === null) {
            $this->encodedTaskID = base64_encode($this->taskID);
        }
        return $this->encodedTaskID;
    }

    /**
     * Returns whether the AsyncTask is still running.
     * 
     * Note: when this method detects that the task has stopped running, it will not recheck whether the task has restarted.
     * Use a fresh status object to track the (restarted) task.
     * @return bool If true, indicates the task is still running.
     */
    public function isRunning(): bool
    {
        if ($this->isStopped) {
            return false;
        }
        // prove it is running
        $isRunning = false;
        if (!$isRunning) {
            $this->isStopped = true;
        }
        return $isRunning;
    }

    /**
     * Attepts to prove whether the AsyncTask is still running
     * @return bool If false, then the task is shown to have been stopped.
     */
    private function proveTaskIsRunning(): bool
    {
        if ($this->lastKnownPID === null) {
            // we don't know where the task runner is at; find it!
            return $this->findTaskRunnerProcess();
        }
        // we know the task runner; is it still running?
        return $this->observeTaskRunnerProcess();
    }

    /**
     * Attempts to find the task runner process (if exists), and writes down its PID.
     * @return bool If true, then the task runner is successfully found.
     */
    private function findTaskRunnerProcess(): bool
    {
        // todo
        return false;
    }

    /**
     * Given a previously-noted PID of the task runner, see if the task runner is still alive.
     * @return bool If true, then the task runner is still running.
     */
    private function observeTaskRunnerProcess(): bool
    {
        // todo
        return false;
    }
}

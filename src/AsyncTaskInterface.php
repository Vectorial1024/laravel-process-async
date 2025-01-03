<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

/**
 * An interface to describe the details of a background async task.
 * 
 * This is the recommended way in case your task is not suitable for a serialized closure (e.g. it involves anonymous classes).
 */
interface AsyncTaskInterface
{
    /**
     * Executes the task when the background async runner is ready to handle this task.
     * 
     * Note: result-checking with the task issuer and exception handling (if needed) must be defined within this method.
     * @return void
     */
    public function execute(): void;

    /**
     * Cleans up the task when the task runner has run out of time specified by its time limit.
     * 
     * Note: there is no need to call exit() again in this function.
     * @return void
     */
    public function handleTimeout(): void;
}

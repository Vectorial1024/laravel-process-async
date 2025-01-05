<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

use RuntimeException;

/**
 * Represents the status of an async task: "running" or "stopped".
 * 
 * This does not tell you whether it was a success/failure, since it depends on the user's custom result checking.
 */
class AsyncTaskStatus
{
    /**
     * Constructs a status object.
     * @param string $taskID The task ID of the async task so to check its status.
     */
    public function __construct(
        public readonly string $taskID
    ) {
        if ($taskID === "") {
            // why no blank IDs? because this will produce blank output via base64 encode.
            throw new RuntimeException("AsyncTask IDs cannot be blank");
        }
    }
}

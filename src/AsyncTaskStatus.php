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
}

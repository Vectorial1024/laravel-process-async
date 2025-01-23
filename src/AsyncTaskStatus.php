<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

use InvalidArgumentException;
use loophp\phposinfo\OsInfo;
use RuntimeException;

/**
 * Represents the status of an async task: "running" or "stopped".
 * 
 * This does not tell you whether it was a success/failure, since it depends on the user's custom result checking.
 */
class AsyncTaskStatus
{
    private const MSG_CANNOT_CHECK_STATUS = "Could not check the status of the AsyncTask.";

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
     * Checks and returns whether the AsyncTask is still running.
     * 
     * On Windows, this may take some time due to underlying bottlenecks.
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
        $isRunning = $this->proveTaskIsRunning();
        if (!$isRunning) {
            $this->isStopped = true;
        }
        return $isRunning;
    }

    /**
     * Attempts to prove whether the AsyncTask is still running
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
        // find the runner in the system
        // we might have multiple PIDs; in this case, pick the first one that appears
        /*
         * note: while the OS may allow reading multiple properties at the same time,
         * we won't risk it because localizations might produce unexpected strings or unusual separators
         * an example would be CJK potentially having an alternate character to replace ":"
         */
        if (OsInfo::isWindows()) {
            // Windows uses GCIM to discover processes
            $results = [];
            $encodedTaskID = $this->getEncodedTaskID();
            $expectedCmdName = "artisan async:run";
            // we can assume we are in cmd, but wcim in cmd is deprecated, and the replacement gcim requires powershell
            $results = [];
            $fullCmd = "powershell echo \"\"(gcim Win32_Process -Filter \\\"CommandLine LIKE '%id=\'$encodedTaskID\'%'\\\").ProcessId\"\"";
            \Illuminate\Support\Facades\Log::info($fullCmd);
            exec("powershell echo \"\"(gcim Win32_Process -Filter \\\"CommandLine LIKE '%id=\'$encodedTaskID\'%'\\\").ProcessId\"\"", $results);
            // will output many lines, each line being a PID
            foreach ($results as $candidatePID) {
                $candidatePID = (int) $candidatePID;
                // then use gcim again to see the cmd args
                $cmdArgs = exec("powershell echo \"\"(gcim Win32_Process -Filter \\\"ProcessId = $candidatePID\\\").CommandLine\"\"");
                if ($cmdArgs === false) {
                    throw new RuntimeException(self::MSG_CANNOT_CHECK_STATUS);
                }
                if (!str_contains($cmdArgs, $expectedCmdName)) {
                    // not really
                    continue;
                }
                $executable = exec("powershell echo \"\"(gcim Win32_Process -Filter \\\"ProcessId = $candidatePID\\\").Name\"\"");
                if ($executable === false) {
                    throw new RuntimeException(self::MSG_CANNOT_CHECK_STATUS);
                }
                if ($executable !== "php.exe") {
                    // not really
                    // note: we currently hard-code "php" as the executable name
                    continue;
                }
                // all checks passed; it is this one
                $this->lastKnownPID = $candidatePID;
                return true;
            }
            return false;
        }
        // assume anything not Windows to be Unix
        // find the runner on Unix systems via pgrep
        $results = [];
        $encodedTaskID = $this->getEncodedTaskID();
        exec("pgrep -f id='$encodedTaskID'", $results);
        // supposedly there should be only 1 entry, but anyway
        $expectedCmdName = "artisan async:run";
        foreach ($results as $candidatePID) {
            $candidatePID = (int) $candidatePID;
            // then use ps to see what really is it
            $fullCmd = exec("ps -p $candidatePID -o args=");
            if ($fullCmd === false) {
                throw new RuntimeException(self::MSG_CANNOT_CHECK_STATUS);
            }
            if (!str_contains($fullCmd, $expectedCmdName)) {
                // not really
                continue;
            }
            $executable = exec("ps -p $candidatePID -o comm=");
            if ($executable === false) {
                throw new RuntimeException(self::MSG_CANNOT_CHECK_STATUS);
            }
            if ($executable !== "php") {
                // not really
                // note: we currently hard-code "php" as the executable name
                continue;
            }
            // this is it!
            $this->lastKnownPID = $candidatePID;
            return true;
        }
        return false;
    }

    /**
     * Given a previously-noted PID of the task runner, see if the task runner is still alive.
     * @return bool If true, then the task runner is still running.
     */
    private function observeTaskRunnerProcess(): bool
    {
        // since we should have remembered the PID, we can just query whether it still exists
        // supposedly, the PID has not rolled over yet, right...?
        if (OsInfo::isWindows()) {
            // Windows can also use Get-Process to probe processes
            $echoedPid = exec("powershell (Get-Process -id {$this->lastKnownPID}).Id");
            if ($echoedPid === false) {
                throw new RuntimeException(self::MSG_CANNOT_CHECK_STATUS);
            }
            $echoedPid = (int) $echoedPid;
            return $this->lastKnownPID === $echoedPid;
        }
        // assume anything not Windows to be Unix
        $echoedPid = exec("ps -p {$this->lastKnownPID} -o pid=");
        if ($echoedPid === false) {
            throw new RuntimeException(self::MSG_CANNOT_CHECK_STATUS);
        }
        $echoedPid = (int) $echoedPid;
        return $this->lastKnownPID === $echoedPid;
    }
}

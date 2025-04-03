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
 * The common handler of an AsyncTask; this can be a closure (will be wrapped inside AsyncTask) or an interface instance.
 */
class AsyncTask
{
    /**
     * The task to be executed in the background.
     * @var Closure|AsyncTaskInterface
     */
    private Closure|AsyncTaskInterface $theTask;

    /**
     * The user-specified ID of the current task. (Null means user did not specify any ID).
     * 
     * If null, the task will generate an unsaved random ID when it is started.
     * @var string|null
     */
    private string|null $taskID;

    /**
     * The process that is actually running this task. Tasks that are not started will have null here.
     * @var InvokedProcess|null
     */
    private InvokedProcess|null $runnerProcess = null;

    /**
     * The maximum real time (in seconds) this task is allowed to run.
     * @var int|null
     */
    private int|null $timeLimit = 30;

    /**
     * The value of constant("LARAVEL_START") for future usage. Apparently, constants are not available during shutdown functions.
     * @var float|null
     */
    private float|null $laravelStartVal = null;

    /**
     * On Unix only. Indicates the process ID that can be used to track the "time elapsed" stat, which resolves to the following:
     * - if the task was started under the `timeout` command, then the PID of said `timeout` command
     * - else (i.e., started without time limit), the self PID
     * 
     * If not yet initialized or on Windows, then will be 0, which indicates an invalid PID.
     * @var int
     */
    private int $timerProcID = 0;

    /**
     * On Unix only. Indicates whether a SIGINT has been received.
     * @var bool
     */
    private bool $hasSigInt = false;

    /**
     * The string constant name for constant('LARAVEL_START'). Mainly to keep the code clean.
     * @var string
     */
    private const LARAVEL_START = "LARAVEL_START";

    /**
     * The bitmask that can filter for fatal runtime errors.
     * 
     * Fatal errors other than the specific "time limit exceeded" error must not trigger the timeout handlers.
     */
    private const FATAL_ERROR_BITMASK = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    /**
     * Indicates whether GNU coreutils is found in the system; in particular, we are looking for the timeout command inside coreutils.
     * 
     * If null, indicates we haven't checked this yet.
     * 
     * Always null in Windows since Windows-side does not require GNU coreutils.
     * @var bool|null
     */
    private static bool|null $hasGnuCoreUtils = null;

    /**
     * The name of the found timeout command inside GNU coreutils.
     * 
     * It is known that older MacOS environments might have "gtimeout" instead of "timeout".
     * @var string|null
     */
    private static string|null $timeoutCmdName = null;

    /**
     * Creates an AsyncTask instance.
     * @param Closure|AsyncTaskInterface $theTask The task to be executed in the background.
     * @param string|null $taskID (optional) The user-specified task ID of this AsyncTask. Should be unique.
     */
    public function __construct(Closure|AsyncTaskInterface $theTask, string|null $taskID = null)
    {
        // opis/closure allows direct storage of closure
        $this->theTask = $theTask;
        if ($taskID === "") {
            throw new InvalidArgumentException("AsyncTask ID cannot be empty.");
        }
        $this->taskID = $taskID;
    }

    /**
     * Returns an instance of a fake AsyncTask with the same task parameters and task ID.
     * @return FakeAsyncTask The fake AsyncTask object for testing.
     */
    public function fake(): FakeAsyncTask
    {
        $fakeTask = new FakeAsyncTask($this->theTask, taskID: $this->taskID);
        if ($this->getTimeLimit() === null) {
            $fakeTask->withoutTimeLimit();
        } else {
            $fakeTask->withTimeLimit($this->timeLimit);
        }
        return $fakeTask;
    }

    public function __serialize(): array
    {
        // serialize only the necessary info to reduce runner cmd length
        return [
            'theTask' => $this->theTask,
            'timeLimit' => $this->timeLimit,
        ];
    }

    public function __unserialize($data): void
    {
        [
            'theTask' => $this->theTask,
            'timeLimit' => $this->timeLimit,
        ] = $data;
    }

    /**
     * Returns a status object for the started AsyncTask.
     * 
     * If this task does not have an explicit task ID, a new one will be generated on-the-fly.
     * @return AsyncTaskStatus The status object for the started AsyncTask.
     */
    protected function getTaskStatusObject(): AsyncTaskStatus 
    {
        $taskID = $this->taskID ?? Str::ulid()->toString();
        return new AsyncTaskStatus($taskID);
    }

    /**
     * Inside an available PHP process, runs this AsyncTask instance.
     * 
     * This should only be called from the runner so that we are really inside an available PHP process.
     * @return void
     * @see AsyncTaskRunnerCommand
     */
    public function run(): void
    {
        // todo startup configs
        // write down the LARAVEL_START constant value for future usage
        $this->laravelStartVal = defined(self::LARAVEL_START) ? constant("LARAVEL_START") : null;

        // install a timeout detector
        // this single function checks all kinds of timeouts
        register_shutdown_function([$this, 'shutdownCheckTaskTimeout']);
        if (OsInfo::isWindows()) {
            // windows can just use PHP's time limit
            if ($this->timeLimit > 0) {
                set_time_limit($this->timeLimit);
            }
        } else {
            // assume anything not Windows to be Unix
            // we already set it to kill this task after the timeout, so we just need to install a listener to catch the signal and exit gracefully
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                // sicne we are already running with nohup, we can use SIGINT to indicate that a timeout has occurred.
                // exit asap so that our error checking inside shutdown functions can take place outside of the usual max_execution_time limit
                $this->hasSigInt = true;
                exit();
            });

            // and we also need to see the command name of our parent, to correctly track time
            $this->timerProcID = getmypid();
            $parentPid = posix_getppid();
            $parentCmd = exec("ps -p $parentPid -o comm=");
            if ($parentCmd === "timeout" || $parentCmd === "gtimeout") {
                // we should use the parent instead to time this task
                $this->timerProcID = $parentPid;
            }
        }

        // then, execute the task itself
        if ($this->theTask instanceof Closure) {
            ($this->theTask)();
        } else {
            // must be AsyncTaskInterface
            $this->theTask->execute();
        }

        // todo what if want some "task complete" event handling?
        return;
    }

    /**
     * Starts this AsyncTask immediately in the background. A runner will then run this AsyncTask.
     * @return AsyncTaskStatus The status object for the started AsyncTask.
     */
    public function start(): AsyncTaskStatus
    {
        // prepare the task details
        $taskStatus = $this->getTaskStatusObject();

        // prepare the runner command
        $serializedTask = $this->toBase64Serial();
        $encodedTaskID = $taskStatus->getEncodedTaskID();
        $baseCommand = "php artisan async:run $serializedTask --id='$encodedTaskID'";

        // then, specific actions depending on the runtime OS
        if (OsInfo::isWindows()) {
            // basically, in windows, it is too tedioous to check whether we are in cmd or ps,
            // but we require cmd (ps won't work here), so might as well force cmd like this
            // windows has real max time limit
            $this->runnerProcess = Process::quietly()->start("cmd >nul 2>nul /c start /b $baseCommand");
            return $taskStatus;
        }
        // assume anything not windows to be unix
        // unix use nohup
        // check time limit settings
        $timeoutClause = "";
        if ($this->timeLimit > 0) {
            // do we really have timeout here?
            if (static::$hasGnuCoreUtils === null) {
                // haven't checked before; check
                $tmpOut = exec("command -v timeout || command -v gtimeout");
                $cmdName = !empty($tmpOut) ? $tmpOut : null;
                unset($tmpOut);
                static::$hasGnuCoreUtils = $cmdName !== null;
                static::$timeoutCmdName = $cmdName;
            }
            if (static::$hasGnuCoreUtils === false) {
                // can't do anything without GNU coreutils!
                throw new RuntimeException("AsyncTask time limit requires GNU coreutils, but GNU coreutils was not installed");
            }
            // 2 is INT signal
            $timeoutClause = static::$timeoutCmdName . " -s 2 {$this->timeLimit}";
        }
        $this->runnerProcess = Process::quietly()->start("nohup $timeoutClause $baseCommand >/dev/null 2>&1");
        return $taskStatus;
    }

    /**
     * Returns the base64-encoded serialization for this object.
     * 
     * This has the benefit of entirely ignoring potential encoding problems, such as '\0' from private instance variables.
     * 
     * This mechanism might have problems if the task closure is too long, but let's be honest: long closures are best converted to dedicated interface objects.
     * @return string The special serialization.
     * @see self::fromBase64Serial()
     */
    public function toBase64Serial(): string
    {
        return base64_encode(serialize($this));
    }

    /**
     * Returns the AsyncTask instance represented by the given base64-encoded serial.
     * @param string $serial The special serialization.
     * @return static|null If the serial is valid, then the reconstructed instance will be returned, else returns null.
     * @see self::toBase64Serial()
     */
    public static function fromBase64Serial(string $serial): ?static
    {
        $temp = base64_decode($serial, true);
        if ($temp === false) {
            // bad data
            return null;
        }
        try {
            $temp = unserialize($temp);
            // also check that we are unserializing into ourselves
            if ($temp instanceof static) {
                // correct value type
                return $temp;
            }
            // incorrect value type
            return null;
        } catch (ErrorException) {
            // bad data
            return null;
        }
    }

    /**
     * Returns the maximum real time this task is allowed to run. This also includes time spent on sleeping and waiting!
     * 
     * Null indicates unlimited time.
     * @return int|null The time limit in seconds.
     */
    public function getTimeLimit(): int|null
    {
        return $this->timeLimit;
    }

    /**
     * Sets the maximum real time this task is allowed to run. Chainable.
     * 
     * When the task reaches the time limit, the timeout handler (if exists) will be called.
     * @param int $seconds The time limit in seconds.
     * @return AsyncTask $this for chaining.
     */
    public function withTimeLimit(int $seconds): static
    {
        if ($seconds == 0) {
            throw new LogicException("AsyncTask time limit must be positive (hint: use withoutTimeLimit() for no time limits)");
        }
        if ($seconds < 0) {
            throw new LogicException("AsyncTask time limit must be positive");
        }
        $this->timeLimit = $seconds;
        return $this;
    }

    /**
     * Sets this task to run with no time limit (PHP INI `max_execution_time` may apply). Chainable.
     * @return AsyncTask $this for chaining.
     */
    public function withoutTimeLimit(): static
    {
        $this->timeLimit = null;
        return $this;
    }

    /**
     * A shutdown function.
     * 
     * Upon shutdown, checks whether this is due to the task timing out, and if so, triggers the timeout handler.
     * @return void
     */
    protected function shutdownCheckTaskTimeout(): void
    {
        if (!$this->hasTimedOut()) {
            // shutdown due to other reasons; skip
            return;
        }
        
        // timeout!
        // trigger the timeout handler
        if ($this->theTask instanceof AsyncTaskInterface) {
            $this->theTask->handleTimeout();
        }
    }

    /**
     * During shutdown, checks whether this shutdown satisfies the "task timed out shutdown" condition.
     * @return bool True if this task is timed out according to our specifications.
     */
    private function hasTimedOut(): bool
    {
        // we perform a series of checks to see if this task has timed out

        // dedicated SIGINT indicates a timeout
        if ($this->hasSigInt) {
            return true;
        }

        // runtime timeout triggers a PHP fatal error
        // this can happen on Windows by our specification, or on Unix when the actual CLI PHP time limit is smaller than the time limit of this task
        $lastError = error_get_last();
        if ($lastError !== null && ($lastError['type'] & self::FATAL_ERROR_BITMASK)) {
            // has fatal error; is it our timeout error?
            return str_contains($lastError['message'], "Maximum execution time");
        }
        unset($lastError);

        // the remaining checks use the time-limit variable, so if it is unset, then there is nothing to check
        if ($this->timeLimit <= 0) {
            return false;
        }

        // not a runtime timeout; one of the following:
        // it ended within the time limit; or
        // on Unix, it ran out of time so it is getting a SIGTERM from us; or
        // it somehow ran out of time, and is being manually detected and killed
        if ($this->laravelStartVal !== null) {
            // this is very important; in some test cases, this is being run directly by PHPUnit, and so LARAVEL_START will be null
            // in this case, we have no idea when this task has started running, so we cannot deduce any timeout statuses

            // check LARAVEL_START with microtime
            $timeElapsed = microtime(true) - $this->laravelStartVal;
            if ($timeElapsed >= $this->timeLimit) {
                // yes
                return true;
            }

            // if we are on Unix, and when we have set a task time limit, then the LARAVEL_START value is inaccurate
            // because there will always be a small but significant delay between `timeout` start time and PHP start time.
            // in this case, we will look at the pre-determined timer PID to ask about the actual elapsed time through the kernel's proc data
            // this method should be slower than the microtime method
            if (OsInfo::isUnix()) {
                // get time elapsed in seconds
                $tempOut = exec("ps -p {$this->timerProcID} -o etimes=");
                // this must exist (we are still running!), otherwise it indicates the kernel is broken and we can go grab a chicken dinner instead
                $timeElapsed = (int) $tempOut;
                unset($tempOut);
                // it seems like etimes can get random off-by-1 inaccuracies (e.g. timeout supposed to be 7, but etimes sees 6.99999... and prints "6")
                return $timeElapsed >= $this->timeLimit;
            }
        }

        // didn't see anything; assume is no
        return false;
    }
}

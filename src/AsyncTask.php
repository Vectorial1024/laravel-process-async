<?php

declare(strict_types=1);

namespace Vectorial1024\LaravelProcessAsync;

use Closure;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Laravel\SerializableClosure\SerializableClosure;
use LogicException;
use loophp\phposinfo\OsInfo;
use RuntimeException;

/**
 * The common handler of an AsyncTask; this can be a closure (will be wrapped inside AsyncTask) or an interface instance.
 */
class AsyncTask
{
    /**
     * The task to be executed in the background.
     * @var SerializableClosure|AsyncTaskInterface
     */
    private SerializableClosure|AsyncTaskInterface $theTask;

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
     * The string constant name for constant('LARAVEL_START'). Mainly to keep the code clean.
     * @var string
     */
    private const LARAVEL_START = "LARAVEL_START";

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
     */
    public function __construct(Closure|AsyncTaskInterface $theTask)
    {
        if ($theTask instanceof Closure) {
            // convert to serializable closure first
            $theTask = new SerializableClosure($theTask);
        }
        $this->theTask = $theTask;
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
        register_shutdown_function([$this, 'checkTaskTimeout']);
        if (OsInfo::isWindows()) {
            // windows can just use PHP's time limit
            set_time_limit($this->timeLimit);
        } else {
            // assume anything not Windows to be Unix
            // we already set it to kill this task after the timeout, so we just need to install a listener
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'pcntlGracefulExit']);
        }

        // then, execute the task itself
        if ($this->theTask instanceof SerializableClosure) {
            $innerClosure = $this->theTask->getClosure();
            $innerClosure();
            unset($innerClosure);
        } else {
            // must be AsyncTaskInterface
            $this->theTask->execute();
        }

        // todo what if want some "task complete" event handling?
        return;
    }

    /**
     * Starts this AsyncTask immediately in the background. A runner will then run this AsyncTask.
     * @return void
     */
    public function start(): void
    {
        // prepare the runner command
        $serializedTask = $this->toBase64Serial();
        $baseCommand = "php artisan async:run $serializedTask";

        // then, specific actions depending on the runtime OS
        if (OsInfo::isWindows()) {
            // basically, in windows, it is too tedioous to check whether we are in cmd or ps,
            // but we require cmd (ps won't work here), so might as well force cmd like this
            // windows has real max time limit
            $this->runnerProcess = Process::quietly()->start("cmd /c start /b $baseCommand");
            return;
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
            $timeoutClause = static::$timeoutCmdName . " {$this->timeLimit}";
        }
        $this->runnerProcess = Process::quietly()->start("nohup $timeoutClause $baseCommand >/dev/null 2>&1");
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
     * When the task reaches the time limit, the relevant handler will be called.
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
     * Sets this task to run forever with no time limit. Chainable.
     * @return AsyncTask $this for chaining.
     */
    public function withoutTimeLimit(): static
    {
        $this->timeLimit = null;
        return $this;
    }

    private function pcntlGracefulExit(): never
    {
        // just exit is ok
        // exit asap so that our error checking inside shutdown functions can take palce outside of the usual max_execution_time limit
        exit();
    }

    /**
     * Checks whether the task timed out, and if so, triggers the timeout handler.
     * 
     * This will check various kinds of timeouts.
     * 
     * This handles Windows timeouts.
     * @return void
     */
    protected function checkTaskTimeout(): void
    {
        // we perform a series of checks to see if this task has timed out
        $hasTimedOut = false;

        // external killing; could be normal Unix timeout SIG_TERM or manual Windows taskkill
        // Laravel Artisan very conveniently has a LARAVEL_START = microtime(true) to let us check time elapsed
        if ($this->laravelStartVal !== null) {
            // we know when we have started; this can be null when running some test cases
            $timeElapsed = microtime(true) - $this->laravelStartVal;
            if ($timeElapsed >= $this->timeLimit) {
                // timeout!
                $hasTimedOut = true;
            }
        }

        // runtime timeout triggers a PHP fatal error
        // check this
        $lastError = error_get_last();
        if ($lastError !== null && str_contains($lastError['message'], "Maximum execution time")) {
            // has error, and is timeout!
            $hasTimedOut = true;
        }

        // all checks concluded
        if (!$hasTimedOut) {
            // not timeout-related
            return;
        }
        // timeout!
        if ($this->theTask instanceof AsyncTaskInterface) {
            $this->theTask->handleTimeout();
        }
    }
}

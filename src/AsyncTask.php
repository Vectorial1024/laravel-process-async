<?php

namespace Vectorial1024\LaravelProcessAsync;

use Closure;
use Illuminate\Support\Facades\Process;
use Laravel\SerializableClosure\SerializableClosure;

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
     * Starts this AsyncTask immediately in the background.
     * @return void
     */
    public function start(): void
    {
        // assume unix for now
        $serializedTask = $this->toBase64Serial();
        Process::quietly()->start("php artisan async:run $serializedTask");
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
}

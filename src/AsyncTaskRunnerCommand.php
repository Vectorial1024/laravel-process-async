<?php

namespace Vectorial1024\LaravelProcessAsync;

use Illuminate\Console\Command;
use Opis\Closure\Security\SecurityException;

/**
 * The Artisan command to run AsyncTask. DO NOT USE DIRECTLY!
 */
class AsyncTaskRunnerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'async:run {task} {--id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs a background async task (DO NOT USE DIRECTLY)';

    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // first, unpack the task
        // (Symfony already safeguards the "task" argument to make it required)
        $theTask = $this->argument('task');
        try {
            $theTask = AsyncTask::fromBase64Serial($theTask);
        } catch (SecurityException $x) {
            // bad secret key; cannot verify sender identity
            $this->error("Unrecognized task giver is trying to start AsyncTaskRunner.");
            return self::FAILURE;
        }
        if ($theTask === null) {
            // bad underializing; probably bad data
            $this->error("Invalid task details!");
            return self::INVALID;
        }

        // the task type is correct; we can execute it!
        /** @var AsyncTask $theTask */
        $theTask->run();
        return self::SUCCESS;
    }
}

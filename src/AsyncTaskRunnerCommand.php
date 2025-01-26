<?php

namespace Vectorial1024\LaravelProcessAsync;

use Illuminate\Console\Command;

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
        $theTask = AsyncTask::fromBase64Serial($theTask);
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

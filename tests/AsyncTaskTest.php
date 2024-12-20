<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use Vectorial1024\LaravelProcessAsync\AsyncTask;

class AsyncTaskTest extends BaseTestCase
{
    // directly running the runner 

    public function testCanRunClosure()
    {
        // tests that our AsyncTask can run closures correctly.
        $testFileName = $this->getStoragePath("testClosure.txt");
        $message = "Hello world!";
        $task = new AsyncTask(function () use ($testFileName, $message) {
            $fp = fopen($testFileName, "w");
            fwrite($fp, $message);
            fflush($fp);
            fclose($fp);
        });
        $task->run();

        $this->assertFileExists($testFileName);
        $this->assertStringEqualsFile($testFileName, $message);

        unlink($testFileName);
    }
    
    public function testCanRunInterface()
    {
        // tests that our AsyncTask can run extending interfaces correctly.
        $testFileName = $this->getStoragePath("testClosure.txt");
        $message = "Hello world!";
        $dummyTask = new DummyAsyncTask($message, $testFileName);
        $task = new AsyncTask($dummyTask);
        $task->run();

        $this->assertFileExists($testFileName);
        $this->assertStringEqualsFile($testFileName, $message);

        unlink($testFileName);
    }

    // ---------

    // integration test with the cli artisan via a mocked artisan file, which tests various features of this library

    public function testAsyncBasic()
    {
        // tests that we can dispatch async tasks to the cli artisan
        $testFileName = $this->getStoragePath("testAsyncBasic.txt");
        $message = "Hello world!";
        $task = new AsyncTask(function () use ($testFileName, $message) {
            $fp = fopen($testFileName, "w");
            fwrite($fp, $message);
            fflush($fp);
            fclose($fp);
        });
        $task->start();

        // sleep a bit to wait for the async
        sleep(1);

        $this->assertFileExists($testFileName, "The async task probably did not run because its output file cannot be found.");
        $this->assertStringEqualsFile($testFileName, $message);

        unlink($testFileName);
    }

    public function testAsyncBackground()
    {
        // tests that the async really runs in the background: it should not block the main thread
        // test by starting a long sleep task and check the elapsed time in the main process
        // note: we cannot test the "nohup" part because we can't really kill phpunit and start it up again on demand
        $sleepDuration = 2; // how many seconds
        $task = new AsyncTask(function () use ($sleepDuration) {
            // just sleep long is ok
            sleep($sleepDuration);
        });
        // time it
        $timeBefore = microtime(true);
        $task->start();
        $timeAfter = microtime(true);
        $timeElapsed = $timeAfter - $timeBefore;
        $this->assertLessThan($sleepDuration, $timeElapsed, "The async task probably did not start in the background because the time taken to start it was too long.");
    }
}

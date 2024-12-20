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

        $this->assertFileExists($testFileName);
        $this->assertStringEqualsFile($testFileName, $message);

        unlink($testFileName);
    }
}

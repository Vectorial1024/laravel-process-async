<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use Vectorial1024\LaravelProcessAsync\AsyncTask;

class AsyncTaskTest extends BaseTestCase
{
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
}

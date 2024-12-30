<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use LogicException;
use RuntimeException;
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

    public function testConfigWithTimeLimit()
    {
        $task = new AsyncTask(fn() => null);

        // test +ve
        $timeLimit = random_int(1, 100);
        $task->withTimeLimit($timeLimit);
        $this->assertEquals($timeLimit, $task->getTimeLimit());

        // test 0; 0 makes no sense here
        $this->expectException(LogicException::class);
        $task->withTimeLimit(0);

        // test -ve; also makes no sense here
        $this->expectException(LogicException::class);
        $task->withTimeLimit(-1);
    }

    public function testConfigNoTimeLimit()
    {
        $task = new AsyncTask(fn() => null);
        $task->withoutTimeLimit();
        $this->assertNull($task->getTimeLimit());
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
        $this->sleep(1);

        $this->assertFileExists($testFileName, "The async task probably did not run because its output file cannot be found.");
        $this->assertStringEqualsFile($testFileName, $message);
        $this->assertNoNohupFile();

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

    public function testAsyncSilence()
    {
        // test that the async runner is really silent: e.g. it should not generate any nohup.out files
        // the intended way of using this library is to send all kinds of debug output to the Laravel logs
        // alternatively, laravel will simply catch exceptions and send them to the laravel logs
        $task = new AsyncTask(function () {
            // randomly throw exception
            throw new RuntimeException("random testing exception");
        });
        $task->start();
        $this->sleep(1);
        $this->assertNoNohupFile();
    }

    public function testAsyncTimeout()
    {
        // test that we can trigger the async task timeout
        $message = "timeout occured";
        $textFilePath = $this->getStoragePath("testAsyncTimeout.txt");
        $timeoutTask = new TestTimeoutNormalTask($message, $textFilePath);
        $task = new AsyncTask($timeoutTask);
        $task->withTimeLimit(1)->start();
        // we wait for it to timeout
        $this->sleep(2);
        // should have timed out
        $this->assertFileExists($textFilePath, "The async task probably did not trigger its timeout handler because its timeout output file is not found.");
        $this->assertStringEqualsFile($textFilePath, $message);
        $this->assertNoNohupFile();
    }
}

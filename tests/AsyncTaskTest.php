<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use InvalidArgumentException;
use LogicException;
use Opis\Closure\Security\SecurityException;
use RuntimeException;
use Vectorial1024\LaravelProcessAsync\AsyncTask;
use Vectorial1024\LaravelProcessAsync\AsyncTaskStatus;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\DummyAsyncTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\SleepingAsyncTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutENoticeTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutErrorTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutNoOpTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutNormalTask;
use function Opis\Closure\init;
use function Opis\Closure\set_security_provider;

class AsyncTaskTest extends BaseTestCase
{
    public function setUp(): void
    {
        // we need to reset the security key in case some of our tests change the secret key
        AsyncTask::loadSecretKey();
    }

    // directly running the runner

    public function testCanRunClosure()
    {
        // tests that our AsyncTask can run closures correctly.
        $testFileName = $this->getStoragePath("testClosure.txt");
        $message = "Hello world!";
        $task = new AsyncTask(function () use ($testFileName, $message) {
            file_put_contents($testFileName, $message);
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

    public function testCanVerifySender()
    {
        // test that, when given a serialized closure signed by us, we can correctly reproduce the closure
        $testSecretKey = "LaravelProcAsync";
        $testClosure = fn () => "Hello there!";
        // reset the security provider
        set_security_provider(null);
        init($testSecretKey);
        // serialize once
        $testTask = new AsyncTask($testClosure);
        $checkingString = $testTask->toBase64Serial();
        // then unserialize it
        $reproducedTask = AsyncTask::fromBase64Serial($checkingString);
        $this->assertTrue($reproducedTask instanceof AsyncTask);
    }

    public function testCannotVerifySender()
    {
        // test that, when given a serialized closure signed by Mallory, we can correctly reject the closure
        $testSecretKey = "LaravelProcAsync";
        $testMalloryKey = "HereComesDatMallory";
        $testClosure = fn () => "Hello there!";
        // reset the security provider
        // mallory appears!
        set_security_provider($testMalloryKey);
        // serialize once
        $testTask = new AsyncTask($testClosure);
        $checkingString = $testTask->toBase64Serial();
        // then unserialize it
        set_security_provider($testSecretKey);
        $this->expectException(SecurityException::class);
        // should throw
        $reproducedTask = AsyncTask::fromBase64Serial($checkingString);
        unset($reproducedTask);
    }

    // ---------

    // integration test with the cli artisan via a mocked artisan file, which tests various features of this library

    public function testAsyncBasic()
    {
        // tests that we can dispatch async tasks to the cli artisan
        $testFileName = $this->getStoragePath("testAsyncBasic.txt");
        @unlink($testFileName);
        $message = "Hello world!";
        $task = new AsyncTask(function () use ($testFileName, $message) {
            file_put_contents($testFileName, $message);
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
        @unlink($textFilePath);
        $task = new AsyncTask($timeoutTask);
        $task->withTimeLimit(1)->start();
        // we wait for it to timeout
        $this->sleep(0.3);
        $this->sleep(0.3);
        $this->sleep(0.3);
        $this->sleep(0.3);
        $this->sleep(0.3);
        $this->sleep(0.3);
        // should have timed out
        $this->assertFileExists($textFilePath, "The async task probably did not trigger its timeout handler because its timeout output file is not found.");
        $this->assertStringEqualsFile($textFilePath, $message);
        $this->assertNoNohupFile();
    }
    
    public function testAsyncTimeoutIgnoreErrors()
    {
        // test that the async timeout handler is not triggered due to other fatal errors
        $message = "timeout occured";
        $textFilePath = $this->getStoragePath("testAsyncTimeoutIgnoreErrors.txt");
        @unlink($textFilePath);
        $timeoutTask = new TestTimeoutErrorTask($message, $textFilePath);
        $task = new AsyncTask($timeoutTask);
        $task->withTimeLimit(2)->start();
        // we wait for it to timeout
        $this->sleep(2);
        // should have timed out
        $this->assertFileDoesNotExist($textFilePath, "The async task timeout handler was inappropriately triggered (PHP fatal errors should not trigger timeouts).");
        $this->assertNoNohupFile();
    }

    public function testAsyncTimeoutIgnoreNoProblem()
    {
        // test that the async timeout handler is not triggered when nothing happened
        $message = "timeout occured";
        $textFilePath = $this->getStoragePath("testAsyncTimeoutIgnoreNoProblem.txt");
        @unlink($textFilePath);
        $timeoutTask = new TestTimeoutNoOpTask($message, $textFilePath);
        $task = new AsyncTask($timeoutTask);
        $task->withTimeLimit(2)->start();
        // we wait for it to timeout
        $this->sleep(2);
        // should have timed out
        $this->assertFileDoesNotExist($textFilePath, "The async task timeout handler was inappropriately triggered (finishing a task before the time limit should not trigger timeouts).");
        $this->assertNoNohupFile();

        // repeat with no time limit
        @unlink($textFilePath);
        $task = new AsyncTask($timeoutTask);
        $task->withoutTimeLimit()->start();
        // we wait for it to timeout
        $this->sleep(0.5);
        // should have timed out
        $this->assertFileDoesNotExist($textFilePath, "The async task timeout handler was inappropriately triggered (tasks without time limits should not trigger timeouts).");
        $this->assertNoNohupFile();
    }
    
    public function testAsyncTimeoutIgnoreENotice()
    {
        // test that the async timeout handler is not triggered when there is an E_NOTICE error
        $message = "timeout occured";
        $textFilePath = $this->getStoragePath("testAsyncTimeoutIgnoreENotice.txt");
        @unlink($textFilePath);
        $timeoutTask = new TestTimeoutENoticeTask($message, $textFilePath);
        $task = new AsyncTask($timeoutTask);
        $task->withTimeLimit(2)->start();
        // we wait for it to timeout
        $this->sleep(2);
        // should have timed out
        $this->assertFileDoesNotExist($textFilePath, "The async task timeout handler was inappropriately triggered (E_NOTICE should not trigger timeouts).");
        $this->assertNoNohupFile();
    }

    public function testAsyncTaskID()
    {
        // test that we can correctly handle good and bad task IDs

        // no ID is ok
        $task = new AsyncTask(new SleepingAsyncTask());
        unset($task);

        // has ID is also ok
        $task = new AsyncTask(new SleepingAsyncTask(), taskID: "yeah");
        unset($task);
        $taskStatus = new AsyncTaskStatus("yeah");
        unset($taskStatus);

        // but blank ID is not allowed
        $this->expectException(InvalidArgumentException::class);
        $task = new AsyncTask(new SleepingAsyncTask(), taskID: "");
        unset($task);
        $this->expectException(InvalidArgumentException::class);
        $taskStatus = new AsyncTaskStatus("");
        unset($taskStatus);
    }

    public function testAsyncTaskNormalStatus()
    {
        // test that the task status reading is correct
        $taskID = "testingTask";
        $task = new AsyncTask(new SleepingAsyncTask(), taskID: $taskID);

        // we haven't started yet, so this should say false 
        $preStatus = new AsyncTaskStatus($taskID);
        $this->assertFalse($preStatus->isRunning());
        // note: since it detects false, it will always continue to say false
        $this->assertFalse($preStatus->isRunning());

        // now we start running the task
        $liveStatus = $task->start();

        // try to sleep a bit, to stabilize the test case
        $this->sleep(0.1);

        // the task is to sleep for some time, and then exit.
        // note: since checking the task statuses take some time, we cannot confirm the actual elapsed time of our tests,
        // and so "task ended" case is not testable
        $this->assertFalse($preStatus->isRunning(), "Stopped tasks should always report \"task stopped\".");
        $this->assertTrue($liveStatus->isRunning(), "Recently-started task does not report \"task running\".");
    }

    public function testAsyncTaskStatusWithTimeLimit()
    {
        // test that the task status reading is correct, even if we are using a time limit
        $taskID = "timeoutTestingTask";
        $task = new AsyncTask(new SleepingAsyncTask(), taskID: $taskID);

        // we haven't started yet, so this should say false 
        $preStatus = new AsyncTaskStatus($taskID);
        $this->assertFalse($preStatus->isRunning());
        // note: since it detects false, it will always continue to say false
        $this->assertFalse($preStatus->isRunning());

        // now we start running the task
        $liveStatus = $task->withTimeLimit(4)->start();

        // try to sleep a bit, to stabilize the test case
        $this->sleep(0.1);

        // the task is to sleep for some time, and then exit.
        // note: since checking the task statuses take some time, we cannot confirm the actual elapsed time of our tests,
        // and so "task ended" case is not testable
        $this->assertFalse($preStatus->isRunning(), "Stopped tasks should always report \"task stopped\".");
        $this->assertTrue($liveStatus->isRunning(), "Recently-started task does not report \"task running\".");
    }
}

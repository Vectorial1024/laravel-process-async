<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Vectorial1024\LaravelProcessAsync\AsyncTask;
use Vectorial1024\LaravelProcessAsync\AsyncTaskStatus;
use Vectorial1024\LaravelProcessAsync\FakeAsyncTaskStatus;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\DummyAsyncTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\SleepingAsyncTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutENoticeTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutErrorTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutNoOpTask;
use Vectorial1024\LaravelProcessAsync\Tests\Tasks\TestTimeoutNormalTask;

// a series of tests that ensure the fake tasks are indeed fake while still look like the same
class FakeAsyncTaskTest extends BaseTestCase
{
    public function testFakeTaskDoesNotRun()
    {
        // the fake task should not even run
        $testFileName = $this->getStoragePath("testFakeTaskDoesNotRun.txt");
        $task = new AsyncTask(new DummyAsyncTask("Hello world!", $testFileName));
        $fakeTask = $task->fake();

        // fake start it
        $fakeTask->start();
        sleep(1);
        // there should have no file outputs
        $this->assertFileDoesNotExist($testFileName);
    }

    public function testFakeTaskSameParams()
    {
        // the fake task should preserve its parameters
        $testFileName = $this->getStoragePath("testFakeTaskSameDetails.txt");
        $task = new AsyncTask(new DummyAsyncTask("Hello world!", $testFileName));
        $randomDuration = rand(1, 10);
        $task->withTimeLimit($randomDuration);

        // fake it...
        $fakeTask = $task->fake();
        // ...and the parameters stay the same
        $this->assertEquals($task->getTimeLimit(), $fakeTask->getTimeLimit());

        // it is difficult to test the task ID since it would be exposing something that should not be exposed, so we will just have to believe it.
    }

    public function testFakeTaskStatus()
    {
        $taskID = "testFakeTaskStatus";
        $taskStatus = new AsyncTaskStatus($taskID);
        $fakeTaskStatusFromFake = $taskStatus->fake();
        $fakeTaskStatusFromNew = new FakeAsyncTaskStatus($taskID);

        // should have same task ID
        $this->assertEquals($taskStatus->getEncodedTaskID(), $fakeTaskStatusFromFake->getEncodedTaskID());
        $this->assertEquals($taskStatus->getEncodedTaskID(), $fakeTaskStatusFromNew->getEncodedTaskID());
    }
}

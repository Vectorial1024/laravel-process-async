<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use Exception;
use Illuminate\Support\Facades\Artisan;
use stdClass;
use Vectorial1024\LaravelProcessAsync\AsyncTask;
use Vectorial1024\LaravelProcessAsync\AsyncTaskRunnerCommand;

class AsyncTaskRunnerTest extends BaseTestCase
{
    public function testNakedCommand()
    {
        // tests that it can cancel itself when it is run with nothing
        // there should be an exception from Symfony since we did not provide the required argument
        $this->expectException(Exception::class);
        Artisan::call(AsyncTaskRunnerCommand::class);
    }

    public function testInvalidSerialCommand()
    {
        // tests that it can cancel itself when it receives strange serialized code
        $statusCode = Artisan::call(AsyncTaskRunnerCommand::class, [
            'task' => "Hello World!",
        ]);
        $this->assertNotEquals(0, $statusCode);
    }

    public function testWrongTypeCommand()
    {
        // tests that it can cancel itself when it receives a valid PHP object, but is not the type it wants
        $badObject = new stdClass();
        $badObject->name = 'apple';
        $statusCode = Artisan::call(AsyncTaskRunnerCommand::class, [
            'task' => base64_encode(serialize($badObject)),
        ]);
        $this->assertNotEquals(0, $statusCode);
    }

    public function testValidCommand()
    {
        // tests that there are no exceptions when the given task is valid and throws no exceptions
        $nop = function () {
            // pass
            return;
        };
        $task = new AsyncTask($nop);
        $statusCode = Artisan::call(AsyncTaskRunnerCommand::class, [
            'task' => $task->toBase64Serial(),
        ]);
        $this->assertEquals(0, $statusCode);
    }
}

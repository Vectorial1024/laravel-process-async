<?php

namespace Vectorial1024\LaravelProcessAsync\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Orchestra\Testbench\TestCase;

/**
 * Base class for project test cases for some common test config.
 */
class BaseTestCase extends TestCase 
{
    protected function getPackageProviders($app)
    {
        // load required package providers for our library to work during testing
        return [
            \Vectorial1024\LaravelProcessAsync\ProcessAsyncServiceProvider::class
        ];
    }

    // ---

    /**
     * Returns the base path of this project (i.e., the directory of composer.json).
     * @return string
     */
    protected function getBasePath(): string
    {
        return dirname(__FILE__, 2);
    }

    /**
     * Returns the path for mocking the Laravel storage path.
     * @param string $fileName
     * @return string
     */
    protected function getStoragePath(string $fileName): string
    {
        return $this->getBasePath() . "/storage/$fileName";
    }

    /**
     * Sleeps for some time.
     * @param float $seconds The number of seconds.
     * @return void
     */
    protected function sleep(float $seconds): void
    {
        $wholeSeconds = (int) $seconds;
        $fractionalSeconds = $seconds - $wholeSeconds;
        if ($wholeSeconds > 0) {
            sleep($wholeSeconds);
        }
        if ($fractionalSeconds > 0) {
            usleep($fractionalSeconds * 1000000);
        }
    }

    /**
     * Asserts that the nohup.out file is not found in our project while running CI/CD. This checks that we are truly silencing the output of the task runner.
     * 
     * Applicable only in Unix systems; Windows systems will get a vacuous successful assertion on this.
     * @return void
     */
    protected function assertNoNohupFile(string $message = ''): void
    {
        $nohupFilePath = $this->getBasePath() . "/nohup.out";
        $this->assertFileDoesNotExist($nohupFilePath, "The async task did not run silently since the nohup.out file can be found.");
    }

    // ---

    public function call($method, $uri, $parameters = [], $files = [], $server = [], $content = null, $changeHistory = true)
    {
        // pass
        return;
    }

    public function be(Authenticatable $user, $driver = null)
    {
        // pass
        return;
    }

    public function seed($class = 'DatabaseSeeder')
    {
        // pass
        return;
    }

    public function artisan($command, $parameters = [])
    {
        // pass
        return;
    }
}

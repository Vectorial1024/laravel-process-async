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
            \Vectorial1024\LaravelProcessAsync\BackgroundAsyncServiceProvider::class
        ];
    }

    // ---

    /**
     * Returns the path for mocking the Laravel storage path.
     * @param string $fileName
     * @return string
     */
    protected function getStoragePath(string $fileName): string
    {
        return dirname(__FILE__, 2) . "/storage/$fileName";
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

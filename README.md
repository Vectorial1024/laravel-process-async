# laravel-process-async
[![Packagist License][packagist-license-image]][packagist-url]
[![Packagist Version][packagist-version-image]][packagist-url]
[![Packagist Downloads][packagist-downloads-image]][packagist-stats-url]
[![PHP Dependency Version][php-version-image]][packagist-url]
[![GitHub Repo Stars][github-stars-image]][github-repo-url]

Utilize Laravel Processes to run PHP code asynchronously, as if using Laravel Concurrency.

## What really is this?
[Laravel Processes](https://laravel.com/docs/10.x/processes) was first introduced in Laravel 10. This library wraps around `Process::start()` to let you execute code in the background to achieve async, albeit with some caveats:
- You may only execute PHP code
- Restrictions from `opis/closure` apply (see [their README](https://github.com/opis/closure))
- Hands-off execution: no built-in result-checking, check the results yourself (e.g. via database, file cache, etc)

This library internally uses an Artisan command to run the async code, which is similar to Laravel 11 [Concurrency](https://laravel.com/docs/11.x/concurrency).

## Why should I want this?
This library is very helpful for these cases:
- You want a cross-platform minimal-setup async for easy vertical scaling
- You want to start quick-and-dirty async tasks right now (e.g. prefetching resources, pinging remote, etc.)
  - Best is if your task only has very few lines of code
- Laravel 11 [Concurrency](https://laravel.com/docs/11.x/concurrency) is too limiting; e.g.:
  - You want to do something else while waiting for results
  - You want to conveniently limit the max (real) execution time of the concurrent tasks
- And perhaps more!

Of course, if you are considering extreme scaling (e.g. Redis queues in Laravel, multi-worker clusters, etc.) or guaranteed task execution, then this library is obviously not for you.

## Installation
via Composer:

```sh
composer require vectorial1024/laravel-process-async
```

This library supports Unix and Windows; see the Testing section for more details.

### Extra requirements for Unix
If you are on Unix, check that you also have the following:
- GNU Core Utilities (`coreutils`)
  - MacOS do `brew install coreutils`!
  - Other Unix distros should check if `coreutils` is preinstalled

## Change log
Please see `CHANGELOG.md`.

## Example code and features
Tasks can be defined as PHP closures, or (recommended) as an instance of a class that implements `AsyncTaskInterface`.

A very simple example task to write Hello World to a file:

```php
// define the task...
$target = "document.txt";
$task = new AsyncTask(function () use ($target) {
    file_put_contents($target, "Hello World!");
});

// if you are using interfaces, then it is just like this:
// $task = new AsyncTask(new WriteToFileTask($target, $message));

// then start it.
$task->start();

// the task is now run in another PHP process, and will not report back to this PHP process.
```

### Task time limits
You can set task time limits before you start them, but you cannot change them after the tasks are started. When the time limit is reached, the async task is killed.

The default time limit is 30 real seconds. You can also choose to not set any time limit, in this case the (CLI) PHP `max_execution_time` directive will control the time limit.

Note: `AsyncTaskInterface` contains an implementable method `handleTimeout` for you to define timeout-related cleanups (e.g. write to some log that the task has timed out). This method is still called when the PHP `max_execution_time` directive is triggered.

```php
// start with the default time limit...
$task->start();

// start task with a different time limit...
$task->withTimeLimit(15)->start();

// ...or not have any limits at all (beware of orphaned processes!)
$task->withoutTimeLimit()->start();
```

Some tips:
- Don't sleep too long! On Windows, timeout handlers cannot trigger while your task is sleeping.
  - Use short but frequent sleeps instead.
- Avoid using `SIGINT`! On Unix, this signal is reserved for timeout detection. 

### Task IDs
You can assign task IDs to tasks before they are run, but you cannot change them after the tasks are started. This allows you to track the statuses of long-running tasks across web requests.

By default, if a task does not has its user-specified task ID when starting, a ULID will be generated as its task ID.

```php
// create a task with a specified task ID...
$task = new AsyncTask(function () {}, "customTaskID");

// will return a status object for immediate checking...
$status = $task->start();

// in case the task ID was not given, what is the generated task ID?
$taskID = $status->taskID;

// is that task still running?
$status->isRunning();

// when task IDs are known, task status objects can be recreated on-the-fly
$anotherStatus = new AsyncTaskStatus("customTaskID");
```

Some tips:
- Task IDs can be optional (i.e. `null`) but CANNOT be blank (i.e. `""`)!
- If multiple tasks are started with the same task ID, then the task status object will only track the first task that was started
- Known issue: on Windows, checking task statuses can be slow (about 0.5 - 1 seconds) due to underlying bottlenecks

### Securing the task runners
The way this library works means that attackers (or other unwanted parties) may simply craft malicious commands that mimic legitimate usage of this library.

To secure the task runners from being started illegitimately, you may configure the `.env` file to contain the following key:

```
PROCESS_ASYNC_SECRET_KEY=[your secret key here]
```

You may need to clear your Laravel optimisation cache after changing this value.

The contents of the async tasks will be signed by this secret key, so that this library can know whether the tasks are started by this library itself or someone else.

### Fake Objects
Fake objects are available for users to simulate async task behaviors without actually starting async tasks. This should be helpful when writing tests that attempts to react to task statuses.

The following fake objects are available:

- `FakeAsyncTask`: a fake of `AsyncTask`
- `FakeAsyncTaskStatus`: a fake of `AsyncTaskStatus`

Notably, they can be used like this:

```php
// you may obtain the task status in the usual way...
$fakeTask = new FakeAsyncTask(/* ... */, taskID: "TestingTask");
$fakeStatus = $fakeTask->start();

// ...or just construct it directly
$fakeStatusDirect = new FakeAsyncTaskStatus("TestingTask");
// both are the same
assert($fakeStatus == $fakeStatusDirect); // passes

// in your test code, fake task status can be used just like the normal task status:
$fakeStatus->isRunning(); // default returns true

// note: FakeAsyncTaskStatus defaults to "is running" when constructed
// to simulate "task ended", simply do:
$fakeStatus->fakeStopRunning();
// then, the following will return false
$fakeStatus->isRunning(); // returns false
```

## Testing
PHPUnit via Composer script:
```sh
composer run-script test
```

<!---
We also include MacOS on top of Ubuntu so that the devs (usually using Mac) can see for themselves it also works.
--->

Latest cross-platform testing results:
|Runtime|MacOS|Ubuntu|Windows|
|---|---|---|---|
|Laravel 10 (PHP 8.1)|skipped*|skipped*|skipped*|
|Laravel 11 (PHP 8.2)|[![Build-M-L11][build-m-l11-image]][build-m-l11-url]|[![Build-U-L11][build-u-l11-image]][build-u-l11-url]|[![Build-W-L11][build-w-l11-image]][build-w-l11-url]|
|Laravel 12 (PHP 8.3)|[![Build-M-L12][build-m-l12-image]][build-m-l12-url]|[![Build-U-L12][build-u-l12-image]][build-u-l12-url]|[![Build-W-L12][build-w-l12-image]][build-w-l12-url]|
|Laravel 13 (PHP ???)|🛠️|🛠️|🛠️|

\*Note: tests for these Laravel versions are skipped because they have old `artisan` file contents:
- It is difficult to mock multi-version `artisan` files for different Laravel versions (see https://github.com/Vectorial1024/laravel-process-async/issues/6).
- It is rare for the `artisan` file at Laravel to be updated
- The actual behavior is expected to be the same.

[packagist-url]: https://packagist.org/packages/vectorial1024/laravel-process-async
[packagist-stats-url]: https://packagist.org/packages/vectorial1024/laravel-process-async/stats
[github-repo-url]: https://github.com/Vectorial1024/laravel-process-async

[build-m-l11-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/macos_l11.yml
[build-m-l11-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/macos_l11.yml?style=plastic
[build-m-l12-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/macos_l12.yml
[build-m-l12-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/macos_l12.yml?style=plastic

[build-u-l11-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/ubuntu_l11.yml
[build-u-l11-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/ubuntu_l11.yml?style=plastic
[build-u-l12-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/ubuntu_l12.yml
[build-u-l12-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/ubuntu_l12.yml?style=plastic

[build-w-l11-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/windows_l11.yml
[build-w-l11-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/windows_l11.yml?style=plastic
[build-w-l12-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/windows_l12.yml
[build-w-l12-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/windows_l12.yml?style=plastic

[packagist-license-image]: https://img.shields.io/packagist/l/vectorial1024/laravel-process-async?style=plastic
[packagist-version-image]: https://img.shields.io/packagist/v/vectorial1024/laravel-process-async?style=plastic
[packagist-downloads-image]: https://img.shields.io/packagist/dm/vectorial1024/laravel-process-async?style=plastic
[php-version-image]: https://img.shields.io/packagist/dependency-v/vectorial1024/laravel-process-async/php?style=plastic&label=PHP
[github-stars-image]: https://img.shields.io/github/stars/vectorial1024/laravel-process-async

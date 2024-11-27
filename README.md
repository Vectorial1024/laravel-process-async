# laravel-process-async
Utilize Laravel Processes to run PHP code asynchronously.

## What really is this?
[Laravel Processes](https://laravel.com/docs/10.x/processes) was first introduced in Laravel 10. This library wraps around `Process::start()` to let you execute code in the background to achieve async, albeit with some caveats:
- You may only execute PHP code
- Restrictions from `laravel/serializable-closure` apply (see (their README)[https://github.com/laravel/serializable-closure])
- Silent execution: no built-in result-checking, check the results yourself (e.g. via database)

## Installation
(WIP)

## Change log
Please see `CHANGELOG.md`.

## Example code
Tasks can be defined as PHP closures, or (recommended) as an instance of a class that implements `AsyncTaskInterface`.

A very simple example task to write Hello World to a file:

```php
// define the task...
$target = "document.txt";
$task = new AsyncTask(function () use ($target) {
    $fp = fopen($target, "w");
    fwrite($fp, "Hello World!!");
    fflush($fp);
    fclose($fp);
});

// then start it.
$task->start();

// the task is now run in another PHP process, and will not report back to this PHP process.
```

## Testing
PHPUnit via Composer script:
```sh
composer run-script test
```

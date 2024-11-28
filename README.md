# laravel-process-async
[![Packagist License][packagist-license-image]][packagist-url]
[![Packagist Version][packagist-version-image]][packagist-url]
[![Packagist Downloads][packagist-downloads-image]][packagist-stats-url]
[![PHP Dependency Version][php-version-image]][packagist-url]
[![GitHub Repo Stars][github-stars-image]][github-repo-url]

Utilize Laravel Processes to run PHP code asynchronously.

## What really is this?
[Laravel Processes](https://laravel.com/docs/10.x/processes) was first introduced in Laravel 10. This library wraps around `Process::start()` to let you execute code in the background to achieve async, albeit with some caveats:
- You may only execute PHP code
- Restrictions from `laravel/serializable-closure` apply (see [their README](https://github.com/laravel/serializable-closure))
- Silent execution: no built-in result-checking, check the results yourself (e.g. via database)

## Installation
via Composer:

```sh
composer require vectorial1024/laravel-process-async
```

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

Latest results of cross-platform testing:
|Runtime|Ubuntu|Windows|
|---|---|---|
|Laravel 10 (PHP 8.1)|[![Build-U-L10-PHP80100][build-u-l10-php80100-image]][build-u-l10-php80100-url]|🛠️|
|Laravel 11 (PHP 8.2)|[![Build-U-L11-PHP80200][build-u-l11-php80200-image]][build-u-l11-php80200-url]|🛠️|
|Laravel 12 (PHP ???)|🛠️|🛠️|

[packagist-url]: https://packagist.org/packages/vectorial1024/laravel-process-async
[packagist-stats-url]: https://packagist.org/packages/vectorial1024/laravel-process-async/stats
[github-repo-url]: https://github.com/Vectorial1024/laravel-process-async

[build-u-l10-php80100-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/ubuntu_l10.yml
[build-u-l10-php80100-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/ubuntu_l10.yml?style=plastic
[build-u-l11-php80200-url]: https://github.com/Vectorial1024/laravel-process-async/actions/workflows/ubuntu_l10.yml
[build-u-l11-php80200-image]: https://img.shields.io/github/actions/workflow/status/Vectorial1024/laravel-process-async/ubuntu_l10.yml?style=plastic

[packagist-license-image]: https://img.shields.io/packagist/l/vectorial1024/laravel-process-async?style=plastic
[packagist-version-image]: https://img.shields.io/packagist/v/vectorial1024/laravel-process-async?style=plastic
[packagist-downloads-image]: https://img.shields.io/packagist/dm/vectorial1024/laravel-process-async?style=plastic
[php-version-image]: https://img.shields.io/packagist/dependency-v/vectorial1024/laravel-process-async/php?style=plastic&label=PHP
[github-stars-image]: https://img.shields.io/github/stars/vectorial1024/laravel-process-async

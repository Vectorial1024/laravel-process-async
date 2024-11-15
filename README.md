# laravel-process-async
Utilize Laravel Processes to run PHP code asynchronously.

## What really is this?
[Laravel Processes](https://laravel.com/docs/10.x/processes) was first introduced in Laravel 10. This library wraps around `Process::start()` to let you execute code in the background to achieve async, albeit with some caveats:
- You may only execute PHP code
- Restrictions from SC apply (see)
- Silent execution: no built-in result-checking, check the results yourself (e.g. via database)

## Installation
(WIP)

## Change log
Please see `CHANGELOG.md`.

## Example code
(WIP)

## Testing
PHPUnit via Composer script:
```sh
composer run-script test
```

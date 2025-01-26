# Change Log of `laravel-process-async`
Note: you may refer to `README.md` for description of features.

## Dev (WIP)
- Task IDs can be given to tasks (generated or not) (https://github.com/Vectorial1024/laravel-process-async/issues/5)
- Updated to use `opis/closure` 4.0 for task details serialization (https://github.com/Vectorial1024/laravel-process-async/issues/12)
  - Technically a breaking internal change, but no code change expected and downtime is expected to be almost negligible

## 0.2.0 (2025-01-04)
- Task runners are now detached from the task giver (https://github.com/Vectorial1024/laravel-process-async/issues/7)
- Task runners can now have time limits (https://github.com/Vectorial1024/laravel-process-async/issues/1)

## 0.1.0 (2024-12-02)
Initial release:
- Run PHP code in parallel via Laravel Process (see `README.md` for more details)

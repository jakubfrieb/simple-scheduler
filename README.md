# Simple Scheduler

A lightweight PHP task scheduler that helps you manage recurring tasks efficiently.

## MoSCoW 

#### Must:
* simple scheduler is not releasing symfony lock, another instance, src/Adapter/Mutex/SymfonyLockMutex.php:46 $this->locks is empty
#### Should:
* be able to pass if task can be overlapping
#### Could:
#### Will not:

### Known bugs:
* default mutex is not default from crunz/laravel but own file
* simple scheduler is not releasing symfony lock, wrapper as another instance, src/Adapter/Mutex/SymfonyLockMutex.php:46 $this->locks is empty
* laravel scheduler, is not using laravel task adapter, is using own task adapter (because init is in laravel scheduler)
* fixed - laravel scheduler, is not using our mutex
* laravel scheduler skips locked tasks, but not reflect it as skipped

## Features

- Schedule tasks with various frequencies (daily, hourly, every minute)
- Run tasks at specific times
- Monitor task execution status and results
- Track execution history with output capture
- Simple command-line interface
- Multiple scheduler backends (Simple, Crunz, Laravel)
- Flexible mutex implementations for controlling concurrent task execution

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/simple-scheduler.git
cd simple-scheduler
```

2. Install dependencies:
```bash
composer install
```

## TLDR - Quick Start

Add a task to run every minute (actually each run of `php bin/run-tasks.php`)
For different scheduler you can set `SCHEDULER_TYPE` to `simple`,`crunz` or `laravel`
If you want override default mutex you can set `SCHEDULER_MUTEX_TYPE` to `file`,`database` or `symfony` if empty will be used default from crunz/laravel
```bash
SCHEDULER_TYPE="simple" php bin/add-task.php "ping -c5 www.seznam.cz" "everyMinute" "Sample task"
```

Run all due tasks:
```bash
SCHEDULER_TYPE="simple" php bin/run-tasks.php
```

## Command Line Tools

The scheduler provides two main entry points:

### 1. Task Management

```bash
php bin/add-task.php "command" "frequency" ["description"]
```

Supported frequencies:
- `daily` - Run once per day
- `hourly` - Run once per hour
- `everyMinute` - Run every minute
- `at:HH:MM` - Run at specific time (24-hour format)

Examples:
```bash
# Run a script daily
php bin/add-task.php "php /path/to/script.php" "daily" "Daily backup"

# Run a command hourly
php bin/add-task.php "curl https://example.com/api" "hourly" "API check"

# Run at specific time
php bin/add-task.php "php /path/to/report.php" "at:08:00" "Morning report"
```

### 2. Task Execution

```bash
php bin/run-tasks.php
```

This will:
- Find all tasks that are due to run
- Execute them in the background
- Track their execution status
- Output results of execution

## Additional Commands

### Listing Tasks

View all scheduled tasks:

```bash
php bin/simple/list-tasks.php
```

Displays a formatted table with task ID, frequency, command, and description.

### Removing Tasks

Remove a task by ID:

```bash
php bin/simple/remove-task.php <task_id>
```

Use the `--force` flag to skip confirmation:

```bash
php bin/simple/remove-task.php <task_id> --force
```

### Clearing Execution History

Clear the task execution history:

```bash
php bin/simple/clear-executions.php
```

This removes all past execution records while preserving the task definitions.

## Architecture

### Scheduler Backends

The scheduler supports multiple backends through adapters:

- `simple`: Default lightweight scheduler with SQLite storage
- `crunz`: Uses the Crunz library for more advanced scheduling
- `laravel`: Leverages Laravel's scheduling capabilities

You can select the scheduler backend using the `SCHEDULER_TYPE` environment variable:

```bash
SCHEDULER_TYPE=crunz php bin/run-tasks.php
```

### Mutex Implementations

To prevent concurrent execution of the same task, the scheduler supports multiple mutex implementations:

- `file`: Uses file locking (default)
- `database`: Uses database locking
- `symfony`: Uses Symfony's Lock component

You can select the mutex implementation using the `SCHEDULER_MUTEX_TYPE` environment variable:

```bash
SCHEDULER_MUTEX_TYPE=database php bin/run-tasks.php
```

Each scheduler backend can either use its native mutex implementation or one of the custom implementations provided by Simple Scheduler.

## Requirements

- PHP 7.4 or higher
- Composer
- SQLite (for task storage)

## License

This project is open source and available under the MIT License.

# Yii2 Scheduler

[![License](https://img.shields.io/github/license/laxity7/yii2-scheduler.svg)](https://github.com/laxity7/yii2-scheduler/blob/master/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/laxity7/yii2-scheduler.svg)](https://packagist.org/packages/laxity7/yii2-scheduler)
[![Total Downloads](https://img.shields.io/packagist/dt/laxity7/yii2-scheduler.svg)](https://packagist.org/packages/laxity7/yii2-scheduler)

A lightweight, dependency-free task scheduler for the Yii2 framework.

This component provides a fluent, expressive API to define scheduled jobs directly within your Yii2 application. It's designed to be flexible and robust, using
a configurable mutex to prevent task overlaps and ensuring that long-running jobs don't start again before they have finished.

## Features

- **Expressive, Fluent API:** Define schedules with readable methods like `->dailyAt('13:00')`, `->everyFiveMinutes()`, or `->weekly()`.
- **Parameter Passing:** Pass parameters to your console commands and callbacks.
- **Timezone Support:** Run tasks in specific timezones, either globally or on a per-task basis.
- **Optional Locking:** By default, tasks can overlap. Use the `withoutOverlapping()` method to ensure a task runs only one instance at a time.
- **Decoupled Architecture:** Uses a `ScheduleKernelInterface` to separate the scheduler's logic from your application's task definitions.
- **Configurable Mutex:** Utilizes any mutex component supported by Yii2 (`FileMutex`, `redis\Mutex`, `MysqlMutex`, etc.).
- **Two Execution Modes:** Run as a persistent background worker using **Supervisor** or via a traditional **cron** entry.

---

## Installation

Install via composer

```shell
composer require laxity7/yii2-scheduler
```

## Configuration

1. **Create Kernel:** Create custom `Kernel` class in your application, e.g., `app\schedule\Kernel.php`. This class should implement the
   `Laxity7\Yii2\Components\Scheduler\ScheduleKernelInterface`.

2. **Configure Component:** Register the `scheduler` in your `config/console.php`.

```php
   'bootstrap' => [
       ... // other components
       'scheduler',
   ],
   'components' => [
       'scheduler' => [
           'class' => \Laxity7\Yii2\Components\Scheduler\Scheduler::class,
           'kernelClass' => \app\schedule\Kernel::class, // Your custom Kernel class
           
            // Optional: Set a global timezone for all tasks.
            // If not set, Yii::$app->timeZone will be used.
            'timeZone' => 'UTC',
            
            // by default, uses a FileMutex to prevent overlapping tasks.
   
            // optional: define a mutex component to acquire a lock
            // while executing tasks so only one execution of schedule tasks
            // can be running at a time.
            'mutex' => [
                'class' => \yii\redis\Mutex::class,
            ], 
            
            // OR optionally reference an existing application mutex component,
            // for example, one named "mutex":
            // 'mutex' => 'mutex',
       ],
   ],
   ```

---

## Defining Schedules

Define all tasks in your `app\schedule\Kernel` class within the `schedule()` method.

```php
namespace app\schedule;

use app\services\ReportService; // Assuming such a service exists
use Laxity7\Yii2\Components\Scheduler\ScheduleKernelInterface;
use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use Yii;

/**
 * Class Kernel
 * The central place for defining all scheduled tasks.
 */
class Kernel implements ScheduleKernelInterface
{
    /**
     * Defines the application's command schedule.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // --- Example 1: Database Backup ---
        // Runs a console command every night at 02:00.
        // This is a critical and potentially long-running task, so overlapping is prevented.
        $schedule->command('backup/create')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        // --- Example 2: Clean Temporary Files ---
        // Runs a simple callback every hour to clean the runtime/tmp directory.
        $schedule->call(function () {
            $tmpPath = Yii::getAlias('@runtime/tmp');
            if (!is_dir($tmpPath)) {
                return;
            }
            // Find and delete files older than 1 day
            $files = glob($tmpPath . '/*');
            $now = time();
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) >= 60 * 60 * 24) {
                    unlink($file);
                }
            }
            Yii::info('Temporary files cleaned up.', 'scheduler');
        })->hourly();

        // --- Example 3: Notify Inactive Users ---
        // Runs a command with parameters to notify users who haven
        // for more than 30 days.
        // The console command would look like: `php yii user/notify --days=30`
        $schedule->command('user/notify')
                 ->withParameters(['days' => 30])
                 ->dailyAt('09:00');

        // --- Example 4: Generate a Report via a Service Class ---
        // Calls a method on a service class. The Yii DI container will automatically
        // create an instance of ReportService.
        // This task is also protected from overlapping.
        $schedule->call([ReportService::class, 'generateDailySummary'])
                 ->dailyAt('00:10')
                 ->withoutOverlapping();
                 
        // --- Example 5: Timezone-Specific Task ---
        // Runs a command daily at 08:00 in a specific timezone.
        $schedule->command('reports/generate-eu')
                 ->dailyAt('08:00')
                 ->timeZone('Europe/Berlin');
    }
}
```

### Preventing Task Overlaps

By default, a task can be started even if the previous instance is still running. To prevent this, use the `withoutOverlapping()` method. This is highly
recommended for long-running tasks.

```php
// This task is guaranteed to only have one instance running at a time.
$schedule->command('reports/generate-monthly')
         ->monthly()
         ->withoutOverlapping();
```

### Passing Parameters

You can pass an array of parameters to both console commands and callbacks using the `withParameters()` method.

**For Console Commands:**
The parameters will be passed to your command's `action...()` method.

```php
// In Kernel.php
$schedule->command('user/notify')
         ->withParameters(['--type=inactive', 50]) // Corresponds to actionNotify($type, $limit = 100)
         ->daily();

// In commands/UserController.php
// public function actionNotify($type, $limit = 100) { ... }
```

**For Callbacks:**
The parameters will be passed to your callable function.

```php
use app\services\BillingService;

// Using DI to inject a service, and passing a scalar parameter
$schedule->call(function (BillingService $billing, $accountId) {
    $billing->processAccount($accountId);
})
->withParameters(['accountId' => 12345])
->hourly();
```

### Timezone Handling

You can control the timezone for your scheduled tasks at two levels:

1. **Global Timezone (in `config/console.php`):**
   Set the `timeZone` property on the `scheduler` component. All tasks will use this timezone by default. If this is not set, `Yii::$app->timeZone` is used.

   ```php
   'scheduler' => [
       'class' => \Laxity7\Yii2\Components\Scheduler\Scheduler::class,
       'kernelClass' => \app\schedule\Kernel::class,
       'timeZone' => 'America/New_York', // All tasks run on this timezone
   ],
   ```

2. **Per-Task Timezone:**
   You can override the global timezone for a specific task using the `timeZone()` method. This is useful for tasks that must run based on a specific region's
   local time.

   ```php
   // This task will run at 09:00 London time, regardless of the global setting.
   $schedule->command('billing/process-uk')
            ->dailyAt('09:00')
            ->timeZone('Europe/London');
   ```

### Other Examples

```php
// Run a standard console command
$schedule->command('backup/create')->dailyAt('02:30')->withoutOverlapping();

// Run a controller action
use app\controllers\FooController;
$schedule->call([FooController::class, 'actionBar'])->daily();
```

---

## Running The Scheduler

### 1. Using Supervisor (Recommended)

Create a Supervisor configuration file (e.g., `/etc/supervisor/conf.d/yii-scheduler.conf`):

```ini
[program:yii-scheduler]
command = php /path/to/your/project/yii scheduler/listen
autostart = true
autorestart = true
user = www-data
...
```

### 2. Using Cron

Add a single cron entry to your server:

```crontab
* * * * * cd /path/to/your/project && php yii scheduler/run >> /dev/null 2>&1
```

---

## Inspecting Schedules

To see a list of all registered tasks, their schedules, and when they are next due to run, use the `scheduler/list` command:

```shell
php yii scheduler/list
```

The output will show the cron expression, the task's specific timezone (or `(app)` if using the default), the next run time in your application's local
timezone, and a human-readable "diff" (e.g., `(in 1h 5m)`).

```
Scheduled Tasks List
Expression                TimeZone          Next Run Time                                     Description
-----------------------   ---------------   -----------------------------------------------   -----------------------------------
0 2 * * * (app)             2025-11-12 02:00:00 (in 2h 30m)                   yii backup/create
0 8 * * * Europe/Berlin     2025-11-12 10:00:00 (in 10h 30m)                  yii reports/generate-eu
* * * * * Europe/London     2025-11-12 00:01:00 (in 31m)                      yii billing/process-uk
```

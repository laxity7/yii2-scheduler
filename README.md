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
- **Optional Locking:** By default, tasks can overlap. Use the `withoutOverlapping()` method to ensure a task runs only one instance at a time.
- **Decoupled Architecture:** Uses a `KernelScheduleInterface` to separate the scheduler's logic from your application's task definitions.
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
   `Laxity7\Yii2\Components\Scheduler\KernelScheduleInterface`.

```php
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
use Laxity7\Yii2\Components\Scheduler\KernelScheduleInterface;
use Laxity7\Yii2\Components\Scheduler\Schedule;
use Yii;

/**
 * Class Kernel
 * The central place for defining all scheduled tasks.
 */
class Kernel implements KernelInterface
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
        // Runs a command with parameters to notify users who haven't logged in
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

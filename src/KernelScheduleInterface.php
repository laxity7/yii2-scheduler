<?php

namespace Laxity7\Yii2\Components\Scheduler;

use Laxity7\Yii2\Components\Scheduler\Components\Schedule;

interface KernelScheduleInterface
{
    /**
     * Schedules tasks to be executed periodically.
     * This method is typically used to define cron jobs or other scheduled tasks
     * that should run at specified intervals.
     *
     * examples:
     *
     *  --- Example 1: Launch of the console team ---
     * This is a preferred way for any complex logic.
     * First, you create a command (for example, PHP YII Backup/Create),
     * and then register it here.
     *
     * Launch the 'Cache/Flush -ell' command every day at 03:00.
     * $schedule->command('cache/flush-all')->dailyAt('03:00');
     *
     * Launch the command 'Backup/Create' every Monday at 01:00.
     * weekly() method by default launches on Sunday (0),
     * Therefore, we use cron () for accurate tuning.
     * $schedule->command('backup/create')->cron('0 1 * * 1');
     *
     * --- Example 2: launching anonymous function (callback) ---
     * Suitable for quick and simple tasks.
     *
     * Every 5 minutes write a message in the log.
     * $schedule->call(static function () {
     *     $message = "Health check: " . date('Y-m-d H:i:s') . "\n";
     *     file_put_contents(Yii::getAlias('@runtime/logs/system-check.log'), $message, FILE_APPEND);
     * )->everyFiveMinutes();
     *
     * --- Example 3: Different intervals ---
     * Launch the team every hour.
     * $schedule->command('reports/generate-hourly')->hourly();
     *
     * Launch the team every 15 minutes.
     * $schedule->command('queue/process')->everyFifteenMinutes();
     */
    public function schedule(Schedule $schedule): void;
}

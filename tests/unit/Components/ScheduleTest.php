<?php

namespace tests\unit\Components;

use DateTime;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Laxity7\Yii2\Components\Scheduler\Components\Schedule
 */
class ScheduleTest extends TestCase
{
    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Schedule::command
     */
    public function testDueTasks(): void
    {
        $schedule = new Schedule();
        $schedule->command('command1')->cron('5 * * * *'); // Due
        $schedule->command('command2')->cron('10 * * * *'); // Not due

        $parser = new CronExpressionParser();
        $date = new DateTime('2025-01-01 12:05:00');

        $dueTasks = $schedule->dueTask($parser, $date);

        self::assertCount(1, $dueTasks);
        self::assertEquals('command1', $dueTasks[0]->command);
    }
}

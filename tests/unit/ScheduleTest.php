<?php

namespace tests\unit;

use DateTime;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{
    public function testDueEvents(): void
    {
        $schedule = new Schedule();
        $schedule->command('command1')->cron('5 * * * *'); // Due
        $schedule->command('command2')->cron('10 * * * *'); // Not due

        $parser = new CronExpressionParser();
        $date = new DateTime('2025-01-01 12:05:00');

        $dueEvents = $schedule->dueEvents($parser, $date);

        $this->assertCount(1, $dueEvents);
        $this->assertEquals('command1', $dueEvents[0]->command);
    }
}

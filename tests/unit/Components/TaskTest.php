<?php

namespace tests\unit\Components;

use Laxity7\Yii2\Components\Scheduler\Components\Task;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task
 */
class TaskTest extends TestCase
{
    /**
     * A dummy method for testing static callables.
     */
    public static function myStaticCallbackMethod(): void
    {
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::cron
     */
    public function testCron(): void
    {
        $task = new Task('test/command');
        $task->cron('1 2 3 4 5');
        self::assertEquals('1 2 3 4 5', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::everyMinute
     */
    public function testEveryMinute(): void
    {
        $task = new Task('test/command');
        $task->everyMinute();
        self::assertEquals('* * * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::everyNMinutes
     */
    public function testEveryNMinutes(): void
    {
        $task = new Task('test/command');
        $task->everyNMinutes(15);
        self::assertEquals('*/15 * * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::everyFiveMinutes
     */
    public function testEveryFiveMinutes(): void
    {
        $task = new Task('test/command');
        $task->everyFiveMinutes();
        self::assertEquals('*/5 * * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::hourly
     */
    public function testHourly(): void
    {
        $task = new Task('test/command');
        $task->hourly();
        self::assertEquals('0 * * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::everyNHours
     */
    public function testEveryNHours(): void
    {
        $task = new Task('test/command');
        $task->everyNHours(3);
        self::assertEquals('0 */3 * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::daily
     */
    public function testDaily(): void
    {
        $task = new Task('test/command');
        $task->daily();
        self::assertEquals('0 0 * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::dailyAt
     */
    public function testDailyAt(): void
    {
        $task = new Task('test/command');
        $task->dailyAt('14:35');
        self::assertEquals('35 14 * * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::weekly
     */
    public function testWeekly(): void
    {
        $task = new Task('test/command');
        $task->weekly();
        self::assertEquals('0 0 * * 0', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::weeklyOn
     */
    public function testWeeklyOn(): void
    {
        $task = new Task('test/command');
        $task->weeklyOn(3, '12:15');
        self::assertEquals('15 12 * * 3', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::monthly
     */
    public function testMonthly(): void
    {
        $task = new Task('test/command');
        $task->monthly();
        self::assertEquals('0 0 1 * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::monthlyOn
     */
    public function testMonthlyOn(): void
    {
        $task = new Task('test/command');
        $task->monthlyOn();
        self::assertEquals('1 0 1 * *', $task->expression);

        $task = new Task('test/command');
        $task->monthlyOn(25, '15:30');
        self::assertEquals('30 15 25 * *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::yearly
     */
    public function testYearly(): void
    {
        $task = new Task('test/command');
        $task->yearly();
        self::assertEquals('0 0 1 1 *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::yearlyOn
     */
    public function testYearlyOn(): void
    {
        $task = new Task('test/command');
        $task->yearlyOn();
        self::assertEquals('1 0 1 1 *', $task->expression);

        $task = new Task('test/command');
        $task->yearlyOn(12, 31, '23:59');
        self::assertEquals('59 23 31 12 *', $task->expression);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::withParameters
     */
    public function testWithParameters(): void
    {
        $task = new Task('test/command');
        $params = ['param1' => 'value1', 'param2' => 123];
        $task->withParameters($params);
        self::assertEquals($params, $task->parameters);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::withoutOverlapping
     */
    public function testWithoutOverlapping(): void
    {
        $task = new Task('test/command');
        self::assertFalse($task->withoutOverlapping);
        $task->withoutOverlapping();
        self::assertTrue($task->withoutOverlapping);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::getName
     */
    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\Task::getName
     */
    public function testGetName(): void
    {
        // Command
        $taskCommand = new Task('test/command');
        self::assertEquals('yii test/command', $taskCommand->getName());

        // String callback
        $taskStringCallback = new Task('my_function');
        self::assertEquals('yii my_function', $taskStringCallback->getName());

        // Array callback (instance method)
        $taskArrayCallback = new Task([$this, 'testDailyAt']);
        self::assertEquals(self::class . '::testDailyAt', $taskArrayCallback->getName());

        // Static array callback
        $taskStaticCallback = new Task([self::class, 'myStaticCallbackMethod']);
        self::assertEquals(self::class . '::myStaticCallbackMethod', $taskStaticCallback->getName());

        // Closure callback
        $taskClosure = new Task(function () {
        });
        self::assertMatchesRegularExpression('/^Closure\(.+?:\d+\)$/', $taskClosure->getName());

        // Invokable object callback
        $taskInvokable = new Task(
            new class {
                public function __invoke(): void
                {
                }
            }
        );
        self::assertMatchesRegularExpression('/^Invokable\(.+?:\d+\)$/', $taskInvokable->getName());
    }
}

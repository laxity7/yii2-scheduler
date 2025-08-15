<?php

namespace tests\unit;

use Laxity7\Yii2\Components\Scheduler\Scheduler;
use tests\TestCase;
use tests\unit\mocks\TestController;
use tests\unit\mocks\TestKernel;
use Yii;

class SchedulerTest extends TestCase
{
    private Scheduler $scheduler;
    private TestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();

        // Reset the static callback before each test
        TestKernel::$scheduleCallback = null;

        $this->scheduler = Yii::$app->scheduler;
        $this->controller = new TestController('test', Yii::$app);
        $this->scheduler->setController($this->controller);
    }

    public function testRunNoDueEvents(): void
    {
        TestKernel::$scheduleCallback = function ($schedule) {
            $schedule->command('test/command')->cron('5 * * * *');
        };

        // We can't mock time easily without extra packages, so we rely on the parser test.
        // This test mainly ensures the "No scheduled commands" message is shown.
        // To test this properly, we'd need a time-mocking library.
        // For now, we'll just check that it doesn't crash.
        $this->scheduler->run();
        $this->assertStringContainsString('No scheduled', $this->controller->output);
    }

    public function testRunDueEventWithOverlappingAndLockSucceeds(): void
    {
        $wasCalled = false;
        TestKernel::$scheduleCallback = function ($schedule) use (&$wasCalled) {
            $schedule->call(function () use (&$wasCalled) {
                $wasCalled = true;
            })->everyMinute()->withoutOverlapping();
        };

        $this->scheduler->run();

        $this->assertTrue($wasCalled);
        $this->assertStringContainsString('OK', $this->controller->output);
    }

    public function testRunDueEventWithOverlappingAndLockFails(): void
    {
        $wasCalled = false;
        TestKernel::$scheduleCallback = function ($schedule) use (&$wasCalled) {
            $schedule->call(function () use (&$wasCalled) {
                $wasCalled = true;
            })->everyMinute()->withoutOverlapping();
        };

        // Manually acquire the lock to simulate a running process
        $mutex = Yii::$app->get('mutex');
        $lockName = 'scheduler-Callable';
        $this->assertTrue($mutex->acquire($lockName));

        $this->scheduler->run();

        $mutex->release($lockName);

        $this->assertFalse($wasCalled);
        $this->assertStringContainsString('Skipping [Callable], task is still running', $this->controller->output);
    }
}

<?php

namespace tests\unit\Commands;

use tests\TestCase;
use tests\unit\mocks\MockScheduleKernel;
use tests\unit\mocks\MockScheduler;
use tests\unit\mocks\MockSchedulerController;
use Yii;

/**
 * @covers \Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController
 */
class SchedulerControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();
        MockScheduleKernel::$scheduleCallback = null;
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController::actionList
     */
    public function testActionList(): void
    {
        // Mock the application timezone
        Yii::$app->timeZone = 'UTC';

        MockScheduleKernel::$scheduleCallback = function ($schedule): void {
            // Task with default (app) timezone
            $schedule->command('backup/run')->dailyAt('02:30');
            // Task with specific timezone
            $schedule->command('backup/run-ny')->dailyAt('09:00')->timeZone('America/New_York');
        };

        $controller = new MockSchedulerController('scheduler', Yii::$app);

        // Mock 'now' for consistent 'Next Run Time' calculation
        // We need to use a consistent "now" to check the output
        // This is complex to mock without DI, so we will just check for names.

        $controller->runAction('list');

        self::assertStringContainsString('Scheduled Tasks List', $controller->output);

        // Check default task
        self::assertStringContainsString('yii backup/run', $controller->output);
        self::assertStringContainsString('(app)', $controller->output);

        // Check timezone-specific task
        self::assertStringContainsString('yii backup/run-ny', $controller->output);
        self::assertStringContainsString('America/New_York', $controller->output);

        // Check that 'America/New_York' is not next to '(app)'
        self::assertStringNotContainsString('(app)             America/New_York', $controller->output);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController::actionRun
     */
    public function testActionRun(): void
    {
        Yii::$app->set('scheduler', Yii::$app->get('mockScheduler'));
        /** @var MockScheduler $mockScheduler */
        $mockScheduler = Yii::$app->get('scheduler');

        $controller = new MockSchedulerController('scheduler', Yii::$app);
        $controller->runAction('run');

        self::assertEquals(1, $mockScheduler->runCount);
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController::actionExecute
     */
    public function testActionExecute(): void
    {
        Yii::$app->set('scheduler', Yii::$app->get('mockScheduler'));
        /** @var MockScheduler $mockScheduler */
        $mockScheduler = Yii::$app->get('scheduler');

        $controller = new MockSchedulerController('scheduler', Yii::$app);
        $testIdentifier = 'my-test-task';
        $encodedIdentifier = base64_encode($testIdentifier);

        $controller->runAction('execute', [$encodedIdentifier]);

        self::assertEquals($testIdentifier, $mockScheduler->executedTaskIdentifier);
    }
}

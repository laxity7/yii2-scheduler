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
        MockScheduleKernel::$scheduleCallback = function ($schedule): void {
            $schedule->command('backup/run')->dailyAt('02:30');
        };

        $controller = new MockSchedulerController('scheduler', Yii::$app);
        $controller->runAction('list');

        self::assertStringContainsString('Scheduled Tasks List', $controller->output);
        self::assertStringContainsString('yii backup/run', $controller->output);
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

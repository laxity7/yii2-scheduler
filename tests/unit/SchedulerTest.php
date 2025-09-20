<?php

namespace tests\unit;

use Laxity7\Yii2\Components\Scheduler\Scheduler;
use tests\TestCase;
use tests\unit\mocks\MockCommandController;
use tests\unit\mocks\MockCommandRunner;
use tests\unit\mocks\MockScheduleKernel;
use Yii;
use yii\console\Controller;

class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();
        MockScheduleKernel::$scheduleCallback = null;
        MockCommandController::$actionIndexCalled = false;
        MockCommandController::$actionParamsCalledWith = [];
    }

    /**
     * A valid static callable for testing purposes.
     */
    public static function dummyTask(): void
    {
        // This is a stub for testing callable resolution.
    }

    /**
     * Tests the main loop (run method) to ensure it spawns correct commands.
     */
    public function testRunSpawnsCorrectCommands(): void
    {
        MockScheduleKernel::$scheduleCallback = function ($schedule) {
            $schedule->command('test/command1')->everyMinute();
            $schedule->call([self::class, 'dummyTask'])->everyMinute();
        };

        $mockRunner = new MockCommandRunner();

        $scheduler = new Scheduler([
            'kernelClass'   => MockScheduleKernel::class,
            'commandRunner' => $mockRunner,
        ]);
        $scheduler->setController(new Controller('test', Yii::$app));
        $scheduler->run();

        self::assertCount(2, $mockRunner->ranCommands);

        $expectedIdentifier1 = base64_encode('yii test/command1');
        self::assertStringContainsString("scheduler/execute " . escapeshellarg($expectedIdentifier1), $mockRunner->ranCommands[0]);

        $expectedIdentifier2 = base64_encode(self::class . '::dummyTask');
        self::assertStringContainsString("scheduler/execute " . escapeshellarg($expectedIdentifier2), $mockRunner->ranCommands[1]);
    }

    /**
     * Tests the executor (runSingleTaskByIdentifier) which runs in a background process.
     */
    public function testRunSingleTaskExecutesTask(): void
    {
        $wasCalled = false;
        MockScheduleKernel::$scheduleCallback = function ($schedule) use (&$wasCalled) {
            $schedule->call(function () use (&$wasCalled) {
                $wasCalled = true;
            })->everyMinute();
        };

        /** @var Scheduler $scheduler */
        $scheduler = Yii::$app->get('scheduler');
        $schedule = $scheduler->getSchedule();
        $tasks = $schedule->getTasks();
        $identifier = $tasks[0]->getName();

        $scheduler->runSingleTaskByIdentifier($identifier);

        self::assertTrue($wasCalled);
    }

    /**
     * Tests that a command with a slash in its name (e.g., 'controller/action') is executed correctly.
     */
    public function testRunSingleTaskExecutesCommandWithSlash(): void
    {
        $this->mockApplication([
            'controllerMap' => [
                'mock-command' => MockCommandController::class,
            ],
        ]);
        MockScheduleKernel::$scheduleCallback = function ($schedule) {
            $schedule->command('mock-command/index')->withParameters(['final', 42])->everyMinute();
        };

        /** @var Scheduler $scheduler */
        $scheduler = Yii::$app->get('scheduler');
        $schedule = $scheduler->getSchedule();
        $tasks = $schedule->getTasks();
        $identifier = $tasks[0]->getName();

        $scheduler->runSingleTaskByIdentifier($identifier);

        self::assertTrue(MockCommandController::$actionIndexCalled);
        self::assertEquals(['param1' => 'final', 'param2' => 42], MockCommandController::$actionParamsCalledWith);
    }

    public function testRunSingleTaskWithMutex(): void
    {
        $wasCalled = false;
        MockScheduleKernel::$scheduleCallback = function ($schedule) use (&$wasCalled) {
            $schedule->call(function () use (&$wasCalled) {
                $wasCalled = true;
            })->everyMinute()->withoutOverlapping();
        };

        /** @var Scheduler $scheduler */
        $scheduler = Yii::$app->get('scheduler');
        $schedule = $scheduler->getSchedule();
        $tasks = $schedule->getTasks();
        $identifier = $tasks[0]->getName();
        $lockName = 'scheduler-' . preg_replace('/[^A-Za-z0-9\-_:]/', '', $identifier);
        /** @var \yii\mutex\Mutex $mutex */
        $mutex = Yii::$app->get('mutex');
        // Acquire lock to simulate running process
        self::assertTrue($mutex->acquire($lockName));
        // This should fail to run the task
        $scheduler->runSingleTaskByIdentifier($identifier);
        self::assertFalse($wasCalled);
        // Release lock
        $mutex->release($lockName);
        // This should now succeed
        $scheduler->runSingleTaskByIdentifier($identifier);
        // @phpstan-ignore staticMethod.impossibleType
        self::assertTrue($wasCalled);
    }
}

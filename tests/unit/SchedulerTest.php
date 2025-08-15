<?php

namespace tests\unit;

use Laxity7\Yii2\Components\Scheduler\Scheduler;
use tests\TestCase;
use tests\unit\mocks\MockCommandRunner;
use tests\unit\mocks\TestKernel;
use Yii;

class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockApplication();
        TestKernel::$scheduleCallback = null;
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
        TestKernel::$scheduleCallback = function ($schedule) {
            $schedule->command('test/command1')->everyMinute();
            $schedule->call([self::class, 'dummyTask'])->everyMinute();
        };

        // Создаем настоящий Scheduler
        $scheduler = new Scheduler(['kernelClass' => TestKernel::class]);

        // Внедряем наш mock-runner
        $mockRunner = new MockCommandRunner();
        $scheduler->commandRunner = $mockRunner;

        $scheduler->setController(new \yii\console\Controller('test', Yii::$app));
        $scheduler->run();

        // Проверяем команды, записанные в mock-runner
        $this->assertCount(2, $mockRunner->ranCommands);

        $expectedIdentifier1 = base64_encode('yii test/command1');
        $this->assertStringContainsString("scheduler/execute '{$expectedIdentifier1}'", $mockRunner->ranCommands[0]);

        $expectedIdentifier2 = base64_encode(self::class . '::dummyTask');
        $this->assertStringContainsString("scheduler/execute '{$expectedIdentifier2}'", $mockRunner->ranCommands[1]);
    }

    /**
     * Tests the executor (runSingleTaskByIdentifier) which runs in a background process.
     */
    public function testRunSingleTaskExecutesTask(): void
    {
        $wasCalled = false;
        TestKernel::$scheduleCallback = function ($schedule) use (&$wasCalled) {
            $schedule->call(function () use (&$wasCalled) {
                $wasCalled = true;
            })->everyMinute();
        };

        /** @var Scheduler $scheduler */
        $scheduler = Yii::$app->scheduler;
        $identifier = 'Callable';

        $scheduler->runSingleTaskByIdentifier($identifier);

        $this->assertTrue($wasCalled);
    }

    public function testRunSingleTaskWithMutex(): void
    {
        $wasCalled = false;
        TestKernel::$scheduleCallback = function ($schedule) use (&$wasCalled) {
            $schedule->call(function () use (&$wasCalled) {
                $wasCalled = true;
            })->everyMinute()->withoutOverlapping();
        };

        /** @var Scheduler $scheduler */
        $scheduler = Yii::$app->scheduler;
        $identifier = 'Callable';
        $lockName = 'scheduler-' . $identifier;
        $mutex = Yii::$app->get('mutex');

        // Acquire lock to simulate running process
        $this->assertTrue($mutex->acquire($lockName));

        // This should fail to run the task
        $scheduler->runSingleTaskByIdentifier($identifier);
        $this->assertFalse($wasCalled);

        // Release lock
        $mutex->release($lockName);

        // This should now succeed
        $scheduler->runSingleTaskByIdentifier($identifier);
        $this->assertTrue($wasCalled);
    }
}

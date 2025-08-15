<?php

namespace Laxity7\Yii2\Components\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use Laxity7\Yii2\Components\Scheduler\Components\Event;
use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use Laxity7\Yii2\Components\Scheduler\Runners\CommandRunnerInterface;
use Laxity7\Yii2\Components\Scheduler\Runners\ShellCommandRunner;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApplication;
use yii\console\Controller;
use yii\helpers\Console;
use yii\mutex\FileMutex;
use yii\mutex\Mutex;

/**
 * A component for managing and starting the planned tasks.
 */
class Scheduler extends Component implements BootstrapInterface
{
    /**
     * @var class-string<KernelScheduleInterface> The class name of the kernel that implements the KernelInterface.
     * This class should define the `schedule(Schedule $schedule)` method to register scheduled events.
     */
    public string $kernelClass;

    /**
     * Configuration for the mutex component.
     * If a string is provided, it should be the ID of the mutex component in the application configuration.
     * If an array is provided, it should be a configuration array for creating a new mutex instance.
     * If null, a default FileMutex instance will be created.
     * @var string|array|null
     * @see Mutex
     */
    public $mutex;
    private Mutex $mutexInstance;
    private ?Controller $controller = null;

    /**
     * @var string|array|CommandRunnerInterface The command runner instance or configuration.
     */
    public $commandRunner = ShellCommandRunner::class;

    public function init(): void
    {
        if (empty($this->kernelClass)) {
            throw new InvalidConfigException('The "kernelClass" property must be set.');
        }
        if (!is_subclass_of($this->kernelClass, KernelScheduleInterface::class)) {
            throw new InvalidConfigException("The class '{$this->kernelClass}' must implement " . KernelScheduleInterface::class);
        }
        $this->resolveMutex();
        $this->commandRunner = Yii::createObject($this->commandRunner);
    }

    private function resolveMutex(): void
    {
        if (class_exists($this->mutex)) {
            if (!is_subclass_of($this->mutex, Mutex::class)) {
                throw new InvalidConfigException('Component "mutex" must be an instance of yii\mutex\Mutex or its subclass.');
            }
            $this->mutexInstance = Yii::createObject($this->mutex);
        } elseif (is_string($this->mutex)) {
            $this->mutexInstance = Yii::$app->get($this->mutex);
        } elseif (is_array($this->mutex)) {
            $this->mutexInstance = Yii::createObject($this->mutex);
        } else {
            $this->mutexInstance = Yii::createObject(FileMutex::class);
        }

        if (!$this->mutexInstance instanceof Mutex) {
            throw new InvalidConfigException('Component "mutex" must be an instance of yii\mutex\Mutex or its subclass.');
        }
    }

    public function setController(Controller $controller): void
    {
        $this->controller = $controller;
    }

    public function run(): void
    {
        if ($this->controller === null) {
            throw new InvalidConfigException('The controller must be set before running the scheduler.');
        }
        $schedule = $this->loadSchedule();
        $parser = new CronExpressionParser();
        $now = new DateTimeImmutable('now', new DateTimeZone(Yii::$app->timeZone));
        $dueEvents = $schedule->dueEvents($parser, $now);
        if (empty($dueEvents)) {
            $this->controller->stdout("No scheduled commands are due to run at " . $now->format('Y-m-d H:i') . "\n", Console::FG_GREY);

            return;
        }
        foreach ($dueEvents as $event) {
            $this->spawnEvent($event);
        }
    }

    private function spawnEvent(Event $event): void
    {
        $description = $event->getSummaryForDisplay();
        $this->controller->stdout("Spawning: [{$description}]\n", Console::FG_CYAN);

        $yiiPath = Yii::getAlias('@app/yii');
        $taskIdentifier = base64_encode($description);

        $command = PHP_BINARY . " {$yiiPath} scheduler/execute " . escapeshellarg($taskIdentifier);

        $this->commandRunner->runInBackground($command);
    }

    public function listen(): void
    {
        $this->checkController();

        $this->controller->stdout("Scheduler is running in daemon mode...\n", Console::FG_GREEN);
        while (true) {
            Yii::getLogger()->flush(true);
            if (Yii::$app->has('db', true)) {
                Yii::$app->db->close();
            }
            $this->run();
            $nextRunTime = ceil(microtime(true) / 60) * 60;
            $sleepSeconds = $nextRunTime - microtime(true);
            if ($sleepSeconds > 0) {
                usleep($sleepSeconds * 1000000);
            }
        }
    }

    /**
     * Finds and runs a single task by its identifier.
     * This method is executed inside the spawned background process.
     *
     * @param string $taskIdentifier
     */
    public function runSingleTaskByIdentifier(string $taskIdentifier): void
    {
        $schedule = $this->loadSchedule();
        $event = null;

        foreach ($schedule->getEvents() as $e) {
            if ($e->getSummaryForDisplay() === $taskIdentifier) {
                $event = $e;
                break;
            }
        }

        if ($event === null) {
            Yii::error("Could not find task with identifier: {$taskIdentifier}", 'scheduler');

            return;
        }

        $lockName = 'scheduler-' . preg_replace('/[^A-Za-z0-9\-_]/', '', $taskIdentifier);

        if ($event->withoutOverlapping && !$this->mutexInstance->acquire($lockName)) {
            Yii::info("Execution of [{$taskIdentifier}] skipped, already running.", 'scheduler');

            return;
        }

        try {
            $dummyController = new Controller('dummy', Yii::$app);
            $event->run($dummyController);
            Yii::info("Task [{$taskIdentifier}] executed successfully.", 'scheduler');
        } catch (\Throwable $e) {
            Yii::error("Task '{$taskIdentifier}' failed: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'scheduler');
        } finally {
            if ($event->withoutOverlapping) {
                $this->mutexInstance->release($lockName);
            }
        }
    }

    private function loadSchedule(): Schedule
    {
        $schedule = new Schedule();
        /** @var KernelScheduleInterface $kernel */
        $kernel = Yii::createObject($this->kernelClass);
        $kernel->schedule($schedule);

        return $schedule;
    }

    private function checkController(): void
    {
        if ($this->controller === null) {
            throw new InvalidConfigException('Scheduler controller must be set before running.');
        }
    }

    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app): void
    {
        if ($app instanceof ConsoleApplication) {
            $app->controllerMap['scheduler'] = [
                'class' => SchedulerController::class,
            ];
        }
    }
}

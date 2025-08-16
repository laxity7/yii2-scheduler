<?php

namespace Laxity7\Yii2\Components\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use Laxity7\Yii2\Components\Scheduler\Components\Task;
use Laxity7\Yii2\Components\Scheduler\Runners\CommandRunnerInterface;
use Laxity7\Yii2\Components\Scheduler\Runners\ShellCommandRunner;
use Throwable;
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
     * @var class-string<ScheduleKernelInterface> The class name of the kernel that implements the KernelInterface.
     */
    public string $kernelClass;

    /**
     * @var class-string<Mutex>|array{class: class-string<Mutex>, ...}|callable|string|null Configuration for the mutex component.
     */
    public $mutex;
    private Mutex $mutexInstance;
    private ?Controller $controller = null;

    /**
     * @var class-string<CommandRunnerInterface>|array{class: class-string<CommandRunnerInterface>, ...}|CommandRunnerInterface The command runner instance or
     *      configuration.
     */
    public $commandRunner = ShellCommandRunner::class;
    private CommandRunnerInterface $commandRunnerInstance;

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        if ($this->kernelClass === '') {
            throw new InvalidConfigException('The "kernelClass" property must be set.');
        }
        if (!is_subclass_of($this->kernelClass, ScheduleKernelInterface::class)) {
            throw new InvalidConfigException("The class '{$this->kernelClass}' must implement " . ScheduleKernelInterface::class);
        }
        $this->resolveMutex();

        if ($this->commandRunner instanceof CommandRunnerInterface) {
            $this->commandRunnerInstance = $this->commandRunner;
        } elseif (is_string($this->commandRunner) || is_array($this->commandRunner)) {
            $this->commandRunnerInstance = Yii::createObject($this->commandRunner);
        } else {
            throw new InvalidConfigException(
                'The "commandRunner" property must be a CommandRunnerInterface instance, a class string, or a configuration array.'
            );
        }
    }

    /**
     * @throws InvalidConfigException
     */
    private function resolveMutex(): void
    {
        if ($this->mutex !== null && is_string($this->mutex) && class_exists($this->mutex)) {
            if (!is_subclass_of($this->mutex, Mutex::class)) {
                throw new InvalidConfigException('Component "mutex" must be an instance of yii\mutex\Mutex or its subclass.');
            }
            $this->mutexInstance = Yii::createObject($this->mutex);
        } elseif (is_string($this->mutex)) {
            $mutex = Yii::$app->get($this->mutex);
            if (!$mutex instanceof Mutex) {
                throw new InvalidConfigException('Component "mutex" must be an instance of yii\mutex\Mutex or its subclass.');
            }
            $this->mutexInstance = $mutex;
        } elseif (is_array($this->mutex)) {
            /** @var array{class: class-string<Mutex>} $mutexConfig */
            $mutexConfig = $this->mutex;
            /** @var Mutex $mutex */
            $mutex = Yii::createObject($mutexConfig);
            $this->mutexInstance = $mutex;
        } else {
            $this->mutexInstance = Yii::createObject(FileMutex::class);
        }

        if (!$this->mutexInstance instanceof Mutex) {
            throw new InvalidConfigException('Component "mutex" must be an instance of yii\mutex\Mutex or its subclass.');
        }
    }

    public function getMutex(): Mutex
    {
        return $this->mutexInstance;
    }

    /**
     * Loads and returns the schedule object with all registered tasks.
     * @return Schedule
     */
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        /** @var ScheduleKernelInterface $kernel */
        $kernel = Yii::createObject($this->kernelClass);
        $kernel->schedule($schedule);

        return $schedule;
    }

    public function setController(Controller $controller): void
    {
        $this->controller = $controller;
    }

    public function listen(): void
    {
        $this->checkController();

        $this->controller->stdout("Scheduler is running in daemon mode...\n", Console::FG_GREEN);
        for (; ;) {
            Yii::getLogger()->flush(true);
            if (Yii::$app->has('db', true)) {
                Yii::$app->db->close();
            }
            $this->run();
            $nextRunTime = ceil(microtime(true) / 60) * 60;
            $sleepSeconds = $nextRunTime - microtime(true);
            if ($sleepSeconds > 0) {
                usleep((int)($sleepSeconds * 1000000));
            }
        }
    }

    public function run(): void
    {
        $this->checkController();
        $schedule = $this->loadSchedule();
        $parser = new CronExpressionParser();
        $now = new DateTimeImmutable('now', new DateTimeZone(Yii::$app->timeZone));
        $dueTasks = $schedule->dueTask($parser, $now);

        $currentTime = $now->format('Y-m-d H:i:s');
        if ([] === $dueTasks) {
            $this->controller->stdout($currentTime . " No scheduled commands are due to run.\n", Console::FG_GREY);

            return;
        }

        foreach ($dueTasks as $task) {
            $this->spawnTask($task);
        }
    }

    protected function spawnTask(Task $task): void
    {
        $this->checkController();
        $description = $task->getName();
        $currentTime = (new DateTimeImmutable('now', new DateTimeZone(Yii::$app->timeZone)))->format('Y-m-d H:i:s');
        $this->controller->stdout($currentTime . " Spawning: [{$description}]\n", Console::FG_CYAN);

        $yiiScript = Yii::getAlias('@app/yii');
        $phpPath = $this->getPhpExecutablePath();
        $taskIdentifier = base64_encode($description);

        $command = "{$phpPath} {$yiiScript} scheduler/execute " . escapeshellarg($taskIdentifier);

        $this->commandRunnerInstance->runInBackground($command);
    }

    public function runSingleTaskByIdentifier(string $taskIdentifier): void
    {
        $schedule = $this->loadSchedule();
        $task = null;

        foreach ($schedule->getTasks() as $t) {
            if ($t->getName() === $taskIdentifier) {
                $task = $t;
                break;
            }
        }

        if ($task === null) {
            Yii::error("Could not find task with identifier: {$taskIdentifier}", 'scheduler');

            return;
        }

        if ($task->withoutOverlapping) {
            $lockName = $this->getLockNameForTask($task);
            if (!$this->mutexInstance->acquire($lockName)) {
                Yii::info("Execution of [{$taskIdentifier}] skipped, task is still running.", 'scheduler');

                return;
            }
        }

        try {
            $dummyController = new Controller('dummy', Yii::$app);
            $task->run($dummyController);
            Yii::info("Task [{$taskIdentifier}] executed successfully.", 'scheduler');
        } catch (Throwable $e) {
            Yii::error("Task '{$taskIdentifier}' failed: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'scheduler');
        } finally {
            if ($task->withoutOverlapping) {
                $lockName = $this->getLockNameForTask($task);
                $this->mutexInstance->release($lockName);
            }
        }
    }

    private function getLockNameForTask(Task $task): string
    {
        return 'scheduler-' . preg_replace('/[^A-Za-z0-9\-_:]/', '', $task->getName());
    }

    protected function getPhpExecutablePath(): string
    {
        if (defined('PHP_BINARY') && strlen(PHP_BINARY) > 0) {
            return PHP_BINARY;
        }
        if (isset($_SERVER['_']) && strlen($_SERVER['_']) > 0) {
            return $_SERVER['_'];
        }
        if (is_executable('/usr/bin/php')) {
            return '/usr/bin/php';
        }

        return 'php';
    }

    /**
     * @throws InvalidConfigException
     */
    private function loadSchedule(): Schedule
    {
        $schedule = new Schedule();
        /** @var ScheduleKernelInterface $kernel */
        $kernel = Yii::createObject($this->kernelClass);
        $kernel->schedule($schedule);

        return $schedule;
    }

    /**
     * @throws InvalidConfigException
     * @phpstan-assert !null $this->controller
     */
    private function checkController(): void
    {
        if ($this->controller === null) {
            throw new InvalidConfigException('Scheduler controller must be set before running.');
        }
    }

    public function bootstrap($app): void
    {
        if ($app instanceof ConsoleApplication) {
            $app->controllerMap['scheduler'] = [
                'class' => SchedulerController::class,
            ];
        }
    }
}

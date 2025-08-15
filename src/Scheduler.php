<?php

namespace Laxity7\Yii2\Components\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use Laxity7\Yii2\Components\Scheduler\Components\Event;
use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
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

    public function init(): void
    {
        parent::init();
        if (empty($this->kernelClass)) {
            throw new InvalidConfigException('Свойство "kernelClass" должно быть установлено.');
        }
        if (!is_subclass_of($this->kernelClass, KernelScheduleInterface::class)) {
            throw new InvalidConfigException("Класс '{$this->kernelClass}' должен реализовывать " . KernelScheduleInterface::class);
        }

        $this->resolveMutex();
    }

    private function resolveMutex(): void
    {
        if (is_string($this->mutex)) {
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
        $this->checkController();

        $schedule = $this->loadSchedule();
        $parser = new CronExpressionParser();
        $now = new DateTimeImmutable('now', new DateTimeZone(Yii::$app->timeZone));
        $dueEvents = $schedule->dueEvents($parser, $now);

        if (empty($dueEvents)) {
            $this->controller->stdout('No scheduled events due at this time: ' . $now->format('Y-m-d H:i') . "\n", Console::FG_GREY);

            return;
        }

        foreach ($dueEvents as $event) {
            $this->runEvent($event);
        }
    }

    public function listen(): void
    {
        $this->checkController();

        $this->controller->stdout('Planned tasks scheduler started. Waiting for events...' . "\n", Console::FG_GREEN);
        while (true) {
            Yii::getLogger()->flush(true);
            if (Yii::$app->has('db', true)) {
                Yii::$app->db->close();
            }
            $this->run();
            sleep(30);
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

    private function runEvent(Event $event): void
    {
        $description = $event->getSummaryForDisplay();
        $lockName = 'scheduler-' . preg_replace('/[^A-Za-z0-9\-_]/', '', $description);

        // Apply mutex only if specified for the task
        if ($event->withoutOverlapping && !$this->mutexInstance->acquire($lockName)) {
            $this->controller->stdout("Skipping [{$description}], task is still running.\n", Console::FG_YELLOW);

            return;
        }

        $this->controller->stdout("Running: [{$description}]... ", Console::FG_CYAN);
        $startTime = microtime(true);

        try {
            $event->run($this->controller);
            $runTime = round(microtime(true) - $startTime, 2);
            $this->controller->stdout("OK ({$runTime}s)\n", Console::FG_GREEN);
        } catch (\Throwable $e) {
            $runTime = round(microtime(true) - $startTime, 2);
            $this->controller->stderr("FAIL ({$runTime}s)\n", Console::FG_RED);
            Yii::error("Task '{$description}' failed: " . $e->getMessage(), 'scheduler');
        } finally {
            // Release mutex only if it was acquired
            if ($event->withoutOverlapping) {
                $this->mutexInstance->release($lockName);
            }
        }
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

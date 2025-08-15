<?php

namespace tests;

use Laxity7\Yii2\Components\Scheduler\Scheduler;
use tests\unit\mocks\TestKernel;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the base class for all yii framework unit tests.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Clean up after test.
     * By default the application created with [[mockApplication]] will be destroyed.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->destroyApplication();
    }

    /**
     * Populates Yii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     *
     * @param array  $config   The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication(array $config = [], string $appClass = \yii\console\Application::class): void
    {
        new $appClass(ArrayHelper::merge([
            'id'         => 'test-app',
            'basePath'   => __DIR__,
            'vendorPath' => dirname(__DIR__) . '/vendor',
            'components' => [
                'scheduler' => [
                    'class'       => Scheduler::class,
                    'kernelClass' => TestKernel::class,
                ],
                'mutex'     => [
                    'class'     => \yii\mutex\FileMutex::class,
                    'mutexPath' => '@runtime/mutex',
                ],
            ],
        ], $config));
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication(): void
    {
        Yii::$app = null;
    }
}

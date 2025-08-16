<?php

namespace tests\unit\mocks;

use Laxity7\Yii2\Components\Scheduler\Scheduler;

/**
 * A mock Scheduler class to intercept method calls for testing.
 */
class MockScheduler extends Scheduler
{
    public int $runCount = 0;
    public ?string $executedTaskIdentifier = null;

    public function run(): void
    {
        $this->runCount++;
        // We don't call parent::run() to avoid spawning real processes
    }

    public function runSingleTaskByIdentifier(string $taskIdentifier): void
    {
        $this->executedTaskIdentifier = $taskIdentifier;
        // We don't call parent::runSingleTaskByIdentifier() to avoid executing real tasks
    }
}

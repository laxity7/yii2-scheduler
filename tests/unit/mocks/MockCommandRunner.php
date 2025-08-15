<?php

namespace tests\unit\mocks;

use Laxity7\Yii2\Components\Scheduler\Runners\CommandRunnerInterface;

/**
 * A test implementation of CommandRunner that records commands instead of executing them.
 */
class MockCommandRunner implements CommandRunnerInterface
{
    /**
     * @var string[] An array of shell commands that were supposed to be run.
     */
    public array $ranCommands = [];

    public function runInBackground(string $command): void
    {
        $this->ranCommands[] = $command;
    }
}

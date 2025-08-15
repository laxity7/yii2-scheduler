<?php

namespace Laxity7\Yii2\Components\Scheduler\Runners;

/**
 * Interface for running shell commands.
 */
interface CommandRunnerInterface
{
    /**
     * Runs a command in the background.
     *
     * @param string $command The full shell command to execute.
     */
    public function runInBackground(string $command): void;
}

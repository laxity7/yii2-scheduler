<?php

namespace Laxity7\Yii2\Components\Scheduler\Runners;

/**
 * Default implementation for running shell commands.
 */
class ShellCommandRunner implements CommandRunnerInterface
{
    public function runInBackground(string $command): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B " . $command, "r"));
        } else {
            exec($command . " > /dev/null 2>&1 &");
        }
    }
}

<?php

namespace Laxity7\Yii2\Components\Scheduler\Runners;

use Yii;

/**
 * Default implementation for running shell commands.
 */
class ShellCommandRunner implements CommandRunnerInterface
{
    public function runInBackground(string $command): void
    {
        Yii::info('Spawning background command: ' . $command, 'scheduler');

        // For Windows systems, the 'start /B' command is the equivalent of 'nohup'.
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $handle = popen("start /B " . $command, "r");
            if ($handle !== false) {
                pclose($handle);
            }

            return;
        }

        // For Linux/Unix systems, prepend the command with 'nohup'.
        // 'nohup' ensures the command continues to run even after the parent process exits.
        // '>/dev/null 2>&1 &' redirects all output and runs it in the background.
        $fullCommand = 'nohup ' . $command . ' > /dev/null 2>&1 &';

        exec($fullCommand);
    }
}

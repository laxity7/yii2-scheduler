<?php

namespace tests\unit\mocks;

use yii\console\Controller;
use yii\console\ExitCode;

class TestController extends Controller
{
    public string $output = '';
    public ?string $ranAction = null;
    public ?array $ranActionParams = null;

    public function stdout($string): int
    {
        $this->output .= $string;

        return strlen($string);
    }

    public function stderr($string): int
    {
        $this->output .= $string;

        return strlen($string);
    }

    public function runAction($id, $params = []): int
    {
        $this->ranAction = $id;
        $this->ranActionParams = $params;

        return ExitCode::OK;
    }
}

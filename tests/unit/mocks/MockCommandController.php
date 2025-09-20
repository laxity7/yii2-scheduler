<?php

namespace tests\unit\mocks;

use yii\console\Controller;

class MockCommandController extends Controller
{
    public static bool $actionIndexCalled = false;
    /** @var array<string, mixed> */
    public static array $actionParamsCalledWith = [];

    public function actionIndex(string $param1 = '', int $param2 = 0): void
    {
        self::$actionIndexCalled = true;
        self::$actionParamsCalledWith = ['param1' => $param1, 'param2' => $param2];
    }
}

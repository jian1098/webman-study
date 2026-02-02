<?php

namespace app\controller;

use support\Request;
use support\Response;
use Workerman\Coroutine;
use Workerman\Timer;
use Workerman\Coroutine\Parallel;

// 协程
class CoroutineController
{
    // 单个协程
    public function index(Request $request)
    {
        Coroutine::create(function () {
            Timer::sleep(1.5);
            echo "hello coroutine\n";
        });
        return response('hello webman');
    }

    // 并发非阻塞协程
    public function parallel(): Response
    {
        $parallel = new Parallel();
        $data = [];
        for ($i = 1; $i < 50; $i++) {
            $parallel->add(function () use ($i, &$data) {
                echo '协程' . $i . '开始' . PHP_EOL;
                Timer::sleep(2); // 模拟耗时操作，不能使用sleep(),不然会线程阻塞
                $data[] = $i;
                echo '协程' . $i . '结束' . PHP_EOL;
            });
        }
        $parallel->wait();
        return response(json_encode($data));
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/14 0014
 * Time: 下午 17:27
 */

namespace App\WebSocket\event;


use App\Process\KeepUser;
use EasySwoole\Component\Timer;

class OnWorkStart
{
    public function onWorkerStart(\swoole_server $server ,$workerId)
    {
        $timer = Timer::getInstance();
        if($workerId == 1)
        {
            $keepuser = new KeepUser();
            //1分钟轮询
            $timer->loop(60 * 1000, function () use ($keepuser) {
                $keepuser->run();
            });

        }

    }

}
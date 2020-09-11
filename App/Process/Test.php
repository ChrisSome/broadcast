<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-26
 * Time: 23:18
 */

namespace App\Process;

use App\lib\Mqtt\Client;
use App\Model\AdminUser;
use App\Utility\Log\Log;
use EasySwoole\Component\Process\AbstractProcess;

/**
 * 暴力热重载
 * Class HotReload
 * @package App\Process
 */
class Test extends AbstractProcess
{
    /** @var \swoole_table $table */
    protected $table;
    protected $isReady = false;

    protected $footballTopic = 'sports/football/match.v1';
    /**
     * mqtt订阅
     */
    public function run($arg)
    {
       Log::getInstance()->info('Test start12333');
//                Log::getInstance()->info('start connect:3333');
        $mqtt = new Client('s.sportnanoapi.com', 443);
//
//        $mqtt->onConnect = function ($mqtt) {
//            Log::getInstance()->info('start connect:');
//
//            $mqtt->subscribe('sports/football/match.v1');
//        };
//
//        $mqtt->onMessage = function ($topic, $content) {
//
//            Log::getInstance()->info('topic:' . $topic);
//            Log::getInstance()->info('content:' . json_encode($content));
//        };
//
//        $mqtt->onError = function ($exception) use ($mqtt) {
//
//            Log::getInstance()->info('mqtt exception' . $exception->errMsg);
//
//        };
//
//        $mqtt->onClose = function () {
//            Log::getInstance()->notice('mqtt断开');
//
//        };
//
//        $mqtt->connect();

    }




    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}
<?php


namespace App\Task;

use App\lib\pool\PhoneCodeService as PhoneCodeService;
use App\lib\Tool;
use App\Model\AdminSysSettings;
use App\Model\AdminUserPhonecode;
use App\Utility\Log\Log;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\lib\pool\Login;

class TestTask implements TaskInterface
{
    use Singleton;
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    function run(int $taskId,int $workerIndex)
    {

        return "返回值:".$this->taskData['name'];
    }

    function insert()
    {


        Log::getInstance()->info('用户' . $this->taskData['mobile'] . '发送短信5555');

        // TODO: Implement run() method.
        $isDebug = AdminSysSettings::getInstance()->getSysKey('is_debug');
        if (!$isDebug) {
            Log::getInstance()->info('用户开始发送短信321');

            //需要引入短信表发送短信
            $phoneCodeS = new PhoneCodeService();
            $content = sprintf(PhoneCodeService::$copying, $this->taskData['code']);
            Log::getInstance()->info('用户内容 ' . $content);

            $xsend = $phoneCodeS->sendMess($this->taskData['mobile'], $content);
            Log::getInstance()->info('用户短信返回 ' . json_encode($xsend));

            if ($xsend['status'] !== PhoneCodeService::STATUS_SUCCESS) {
//                Log::getInstance()->info('用户' . $this->taskData['mobile'] . '短信发送失败 ：' . $this->taskData['code']);
                Log::getInstance()->info('reason : ' . json_encode($xsend));
            } else {
                $data = [
                    'mobile' => $this->taskData['mobile'],
                    'code' => $this->taskData['code']
                ];
                AdminUserPhonecode::getInstance()->insert($data);
                Log::getInstance()->info('用户' . $this->taskData['mobile'] . '短信发送成功 ：' . $this->taskData['code']);

            }

        } else {
            Log::getInstance()->info('短信功能未开启');

        }

    }
    function finish()
    {
        return '123';
    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}
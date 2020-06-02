<?php


namespace App\Task;

use App\Model\AdminSysSettings;
use App\Model\AdminUserPhonecode;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\lib\pool\Login;

class PhoneTask implements TaskInterface
{
    use Singleton;
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    function run(int $taskId,int $workerIndex)
    {

    }

    function insert()
    {
        // TODO: Implement run() method.
        $isDebug = AdminSysSettings::getInstance()->getSysKey('is_debug');
        if (!$isDebug) {
            //需要引入短信表发送短信
        }
        $data = [
            'mobile' => $this->taskData['mobile'],
            'code' => $this->taskData['code']
        ];
        AdminUserPhonecode::getInstance()->insert($data);
    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/3/6
 * Time: 下午12:14
 */

namespace App\Process;


use App\lib\pool\Login;
use App\Storage\OnlineUser;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Process;
/**
 * Class KeepUser
 * @package App\Process
 * 定时任务，统计哪些不在线的用户删除其缓存
 */
class KeepUser
{

    public function run()
    {
        $online = OnlineUser::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        foreach ($online->table() as $mid => $info) {
            $connection = $server->connection_info($info['fd']);
            if (!(is_array($connection) && $connection['websocket_status'] == 3)) {
                //删除
                $online->delete($mid);
            }
        }
    }

}
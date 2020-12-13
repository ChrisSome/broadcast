<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:54
 */

namespace App\WebSocket\Controller;

use App\lib\Tool;
use App\Model\AdminNormalProblems;
use App\Model\AdminUser;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\Socket\Client\WebSocket as WebSocketClient;
use Exception;

/**
 * 基础控制器
 * Class Base
 * @package App\WebSocket\Controller
 */
class Base extends Controller
{

    public $is_login = false;
    /**
     * 获取用户信息
     * @param $fd
     * @param $args
     * @param string $message
     * @return bool
     */
    public function checkUserRight($fd, $args = [], &$message = '')
    {
        $bool = true;
        $info = OnlineUser::getInstance()->get($fd);

        if (!$info) {
            $message = '用户已下线';
            $bool = false;

        } else {
            //判断是否他人窃取
            if($info['fd'] != $fd) {
                $bool = false;
                $message = '禁止获取他人用户信息';

            }

            $user = AdminUser::getInstance()->where('id', $info['user_id'])->get();
            if (isset($user['status']) && !in_array($user['status'], [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED])) {
                $bool = false;
                $message = '违反直播间规定，详情请联系客服';

            }

        }
        return $bool;


    }



    public function checkUser($fd, $jurisdiction)
    {
        if (!$onLineUser = OnlineUser::getInstance($fd)) {
            return false;
        }
        if (isset($jurisdiction['login']) && !$onLineUser['user_id']) {
            return false;
        }
        if (isset($jurisdiction['status']) && !$onLineUser['user_id']) {
            if (!$user = AdminUser::getInstance()->find(['id' => $onLineUser['user_id']])) {
                return false;
            } else if (!in_array($user['status'], [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED])) {
                return false;
            }
        }

        return true;

    }

    /**
     * 获取当前的用户
     * @return array|string
     * @throws Exception
     */
    public function currentUser($fd)
    {
        /** @var WebSocketClient $client */
        return OnlineUser::getInstance()->get($fd);
    }

    /**
     * 未找到的方法
     */
    public function actionNotFund()
    {
        $this->response()->setMessage(Tool::getInstance()->writeJson(404, '方法未定义'));

        return ;
    }


    /**
     * json格式错误
     */
    public function actionParseError()
    {
        $this->response()->setMessage(Tool::getInstance()->writeJson(406, '请检查json格式'));

        return ;
    }



}
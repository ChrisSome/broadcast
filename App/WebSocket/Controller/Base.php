<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:54
 */

namespace App\WebSocket\Controller;

use App\lib\pool\Login;
use App\lib\Tool;
use App\Model\AdminUser;
use App\Storage\OnlineUser;
use EasySwoole\Component\Pool\PoolManager;
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
    /**
     * 获取用户信息
     * @param $fd
     * @param $args
     * @param string $message
     * @return bool
     */
    public function checkUserRight($fd, $args, &$message = '')
    {
        if (!isset($args['mid'])) {
            $message = '缺少参数';
            return false;
        }
        $info = OnlineUser::getInstance()->get($args['mid']);

        if (!$info) {
            $message = '用户已下线';
            return false;
        } else {
            AdminUser::getInstance()->getOneByToken($info['token']); //更新token时间
            //判断是否他人窃取
            if($info['fd'] != $fd) {
                $message = '禁止获取他人用户信息';
                return false;
            }

            $message = $info;
            $user = AdminUser::getInstance()->where('id', $info['user_id'])->getOne();
            if ($user['status'] != 1) {
                $message = '违反直播间规定，详情请联系客服';
                return false;
            }

            return true;
        }
    }
    /**
     * 获取当前的用户
     * @return array|string
     * @throws Exception
     */
    public function currentUser($mid)
    {
        /** @var WebSocketClient $client */
        return OnlineUser::getInstance()->get($mid);
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

    /**
     * 获取用户信息
     * @param $uid
     * @param $mid
     * @return mixed
     */
    public function getUserInfoByMiD($uid, $mid)
    {
        return Login::getInstance()->getUser($uid, $mid);
    }

}
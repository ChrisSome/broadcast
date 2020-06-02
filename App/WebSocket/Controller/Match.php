<?php


namespace App\WebSocket\Controller;


use App\lib\pool\Login;
use App\lib\Tool;
use App\Storage\OnlineUser;
use App\Task\BroadcastTask;
use EasySwoole\EasySwoole\Task\TaskManager;

class Match extends Base
{
    public function enter()
    {
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        $args = $this->caller()->getArgs();
        $tool = Tool::getInstance();
        if (!isset($args['mid']) || !isset($args['match_id'])) {
            //参数不正确
            $this->response()->setMessage($tool->writeJson(403, '参数不正确'));

            return;
        }
        if (!$this->checkUserRight($fd, $args, $message)) {
            $this->response()->setMessage($tool->writeJson(406, $message));

            return  ;
        }

        //$info = $message;
        //查看比赛消息, debug不处理
        if ($match = true) {
            //设置在线用户表
            //$online->setMatchId($args['id']);
            $customerUsers = Login::getInstance()->lrange(sprintf(OnlineUser::LIST_ONLINE, $args['match_id']), 0, -1);
            if (!in_array($args['mid'], $customerUsers)) {
                OnlineUser::getInstance()->update($args['mid'], ['match_id' => $args['match_id']]);
            }

            //并获取最新直播间字信息给用户
            $chatMessages = [
                1 => ['id' => 1, 'message' => '鲁尼射门']
            ];

            $basicData = [
                'bifen' => "4:2"
            ];
            $this->response()->setMessage($tool->writeJson(200, 'ok', [
                'msgType' => 'enter',
                'msgContent' => [
                    'messages' => $chatMessages,
                    'ball' => $basicData
                ]
            ]));
        }
    }


    /**
     * 离开直播间
     */
    public function leave()
    {
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        $args = $this->caller()->getArgs();
        $tool = Tool::getInstance();
        if (!isset($args['match_id']) || !isset($args['mid'])) {
            //参数不正确
            $this->response()->setMessage($tool->writeJson(406, '参数不正确'));

            return  ;
        }

        if (!$this->checkUserRight($fd, $args, $message)) {
            $this->response()->setMessage($tool->writeJson(406, $message));

            return  ;
        }
        if ($onlineInfo = OnlineUser::getInstance()->get($args['mid'])) {
            if ($onlineInfo['match_id'] == 0) {
                $this->response()->setMessage($tool->writeJson(405, '不在直播间， 不需要退出'));

                return  ;
            }

            if ($onlineInfo['match_id'] != $args['match_id']) {
                $this->response()->setMessage($tool->writeJson(406, '退出比赛id跟用户当前所在直播间不一致'));

                return  ;
            }
            if ( OnlineUser::getInstance()->delete($args['mid'])) {
                $this->response()->setMessage($tool->writeJson(200, '退出直播间成功'));

                return  ;
            } else {
                $this->response()->setMessage($tool->writeJson(500, '退出直播间失败'));

                return  ;
            }
        } else {
            $this->response()->setMessage($tool->writeJson(406, '用户已下线'));

            return  ;
        }

    }

}
<?php


namespace App\WebSocket\Controller;


use App\lib\pool\Login;
use App\lib\Tool;
use App\Model\AdminMatchTlive;
use App\Model\AdminMessage;
use App\Model\ChatHistory;
use App\Storage\MatchLive;
use App\Utility\Log\Log;
use App\Storage\OnlineUser;
use App\Task\BroadcastTask;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\Task\TaskManager;

class Match extends Base
{

    /**
     * 用户进入房间
     * @throws \Exception
     */
    public function enter()
    {
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        $args = $this->caller()->getArgs();
        $tool = Tool::getInstance();
        if (!isset($args['match_id'])) {
            //参数不正确
            $this->response()->setMessage($tool->writeJson(403, '参数不正确'));

            return;
        }
        if (!$this->checkUserRight($fd, $args, $message)) {
            $this->response()->setMessage($tool->writeJson(406, $message));
        }
        //用户进入房间

        $user = $this->currentUser($fd);

        //记录房间内用户
        $matchId = $args['match_id'];
        if ($matchId == true) {
            //对比赛状态查询, 状态正常
            if (!OnlineUser::getInstance()->get($fd)) {
                $mid = Login::getInstance()->getMid();
                OnlineUser::getInstance()->set($fd, ['match_id' => $matchId, 'mid' => $mid, 'fd'=>$fd]);
            } else {
                OnlineUser::getInstance()->update($fd, ['match_id' => $matchId]);
            }
        } else {
            //比赛状态异常
        }
        $user['match_id'] = $matchId;
        $user['fd'] = $fd;
        //设置房间对象
        Login::getInstance()->userInRoom($args['match_id'], $fd);
        //最近二十条聊天记录
        $lastMessages = ChatHistory::getInstance()->where('match_id', $args['match_id'])->order('created_at', 'DESC')->limit(20)->all();
        //该场比赛的文字直播内容
        $oldContent = MatchLive::getInstance()->get($matchId);
        if ($oldContent) {
            $tlive = json_decode($oldContent['tlive'], true);
            $stats = json_decode($oldContent['stats'], true);
            $score = json_decode($oldContent['score'], true);
        } else {
            $matchTlive = AdminMatchTlive::getInstance()->where('match_id', $matchId)->get();
            if ($matchTlive) {
                $tlive = json_decode($matchTlive->tlive, true);
                $stats = json_decode($matchTlive->stats, true);
                $score = json_decode($matchTlive->score, true);
            } else {
                $tlive = [];
                $stats = [];
                $score = [];
            }

        }
        if ($lastMessages) {
            foreach ($lastMessages as $lastMessage) {
                $data['message_id'] = $lastMessage['id'];
                $data['sender_user_id'] = $lastMessage['sender_user_id'];
                $data['sender_user_nickname'] = $lastMessage->getSenderNickname()['nickname'];
                $data['at_user_id'] = $lastMessage['at_user_id'];
                $data['at_user_nickname'] = $lastMessage->getAtNickname()['nickname'];
                $data['content'] = $lastMessage['content'];
                $messages[] = $data;
                unset($data);
            }
        } else {
            $messages = [];
        }
        $respon = [
            'event' => 'match-enter',
            'data' => [
                'userInfo' => $user,
                'matchInfo' => [],
                'lastMessage' => $messages,
                'tlive' => $tlive,
                'stats' => $stats,
                'score' => $score
            ],

        ];
        $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $respon));
        return;
        //查看比赛消息, debug不处理
        if (true) {
            //设置在线用户表
            //$online->setMatchId($args['id']);
            $customerUsers = Login::getInstance()->lrange(sprintf(OnlineUser::LIST_ONLINE, $args['match_id']), 0, -1);
            if (!in_array($args['mid'], $customerUsers)) {
                OnlineUser::getInstance()->update($fd, ['match_id' => $args['match_id']]);
            }

            //并获取最新直播间字信息给用户
            $chatMessages = [
                1 => ['id' => 1, 'message' => '鲁尼射门']
            ];

            $basicData = [
                'bifen' => "4:2"
            ];
            $this->response()->setMessage($tool->writeJson(200, 'ok', [
                'event' => 'match-enter',
                'msgType' => 'enter',
                'msgContent' => [
                    'messages' => $chatMessages,
                    'ball' => $basicData
                ]
            ]));
        } else {
            $this->response()->setMessage($tool->writeJson(403, '参数不正确'));

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
        if (!isset($args['match_id'])) {
            //参数不正确
            $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_W_PARAM, WebSocketStatus::$msg[WebSocketStatus::STATUS_W_PARAM]));

            return  ;
        }

        if (!$this->checkUserRight($fd, $args, $message)) {
            $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_W_USER_RIGHT, $message));

            return  ;
        }

        if ($onlineInfo = OnlineUser::getInstance()->get($fd)) {
            if ($onlineInfo['match_id'] == 0) {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_NOT_IN_ROOM, WebSocketStatus::$msg[WebSocketStatus::STATUS_NOT_IN_ROOM]));
                return  ;
            }

            if ($onlineInfo['match_id'] != $args['match_id']) {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_NOT_IN_ROOM, WebSocketStatus::$msg[WebSocketStatus::STATUS_NOT_IN_ROOM]));
                return  ;
            }
            //将用户删除本直播间
            $user = $this->currentUser($fd);
            $res_outroom = Login::getInstance()->userOutRoom($user['match_id'], json_encode($user));
            $resp = [
                'event' => 'match-leave'
            ];
            if ($res_outroom) {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $resp));
                return  ;
            } else {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_LEAVE_ROOM, WebSocketStatus::$msg[WebSocketStatus::STATUS_LEAVE_ROOM]));

                return  ;
            }

        } else {
            $this->response()->setMessage($tool->writeJson(406, '用户已下线'));

            return  ;
        }

    }


    public function matchList()
    {

    }

}
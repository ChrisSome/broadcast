<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\lib\pool\Login;
use App\lib\Tool;
use App\Model\AdminUser;
use App\Model\ChatHistory;
use App\Storage\ChatMessage;
use App\Storage\OnlineUser;
use EasySwoole\EasySwoole\ServerManager;
use App\WebSocket\WebSocketAction;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Task\AbstractInterface\TaskInterface;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class BroadcastTask implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    /**
     * 执行投递
     * @param $taskData
     * @param $taskId
     * @param $fromWorkerId
     * @param $flags
     * @return bool
     */
     function run(int $taskId, int $workerIndex)
    {
        $taskData = $this->taskData;
        /** @var \swoole_websocket_server $server */

        $server = ServerManager::getInstance()->getSwooleServer();

        $messages = $taskData['payload'];
        $aMessage = json_decode($messages, true);
        if (json_last_error()) {
            Logger::getInstance()->log("发送信息解析json失败");
            return ;
        }
        $iMatchId = $aMessage['matchId'];
        $online = OnlineUser::getInstance();
        $aCustomers = Login::getInstance()->lrange(sprintf($online::LIST_ONLINE, $iMatchId), 0, -1);
        //先将信息插入库，然后再分发
        $messageType = $aMessage['type'];
        $iFromUserId = $aMessage['fromUserId'];
        $mFromUser = AdminUser::getInstance()->findOne($iFromUserId);
        $messageData = [
            'sender_user_id' => $iFromUserId,
            'sender_mobile' => $mFromUser['mobile'],
            'sender_nickname' => $mFromUser['nickname'],
            'type' => $messageType,
            'match_id' => $iMatchId,
            'with_message_id' => intval($aMessage['messageId'])
        ];

        $toUser = [];
        $originMessage = [];
        if (!empty($aMessage['messageId'])) {
            //获取to用户相关信息，方便客户端提示用户
            $originMessage = ChatHistory::getInstance()->where('id', $aMessage['messageId'])->getOne();
            $toUser =  AdminUser::getInstance()->findOne($originMessage['sender_user_id']);

        }
        switch ($messageType) {
            case 'text':
                $messageData['content'] = htmlspecialchars(addslashes($aMessage['content']));
                break;
        }

        $iLastInsertId = ChatHistory::getInstance()->insert($messageData);
        if (!$iLastInsertId) {
            Logger::getInstance()->log("发布聊天信息失败");
            return ;
        }
        $tool = Tool::getInstance();

        $aMessageBody = [
            'messageType' => $messageType,
            'messageContent' => [
                'fromMid' => $aMessage['mid'],
                'fromUserId' => $iFromUserId,
                'message_id' => $iLastInsertId,
                'type' => $messageType,
                'content' =>  $messageData['content'],
                'match_id' => $iMatchId,
                'toUser' => $toUser,
                'originMessage' => $originMessage
            ]
        ];

        foreach ($aCustomers as $mid) {
            $userInfo = $online->get($mid);
            $connection = $server->connection_info($userInfo['fd']);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                $server->push($userInfo['fd'], $tool->writeJson(200, 'ok', $aMessageBody));
            }
        }

        // 添加到离线消息
      /*  $payload = json_decode($taskData['payload'], true);
        if ($payload['action'] == 103) {

            $userinfo = OnlineUser::getInstance()->get($taskData['fromFd']);
            $payload['fromUserFd'] = 0;
            $payload['action'] = WebSocketAction::BROADCAST_LAST_MESSAGE;
            $payload['username'] = $userinfo['username'];
            $payload['avatar'] = $userinfo['avatar'];
            ChatMessage::getInstance()->saveMessage(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        }*/
        return true;
    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        throw $throwable;
    }

}
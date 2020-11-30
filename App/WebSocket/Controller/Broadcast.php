<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:19
 */

namespace App\WebSocket\Controller;

use App\lib\Tool;
use App\Task\BroadcastTask;
use App\Utility\Log\Log;
use App\WebSocket\Actions\Broadcast\BroadcastMessage;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\Socket\Client\WebSocket as WebSocketClient;

class Broadcast extends Base
{

    public static $type = ['text' => 0, 'img' => 1];



    /**
     * 发送消息给房间内的所有人
     * @throws \Exception
     */
    function roomBroadcast()
    {

        /** @var WebSocketClient $client */
        $client = $this->caller()->getClient();
        $broadcastPayload = $this->caller()->getArgs();
        if (isset(self::$type[$broadcastPayload['content']])) {
            $type = self::$type[$broadcastPayload['content']];
        } else {
            $type = 1;
        }
        $sender_user_id = $broadcastPayload['sender_user_id'] ?: 0;
        if (!$sender_user_id) {
            $server = ServerManager::getInstance()->getSwooleServer();
            $server->push($client->getFd(), $tool = Tool::getInstance()->writeJson(WebSocketStatus::STATUS_NOT_LOGIN, WebSocketStatus::$msg[WebSocketStatus::STATUS_NOT_LOGIN]));
        }
//        $fd, $args, $message
        if (!$bool = $this->checkUserRight($client->getFd(), $broadcastPayload, $message)) {
            $this->response()->setMessage(Tool::getInstance()->writeJson(406, $message));
            return  ;
        }
        if (!empty($broadcastPayload) && isset($broadcastPayload['content']) && isset($broadcastPayload['match_id'])) {

            $message = new BroadcastMessage;
            $message->setFromUserId($sender_user_id);
            $message->setFromUserFd($client->getFd());
            $message->setContent($broadcastPayload['content']);
            $message->setType($type);
            $message->setSendTime(date('Y-m-d H:i:s'));
            $message->setMatchId($broadcastPayload['match_id']);
            $message->setAtUserId($broadcastPayload['at_user_id']);
            TaskManager::getInstance()->async(new BroadcastTask(['payload' => $message->__toString(), 'fromFd' => $client->getFd()]));
        }
        $this->response()->setStatus($this->response()::STATUS_OK);
    }



}
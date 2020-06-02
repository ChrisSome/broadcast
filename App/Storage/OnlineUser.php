<?php

namespace App\Storage;

use App\lib\pool\Login;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use Swoole\Table;

/**
 * 在线用户
 * Class OnlineUser
 * @package App\Storage
 */
class OnlineUser
{
    use Singleton;
    protected $table;  // 储存用户信息的Table

    const INDEX_TYPE_ROOM_ID = 1;
    const INDEX_TYPE_ACTOR_ID = 2;
    const LIST_ONLINE = 'match:online:user:%s';

    public function __construct()
    {
        TableManager::getInstance()->add('onlineUsers', [
            'fd' => ['type' => Table::TYPE_INT, 'size' => 8],
            'nickname' => ['type' => Table::TYPE_STRING, 'size' => 128], //昵称
            'token' => ['type' => Table::TYPE_STRING, 'size' => 128], //token
            'mid' => ['type' => Table::TYPE_STRING, 'size' => 15], //websocket分配mid
            'last_heartbeat' => ['type' => Table::TYPE_INT, 'size' => 4], //最后心跳
            'match_id' => ['type' => Table::TYPE_INT, 'size' => 4], //比赛id
            'user_id' => ['type' => Table::TYPE_INT, 'size' => 4], //用户id
        ]);

        $this->table = TableManager::getInstance()->get('onlineUsers');
    }

    /**
     * 设置一条用户信息
     * @param $fd
     * @param $mid
     * @param $info
     * @return mixed
     */
    function set($fd, $mid,$info)
    {
        return $this->table->set($mid, [
            'fd' => $fd,
            'mid' => $mid,
            'nickname' => $info['nickname'],
            'token' => $info['token'],
            'user_id' => $info['id'],
            'last_heartbeat' => time()
        ]);
    }

    /**
     * 获取一条用户信息
     * @param $fd
     * @return array|mixed|null
     */
    function get($fd)
    {
        $info = $this->table->get($fd);
        return is_array($info) ? $info : null;
    }

    /**
     * 更新一条用户信息
     * @param $fd
     * @param $data
     */
    function update($mid, $data)
    {
        $info = $this->get($mid);
        if ($info) {
            if (isset($data['match_id']) && !empty($data['match_id'])) {
                $key = sprintf(self::LIST_ONLINE, $data['match_id']);
                Login::getInstance()->rpush($key, $info['mid']);
            }
            $info = $data + $info;
            $this->table->set($mid, $info);
        }
    }


    public function close($mid)
    {
        $info = $this->get($mid);
        if ($info) {
            var_dump($info);
            $key = sprintf(self::LIST_ONLINE, $info['match_id']);
            Login::getInstance()->lrem($key, 0, $mid);
            return $this->table->del($mid);
        }

        return false;

    }
    /**
     * 删除一条用户信息
     * @param $fd
     */
    function delete($fd)
    {
        $info = $this->get($fd);
        if ($info) {
            var_dump('delete', $info);
            $key = sprintf(self::LIST_ONLINE, $info['match_id']);
            Login::getInstance()->lrem($key, 0, $info['mid']);
            $info = ['match_id' => 0] + $info;
            return $this->table->set($info['mid'], $info);
        }

        return false;
    }

    /**
     * 心跳检查
     * @param int $ttl
     */
    function heartbeatCheck($ttl = 86400)
    {
        foreach ($this->table as $item) {
            $time = $item['time'];
            if (($time + $ttl) < $time) {
                var_dump('auto delete:'.$item['mid']);
                $this->close($item['mid']);
            }
        }
    }

    /**
     * 心跳更新
     * @param $fd
     */
    function updateHeartbeat($fd)
    {
        $this->update($fd, [
            'last_heartbeat' => time()
        ]);
    }

    /**
     * 直接获取当前的表
     * @return Table|null
     */
    function table()
    {
        return $this->table;
    }
}
<?php

namespace App\Storage;

use App\lib\pool\Login;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use Swoole\Table;

/**
 * 在线用户
 * Class MatchLive
 * @package App\Storage
 */
class MatchLive
{
    use Singleton;
    protected $table;  // 储存用户信息的Table
    const LIST_ONLINE = 'match:online:user:%s';

    public function __construct()
    {
        TableManager::getInstance()->add('matchLive', [
            'match_id' => ['type' => Table::TYPE_INT, 'size' => 11],
            'tlive' => ['type' => Table::TYPE_STRING, 'size' => 16384], //文字直播字段
            'stats' => ['type' => Table::TYPE_STRING, 'size' => 4096], //比赛统计字段
            'score' => ['type' => Table::TYPE_STRING, 'size' => 4096], //比赛比分字段
            'last_heartbeat' => ['type' => Table::TYPE_INT, 'size' => 4], //最后心跳

        ]);

        $this->table = TableManager::getInstance()->get('matchLive');
    }

    /**
     * @param $match_id
     * @param $tlive
     * @param $stats
     * @param $score
     * @return mixed
     */
    function set($match_id, $tlive, $stats, $score)
    {


        return $this->table->set($match_id, [
            'match_id' => $match_id,
            'tlive' => $tlive,
            'stats' => $stats,
            'score' => $score,
            'last_heartbeat' => time(),
        ]);
    }

    /**
     * @param $match_id
     * @return array|null
     */
    function get($match_id)
    {

        $info = $this->table->get($match_id);
        return is_array($info) ? $info : null;
    }

    /**
     * 更新一条用户信息
     * @param $match_id
     * @param $data
     */
    function update($match_id, $data)
    {
        $info = $this->get($match_id);
        if ($info) {
            $info = $data + $info;
            $this->table->set($match_id, $info);
        }
    }


    /**
     * @param $match_id
     * @return bool
     */
    public function close($match_id)
    {
        $info = $this->get($match_id);
        if ($info) {

            return $this->table->del($match_id);
        }

        return false;

    }


    /**
     * @param $match_id
     * @return bool
     */
    function delete($match_id)
    {
        $info = $this->get($match_id);
        if ($info) {
            return $this->table->del($match_id);

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
     * 直接获取当前的表所有数据
     * @return Table|null
     */
    function table()
    {
        return $this->table;
    }


}
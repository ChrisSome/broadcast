<?php


namespace App\lib\pool;


use App\lib\Tool;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Singleton;

class User extends RedisPool
{

    /**
     * 用户信息redis lib,用户储存一些非必要数据
     */
    use Singleton;

    const USER_FOLLOWS = "user_follows:uid:%s";  //用户关注列表


    /**
     * 关注用户
     * @param $key
     * @param $val
     * @return mixed
     */
    public function addUserFollows($key, $val)
    {
        return $this->sadd($key, $val);
    }

    public function delUserFollows($key, $val)
    {
        return $this->srem($key, $val);
    }
    /**
     * 关注列表
     * @param $id
     * @param $mid
     * @return string
     */
    public function getUserFollowings($key)
    {
        return $this->smembers($key);
    }

    public function getCount($key)
    {
        return $this->scard($key);
    }


    /**
     * 更新token
     * @param $uid
     * @param $mid
     * @return mixed
     */
    public function updateToken($uid, $mid)
    {
        return $this->expires(sprintf('hash:user:%s:%s', $uid, $mid), 7200);
    }
}
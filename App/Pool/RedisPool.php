<?php
/**
 * Created by PhpStorm.
 * User: ethan
 */

namespace App\Pool;

use EasySwoole\Pool\Config;
use EasySwoole\Pool\AbstractPool;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\Redis\Redis;

use EasySwoole\Pool\Manager;

class RedisPool extends AbstractPool
{
    protected $redisConfig;

    /**
     * 重写构造函数,为了传入redis配置
     * RedisPool constructor.
     * @param Config      $conf
     * @param RedisConfig $redisConfig
     * @throws \EasySwoole\Pool\Exception\Exception
     */
    public function __construct()
    {
        $config = new Config();
        $redisConfig = new RedisConfig(\EasySwoole\EasySwoole\Config::getInstance()->getConf('REDIS'));
        Manager::getInstance()->register(new RedisPool($config,$redisConfig),'redis');


        $this->redisConfig = $redisConfig;
    }

    protected function createObject()
    {
        //根据传入的redis配置进行new 一个redis
        $redis = new Redis($this->redisConfig);
        return $redis;
    }
}
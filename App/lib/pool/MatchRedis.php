<?php


namespace App\lib\pool;


use App\lib\Tool;
use App\Utility\Log\Log;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Singleton;

class MatchRedis extends RedisPool
{
    const MATCH_TLIVE_KEY = 'match_tlive_%s';    //储存match_tlive事件
    const MATCH_STATS_KEY = 'match_stats_%s';    //储存match_tlive事件
    const MATCH_SCORE_KEY = 'match_score_%s';    //储存match_tlive事件
    const MATCH_INCIDENT_KEY = 'match_incident_%s';    //储存match_tlive事件
}
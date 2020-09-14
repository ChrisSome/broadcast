<?php


namespace App\Process;


use App\lib\pool\Login;
use App\lib\pool\MatchRedis;
use App\lib\Tool;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Storage\MatchLive;
use App\Utility\Log\Log;
use App\WebSocket\Controller\Match;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;

class NamiPushTask extends AbstractProcess
{
    protected $taskData;
    private $url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
    private $user = 'mark9527';
    private $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    function run($args)
    {

        Timer::getInstance()->loop(2 * 1000, function () {
            $res = Tool::getInstance()->postApi(sprintf($this->url, $this->user, $this->secret));
            if ($res) {
                $resDecode = json_decode($res, true);

                foreach ($resDecode as $item) {
                    if (!isset($item['tlive']) || Cache::get('is_back_up_' . $item['id'])) {

                        continue;
                    }

                    TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($item) {
                       $match = AdminMatch::getInstance()->where('match_id', $item['id'])->get();
                        if ($match && $match->status_id == 8) {
                            if (!$matchTlive = AdminMatchTlive::getInstance()->where('match_id', $item['id'])->get()) {
                                $data = [
                                    'score' => json_encode($item['score']),
                                    'stats' => isset($item['stats']) ? json_encode($item['stats']) : '',
                                    'incidents' => isset($item['incidents']) ? json_encode($item['incidents']) : '',
                                    'tlive' => isset($item['tlive']) ? json_encode($item['tlive']) : '',
                                    'match_id' => $item['id'],
                                ];
                                AdminMatchTlive::getInstance()->insert($data);
                                Cache::set('is_back_up_' . $item['id'], 1, 60*240);
                            }

                        }
                    });
                    if (isset($item['stats'])) {
                        $matchStats = [];
                        foreach ($item['stats'] as $ki=>$vi) {
                            //2 角球  4：红牌 3：黄牌 21：射正 22：射偏  23:进攻  24危险进攻 25：控球率
                            if ($vi['type'] == 2 || $vi['type'] == 4 || $vi['type'] == 3 || $vi['type'] == 21 || $vi['type'] == 22 || $vi['type'] == 23  || $vi['type'] == 24  || $vi['type'] == 25) {
                                $matchStats[] = $vi;
                            }

                        }

                    } else {
                        $matchStats = [];
                    }
                    if (isset($item['tlive'])) {
                        foreach ($item['tlive'] as $k=>$v) {
                            unset($item['tlive'][$k]['time']);
                            unset($item['tlive'][$k]['main']);

                        }
                    }
                    $tlive_key = sprintf(MatchRedis::MATCH_TLIVE_KEY, $item['id']);
                    $stats_key = sprintf(MatchRedis::MATCH_STATS_KEY, $item['id']);
                    $score_key = sprintf(MatchRedis::MATCH_SCORE_KEY, $item['id']);


                    $matchRedis = new MatchRedis();
                    if (!$oldStats = MatchRedis::getInstance()->get($stats_key) && $matchStats) {
//                        MatchRedis::getInstance()->setEx($stats_key, 60*240, json_encode($matchStats));
                        $matchRedis->setEx($stats_key, 60*240, json_encode($matchStats));
                    } else {
//                        MatchRedis::getInstance()->set($stats_key, json_encode($matchStats));
                        $matchRedis->set($stats_key, json_encode($matchStats));
                    }

                    if (!$oldScore = MatchRedis::getInstance()->get($score_key) && $item['score']) {
//                        MatchRedis::getInstance()->setEx($score_key, 60*240, json_encode($item['score']));
                        $matchRedis->setEx($score_key, 60*240, json_encode($matchStats));


                    } else {
//                        MatchRedis::getInstance()->set($score_key, json_encode($item['score']));
                        $matchRedis->set($score_key, json_encode($matchStats));

                    }

                    if (!$oldTlive = MatchRedis::getInstance()->get($tlive_key) && $item['tlive']) {
//                        MatchRedis::getInstance()->setEx($tlive_key, 60*240, json_encode($item['tlive']));
                        $matchRedis->setEx($tlive_key, 60*240, json_encode($matchStats));

                    } else {
                        $diff = array_slice($item['tlive'], count(json_decode($oldTlive, true)));
                        if ($diff) {
//                            MatchRedis::getInstance()->set($tlive_key, json_encode($item['tlive']));
                            $matchRedis->set($tlive_key, json_encode($matchStats));

                        }
                        $this->pushContent($item['id'], $diff, $matchStats, $item['score']);


                    }

                }


            }


        });
    }




    /**
     * @param $match_id
     * @param $tlive
     * @param $stats
     * @param $score
     */
    function pushContent($match_id, $tlive, $stats, $score)
    {
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $users = Login::getInstance()->getUsersInRoom($match_id);
        $returnData = [
            'event' => 'match_tlive',
            'contents' => ['tlive' => $tlive, 'stats' => $stats, 'score' => $score, 'match_id' => $match_id]
        ];


        if ($users) {
            foreach ($users as $user) {
                $connection = $server->connection_info($user);

                if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                    $server->push($user, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                }
            }
        }
    }




    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}
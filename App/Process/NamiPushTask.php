<?php


namespace App\Process;


use App\Common\AppFunc;
use App\HttpController\User\WebSocket;
use App\lib\Tool;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Storage\MatchLive;
use App\Task\MatchNotice;
use App\Task\MatchUpdate;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\Task\TaskManager;

class NamiPushTask extends AbstractProcess
{
    protected $taskData;
    private $url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
    protected $trend_detail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s'; //获取比赛趋势详情

    private $user = 'mark9527';
    private $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    function run($args)
    {

        Timer::getInstance()->loop(30 * 1000, function () {
            $res = Tool::getInstance()->postApi(sprintf($this->url, $this->user, $this->secret));
            if ($decode = json_decode($res, true)) {
                $match_info = [];
                foreach ($decode as $item) {

                    //无效比赛 跳过
                    if (!$match = AdminMatch::getInstance()->where('match_id', $item['id'])->get()) {
                        continue;
                    }

                    //比赛结束 跳过
                    if (Cache::get('match_notice_over:' . $item['id'])) {
                        continue;
                    }
                    $status = $item['score'][1];
                    if (!in_array($status, [1, 2, 3, 4, 5, 7, 8])) { //上半场 / 下半场 / 中场 / 加时赛 / 点球决战 / 结束
                        continue;
                    }
                    //比赛趋势
                    $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['id']));
                    $match_trend = json_decode($match_res, true);
                    if ($match_trend['code'] != 0) {
                        $match_trend_info = [];
                    } else {
                        $match_trend_info = $match_trend['results'];
                    }
                    //比赛结束通知
                    if ($item['score'][1] == 8) { //结束
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'],  'item' => $item,  'type'=>12, 'match_trend' => $match_trend_info, 'competition_id' => $match->competition_id]));
                    }

                    //不在热门赛事中  跳过
                    if (!AppFunc::isInHotCompetition($match->competition_id)) {

                        continue;
                    }
                    //设置比赛进行时间
                    AppFunc::setPlayingTime($item['id'], $item['score']);

                    //比赛开始的通知
                    if ($item['score'][1] == 2 && !Cache::get('match_notice_start:' . $item['id'])) { //开始
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'],  'item' => $item,  'type'=>10, 'match_trend' => $match_trend_info]));
                        Cache::set('match_notice_start:' . $item['id'], 1, 60 * 240);
                    }
                    $matchStats = [];
                    if (isset($item['stats'])) {
                        foreach ($item['stats'] as $ki => $vi) {
                            // 21：射正 22：射偏  23:进攻  24危险进攻 25：控球率
                            if ($vi['type'] == 21 || $vi['type'] == 22 || $vi['type'] == 23 || $vi['type'] == 24 || $vi['type'] == 25) {
                                $matchStats[] = $vi;
                            }

                        }
                        Cache::set('match_stats_' . $item['id'], json_encode($matchStats), 60 * 240);

                    }


                    if (isset($item['tlive'])) {

                        Cache::set('match_tlive_' . $item['id'], json_encode($item['tlive']), 60 * 240);
                        $goal_count_old = Cache::get('goal_count_' . $item['id']);
                        $corner_count_old = Cache::get('corner_count' . $item['id']);
                        $yellow_card_count_old = Cache::get('yellow_card_count' . $item['id']);
                        $red_card_count_old = Cache::get('red_card_count' . $item['id']);
                        $match_tlive_count_old = Cache::get('match_tlive_count' . $item['id']);

                        $last_goal_tlive = [];
                        $last_corner_tlive = [];
                        $last_yellow_card_tlive = [];
                        $last_red_card_tlive = [];

                        $goal_count_new = 0;
                        $corner_count_new = 0;
                        $yellow_card_count_new = 0;
                        $red_card_count_new = 0;
                        $match_tlive_count_new = 0;

                        $goal_tlive_total = [];
                        $corner_tlive_total = [];
                        $yellow_card_tlive_total = [];
                        $red_card_tlive_total = [];


                        foreach ($item['tlive'] as $item_tlive) {

                            $match_tlive_count_new += 1;
                            $item_tlive['time'] = intval($item_tlive['time']);

                            if ($item_tlive['type'] == 1 || $item_tlive['type'] == 8) { //进球 /点球
                                $last_goal_tlive = $item_tlive;
                                $goal_count_new += 1;
                                $goal_tlive_total[] = $item_tlive;
                            }
                            if ($item_tlive['type'] == 2) { //角球
                                $last_corner_tlive = $item_tlive;
                                $corner_count_new += 1;
                                $corner_tlive_total[] = $item_tlive;
                            }
                            if ($item_tlive['type'] == 3) { //黄牌
                                $last_yellow_card_tlive = $item_tlive;
                                $yellow_card_count_new += 1;
                                $yellow_card_tlive_total[] = $item_tlive;
                            }

                            if ($item_tlive['type'] == 4) { //红牌
                                $last_red_card_tlive = $item_tlive;
                                $red_card_count_new += 1;
                                $red_card_tlive_total[] = $item_tlive;
                            }

                        }
                        $signal_count = [
                            'goal' => isset($goal_tlive_total) ? $goal_tlive_total : [], //所有进球tlive
                            'corner' => isset($corner_tlive_total) ? $corner_tlive_total : [],
                            'yellow_card' => isset($yellow_card_tlive_total) ? $yellow_card_tlive_total : [],
                            'red_card' => isset($red_card_tlive_total) ? $red_card_tlive_total : [],
                        ];

                        $signal_match_info = [
                            'signal_count' => $signal_count,
                            'match_trend' => $match_trend_info,
                            'match_id' => $item['id'],
                            'time' => AppFunc::getPlayingTime($item['id']),
                            'status' => $status,
                            'match_stats' => $matchStats,
                            'score' => [
                                'home' => $item['score'][2],
                                'away' => $item['score'][3]
                            ]
                        ];

                        $match_info[] = $signal_match_info;

                        Cache::set('match_data_info' .$item['id'], json_encode($signal_match_info), 60 * 240);

                        unset($signal_match_info);

                        if ($goal_count_new > $goal_count_old) {//进球
                            TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_goal_tlive, 'score' => $item['score'], 'type'=>1]));
                            Cache::set('goal_count_' . $item['id'], $goal_count_new, 60 * 240);
                        }

                        if ($yellow_card_count_new > $yellow_card_count_old) {//黄牌

                            TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_yellow_card_tlive, 'score' => $item['score'], 'type'=>3]));
                            Cache::set('yellow_card_count' . $item['id'], $yellow_card_count_new, 60 * 240);

                        }

                        if ($red_card_count_new > $red_card_count_old) {//红牌

                            TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_red_card_tlive, 'score' => $item['score'], 'type'=>4]));
                            Cache::set('red_card_count' . $item['id'], $red_card_count_new, 60 * 240);

                        }

                        if ($match_tlive_count_new > $match_tlive_count_old) { //直播文字
                            Cache::set('match_tlive_count' . $item['id'], $match_tlive_count_new, 60 * 240);
                            $diff = array_slice($item['tlive'], $match_tlive_count_old);

                            (new WebSocket())->contentPush($diff, $item['id']);
                        }
                    }

                }
                Log::getInstance()->info('exist');
                TaskManager::getInstance()->async(new MatchUpdate(['match_info_list' => $match_info]));


            }

        });
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
<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\GeTui\BatchSignalPush;
use App\lib\pool\MatchRedis;
use App\lib\pool\User as UserRedis;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminNoticeMatch;
use App\Model\AdminUser;
use App\Model\AdminUserSetting;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;
use EasySwoole\Task\AbstractInterface\TaskInterface;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class GameOverTask implements TaskInterface{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    function run(int $taskId, int $workerIndex){

        $match_id = $this->taskData['match_id'];
        if (Cache::get('is_back_up_' . $match_id)) {
            return;
        } else {
            //持久化数据
            $item = $this->taskData['item'];
            if (!AdminMatchTlive::getInstance()->where('match_id', $match_id)->get()) {
                $data = [
                    'score' => json_encode($item['score']),
                    'stats' => json_encode($item['stats']),
                    'incidents' => json_encode($item['incidents']),
                    'tlive' => json_encode($item['tlive']),
                    'match_id' => $item['id']
                ];
                AdminMatchTlive::getInstance()->insert($data);
                Cache::set('is_back_up_' . $item['id'], 1, 60*240);
            }

        }

        //发送通知
        $goal_key = sprintf(MatchRedis::MATCH_GOAL_COUNT_KEY, $match_id);

        $goal_info = MatchRedis::getInstance()->get($goal_key);
        $lastIncident = $goal_info ? json_decode($goal_info) : [];
        $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
        if ($match) {
            $key = sprintf(UserRedis::USER_INTEREST_MATCH, $match->match_id);
            if (!$prepareNoticeUserIds = UserRedis::getInstance()->smembers($key)) {
                return;
            } else {

                $users = AdminUser::getInstance()->where('id', $prepareNoticeUserIds, 'in')->field(['cid', 'id'])->all();
                foreach ($users as $k=>$user) {

                    $userSetting = AdminUserSetting::getInstance()->where('user_id', $user['id'])->get();
                    $pushSetting = json_decode($userSetting->push, true);

                    if (!$userSetting || !$pushSetting['over']) {
                        unset($users[$k]);
                    }
                }
                $uids = array_column($users, 'id');
                $cids = array_column($users, 'cid');

                if (!$uids) {

                    return;
                }
                $batchPush = new BatchSignalPush();
                $info = [
                    'match_id' => $match->match_id,
                    'home_name_zh' => $match->homeTeamName()->name_zh,
                    'away_name_zh' => $match->awayTeamName()->name_zh,
                    'competition_name' => $match->competitionName()->short_name_zh,
                ];
                $info['type'] = 2;  //完赛通知
                $info['title'] = '完赛通知';

                $homeScore = isset($lastIncident['home_score']) ? $lastIncident['home_score'] : 0;
                $awayScore = isset($lastIncident['away_score']) ? $lastIncident['away_score'] : 0;
                $info['content'] = sprintf("%s %s(%s)-%s(%s),比赛结束",  $info['competition_name'], $info['home_name_zh'],$homeScore, $info['away_name_zh'], $awayScore);
                $insertData = [
                    'uids' => json_encode($uids),
                    'match_id' => $match->match_id,
                    'type' => 2,
                    'title' => $info['title'],
                    'content' => $info['content']
                ];
                if (!$res = AdminNoticeMatch::getInstance()->where('match_id', $match->match_id)->where('type', 2)->get()) {

                    $rs = AdminNoticeMatch::getInstance()->insert($insertData);
                    $info['rs'] = $rs;  //开赛通知

                    $batchPush->pushMessageToSingleBatch($cids, $info);


                } else {

                    $batchPush = new BatchSignalPush();
                    if ($res->is_notice == 1) {
                        return;
                    }
                    $info['rs'] = $res->id;

                    $batchPush->pushMessageToSingleBatch($cids, $info);
                }
            }
        }
    }
    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        throw $throwable;
    }
}
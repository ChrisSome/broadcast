<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\GeTui\BatchSignalPush;
use App\lib\pool\User as UserRedis;
use App\Model\AdminMatch;
use App\Model\AdminNoticeMatch;
use App\Model\AdminUser;
use App\Model\AdminUserSetting;
use App\Utility\Log\Log;
use EasySwoole\Task\AbstractInterface\TaskInterface;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class GoalTask implements TaskInterface{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }
    function run(int $taskId, int $workerIndex){
        $match_id = $this->taskData['match_id'];
        $incident = $this->taskData['incident'];
        $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
        if ($match) {
            $key = sprintf(UserRedis::USER_INTEREST_MATCH, $match->match_id);
            if (!$prepareNoticeUserIds = UserRedis::getInstance()->smembers($key)) {
                Log::getInstance()->info('无人关注');
                return;
            } else {
                $users = AdminUser::getInstance()->where('id', $prepareNoticeUserIds, 'in')->field(['cid', 'id'])->all();
                foreach ($users as $k=>$user) {
                    $userSetting = AdminUserSetting::getInstance()->where('user_id', $user['id'])->get();
                    if (!$userSetting || !$userSetting->followMatch) {
                        unset($users[$k]);
                    }
                }
                $uids = array_column($users, 'id');
                $cids = array_column($users, 'cid');

                if (!$uids || !$cids) {
                    return ;
                }
                $insertData = [
                    'uids' => json_encode($uids),
                    'match_id' => $match->match_id,
                    'type' => 3
                ];
                $batchPush = new BatchSignalPush();
                $info = [
                    'match_id' => $match->match_id,
                    'home_name_zh' => $match->homeTeamName()->name_zh,
                    'away_name_zh' => $match->awayTeamName()->name_zh,
                    'competition_name' => $match->competitionName()->short_name_zh,
                ];
                $info['type'] = 3;  //进球通知
                $info['title'] = '进球提示';
                if ($incident['position'] == 1) {
                    $info['content'] = sprintf("%s' %s %s(进球)%s-%s %s", $incident['time'], $info['competition_name'], $info['home_name_zh'], $incident['home_score'], $incident['away_score'], $info['away_name_zh']);

                } else {
                    $info['content'] = sprintf("%s' %s %s%s-%s %s(进球)", $incident['time'], $info['competition_name'], $info['home_name_zh'], $incident['home_score'], $incident['away_score'], $info['away_name_zh']);

                }

                if (!$res = AdminNoticeMatch::getInstance()->where('match_id', $match->match_id)->get()) {


                    $rs = AdminNoticeMatch::getInstance()->insert($insertData);
                    $info['rs'] = $rs;  //进球通知

                    $batchPush->pushMessageToSingleBatch($cids, $info);


                } else {

                    $batchPush = new BatchSignalPush();
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
<?php
namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\pool\Login;
use App\lib\pool\MatchRedis;
use App\Model\AdminAllStat;
use App\Model\AdminClashHistory;
use App\Model\AdminCompetition;
use App\Model\AdminHonorList;
use App\Model\AdminMatch;
use App\Model\AdminNoticeMatch;
use App\Model\AdminPlayer;
use App\Model\AdminPlayerChangeClub;
use App\Model\AdminPlayerStat;
use App\Model\AdminSeason;
use App\Model\AdminSteam;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminTeamHonor;
use App\Model\AdminTeamLineUp;
use App\Model\AdminUser;
use App\Model\AdminUserSetting;
use App\Storage\OnlineUser;
use App\Task\GameOverTask;
use App\Task\GoalTask;
use App\Utility\Log\Log;
use App\lib\Tool;
use App\Utility\Message\Status;
use App\lib\pool\User as UserRedis;
use App\GeTui\BatchSignalPush;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;

class FootBallMatch extends FrontUserController
{
    const STATUS_SUCCESS = 0; //请求成功
    protected $isCheckSign = false;
    public $needCheckToken = false;
    public $start_id = 0;
    protected $user = 'mark9527';

    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    protected $url = 'https://open.sportnanoapi.com';

    protected $uriMatchList = '/api/v4/football/competition/list?user=%s&secret=%s';
    protected $uriTeamList = '/api/v4/football/team/list?user=%s&secret=%s&id=%s';

    protected $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
    protected $uriCompetition = '/api/v4/football/competition/list?user=%s&secret=%s&id=%s';

    protected $uriStage = '/api/v4/football/stage/list?user=%s&secret=%s&date=%s';

    protected $uriSteam = '/api/sports/stream/urls_free?user=%s&secret=%s'; //直播地址
    protected $uriLineUp = '/api/v4/football/team/squad/list?user=%s&secret=%s&id=%s';  //阵容
    protected $uriPlayer = '/api/v4/football/player/list?user=%s&secret=%s&time=%s';  //球员
    protected $uriCompensation = '/api/v4/football/compensation/list?user=%s&secret=%s&date=%s&id=%s';  //获取比赛历史同赔统计数据列表
    protected $live_url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
    protected $season_url = 'https://open.sportnanoapi.com/api/v4/football/season/list?user=%s&secret=%s&id=%s'; //更新赛季
    protected $player_stat = 'https://open.sportnanoapi.com/api/v4/football/player/list/with_stat?user=%s&secret=%s&id=%s'; //获取球员能力技术列表
    protected $player_change_club_history = 'https://open.sportnanoapi.com/api/v4/football/transfer/list?user=%s&secret=%s&id=%s'; //球员转会历史
    protected $team_honor = 'https://open.sportnanoapi.com/api/v4/football/team/honor/list?user=%s&secret=%s&id=%s'; //球队荣誉
    protected $honor_list = 'https://open.sportnanoapi.com/api/v4/football/honor/list?user=%s&secret=%s&id=%s'; //荣誉详情
    protected $all_stat = 'https://open.sportnanoapi.com/api/v4/football/season/all/stats/detail?user=%s&secret=%s&id=%s'; //获取赛季球队球员统计详情-全量

    protected $uriDeleteMatch = '/api/v4/football/deleted?user=%s&secret=%s'; //删除或取消的比赛

    public function allStat()
    {


        $a = AdminAllStat::getInstance()->max('season_id');

        $seasonids = AdminSeason::getInstance()->where('season_id', isset($a) ? $a : 0, '>')->field(['season_id'])->all();

        $seasonids = array_column($seasonids, 'season_id');

//        return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN], $seasonids);

        foreach ($seasonids as $seasonid) {
            if (AdminAllStat::getInstance()->where('season_id', $seasonid)->get()) {
                continue;
            } else {
                $res = Tool::getInstance()->postApi(sprintf($this->all_stat, $this->user, $this->secret, 3150));
                $teams = json_decode($res, true);
//                return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN], $teams);

                $decode = $teams['results'];
                if (!$decode) {
                    continue;
                }
                if (AdminAllStat::getInstance()->where('season_id', $seasonid)->get()) {
                    continue;
                }
                $data = [
                    'players_stats' => json_encode($decode['players_stats']),
                    'shooters' => json_encode($decode['shooters']),
                    'teams_stats' => json_encode($decode['teams_stats']),
                    'updated_at' => $decode['updated_at'],
                    'season_id' => $seasonid,
                ];
                AdminAllStat::getInstance()->insert($data);

            }
        }


        return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN], $teams);

    }
    function index()
    {
        $res = Tool::getInstance()->postApi(sprintf($this->url . $this->uriMatchList, $this->user, $this->secret));

        $matchsInfo = json_decode($res, true);
        if (json_last_error()) {
            return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN]);

        }
        if ($matchsInfo['code'] == self::STATUS_SUCCESS) {

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $matchsInfo);

        } else {

            return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN]);

        }

    }


    /**
     * 每天跑一次
     * @return bool
     */
    function teamList()
    {

        $max = AdminTeam::getInstance()->order('team_id', 'DESC')->limit(1)->all()[0];
        $maxId = $max['team_id'];
        $url = sprintf($this->url . $this->uriTeamList, $this->user, $this->secret, $maxId + 1);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        if ($teams['query']['total'] == 0) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], '插入完成');

        }
        $decodeTeams = $teams['results'];
        foreach ($decodeTeams as $team) {
            $exist = AdminTeam::getInstance()->where('team_id', $team['id'])->all();
            if ($exist) {
                continue;
            }
            $data = [
                'team_id' => $team['id'],
                'competition_id' => $team['competition_id'],
                'country_id' => $team['country_id'],
                'name_zh' => $team['name_zh'],
                'logo' => $team['logo'],
                'national' => $team['national'],
                'foundation_time' => $team['foundation_time'],
                'website' => $team['website'],
                'manager_id' => $team['manager_id'],
                'venue_id' => $team['venue_id'],
                'market_value' => $team['market_value'],
                'market_value_currency' => $team['market_value_currency'],
                'country_logo' => $team['country_logo'],
                'total_players' => $team['total_players'],
                'foreign_players' => $team['foreign_players'],
                'national_players' => $team['national_players'],
                'updated_at' => $team['updated_at'],
            ];

            if (!AdminTeam::getInstance()->insert($data)) {
                $sql = AdminTeam::getInstance()->lastQuery()->getLastQuery();
            }
        }
        self::teamList();
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $max);


    }

    /**
     * 当天比赛 十分钟一次
     * @param int $isUpdateYes
     */
    function todayMatchList($isUpdateYes = 0)
    {

        if ($isUpdateYes) {
            $time = date("Ymd", strtotime("-1 day"));
        } else {
            $time = date('Ymd');
        }

        $url = sprintf($this->uriM, $this->user, $this->secret, $time);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
//                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $teams);

        $decodeDatas = $teams['results'];
        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' 更新无数据');
        }
        foreach ($decodeDatas as $data) {

            $insertData = [
                'match_id' => $data['id'],
                'competition_id' => $data['competition_id'],
                'home_team_id' => $data['home_team_id'],
                'away_team_id' => $data['away_team_id'],
                'match_time' => $data['match_time'],
                'neutral' => $data['neutral'],
                'note' => $data['note'],
                'home_scores' => json_encode($data['home_scores']),
                'away_scores' => json_encode($data['away_scores']),
                'home_position' => $data['home_position'],
                'away_position' => $data['away_position'],
                'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                'round' => isset($data['round']) ? json_encode($data['round']) : '',
                'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                'status_id' => $data['status_id'],
                'updated_at' => $data['updated_at'],
            ];

            if ($signal = AdminMatch::getInstance()->where('match_id', $data['id'])->get()) {
                $signal->neutral = $data['neutral'];
                $signal->note = $data['note'];
                $signal->match_time = $data['match_time'];
                $signal->competition_id = $data['competition_id'];
                $signal->home_team_id = $data['home_team_id'];
                $signal->away_team_id = $data['away_team_id'];
                $signal->home_scores = json_encode($data['home_scores']);
                $signal->away_scores = json_encode($data['away_scores']);
                $signal->home_position = $data['home_position'];
                $signal->away_position = $data['away_position'];
                $signal->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                $signal->venue_id = isset($data['venue_id']) ? $data['venue_id'] : 0;
                $signal->referee_id = isset($data['referee_id']) ? $data['referee_id'] : 0;
                $signal->round = isset($data['round']) ? json_encode($data['round']) : '';
                $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal->status_id = $data['status_id'];
                $signal->updated_at = $data['updated_at'];
                $signal->update();

            } else {
                AdminMatch::getInstance()->insert($insertData);
            }
        }
        if ($isUpdateYes) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' 昨日比赛更新完成');

        } else {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' 当天比赛更新完成');

        }

    }


    /**
     * 昨天的比赛 十分钟一次  凌晨0-3
     */
    public function updateYesMatch()
    {

        $this->todayMatchList(1);
    }

    /**
     *
     * @return bool
     */
    function getWeekMatches()
    {
        $weeks = FrontService::getWeek();
        foreach ($weeks as $week) {
            $url = sprintf($this->uriM, $this->user, $this->secret, $week);
//            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $weeks);

            $res = Tool::getInstance()->postApi($url);
            $teams = json_decode($res, true);
            $decodeDatas = $teams['results'];
            if (!$decodeDatas) {
                return;
            }
            foreach ($decodeDatas as $data) {

                $insertData = [
                    'match_id' => $data['id'],
                    'competition_id' => $data['competition_id'],
                    'home_team_id' => $data['home_team_id'],
                    'away_team_id' => $data['away_team_id'],
                    'match_time' => $data['match_time'],
                    'neutral' => $data['neutral'],
                    'note' => $data['note'],
                    'home_scores' => json_encode($data['home_scores']),
                    'away_scores' => json_encode($data['away_scores']),
                    'home_position' => $data['home_position'],
                    'away_position' => $data['away_position'],
                    'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                    'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                    'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                    'round' => isset($data['round']) ? json_encode($data['round']) : '',
                    'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                    'status_id' => $data['status_id'],
                    'updated_at' => $data['updated_at'],

                ];

                if ($signal = AdminMatch::getInstance()->where('match_id', $data['id'])->get()) {
                    AdminMatch::getInstance()->update($insertData, ['match_id' => $data['id']]);

                } else {
                    AdminMatch::getInstance()->insert($insertData);
                }
            }
        }
        Log::getInstance()->info(date('Y-d-d H:i:s') . ' 未来一周比赛更新完成');

    }

    /**
     * 每天一次
     * @return bool
     */
    function competitionList()
    {

        $max = AdminCompetition::getInstance()->order('competition_id', 'DESC')->limit(1)->all()[0];
        $maxId = $max['competition_id'];
        $url = sprintf($this->url . $this->uriCompetition, $this->user, $this->secret, $maxId + 1);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        if ($teams['query']['total'] == 0) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], '插入完成');

        }
        $datas = $teams['results'];
        foreach ($datas as $data) {
            $insertData = [
                'competition_id' => $data['id'],
                'category_id' => $data['category_id'],
                'country_id' => $data['country_id'],
                'name_zh' => $data['name_zh'],
                'short_name_zh' => $data['short_name_zh'],
                'type' => $data['type'],
                'cur_season_id' => $data['cur_season_id'],
                'cur_stage_id' => $data['cur_stage_id'],
                'cur_round' => $data['cur_round'],
                'round_count' => $data['round_count'],
                'logo' => $data['logo'],
                'title_holder' => $data['title_holder'] ? json_encode($data['title_holder']) : null,
                'most_titles' => $data['most_titles'] ? json_encode($data['most_titles']) : null,
                'newcomers' => $data['newcomers'] ? json_encode($data['newcomers']) : null,
                'divisions' => $data['divisions'] ? json_encode($data['divisions']) : null,
                'host' => $data['host'] ? json_encode($data['host']) : null,
                'primary_color' => $data['primary_color'],
                'secondary_color' => $data['secondary_color'],
            ];
            $exist = AdminCompetition::getInstance()->where('competition_id', $data['id'])->all();
            if ($exist) {
                AdminCompetition::getInstance()->update($insertData, ['competition_id' => $data['id']]);
            } else {
                AdminCompetition::getInstance()->insert($insertData);
            }
        }

        Log::getInstance()->info(date('Y-m-d H:i:s') . ' 更新赛季');
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $teams);

    }


    /**
     * 赛事阶段信息
     */
    public function stageList()
    {
//        $time = strtotime(date('Y-m-d',strtotime('-7 day')));

        $url = sprintf($this->url . $this->uriStage, $this->user, $this->secret, time());

        $res = Tool::getInstance()->postApi($url);
        $stages = json_decode($res, true);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $stages);


    }

    /**
     * 直播地址  每分钟一次
     * @return bool
     */
    public function steamList()
    {
        $url = sprintf($this->url . $this->uriSteam, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $steam = json_decode($res, true)['data'];
        if (!$steam) {
            return;
        }
        foreach ($steam as $item) {
            $data = [
                'sport_id' => $item['sport_id'],
                'match_id' => $item['match_id'],
                'match_time' => $item['match_time'],
                'comp' => $item['comp'],
                'home' => $item['home'],
                'away' => $item['away'],
                'mobile_link' => $item['mobile_link'],
                'pc_link' => $item['pc_link'],
            ];

            if (AdminSteam::getInstance()->where('match_id', $item['match_id'])->get()) {
                AdminSteam::getInstance()->update($data, ['match_id' => $item['match_id']]);
            } else {
                AdminSteam::getInstance()->insert($data);

            }
        }
        Log::getInstance()->info('视频直播源更新完毕');

    }

    /**
     * 阵容
     */
    public function getLineUp($maxId = 0)
    {

//        $maxid = $maxId ? $maxId : 0;
        $url = sprintf($this->url . $this->uriLineUp, $this->user, $this->secret, 24834);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if (!$resp['results']) {
            return $this->writeJson(Status::CODE_OK, '更新完成');

        }
        foreach ($resp['results'] as $item) {
            $inert = [
                'team_id' => $item['id'],
                'team' => json_encode($item['team']),
                'squad' => json_encode($item['squad']),
                'updated_at' => $item['updated_at'],
            ];
            if (AdminTeamLineUp::getInstance()->where('team_id', $item['id'])->get()) {
                AdminTeamLineUp::getInstance()->update($inert, ['team_id' => $item['id']]);
            } else {
                AdminTeamLineUp::getInstance()->insert($inert);
            }
        }

        self::getLineUp($resp['query']['max_id']);


    }


    /**
     * 更新球员列表
     * @return bool
     */
    public function getPlayers()
    {
        $time = strtotime(date("Y-m-d"),time());
        $url = sprintf($this->url . $this->uriPlayer, $this->user, $this->secret, $time);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if (!$resp['query']['total']) {
            return $this->writeJson(Status::CODE_OK, '更新完成');

        }
        foreach ($resp['results'] as $item) {
            $inert = [
                'player_id' => $item['id'],
                'team_id' => $item['team_id'],
                'birthday' => $item['birthday'],
                'age' => $item['age'],
                'weight' => $item['weight'],
                'height' => $item['height'],
                'nationality' => $item['nationality'],
                'market_value' => $item['market_value'],
                'market_value_currency' => $item['market_value_currency'],
                'contract_until' => $item['contract_until'],
                'position' => $item['position'],
                'name_zh' => $item['name_zh'],
                'name_en' => $item['name_en'],
                'logo' => $item['logo'],
                'country_id' => $item['country_id'],
                'preferred_foot' => $item['preferred_foot'],
                'updated_at' => $item['updated_at'],
            ];
            if (AdminPlayer::getInstance()->where('player_id', $item['id'])->get()) {
//                continue;
                AdminPlayer::getInstance()->update($inert, ['player_id' => $item['id']]);
            } else {
                AdminPlayer::getInstance()->insert($inert);

            }
        }
        Log::getInstance()->info('球员更新完成');

    }

    /**
     * 每天凌晨十二点半一次
     * @return bool
     */
    public function clashHistory()
    {
        $date = strtotime(date('Y-m-d', time()));
        $url = sprintf($this->url . $this->uriCompensation, $this->user, $this->secret, $date, $this->start_id + 1);
        $res = json_decode(Tool::getInstance()->postApi($url), true);
        if ($res['code'] == 0) {
            if ($res['query']['total'] == 0) {
                return $this->writeJson(Status::CODE_OK, '更新完成');

            } else {
                foreach ($res['results'] as $item) {
                    $insert = [
                        'match_id' => $item['id'],
                        'history' => json_encode($item['history']),
                        'recent' => json_encode($item['recent']),
                        'similar' => json_encode($item['similar']),
                        'updated_at' => $item['updated_at'],
                    ];
                    if (AdminClashHistory::getInstance()->where('match_id', $item['id'])->get()) {
                        AdminClashHistory::getInstance()->update($insert, ['match_id' => $item['id']]);
                    } else {
                        AdminClashHistory::getInstance()->insert($insert);
                    }


                }
                $this->start_id = $res['query']['max_id'] + 1;
                self::clashHistory();
            }


        } else {
            return $this->writeJson(Status::CODE_OK, '更新异常');

        }

    }


    /**
     * 每分钟一次
     * 通知用户关注比赛即将开始 提前十五分钟通知
     */
    public function noticeUserMatch()
    {

//        $matches = AdminMatch::getInstance()->where('match_id', 3449756)->all();
        $matches = AdminMatch::getInstance()->where('match_time', time() + 60 * 15, '>')->where('match_time', time() + 60 * 16, '<=')->where('status_id', 1)->all();

        if ($matches) {
            foreach ($matches as $match) {
                if (AdminNoticeMatch::getInstance()->where('match_id', $match->id)->where('is_notice', 1)->get()) {
                    continue;
                }
                $key = sprintf(UserRedis::USER_INTEREST_MATCH, $match->match_id);
                if (!$prepareNoticeUserIds = UserRedis::getInstance()->smembers($key)) {
                    continue;
                } else {
                    $users = AdminUser::getInstance()->where('id', $prepareNoticeUserIds, 'in')->field(['cid', 'id'])->all();

                    foreach ($users as $k => $user) {
                        $userSetting = AdminUserSetting::getInstance()->where('user_id', $user['id'])->get();
                        $startSetting = json_decode($userSetting->push, true)['start'];
                        if (!$userSetting || !$startSetting) {
                            unset($users[$k]);
                        }
                    }
                    $uids = array_column($users, 'id');
                    $cids = array_column($users, 'cid');

                    if (!$uids) {
                        return;
                    }


                    $info = [
                        'match_id' => $match->match_id,
                        'home_name_zh' => $match->homeTeamName()->name_zh,
                        'away_name_zh' => $match->awayTeamName()->name_zh,
                        'competition_name' => $match->competitionName()->short_name_zh,
                    ];
                    $info['type'] = 1;  //开赛通知
                    $info['title'] = '开赛通知';
                    $info['content'] = sprintf('您关注的【%s联赛】%s-%s将于15分钟后开始比赛，不要忘了哦', $info['competition_name'], $info['home_name_zh'], $info['away_name_zh']);
                    $batchPush = new BatchSignalPush();
                    $insertData = [
                        'uids' => json_encode($uids),
                        'match_id' => $match->match_id,
                        'type' => 1,
                        'title' => $info['title'],
                        'content' => $info['content']
                    ];
                    if (!$res = AdminNoticeMatch::getInstance()->where('match_id', $match->match_id)->where('type', 1)->get()) {


                        $rs = AdminNoticeMatch::getInstance()->insert($insertData);
                        $info['rs'] = $rs;  //开赛通知

                        $batchPush->pushMessageToSingleBatch($cids, $info);


                    } else {
                        if ($res->is_notice == 1) {
                            return;
                        }
                        $info['rs'] = $res->id;
                        $batchPush = new BatchSignalPush();

                        $batchPush->pushMessageToSingleBatch($cids, $info);
                    }
                }


            }
        } else {

        }
    }

    /**
     * 取消或者删除的比赛
     * @return bool
     */
    public function deleteMatch()
    {
        $url = sprintf($this->url . $this->uriDeleteMatch, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if ($resp['code'] == 0) {
            $dMatches = $resp['results']['match'];
            if ($dMatches) {

                foreach ($dMatches as $dMatch) {
                    if ($match = AdminMatch::getInstance()->where('match_id', $dMatch)->get()) {

                        $match->is_delete = 1;
                        $match->update();
                    }
                }
            }
        }

        Log::getInstance()->info(date('Y-m-d H:i:s') . ' 删除或取消比赛完成');


    }

    public function test()
    {
        $history = AdminPlayerChangeClub::getInstance()->where('player_id', 29651)->where('from_team_id', 0, '!=')->where('to_team_id', 0, '!=')->all();

//        $join_team_ids = AdminTeam::getInstance()->where('competition_id', 82)->field(['team_id'])->all();

//$ids = array_column($join_team_ids, 'team_id');
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $history);

        $match_id = 3449756;
//        if (Cache::get('is_back_up_' . $match_id)) {
//            return;
//        }

        $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();


        return;

        $url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
        $url = sprintf($url, $this->user, $this->secret);

        $res = Tool::getInstance()->postApi($url);
        $decode = json_decode($res, true);

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decode);


    }


    /**
     * 实时比赛 1次/2s
     */
    public function matchTlive()
    {
//        return;
        $res = Tool::getInstance()->postApi(sprintf($this->live_url, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96'));
        if ($res) {
            $resDecode = json_decode($res, true);
            if (!$resDecode) {
                return;
            }
            foreach ($resDecode as $item) {
                if (Cache::get('is_back_up_' . $item['id'])) {
                    continue;
                }
                $ins = [];
                $isover = false;
                if (isset($item['incidents'])) {

                    foreach ($item['incidents'] as $incident) {

                        if ($incident['type'] == 1) {//进球
                            $ins[] = $incident;
                        }

                    }

                }
                if (isset($item['score'])) {
                    if ($item['score'][1] == 8) {
                        $isover = true;

                    }
                }
                if ($isover) {
                    TaskManager::getInstance()->async(new GameOverTask(['match_id' => $item['id'], 'item' => $item]));
                    continue;
                }
                $goal_key = sprintf(MatchRedis::MATCH_GOAL_COUNT_KEY, $item['id']);

                if (isset($item['score']) && $ins) {
                    // [2783605,8,[1(主队常规), 0（主队半场）, 0, 0, -1（主队角球）, 0（主队加时）, 0（主队点球）],[1, 0, 0, 0, -1, 0, 0],0,""]
                    if (!$item['score'][2][5] && !$item['score'][3][5]) {
                        //当主客加时比分为0时，最终比分=常规赛比分+点球大战比分
                        $homeScore = $item['score'][2][0] + $item['score'][2][6];
                        $awayScore = $item['score'][3][0] + $item['score'][3][6];
                    } else {
                        //当主客加时比分不为0，最终比分=加时比分+点球大战比分
                        $homeScore = $item['score'][2][5] + $item['score'][2][6];
                        $awayScore = $item['score'][3][5] + $item['score'][3][6];
                    }
                    $value = ['homeScore' => $homeScore, 'awayScore' => $awayScore, 'position' => end($ins)['position'], 'time' => end($ins)['time']];
                    if (!MatchRedis::getInstance()->get($goal_key)) {
                        MatchRedis::getInstance()->setEx($goal_key, 60 * 240, json_encode($value));
                    } else {
                        MatchRedis::getInstance()->set($goal_key, json_encode($value));

                    }

                }
                $goal_info_key = sprintf(MatchRedis::MATCH_GOAL_INFO_KEY, $item['id']);
                //新进球总数
                $newGoalInfo = count($ins);
                //旧进球总数
                $oldGoalInfo = MatchRedis::getInstance()->get($goal_info_key);
                if (!$oldGoalInfo && $newGoalInfo) {
                    MatchRedis::getInstance()->setEx($goal_info_key, 60 * 240, $newGoalInfo);

                } else {
                    if ($oldGoalInfo != $newGoalInfo) {
                        //修改进球总数
                        MatchRedis::getInstance()->set($goal_info_key, $newGoalInfo);

                        //进球推送
                        $goalScore = MatchRedis::getInstance()->get($goal_key);
                        TaskManager::getInstance()->async(new GoalTask(['match_id' => $item['id'], 'incident' => json_decode($goalScore, true)]));

                    }
                }





                if (isset($item['stats'])) {
                    $matchStats = [];
                    foreach ($item['stats'] as $ki => $vi) {
                        //2 角球  4：红牌 3：黄牌 21：射正 22：射偏  23:进攻  24危险进攻 25：控球率
                        if ($vi['type'] == 2 || $vi['type'] == 4 || $vi['type'] == 3 || $vi['type'] == 21 || $vi['type'] == 22 || $vi['type'] == 23 || $vi['type'] == 24 || $vi['type'] == 25) {
                            $matchStats[] = $vi;
                        }

                    }

                } else {
                    $matchStats = [];
                }
                if (isset($item['tlive'])) {
                    foreach ($item['tlive'] as $k => $v) {
                        unset($item['tlive'][$k]['time']);
                        unset($item['tlive'][$k]['main']);

                    }
                }
                $tlive_key = sprintf(MatchRedis::MATCH_TLIVE_KEY, $item['id']);
                $stats_key = sprintf(MatchRedis::MATCH_STATS_KEY, $item['id']);
                $score_key = sprintf(MatchRedis::MATCH_SCORE_KEY, $item['id']);


                if ((!$oldStats = MatchRedis::getInstance()->get($stats_key)) && $matchStats) {
                    MatchRedis::getInstance()->setEx($stats_key, 60 * 240, json_encode($matchStats, JSON_UNESCAPED_SLASHES));
                } else {
                    MatchRedis::getInstance()->set($stats_key, json_encode($matchStats, JSON_UNESCAPED_SLASHES));
                }

                if ((!$oldScore = MatchRedis::getInstance()->get($score_key)) && $item['score']) {
                    MatchRedis::getInstance()->setEx($score_key, 60 * 240, json_encode($item['score'], JSON_UNESCAPED_SLASHES));


                } else {
                    MatchRedis::getInstance()->set($score_key, json_encode($item['score'], JSON_UNESCAPED_SLASHES));

                }

                if ((!$oldTlive = MatchRedis::getInstance()->get($tlive_key)) && isset($item['tlive'])) {
                    MatchRedis::getInstance()->setEx($tlive_key, 60 * 240, json_encode($item['tlive'], JSON_UNESCAPED_SLASHES));

                } else {
                    if (!isset($item['tlive'])) {
                        continue;
                    }
                    $diff = array_slice($item['tlive'], count(json_decode($oldTlive, true)));


                    if ($diff) {
                        MatchRedis::getInstance()->set($tlive_key, json_encode($item['tlive']));

                    }
                    $this->pushContent($item['id'], $diff, $matchStats, $item['score']);


                }

            }


        }

    }

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

    /**
     * 赛季
     */
    public function updateSeason()
    {
        $i = 0;
        do{


        $max = AdminSeason::getInstance()->max('season_id');

        $url = sprintf($this->season_url, $this->user, $this->secret, $max+1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
            $decode = $resp['results'];
            if ($decode) {

                foreach ($decode as $item) {
                    $data = [
                        'season_id' => $item['id'],
                        'competition_id' => $item['competition_id'],
                        'year' => $item['year'],
                        'updated_at' => $item['updated_at'],
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                        'competition_rule_id' => $item['competition_rule_id'],
                        'has_player_stats' => $item['has_player_stats'],
                        'has_team_stats' => $item['has_team_stats'],
                        'has_table' => $item['has_table'],
                        'is_current' => $item['is_current'],
                    ];
                    if (!$season = AdminSeason::getInstance()->where('season_id', $item['id'])->get()) {

                        AdminSeason::getInstance()->insert($data);
                    } else {
                        AdminSeason::getInstance()->update($data, ['season_id', $item['id']]);
                    }
                }
            }
        }
        } while($i<=10);
        Log::getInstance()->info(date('Y-m-d H:i:s') . ' 赛季完成');
    }

    /**
     * 获取球员能力技术列表
     */
    public function updatePlayerStat()
    {
        ini_set('max_execution_time', 600);
        $i = 0;

        do{


            $max = AdminPlayerStat::getInstance()->max('player_id');

            $url = sprintf($this->player_stat, $this->user, $this->secret, $max+1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];

                if ($decode) {

                    foreach ($decode as $item) {
                        $data = [
                            'player_id' => $item['id'],
                            'team_id' => $item['team_id'],
                            'birthday' => $item['birthday'],
                            'age' => $item['age'],
                            'weight' => $item['weight'],
                            'height' => $item['height'],
                            'nationality' => $item['nationality'],
                            'market_value' => $item['market_value'],
                            'market_value_currency' => $item['market_value_currency'],
                            'contract_until' => $item['contract_until'],
                            'position' => $item['position'],
                            'name_zh' => $item['name_zh'],
                            'short_name_zh' => $item['short_name_zh'],
                            'name_en' => $item['name_en'],
                            'short_name_en' => $item['short_name_en'],
                            'logo' => $item['logo'],
                            'country_id' => $item['country_id'],
                            'preferred_foot' => $item['preferred_foot'],
                            'updated_at' => $item['updated_at'],
                            'ability' => !isset($item['ability']) ? '' : json_encode($item['ability']),
                            'characteristics' => !isset($item['characteristics']) ? '' : json_encode($item['characteristics']),
                            'positions' => !isset($item['positions']) ? '' : json_encode($item['positions']),
                        ];
                        if (!$player = AdminPlayerStat::getInstance()->where('player_id', $item['id'])->get()) {
//                            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT], [$player, $item]);

                            AdminPlayerStat::getInstance()->insert($data);
                        } else {
                            AdminPlayerStat::getInstance()->update($data, ['player_id' => $item['id']]);
                        }
                    }
                }
            }
        } while($i<=100);
    }


    /**
     * 球员转会历史
     * @return bool
     */
    public function playerChangeClubHistory()
    {
        ini_set('max_execution_time', 600);
        $i = 0;

        do{


            $max = AdminPlayerChangeClub::getInstance()->max('id');

            $url = sprintf($this->player_change_club_history, $this->user, $this->secret, $max+1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];

                if ($decode) {

                    foreach ($decode as $item) {
                        $data = [
                            'id' => $item['id'],
                            'player_id' => $item['player_id'],
                            'from_team_id' => $item['from_team_id'],
                            'from_team_name' => $item['from_team_name'],
                            'to_team_id' => $item['to_team_id'],
                            'to_team_name' => $item['to_team_name'],
                            'transfer_type' => $item['transfer_type'],
                            'transfer_time' => $item['transfer_time'],
                            'transfer_fee' => $item['transfer_fee'],
                            'transfer_desc' => $item['transfer_desc'],
                            'updated_at' => $item['updated_at'],
                        ];
                        if (!AdminPlayerChangeClub::getInstance()->where('id', $item['id'])->get()) {

                            AdminPlayerChangeClub::getInstance()->insert($data);
                        } else {
                            unset($data['id']);
                            AdminPlayerChangeClub::getInstance()->update($data, ['id' => $item['id']]);
                        }
                    }
                }
            }
        } while($i<=100);
//        Log::getInstance()->info(date('Y-m-d H:i:s') . ' 赛季完成');
    }

    /**
     * 球队荣誉
     * @return bool
     */
    public function teamHonor()
    {
        ini_set('max_execution_time', 600);
        $i = 0;
        do{

            $url = sprintf($this->team_honor, $this->user, $this->secret, $this->start_id+1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];
                foreach ($decode as $item) {
                    $data = [
                        'team_id' => $item['id'],
                        'honors' => json_encode($item['honors']),
                        'team' => json_encode($item['team']),
                        'update_at' => $item['updated_at']
                    ];
                    if (!AdminTeamHonor::getInstance()->where('team_id', $item['id'])->get()) {
                        AdminTeamHonor::getInstance()->insert($data);
                    } else {
                        $team_id = $data['team_id'];
                        unset($data['team_id']);
                        AdminTeamHonor::getInstance()->update($data, ['team_id', $team_id]);
                    }
                }
                $this->start_id = $resp['query']['max_id'];
            }
        }while($i <= 100);

    }

    /**
     * 荣誉详情
     * @return bool
     */
    public function honorList()
    {

        ini_set('max_execution_time', 600);
        $i = 0;
        do{

            $url = sprintf($this->honor_list, $this->user, $this->secret, $this->start_id+1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];
                foreach ($decode as $item) {
                    $data = [
                        'id' => $item['id'],
                        'title_zh' => $item['title_zh'],
                        'logo' => $item['logo'],
                        'updated_at' => $item['updated_at']
                    ];
                    if (!AdminHonorList::getInstance()->where('id', $item['id'])->get()) {
                        AdminHonorList::getInstance()->insert($data);
                    } else {
                        $id = $data['id'];
                        unset($data['id']);
                        AdminHonorList::getInstance()->update($data, ['id', $id]);
                    }
                }
                $this->start_id = $resp['query']['max_id'];
            }
        }while($i <= 100);

    }

}
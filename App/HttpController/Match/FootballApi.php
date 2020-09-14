<?php

namespace App\HttpController\Match;
use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\pool\Login;
use App\lib\pool\MatchRedis;
use App\lib\pool\User as UserRedis;
use App\lib\Tool;
use App\Model\AdminClashHistory;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminPlayer;
use App\Model\AdminSystemAnnoucement;
use App\Model\AdminUserInterestCompetition;
use App\Utility\Message\Status;
use App\Model\AdminInterestMatches;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Validate\Validate;

class FootballApi extends FrontUserController
{
    protected $lineUpDetail = 'https://open.sportnanoapi.com/api/v4/football/match/lineup/detail?user=%s&secret=%s&id=%s';
    protected $urlIntvalRank = 'https://open.sportnanoapi.com/api/v4/football/season/table/detail?user=%s&secret=%s&id=%s';

    protected $playerLogo = 'http://cdn.sportnanoapi.com/football/player/';
    public $needCheckToken = false;

    const STATUS_PLAYING = [2, 3, 4, 5, 7];
    const STATUS_SCHEDULE = [0, 1, 9, 11, 12, 13];
    const STATUS_RESULT= [8, 10];

    const hotCompetition = [
        'hot' => [['competition_id' => 45, 'short_name_zh' => '欧洲杯'], ['competition_id'=>47, 'short_name_zh' =>'欧联杯'], ['competition_id'=>542, 'short_name_zh' =>'中超']],
        'A' => [
            ['competition_id' => 3099, 'short_name_zh' => '澳威北后备'],
            ['competition_id' => 1961, 'short_name_zh' => '澳黄后备'],
            ['competition_id' => 3109, 'short_name_zh' => '澳昆后备'],
            ['competition_id' => 598, 'short_name_zh' => '澳昆超'],
            ['competition_id' => 1858, 'short_name_zh' => '澳昆U20'],
            ['competition_id' => 3083, 'short_name_zh' => '澳达超'],
            ['competition_id' => 1878, 'short_name_zh' => '澳威北超'],
//            ['competition_id' => 2996, 'short_name_zh' => '澳阳超'],
//            ['competition_id' => 1869, 'short_name_zh' => '澳西甲'],
//            ['competition_id' => 730, 'short_name_zh' => '埃及超'],
//            ['competition_id' => 3014, 'short_name_zh' => '爱沙U19'],
//            ['competition_id' => 228, 'short_name_zh' => '爱超'],
//            ['competition_id' => 221, 'short_name_zh' => '奥丙西'],
//            ['competition_id' => 1726, 'short_name_zh' => '奥丁'],
//            ['competition_id' => 229, 'short_name_zh' => '爱甲'],
//            ['competition_id' => 1759, 'short_name_zh' => '爱尔高联'],
//            ['competition_id' => 829, 'short_name_zh' => '澳布甲'],
        ],
        'B' => [
            ['competition_id' => 447, 'short_name_zh' => '巴波联'],
            ['competition_id' => 178, 'short_name_zh' => '比U21'],
            ['competition_id' => 282, 'short_name_zh' => '冰岛乙'],
            ['competition_id' => 284, 'short_name_zh' => '冰岛杯'],
            ['competition_id' => 469, 'short_name_zh' => '巴拉甲'],
            ['competition_id' => 436, 'short_name_zh' => '巴西乙'],
            ['competition_id' => 1821, 'short_name_zh' => '巴丙'],
        ],
        'D' => [
            ['competition_id' => 1675, 'short_name_zh' => '德堡州联'],
            ['competition_id' => 132, 'short_name_zh' => '德地区北'],
        ],
        'E' => [
            ['competition_id' => 238, 'short_name_zh' => '俄超'],
            ['competition_id' => 478, 'short_name_zh' => '厄瓜甲秋'],
            ['competition_id' => 240, 'short_name_zh' => '俄乙'],

        ],
        'F' => [
            ['competition_id' => 195, 'short_name_zh' => '芬超'],
            ['competition_id' => 1940, 'short_name_zh' => '芬丙'],
            ['competition_id' => 1816, 'short_name_zh' => '法戊'],
        ],
        'G' => [
            ['competition_id' => 486, 'short_name_zh' => '哥斯甲'],
            ['competition_id' => 385, 'short_name_zh' => '格鲁甲'],
            ['competition_id' => 386, 'short_name_zh' => '格鲁乙'],
        ],
        'H' => [
            ['competition_id' => 356, 'short_name_zh' => '哈萨超'],
//            ['competition_id' => 356, 'short_name_zh' => '韩女足'],
        ],
        'J' => [
            ['competition_id' => 2984, 'short_name_zh' => '加拿职'],
        ],
        'K' => [
            ['competition_id' => 1785, 'short_name_zh' => '卡塔乙'],
        ],
        'L' => [
            ['competition_id' => 2979, 'short_name_zh' => '老挝超'],
        ],
        'M' => [
            ['competition_id' => 2115, 'short_name_zh' => '墨女超'],
            ['competition_id' => 458, 'short_name_zh' => '美乙'],
            ['competition_id' => 465, 'short_name_zh' => '墨西超'],
            ['competition_id' => 2846, 'short_name_zh' => '蒙古超'],
//            ['competition_id' => 471, 'short_name_zh' => '秘鲁甲'],
//            ['competition_id' => 721, 'short_name_zh' => '摩洛超'],
//            ['competition_id' => 466, 'short_name_zh' => '墨西乙'],
//            ['competition_id' => 1929, 'short_name_zh' => '美超'],
//            ['competition_id' => 457, 'short_name_zh' => '美职业'],
        ],
        'N' => [
            ['competition_id' => 717, 'short_name_zh' => '南非甲'],
            ['competition_id' => 716, 'short_name_zh' => '南非超'],
            ['competition_id' => 202, 'short_name_zh' => '挪甲'],
            ['competition_id' => 203, 'short_name_zh' => '挪乙'],
        ],
        'O' => [
            ['competition_id' => 53, 'short_name_zh' => '欧青U19'],
        ],
        'Q' => [
            ['competition_id' => 24, 'short_name_zh' => '球会友谊'],
        ],
        'R' => [
            ['competition_id' => 185, 'short_name_zh' => '瑞典超甲'],
            ['competition_id' => 187, 'short_name_zh' => '瑞典乙'],
            ['competition_id' => 192, 'short_name_zh' => '瑞典杯'],
            ['competition_id' => 226, 'short_name_zh' => '瑞士甲'],
            ['competition_id' => 186, 'short_name_zh' => '瑞典甲'],
        ],
        'S' => [
            ['competition_id' => 3121, 'short_name_zh' => '斯里总统杯'],
            ['competition_id' => 277, 'short_name_zh' => '斯伐杯'],
        ],
        'W' => [
            ['competition_id' => 674, 'short_name_zh' => '乌兹超'],
            ['competition_id' => 1736, 'short_name_zh' => '乌拉乙'],
        ],
        'X' => [
            ['competition_id' => 120, 'short_name_zh' => '西甲'],
            ['competition_id' => 126, 'short_name_zh' => '西超杯'],
        ],
        'Y' => [
            ['competition_id' => 1788, 'short_name_zh' => '印尼甲'],
            ['competition_id' => 625, 'short_name_zh' => '伊朗甲'],
        ],
        'Z' => [
            ['competition_id' => 1928, 'short_name_zh' => '中台联'],
            ['competition_id' => 543, 'short_name_zh' => '中甲'],
        ],
    ];

    public function getAll()
    {
        $time = strtotime(date('Y-m-d',time()));

        $match = AdminMatch::getInstance()->where('match_time', $time, '>')->all();
        $sql = AdminMatch::getInstance()->lastQuery()->getLastQuery();

        return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN], $match);

    }

    public function getCompetition()
    {
        $arr = self::hotCompetition;
        $uid = isset($this->auth['id']) ? $this->auth['id'] : 0;
        $res = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get();
        if ($res) {
            $competitions = json_decode($res['competition_ids'], true);
            if ($competitions) {
                foreach ($arr as $k => $items) {
                    foreach ($items as $sk => $item) {
                        if (in_array($item['competition_id'], $competitions)) {
                            $arr[$k][$sk]['is_notice'] = true;
                        } else {
                            $arr[$k][$sk]['is_notice'] = false;

                        }
                    }
                }
            } else {
                foreach ($arr as $k=>$items) {
                    foreach ($items as $item) {
                        $item['is_notice'] = false;
                    }
                }
            }
        } else {
            foreach ($arr as $k=>$items) {
                foreach ($items as $item) {
                    $item['is_notice'] = false;

                }
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $arr);

    }

    public function frontMatchList()
    {
//        $todayMatch =
        $todayTime = strtotime(date('Y-m-d', time()));
        $tomorrowTime = strtotime(date('Y-m-d',strtotime('+1 day')));
        $afterTomorrowTime = strtotime(date('Y-m-d',strtotime('+2 day')));
        $hotCompetition = FrontService::getHotCompetitionIds();
        $playingMatch = AdminMatch::getInstance()->where('status_id', self::STATUS_PLAYING, 'in')->where('match_time', $todayTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();

        $todayMatch = AdminMatch::getInstance()->where('match_time', $todayTime, '>')->where('match_time', $tomorrowTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $tomorrowMatch = AdminMatch::getInstance()->where('match_time', $tomorrowTime, '>')->where('match_time', $afterTomorrowTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $sql = AdminMatch::getInstance()->lastQuery()->getLastQuery();
        $playing = FrontService::handMatch($playingMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $today = FrontService::handMatch($todayMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $tomorrow = FrontService::handMatch($tomorrowMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $resp = [
            'playing' => $playing,
            'today' => $today,
            'tomorrow' => $tomorrow
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resp);

    }

    /**
     * 正在进行中的比赛列表
     * @return bool
     */
    public function playingMatches()
    {

        $hotCompetition = FrontService::getHotCompetitionIds();
        $playMatch = AdminMatch::getInstance()->where('status_id', self::STATUS_PLAYING, 'in')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();

        $formatMatch = FrontService::handMatch($playMatch, $this->auth['id']);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatMatch);


    }

    /**
     * time 2020-08-19
     * 赛程列表
     */
    public function matchSchedule()
    {
        if (!isset($this->params['time'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->params['time'] == date('Y-m-d')) {
            $is_today = true;
        } else {
            $is_today = false;
        }
        $start = strtotime($this->params['time']);

        $end = $start + 60 * 60 * 24;

        $matches = AdminMatch::getInstance()->where('match_time', $is_today ? time() : $start, '>=')->where('match_time', $end, '<')->where('status_id', self::STATUS_SCHEDULE, 'in')->where('is_delete', 0)->all();
        $sql = AdminMatch::getInstance()->lastQuery()->getLastQuery();

        $formatMatch = FrontService::handMatch($matches, $this->auth['id']);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatMatch);

    }

    /**
     * time 2020-08-19
     * 赛果列表
     * @return bool
     */
    public function matchResult()
    {
        if (!isset($this->params['time'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $start = strtotime($this->params['time']);
        $end = $start + 60 * 60 * 24;

        $matches = AdminMatch::getInstance()->where('match_time', $start, '>=')->where('match_time', $end, '<')->where('status_id', self::STATUS_RESULT, 'in')->where('is_delete', 0)->all();
        $formatMatch = FrontService::handMatch($matches, $this->auth['id']);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatMatch);
    }

    /**
     * 用户关注比赛
     * @return bool
     */
    function userInterestMatch()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');

        }

        $validate = new Validate();
        $validate->addColumn('match_id')->required();
        $validate->addColumn('type')->required()->inArray(['add', 'del']);
        if ($validate->validate($this->params))
        {
            $uid = $this->auth['id'];
            $match_id = $this->params['match_id'];
            $matches = AdminInterestMatches::getInstance()->get(['uid' => $uid]);
            if ($this->params['type'] == 'add')
            {
                $insertData = [
                    'uid' => $this->auth['id'],
                    'match_ids' => json_encode([$match_id])
                ];
                if ($matches) {
                    $matchesArr = json_decode($matches['match_ids'], true);
                    array_push($matchesArr, $match_id);
                    if (!AdminInterestMatches::getInstance()->update(['match_ids' => json_encode($matchesArr)], ['uid' => $uid])) {
                        $code = Status::CODE_MATCH_FOLLOW_ERR;

                    } else {
                        $code = Status::CODE_OK;

                    }
                } else {
                    if (!AdminInterestMatches::getInstance()->insert($insertData)) {
                        $code = Status::CODE_MATCH_FOLLOW_ERR;

                    } else {

                        $code = Status::CODE_OK;

                    }
                }

            } else if($this->params['type'] == 'del') {

                if (!$matches)
                {
                    $code = Status::CODE_WRONG_RES;

                } else {
                    $mids = json_decode($matches['match_ids'], true);
                    foreach ($mids as $k => $mid) {
                        if ($mid == $this->params['match_id']) {
                            unset($mids[$k]);
                        }
                    }
                    if (AdminInterestMatches::getInstance()->update(['match_ids' => json_encode($mids)], ['uid' => $uid])) {
                        $code = Status::CODE_OK;

                    } else {
                        $code = Status::CODE_WRONG_RES;

                    }
                }
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            TaskManager::getInstance()->async(function () use ($uid, $match_id) {
                //用户关注比赛redis
                $key = sprintf(UserRedis::USER_INTEREST_MATCH, $match_id);
                UserRedis::getInstance()->sadd($key, $uid);
            });

        } else {
            $code = Status::CODE_W_PARAM;
        }

        return $this->writeJson($code, Status::$msg[$code]);

    }


    public function userInterestMatchList()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');

        }

        $res = AdminInterestMatches::getInstance()->where('uid', $this->auth['id'])->get();
        if (!$res) {
            $data = [];
        } else {
            $matchIds = json_decode($res->match_ids, true);
            if (!$matchIds) {
                $data = [];
            } else {
                $matches = AdminMatch::getInstance()->where('match_id', $matchIds, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
                $data = FrontService::handMatch($matches, $this->auth['id']);


            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 首发阵容
     * @return bool
     */
    public function lineUpDetail()
    {
        $match_id = $this->params['match_id'] ?: 0;
        if (!$match_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $res = Tool::getInstance()->postApi(sprintf($this->lineUpDetail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $match_id));
        $decode = json_decode($res, true);
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decode);
        $homePlayers = [];
        $awayPlayers = [];
        if ($decode['code'] == 0) {
            $home = $decode['results']['home'];
            $away = $decode['results']['away'];
            if ($home) {
                foreach ($home as $homeItem)
                {
                    if ($homeItem['first']) {
                        if (!isset($home['logo'])) {
                            $homeplayerinfo = AdminPlayer::getInstance()->where('player_id', $homeItem['id'])->get();

                        }

                        $homePlayer['player_id'] = $homeItem['id'];
                        $homePlayer['name'] = $homeItem['name'];
                        $homePlayer['logo'] = isset($home['logo']) ? $this->playerLogo . $homeItem['logo'] : ($homeplayerinfo ? $homeplayerinfo->logo : '');
                        $homePlayer['position'] = $homeItem['position'];
                        $homePlayer['shirt_number'] = $homeItem['shirt_number'];

                        $homePlayers[] = $homePlayer;
                        unset($homePlayer);
                    } else {
                        continue;
                    }

                }
            }

            if ($away) {
                foreach ($away as $awayItem) {
                    if ($awayItem['first']) {
                        if (!$awayItem['logo']) {
                            $awayplayerinfo = AdminPlayer::getInstance()->where('player_id', $awayItem['id'])->get();

                        }

                        $awayPlayer['player_id'] = $awayItem['id'];
                        $awayPlayer['name'] = $awayItem['name'];
                        $awayPlayer['logo'] = $awayItem['logo'] ? $this->playerLogo . $awayItem['logo'] : ($awayplayerinfo ? $awayplayerinfo->logo : '');
                        $awayPlayer['position'] = $awayItem['position'];
                        $awayPlayer['shirt_number'] = $awayItem['shirt_number'];
                        $awayPlayers[] = $awayPlayer;
                        unset($awayPlayer);
                    } else {
                        continue;
                    }

                }
            }

            $matchInfo = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->where('is_delete', 0)->get();
            $homeTeamInfo['firstPlayers'] = $homePlayers;
            $homeTeamInfo['teamName'] = $matchInfo->homeTeamName()['name_zh'];
            $homeTeamInfo['teamLogo'] = $matchInfo->homeTeamName()['logo'];

            $awayTeamInfo['firstPlayers'] = $awayPlayers;
            $awayTeamInfo['teamName'] = $matchInfo->awayTeamName()['name_zh'];
            $awayTeamInfo['teamLogo'] = $matchInfo->awayTeamName()['logo'];
            $data = [
                'home' => $homeTeamInfo,
                'away' => $awayTeamInfo,
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            return $this->writeJson(Status::CODE_MATCH_LINE_UP_ERR, Status::$msg[Status::CODE_MATCH_LINE_UP_ERR]);

        }



    }


    /**
     * 历史
     * @return bool
     */
    public function clashHistory()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $matchId = $this->params['match_id'];
        $sensus = AdminClashHistory::getInstance()->where('match_id', $this->params['match_id'])->get();

        $matchInfo = AdminMatch::getInstance()->where('match_id', $matchId)->where('is_delete', 0)->get();


        //积分排名
        $currentSeasonId = $matchInfo->competitionName()->cur_season_id;
        if (!$currentSeasonId) {
            $intvalRank = [];
        } else {
            $res = Tool::getInstance()->postApi(sprintf($this->urlIntvalRank, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $currentSeasonId));
            $decode = json_decode($res, true);
            if ($decode['code'] == 0) {
                if (!isset($decode['results']['tables'][0]['rows'])) {
                    $intvalRank = [];
                } else {
                    $rows = $decode['results']['tables'][0]['rows'];
                    if ($rows) {
                        $intvalRank = [];
                        foreach ($rows as $row) {
                            if ($row['team_id'] == $matchInfo->home_team_id) {
                                $intvalRank['homeIntvalRank'] = $row;
                            }

                            if ($row['team_id'] == $matchInfo->away_team_id) {
                                $intvalRank['awayIntvalRank'] = $row;

                            }
                        }
                    } else {
                        $intvalRank = [];
                    }
                }

            } else {
                $intvalRank = [];
            }

        }


        $homeTid = $matchInfo->home_team_id;
        $awayTid = $matchInfo->away_team_id;

        //历史交锋
        $matches = AdminMatch::getInstance()->where('status_id', 8)->where('((home_team_id=' . $homeTid . ' and away_team_id=' . $awayTid . ') or (home_team_id='.$awayTid.' and away_team_id='.$homeTid.'))')->where('is_delete', 0)->order('match_time', 'DESC')->all();
        //是否显示不感兴趣的赛事
        $formatHistoryMatches = FrontService::handMatch($matches, 0, true);

        //近期战绩


        $homeRecentMatches = AdminMatch::getInstance()->where('status_id', 8)->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid)->where('is_delete', 0)->order('match_time', 'DESC')->all();
        $awayRecentMatches = AdminMatch::getInstance()->where('status_id', 8)->where('home_team_id='.$awayTid. ' or away_team_id='.$awayTid)->where('is_delete', 0)->order('match_time', 'DESC')->all();

        //近期赛程
        $homeRecentSchedule = AdminMatch::getInstance()->where('status_id', [1,2,3,4,5,7,8], 'in')->where('(home_team_id = ' . $homeTid . ' or away_team_id = ' . $homeTid . ')')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $awayRecentSchedule = AdminMatch::getInstance()->where('status_id', [1,2,3,4,5,7,8], 'in')->where('(home_team_id = ' . $awayTid . ' or away_team_id = ' . $awayTid . ')')->where('is_delete', 0)->order('match_time', 'ASC')->all();


        $returnData = [
            'intvalRank' => $intvalRank, //积分排名
            'historyResult' => json_decode($sensus['history'], true),
            'recentResult' => json_decode($sensus['recent'], true),
            'history' => $formatHistoryMatches, //历史交锋
            'homeRecent' => FrontService::handMatch($homeRecentMatches, 0, true),//主队近期战绩
            'awayRecent' => FrontService::handMatch($awayRecentMatches, 0, true),//客队近期战绩
            'homeRecentSchedule' => FrontService::handMatch($homeRecentSchedule, $this->auth['id'] ?: 0, true),//主队近期赛程
            'awayRecentSchedule' => FrontService::handMatch($awayRecentSchedule, $this->auth['id'] ?: 0, true),//客队近期赛程
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

    /**
     * 直播间公告
     * @return bool
     */
    public function noticeMatch()
    {
        $matchNotice = AdminSystemAnnoucement::getInstance()->where('type', 2)->order('created_at', 'DESC')->limit(1)->get();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $matchNotice ?: []);

    }

    public function test()
    {

        $time = date('Ymd');
//        $time = '20200821';
        $url = sprintf($this->uriM, $this->user, $this->secret, $time);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decodeDatas);


    }


    public function getMatchInfo()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $match = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->get();
        $formatMatch = FrontService::handMatch([$match], $this->auth['id'] ?: 0);
        $return = isset($formatMatch[0]) ? $formatMatch[0] : [];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

}

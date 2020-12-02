<?php

namespace App\HttpController\Match;
use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\Tool;
use App\Model\AdminClashHistory;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminPlayer;
use App\Model\AdminSysSettings;
use App\Model\AdminSystemAnnoucement;
use App\Model\AdminUserInterestCompetition;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use App\Model\AdminInterestMatches;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Validate\Validate;

class FootballApi extends FrontUserController
{
    protected $lineUpDetail = 'https://open.sportnanoapi.com/api/v4/football/match/lineup/detail?user=%s&secret=%s&id=%s';
    protected $urlIntvalRank = 'https://open.sportnanoapi.com/api/v4/football/season/table/detail?user=%s&secret=%s&id=%s';
    protected $matchHistory = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s';



    protected $playerLogo = 'http://cdn.sportnanoapi.com/football/player/';
    public $needCheckToken = false;

    const STATUS_PLAYING = [2, 3, 4, 5, 7];
    const STATUS_SCHEDULE = [0, 1, 9];
    const STATUS_RESULT= [8, 9, 10, 11, 12, 13];

    const STATUS_NO_START = 1;

    const hotCompetition = [
        'hot' => [['competition_id' => 45, 'short_name_zh' => '欧洲杯'],
            ['competition_id'=>47, 'short_name_zh' =>'欧联杯'],
            ['competition_id'=>542, 'short_name_zh' =>'中超']],
        'A' => [
            ['competition_id' => 1961, 'short_name_zh' => '澳黄后备'],
            ['competition_id' => 3109, 'short_name_zh' => '澳昆后备'],
            ['competition_id' => 598, 'short_name_zh' => '澳昆超'],
            ['competition_id' => 1858, 'short_name_zh' => '澳昆U20'],
            ['competition_id' => 1850, 'short_name_zh' => '澳南后备'],
        ],
        'B' => [
            ['competition_id' => 447, 'short_name_zh' => '巴波联'],
            ['competition_id' => 282, 'short_name_zh' => '冰岛乙'],
            ['competition_id' => 284, 'short_name_zh' => '冰岛杯'],
            ['competition_id' => 436, 'short_name_zh' => '巴西乙'],
            ['competition_id' => 1821, 'short_name_zh' => '巴丙'],
        ],
        'D' => [
            ['competition_id' => 1675, 'short_name_zh' => '德堡州联'],
            ['competition_id' => 132, 'short_name_zh' => '德地区北'],
        ],
        'E' => [
            ['competition_id' => 238, 'short_name_zh' => '俄超'],
            ['competition_id' => 241, 'short_name_zh' => '俄青联'],
            ['competition_id' => 240, 'short_name_zh' => '俄乙'],

        ],
        'F' => [
            ['competition_id' => 195, 'short_name_zh' => '芬超'],
            ['competition_id' => 1940, 'short_name_zh' => '芬丙'],
            ['competition_id' => 3053, 'short_name_zh' => '斐济女联'],
            ['competition_id' => 1932, 'short_name_zh' => '斐济杯'],
        ],
        'G' => [
            ['competition_id' => 486, 'short_name_zh' => '哥斯甲'],
            ['competition_id' => 385, 'short_name_zh' => '格鲁甲'],
            ['competition_id' => 386, 'short_name_zh' => '格鲁乙'],
        ],
        'H' => [
            ['competition_id' => 356, 'short_name_zh' => '哈萨超'],
            ['competition_id' => 357, 'short_name_zh' => '哈萨甲'],
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
            ['competition_id' => 457, 'short_name_zh' => '美职业'],
            ['competition_id' => 466, 'short_name_zh' => '墨西乙'],
            ['competition_id' => 2115, 'short_name_zh' => '墨女超'],
        ],
        'N' => [
            ['competition_id' => 716, 'short_name_zh' => '南非超'],
            ['competition_id' => 203, 'short_name_zh' => '挪乙'],
        ],
        'O' => [
            ['competition_id' => 53, 'short_name_zh' => '欧青U19'],
        ],
        'Q' => [
            ['competition_id' => 24, 'short_name_zh' => '球会友谊'],
        ],
        'R' => [
            ['competition_id' => 568, 'short_name_zh' => '日职乙'],
            ['competition_id' => 569, 'short_name_zh' => '日足联'],
            ['competition_id' => 572, 'short_name_zh' => '日职丙'],
            ['competition_id' => 567, 'short_name_zh' => '日职联'],
        ],
        'S' => [
            ['competition_id' => 3121, 'short_name_zh' => '斯里总统杯'],
            ['competition_id' => 277, 'short_name_zh' => '斯伐杯'],
            ['competition_id' => 3127, 'short_name_zh' => '所罗门群岛S联赛'],
        ],
        'T' => [
            ['competition_id' => 1842, 'short_name_zh' => '泰乙'],
            ['competition_id' => 317, 'short_name_zh' => '土乙红'],
            ['competition_id' => 318, 'short_name_zh' => '土丙C'],
        ],
        'W' => [
            ['competition_id' => 674, 'short_name_zh' => '乌兹超'],
            ['competition_id' => 675, 'short_name_zh' => '乌兹甲'],
            ['competition_id' => 1736, 'short_name_zh' => '乌拉乙'],
        ],
        'X' => [
            ['competition_id' => 547, 'short_name_zh' => '香港甲'],
            ['competition_id' => 1732, 'short_name_zh' => '香港乙'],
        ],
        'Y' => [
            ['competition_id' => 2912, 'short_name_zh' => '越青锦U21'],
            ['competition_id' => 370, 'short_name_zh' => '亚美超'],
        ],
        'Z' => [
            ['competition_id' => 543, 'short_name_zh' => '中甲'],
            ['competition_id' => 544, 'short_name_zh' => '中乙'],
        ],
    ];

    public function getAll()
    {
        $time = strtotime(date('Y-m-d',time()));

        $match = AdminMatch::getInstance()->where('match_time', $time, '>')->all();

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

        $todayTime = strtotime(date('Y-m-d', time()));
        $tomorrowTime = strtotime(date('Y-m-d',strtotime('+1 day')));
        $afterTomorrowTime = strtotime(date('Y-m-d',strtotime('+2 day')));
        $hotCompetition = FrontService::getHotCompetitionIds();
        $playingMatch = AdminMatch::getInstance()->where('status_id', self::STATUS_PLAYING, 'in')->where('match_time', time(), '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();

        $todayMatch = AdminMatch::getInstance()->where('match_time', $todayTime, '>')->where('status_id', self::STATUS_PLAYING, 'not in')->where('match_time', $tomorrowTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $tomorrowMatch = AdminMatch::getInstance()->where('match_time', $tomorrowTime, '>')->where('match_time', $afterTomorrowTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $playing = FrontService::handMatch($playingMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $today = FrontService::handMatch($todayMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $tomorrow = FrontService::handMatch($tomorrowMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $resp = [
            'playing' => $playing,
            'today' => $today,
            'tomorrow' => $tomorrow
        ];
        Cache::set('today_match_list', json_encode($resp), 60);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resp);

    }

    /**
     * 正在进行中的比赛列表
     * @return bool
     */
    public function matchListPlaying()
    {

        $hotCompetition = FrontService::getHotCompetitionIds();
        $playMatch = AdminMatch::getInstance()->where('status_id', self::STATUS_PLAYING, 'in')
            ->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();


        $formatMatch = FrontService::handMatch($playMatch, $this->auth['id'], false, true);
        if (isset($this->auth['id'])) {
            if ($userInterestMatch = AdminInterestMatches::getInstance()->where('uid', $this->auth['id'])->get()) {
                $match = json_decode($userInterestMatch->match_ids);
                $count = count($match);
            } else {
                $count = 0;
            }


        } else {
            $count = 0;
        }
        $return = [
            'user_interest_count' => $count, //关注的比赛数
            'list' => $formatMatch
        ];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


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

        $matches = AdminMatch::getInstance()->where('status_id', self::STATUS_SCHEDULE, 'in')->where('is_delete', 0)->where('match_time', $is_today ? time() : $start, '>=')->where('match_time', $end, '<')->order('match_time', 'ASC')->all();

        $formatMatch = FrontService::handMatch($matches, $this->auth['id'], false, true);
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

        $matches = AdminMatch::getInstance()->where('status_id', self::STATUS_RESULT, 'in')->where('is_delete', 0)->where('match_time', $start, '>=')->where('match_time', $end, '<')->order('match_time', 'DESC')->all();

        $formatMatch = FrontService::handMatch($matches, $this->auth['id'], false, true);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatMatch);
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
        $homeFirstPlayers = [];
        $homeAlternatePlayers = [];
        $awayFirstPlayers = [];
        $awayAlternatePlayers = [];
        if ($decode['code'] == 0) {
            $homeFormation = $decode['results']['home_formation'];
            $awayFormation = $decode['results']['away_formation'];
            $home = $decode['results']['home'];
            $away = $decode['results']['away'];
            if ($home) {
                foreach ($home as $homeItem)
                {
                    if (!isset($home['logo'])) {
                        $homeplayerinfo = AdminPlayer::getInstance()->where('player_id', $homeItem['id'])->get();

                    }

                    $homePlayer['player_id'] = $homeItem['id'];
                    $homePlayer['name'] = $homeItem['name'];
                    $homePlayer['logo'] = isset($home['logo']) ? $this->playerLogo . $homeItem['logo'] : ($homeplayerinfo ? $homeplayerinfo->logo : '');
                    $homePlayer['position'] = $homeItem['position'];
                    $homePlayer['shirt_number'] = $homeItem['shirt_number'];
                    if ($homeItem['first']) {
                        $homeFirstPlayers[] = $homePlayer;
                    } else {
                        $homeAlternatePlayers[] = $homePlayer;

                    }
                    unset($homePlayer);


                }
            }

            if ($away) {
                foreach ($away as $awayItem) {
                    if (!$awayItem['logo']) {
                        $awayplayerinfo = AdminPlayer::getInstance()->where('player_id', $awayItem['id'])->get();
                    }
                    $awayPlayer['player_id'] = $awayItem['id'];
                    $awayPlayer['name'] = $awayItem['name'];
                    $awayPlayer['logo'] = $awayItem['logo'] ? $this->playerLogo . $awayItem['logo'] : ($awayplayerinfo ? $awayplayerinfo->logo : '');
                    $awayPlayer['position'] = $awayItem['position'];
                    $awayPlayer['shirt_number'] = $awayItem['shirt_number'];
                    if ($awayItem['first']) {

                        $awayFirstPlayers[] = $awayPlayer;
                    } else {
                        $awayAlternatePlayers[] = $awayPlayer;
                    }
                    unset($awayPlayer);


                }
            }

            $matchInfo = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->where('is_delete', 0)->get();
            $homeTeamInfo['firstPlayers'] = $homeFirstPlayers;
            $homeTeamInfo['alternatePlayers'] = $homeAlternatePlayers;
            $homeTeamInfo['teamName'] = $matchInfo->homeTeamName()['name_zh'];
            $homeTeamInfo['teamLogo'] = $matchInfo->homeTeamName()['logo'];
            $homeTeamInfo['homeManagerName'] = $matchInfo->homeTeamName()->getManager()->name_zh;
            $homeTeamInfo['homeFormation'] = $homeFormation;

            $awayTeamInfo['firstPlayers'] = $awayFirstPlayers;
            $awayTeamInfo['alternatePlayers'] = $awayAlternatePlayers;
            $awayTeamInfo['teamName'] = $matchInfo->awayTeamName()['name_zh'];
            $awayTeamInfo['teamLogo'] = $matchInfo->awayTeamName()['logo'];
            $awayTeamInfo['awayManagerName'] = $matchInfo->awayTeamName()->getManager()->name_zh;
            $awayTeamInfo['awayFormation'] = $awayFormation;

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
    public function getClashHistory()
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
            'historyResult' => !empty($sensus['history']) ? json_decode($sensus['history'], true) : [],
            'recentResult' => !empty($sensus['recent']) ? json_decode($sensus['recent'], true) : [],
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
    public function noticeInMatch()
    {
        $setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_MATCH_NOTICEMENT)->get();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], isset($setting->sys_value) ? $setting->sys_value : []);

    }

    public function test()
    {
//        go(function () {
//            $redis_client = Manager::getInstance()->get('redisClient')->getObj();
//            $redis_client->set('test_redis', 2);
//
//        });
//        $a = UserRedis::getInstance()->get('test_redis');
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 2);


    }


    public function getMatchInfo()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else if (!$match = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->get()) {
            return $this->writeJson(Status::CODE_WRONG_MATCH, Status::$msg[Status::CODE_WRONG_MATCH]);

        }

        $match = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->get();
        $formatMatch = FrontService::handMatch([$match], $this->auth['id'] ?: 0, true, true);
        $return = isset($formatMatch[0]) ? $formatMatch[0] : [];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }


    public function getMatchHistory()
    {


        $url = sprintf($this->matchHistory, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', 3478550);

        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true)['results'];


        if (!$matchTlive = AdminMatchTlive::getInstance()->where('match_id', $resp['id'])->get()) {
            $data = [
                'match_id' => $resp['id'],
                'score' => json_encode($resp['score']),
                'stats' => json_encode($resp['stats']),
                'incidents' => json_encode($resp['incidents']),
                'tlive' => json_encode($resp['tlive']),
            ];
            AdminMatchTlive::getInstance()->insert($data);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resp);

    }

}

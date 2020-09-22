<?php

namespace App\HttpController\Match;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\Tool;
use App\Model\AdminCompetition;
use App\Model\AdminCountryList;
use App\Model\AdminMatch;
use App\Model\AdminCountryCategory;
use App\Model\AdminPlayer;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\SeasonTeamPlayer;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;

class DataApi extends FrontUserController{

    protected $user = 'mark9527';

    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    public $team_logo = 'https://cdn.sportnanoapi.com/football/team/';
    public $player_logo = 'https://cdn.sportnanoapi.com/football/player/';

    protected $season_url = 'https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s';  //赛季查询
    protected $integral_url = 'https://open.sportnanoapi.com/api/v4/football/season/all/table/detail?user=%s&secret=%s&id=%s'; //赛季积分榜数据
    protected $best_player = 'https://open.sportnanoapi.com/api/v4/football/season/stats/detail?user=%s&secret=%s&id=%s'; //获取赛季球队球员统计详情
    protected $FIFA_male_rank = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/men?user=%s&secret=%s'; //FIFA男子排名
    protected $FIFA_female_rank = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/women?user=%s&secret=%s'; //FIFA女子子排名


    public function competitionInfo()
    {
        $cid = $this->params['competition_id'];
        $type = $this->params['type']; //0基本信息  1积分榜 2比赛 3最佳球员 4最佳球队
        if ($cid) {
            //概况  最近比赛 统计（参赛球队 球员总数）
            $lastMatch = AdminMatch::getInstance()->where('match_time', time(), '<')->order('match_time', 'desc')->limit(1)->get();
            $formatMatch = FrontService::handMatch([$lastMatch], 1);
            $formatLastMatch = $formatMatch ? $formatMatch[0] : [];
            if ($competition = AdminCompetition::getInstance()->where('competition_id', $cid)->get()) {
                $competition_info = [
                    'competition_id' => $competition->competition_id,
                    'short_name_zh' => $competition->short_name_zh,
                    'name_zh' => $competition->name_zh,
                    'logo' => $competition->logo
                ];
                $current_season_id = $competition->cur_season_id;
                //基本信息
                if (!$type) {
                    $title_holder = json_decode($competition['title_holder'], true);  //卫冕冠军
                    if ($title_holder) {
                        $title_holder_team = AdminTeam::getInstance()->where('team_id', $title_holder[0])->get();
                        $title_holder_team_info = [
                            'team_id' => $title_holder_team->id,
                            'name_zh' => $title_holder_team->name_zh,
                            'short_name_zh' => $title_holder_team->short_name_zh,
                            'logo' => $title_holder_team->logo,
                            'champion_time' => $title_holder[1]
                        ];
                    }
                    $most_titles = json_decode($competition['most_titles'], true);    //夺冠最多
                    if ($most_titles) {
                        $most_titles_teams = AdminTeam::getInstance()->where('team_id', $most_titles[0], 'in')->all();
                        if ($most_titles_teams) {
                            foreach ($most_titles_teams as $most_titles_team) {
                                $data['team_od'] = $most_titles_team['team_id'];
                                $data['name_zh'] = $most_titles_team['name_zh'];
                                $data['short_name_zh'] = $most_titles_team['short_name_zh'];
                                $data['logo'] = $most_titles_team['logo'];
                                $data['champion_time'] = $most_titles[1];
                                $most_titles_team_info[] = $data;
                                unset($data);
                            }
                        }
                    }


                    if ($value = Cache::get('unique_team_ids_' . $current_season_id)) {
                        $unique_team_ids = json_decode($value, true);
                    } else {
                        $matchSeason = Tool::getInstance()->postApi(sprintf($this->season_url, $this->user, $this->secret, $current_season_id));
                        $teams = json_decode($matchSeason, true);
                        $decodeDatas = $teams['results'];

                        if ($decodeDatas) {
                            foreach ($decodeDatas as $data) {
                                $team_ids[] = $data['home_team_id'];
                                $team_ids[] = $data['away_team_id'];

                            }

                        }

                        $unique_team_ids = array_unique($team_ids);
                        Cache::set('unique_team_ids_' . $current_season_id, json_encode($unique_team_ids), 60*60*24*7);
                    }
                    //球队数量
                    $team_season_count = count($unique_team_ids);
                    //球队总市值
                    $team_total_value = AdminTeam::getInstance()->where('team_id', $unique_team_ids, 'in')->sum('market_value');
                    $formatValue = AppFunc::formatValue($team_total_value);


                    //球员总数
                    $player_count = AdminTeam::getInstance()->where('team_id', $unique_team_ids, 'in')->sum('total_players');
                    //非本土球员数
                    $player_count_foreign = AdminTeam::getInstance()->where('team_id', $unique_team_ids, 'in')->where('foreign_players', '0', '>=')->sum('foreign_players');


                    //基本信息
                    $returnData = [
                        'last_match' => $formatLastMatch,
                        'statistics' => [
                            'title_holder' => isset($title_holder_team_info) ? $title_holder_team_info : [],
                            'most_titles' => isset($most_titles_team_info) ? $most_titles_team_info : [],
                            'join_team_count' => $team_season_count,
                            'player_count' => $player_count,
                            'player_count_no_native' => $player_count_foreign,
                            'format_value' => $formatValue
                        ]
                    ];
                } else if($type == 1) {
                    /************************************************************************
                     * 积分榜
                     */
                    //积分榜

                    $matchSeason = Tool::getInstance()->postApi(sprintf($this->integral_url, $this->user, $this->secret, $current_season_id));
                    $teams = json_decode($matchSeason, true);
                    $decodeDatas = $teams['results'];
//                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM], $decodeDatas);
//
//                    if (isset($decodeDatas['tables']['rows']) && $decodeDatas['tables']['rows']) {
//                        foreach ($decodeDatas['tables']['rows'] as $)
//                    }
                    $return = [];
                    $integral_table = [];

                    if ($decodeDatas['promotions']) {
                        foreach ($decodeDatas['promotions'] as $promotion) {
//                            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM], $decodeDatas['tables'][0]['rows']);

                            $teamInfo['name_zh'] = $promotion['name_zh'];
                            foreach ($decodeDatas['tables'][0]['rows'] as $row) {
                                $team = AdminTeam::getInstance()->where('team_id', $row['team_id'])->get();

                                $data['total'] = $row['total'];
                                $data['won'] = $row['won'];
                                $data['draw'] = $row['draw'];
                                $data['goals'] = $row['goals'];

                                $data['goals_against'] = $row['goals_against'];
                                $data['points'] = $row['points'];
                                $data['logo'] = $team['logo'];
                                $data['name_zh'] = $team['name_zh'];
                                $data['team_id'] = $team['team_id'];

                                $teamInfo['teams'][] = $data;

                                unset($data);
                            }
                            $teamInfo_sort = array_column($teamInfo, 'points');
                            array_multisort($teamInfo_sort,SORT_DESC,$teamInfo);
                            $integral_table[] = $teamInfo;
                        }
                    } else {
                        foreach ($decodeDatas['tables'] as $k => $table) {

                            if (isset($table['rows'])) {

                                foreach ($table['rows'] as $ki => $item) {
                                    $team = AdminTeam::getInstance()->where('team_id', $item['team_id'])->get();


                                    $data['total'] = $item['total'];
                                    $data['won'] = $item['won'];
                                    $data['draw'] = $item['draw'];
                                    $data['goals'] = $item['goals'];

                                    $data['goals_against'] = $item['goals_against'];
                                    $data['points'] = $item['points'];
                                    $data['logo'] = $team['logo'];
                                    $data['name_zh'] = $team['name_zh'];
                                    $data['team_id'] = $team['team_id'];

                                    $return[] = $data;

                                    unset($data);
                                }

                                $integral_table[] = $return;
                                unset($return);
                            }
                        }
                    }


                    $returnData = $integral_table;

                } else if ($type == 2) {
                    //比赛


                } else {
                    /************************************************************************
                     * 最佳球员
                     */
                    //最佳球员
                    if (!$season_team_player = SeasonTeamPlayer::getInstance()->where('season_id', $current_season_id)->get()) {
                        $res = Tool::getInstance()->postApi(sprintf($this->best_player, $this->user, $this->secret, 7555));
                        $decode = json_decode($res, true);
                        $decodeDatas = $decode['results'];
                        $players_stats = $decodeDatas['players_stats'];
                        $shooters = $decodeDatas['shooters'];
                        $teams_stats = $decodeDatas['teams_stats'];
                        $insert = [
                            'season_id' => $current_season_id,
                            'updated_at' => $decodeDatas['updated_at'],
                            'players_stats' => json_decode($players_stats),
                            'shooters' => json_encode($shooters),
                            'teams_stats' => json_decode($teams_stats),
                        ];
                        SeasonTeamPlayer::getInstance()->insert($insert);
                    } else {
                        $players_stats = json_decode($season_team_player->players_stats, true);
                        $shooters = json_decode($season_team_player->shooters, true);
                        $teams_stats = json_decode($season_team_player->teams_stats, true);
                    }
                    if ($type == 3) {
                        //射手榜
                        foreach ($shooters as $shooter) {
                            $data['position'] = $shooter['position'];
                            $data['player_id'] = $shooter['player']['id'];
                            $data['name_zh'] = end(explode('·', $shooter['player']['name_zh']));
                            $data['player_logo'] = $this->player_logo . $shooter['player']['logo'];
                            $data['team_logo'] = $this->team_logo . $shooter['team']['logo'];
                            $data['goal'] = $shooter['goal'];
                            $shooter_table[] = $data;
                            unset($data);
                        }

                        //助攻榜
                        $assisting = array_column($players_stats, 'assists');
                        array_multisort($assisting,SORT_DESC,$players_stats);
                        $assisting_table = FrontService::handBestPlayerTable($players_stats, 'assists');

                        //射门
                        $shots = array_column($players_stats, 'shots');
                        array_multisort($shots,SORT_DESC,$players_stats);
                        $shots_table = FrontService::handBestPlayerTable($players_stats, 'shots');

                        //射正
                        $shots_on_target = array_column($players_stats, 'shots_on_target');
                        array_multisort($shots_on_target,SORT_DESC,$players_stats);
                        $shots_on_target_table = FrontService::handBestPlayerTable($players_stats, 'shots_on_target');

                        //传球
                        $passes = array_column($players_stats, 'passes');
                        array_multisort($passes,SORT_DESC,$players_stats);
                        $passes_table = FrontService::handBestPlayerTable($players_stats, 'passes');

                        //成功传球
                        $passes_accuracy = array_column($players_stats, 'passes_accuracy');
                        array_multisort($passes_accuracy,SORT_DESC,$players_stats);
                        $passes_accuracy_table = FrontService::handBestPlayerTable($players_stats, 'passes_accuracy');

                        //关键传球
                        $key_passes = array_column($players_stats, 'key_passes');
                        array_multisort($key_passes,SORT_DESC,$players_stats);
                        $key_passes_table = FrontService::handBestPlayerTable($players_stats, 'key_passes');


                        //拦截
                        $interceptions = array_column($players_stats, 'interceptions');
                        array_multisort($interceptions,SORT_DESC,$players_stats);
                        $interceptions_table = FrontService::handBestPlayerTable($players_stats, 'interceptions');

                        //解围
                        $clearances = array_column($players_stats, 'clearances');
                        array_multisort($clearances,SORT_DESC,$players_stats);
                        $clearances_table = FrontService::handBestPlayerTable($players_stats, 'clearances');

                        //扑救
                        $saves = array_column($players_stats, 'saves');
                        array_multisort($saves,SORT_DESC,$players_stats);
                        $saves_table = FrontService::handBestPlayerTable($players_stats, 'saves');

                        //黄牌
                        $yellow_cards = array_column($players_stats, 'yellow_cards');
                        array_multisort($yellow_cards,SORT_DESC,$players_stats);
                        $yellow_cards_table = FrontService::handBestPlayerTable($players_stats, 'yellow_cards');

                        //红牌
                        $red_cards = array_column($players_stats, 'red_cards');
                        array_multisort($red_cards,SORT_DESC,$players_stats);
                        $red_cards_table = FrontService::handBestPlayerTable($players_stats, 'red_cards');

                        //出场时间
                        $minutes_played = array_column($players_stats, 'minutes_played');
                        array_multisort($minutes_played,SORT_DESC,$players_stats);
                        $minutes_played = FrontService::handBestPlayerTable($players_stats, 'minutes_played');

                        $returnData = [
                            'shooter_table' => $shooter_table ?: [],
                            'assisting_table' => $assisting_table,
                            'shots_table' => $shots_table,
                            'shots_on_target_table' => $shots_on_target_table,
                            'passes_table' => $passes_table,
                            'passes_accuracy_table' => $passes_accuracy_table,
                            'key_passes_table' => $key_passes_table,
                            'interceptions_table' => $interceptions_table,
                            'clearances_table' => $clearances_table,
                            'saves_table' => $saves_table,
                            'yellow_cards_table' => $yellow_cards_table,
                            'red_cards_table' => $red_cards_table,
                            'minutes_played' => $minutes_played,
                        ];
                    } else if ($type == 4) {
                        /************************************************************************
                         * 最佳球队
                         */

                        //进球
                        $goals = array_column($teams_stats, 'goals');
                        array_multisort($goals,SORT_DESC,$teams_stats);
                        $team_goals_table = FrontService::handBestTeamTable($teams_stats, 'goals');

                        //失球
                        $goals_against = array_column($teams_stats, 'goals_against');
                        array_multisort($goals_against,SORT_DESC,$teams_stats);
                        $team_goals_against_table = FrontService::handBestTeamTable($teams_stats, 'goals_against');

                        //获得点球
                        $penalty = array_column($teams_stats, 'penalty');
                        array_multisort($penalty,SORT_DESC,$teams_stats);
                        $team_penalty_table = FrontService::handBestTeamTable($teams_stats, 'penalty');

                        //射门
                        $shots = array_column($teams_stats, 'shots');
                        array_multisort($shots,SORT_DESC,$teams_stats);
                        $team_shots_table = FrontService::handBestTeamTable($teams_stats, 'shots');

                        //射正
                        $shots_on_target = array_column($teams_stats, 'shots_on_target');
                        array_multisort($shots_on_target,SORT_DESC,$teams_stats);
                        $team_shots_on_target_table = FrontService::handBestTeamTable($teams_stats, 'shots_on_target');

                        //关键传球
                        $key_passes = array_column($teams_stats, 'key_passes');
                        array_multisort($key_passes,SORT_DESC,$teams_stats);
                        $team_key_passes_table = FrontService::handBestTeamTable($teams_stats, 'key_passes');

                        //拦截
                        $interceptions = array_column($teams_stats, 'interceptions');
                        array_multisort($interceptions,SORT_DESC,$teams_stats);
                        $team_interceptions_table = FrontService::handBestTeamTable($teams_stats, 'interceptions');

                        //解围
                        $clearances = array_column($teams_stats, 'clearances');
                        array_multisort($clearances,SORT_DESC,$teams_stats);
                        $team_clearances_table = FrontService::handBestTeamTable($teams_stats, 'clearances');

                        //黄牌
                        $yellow_cards = array_column($teams_stats, 'yellow_cards');
                        array_multisort($yellow_cards,SORT_DESC,$teams_stats);
                        $team_yellow_cards_table = FrontService::handBestTeamTable($teams_stats, 'yellow_cards');

                        //红牌
                        $red_cards = array_column($teams_stats, 'red_cards');
                        array_multisort($red_cards,SORT_DESC,$teams_stats);
                        $team_red_cards_table = FrontService::handBestTeamTable($teams_stats, 'red_cards');

                        $returnData = [
                            'team_goals_table' => $team_goals_table,                        //进球
                            'team_goals_against_table' => $team_goals_against_table,        //失球
                            'team_penalty_table' => $team_penalty_table,                    //点球
                            'team_shots_table' => $team_shots_table,                        //射门
                            'team_shots_on_target_table' => $team_shots_on_target_table,    //射正
                            'team_key_passes_table' => $team_key_passes_table,              //关键传球
                            'team_interceptions_table' => $team_interceptions_table,        //拦截
                            'team_clearances_table' => $team_clearances_table,              //解围
                            'team_yellow_cards_table' => $team_yellow_cards_table,          //黄牌
                            'team_red_cards_table' => $team_red_cards_table,                //红牌
                        ];
                    } else  {
                        return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

                    }

                }


                $return['competition_info'] = $competition_info;
                $return['data'] = $returnData;

                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


            } else {
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

            }

        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }


    }


    /**
     * 数据中心推荐热门赛事
     */
    public function getHotCompetition()
    {

    }

    /**
     * 最新FIFA男子排名
     *
     */
    public function FIFAMaleRank()
    {
        $region_id = isset($this->params['region_id']) ? $this->params['region_id'] : 0;
        $is_male = isset($this->params['is_male']) ? $this->params['is_male'] : 0;
        $matchSeason = Tool::getInstance()->postApi(sprintf($is_male ? $this->FIFA_male_rank : $this->FIFA_female_rank, $this->user, $this->secret));
        $teams = json_decode($matchSeason, true);
        $decodeDatas = $teams['results'];

        foreach ($decodeDatas['items'] as $k=>$decodeData) {
            $decodeData['team']['country_logo'] = $this->team_logo . $decodeData['team']['country_logo'];
            if ($region_id) {
                if ($region_id == $decodeData['region_id']) {
                    $datas[] = $decodeData;

                } else {
                    continue;
                }
            } else {
                $datas[] = $decodeData;

            }

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $datas);

    }


    public function FIFAFemaleRank()
    {

    }


    /**
     * 全部赛事 国家分类
     * @return bool
     */
    public function CategoryCountry()
    {
        $categorys = AdminCountryCategory::getInstance()->all();
        foreach ($categorys as $category) {

            $countrys = AdminCountryList::getInstance()->where('category_id', $category->category_id)->field(['country_id', 'name_zh', 'logo'])->all();
            $cate_info = [
                'category_id' => $category->category_id,
                'category_name_zh' => $category->name_zh,
                'country' => $countrys,

            ];

            $return[] = $cate_info;
            unset($cate_info);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }


    /**
     * 获取赛事
     * @return bool
     */
    public function competitionByCid()
    {
        $category_id = $this->params['category_id'];
        $country_id = $this->params['country_id'];
        $competitions[] = AdminCompetition::getInstance()->where('category_id', $category_id)
            ->field(['competition_id', 'short_name_zh', 'logo'])
            ->where('country_id', $country_id)
            ->where('type', [1,2], 'in')
            ->all();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competitions);

    }

    public function formatValue()
    {
//        $match = AdminMatch::getInstance()->where('competition_id', 82)->all();
//        $format = FrontService::handMatch($match, 0, true);
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $format);

        $matchSeason = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/competition/list?user=%s&secret=%s&id=%s', $this->user, $this->secret, 82));
        $teams = json_decode($matchSeason, true);
        $decodeDatas = $teams['results'];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decodeDatas);

        foreach ($decodeDatas as $item) {
            $data = [
                'match_time' => date('Y-m-d H:i:s', $item['match_time']),
                'home_team' => (AdminTeam::getInstance()->where('team_id', $item['home_team_id'])->get())['name_zh'],
                'away_team' => (AdminTeam::getInstance()->where('team_id', $item['away_team_id'])->get())['name_zh']
            ];

            $times[] = $data;
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $times);






        $matchSeason = Tool::getInstance()->postApi(sprintf($this->best_player, $this->user, $this->secret, 7555));
        $teams = json_decode($matchSeason, true);
        $decodeDatas = $teams['results'];


        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decodeDatas);




    }
}
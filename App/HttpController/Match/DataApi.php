<?php

namespace App\HttpController\Match;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\Tool;
use App\Model\AdminCompetition;
use App\Model\AdminCompetitionRuleList;
use App\Model\AdminCountryList;
use App\Model\AdminHonorList;
use App\Model\AdminMatch;
use App\Model\AdminCountryCategory;
use App\Model\AdminPlayer;
use App\Model\AdminPlayerChangeClub;
use App\Model\AdminPlayerHonorList;
use App\Model\AdminPlayerStat;
use App\Model\AdminSeason;
use App\Model\AdminStageList;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminTeamHonor;
use App\Model\AdminTeamLineUp;
use App\Model\SeasonAllTableDetail;
use App\Model\SeasonMatchList;
use App\Model\SeasonTeamPlayer;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\Redis\Redis as Redis;
use EasySwoole\RedisPool\Redis as RedisPool;

class DataApi extends FrontUserController{

    protected $user = 'mark9527';

    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    public $team_logo = 'https://cdn.sportnanoapi.com/football/team/';
    public $player_logo = 'https://cdn.sportnanoapi.com/football/player/';

//    protected $season_url = 'https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s';  //赛季查询
//    protected $best_player = 'https://open.sportnanoapi.com/api/v4/football/season/all/stats/detail?user=%s&secret=%s&id=%s'; //获取赛季球队球员统计详情
    protected $FIFA_male_rank = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/men?user=%s&secret=%s'; //FIFA男子排名
    protected $FIFA_female_rank = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/women?user=%s&secret=%s'; //FIFA女子子排名


    /**
     * 赛事信息
     * @return bool
     */
    public function competitionInfo()
    {
        $hot_competition = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();
        $sys_value = json_decode($hot_competition['sys_value'], true);
        $competition_id = $sys_value[0];

        $cid = $this->params['competition_id'] ? $this->params['competition_id'] : $competition_id;
        $type = $this->params['type']; //0基本信息  1积分榜 2比赛 3最佳球员 4最佳球队
        if ($cid) {

            if ($competition = AdminCompetition::getInstance()->where('competition_id', $cid)->get()) {

                $select_season_id = !empty($this->params['season_id']) ? $this->params['season_id'] : $competition->cur_season_id;

                $seasons = $competition->getSeason();
                $competition_info = [

                    'season' => $seasons
                ];
                $current_season_id = $this->params['season_id'] ? $this->params['season_id'] : $competition->cur_season_id;
                //基本信息
                if($type == 1) {
                    /************************************************************************
                     * 积分榜
                     */

                    $competition_describe = '';
                    if ($competition_rules = AdminCompetitionRuleList::getInstance()->where('competition_id', $cid)->all()) {
                        foreach ($competition_rules as $competition_rule) {
                            if (in_array($select_season_id ,json_decode($competition_rule->season_ids, true))) {
                                $competition_describe = $competition_rule->text;
                            }
                        }
                    }
                    $dataT = [];
                    $promotion = 0;

                    //积分榜
                    if ($table_detail = SeasonAllTableDetail::getInstance()->where('season_id', $current_season_id)->get()) {
                        $promotions = json_decode($table_detail->promotions, true);
                        $tables = json_decode($table_detail->tables, true);

                        if ($promotions) {
                            foreach ($tables[0]['rows'] as $row) {
                                $promotion_name_zh = '';
                                foreach ($promotions as $promotion) {
                                    if ($row['promotion_id'] == $promotion['id']) {
                                        $promotion_name_zh = $promotion['name_zh'];
                                    }
                                }
                                $team = AdminTeam::getInstance()->where('team_id', $row['team_id'])->get();
                                $data['total'] = $row['total'];
                                $data['won'] = $row['won'];
                                $data['draw'] = $row['draw'];
                                $data['loss'] = $row['loss'];
                                $data['goals'] = $row['goals'];
                                $data['goals_against'] = $row['goals_against'];
                                $data['points'] = $row['points'];
                                $data['logo'] = $team['logo'];
                                $data['name_zh'] = $team['name_zh'];
                                $data['team_id'] = $team['team_id'];
                                $data['promotion_id'] = $row['promotion_id'];
                                $data['promotion_name_zh'] = ($row['promotion_id'] == 0) ? '' : $promotion_name_zh;

                                $dataT[] = $data;

                                unset($data);
                            }
                            $promotion = 1;
                        } else {
                            $promotion = 0;
                            $dataT = [];
                            foreach ($tables as $item_table) {
                                $data = [];
                                foreach ($item_table['rows'] as $item_row) {
                                    $team = AdminTeam::getInstance()->where('team_id', $item_row['team_id'])->get();
                                    if (!$team) continue;
                                    $row_info['team_id'] = $team->team_id;
                                    $row_info['name_zh'] = $team->name_zh;
                                    $row_info['logo'] = $team->logo;
                                    $row_info['total'] = $item_row['total'];
                                    $row_info['won'] = $item_row['won'];
                                    $row_info['draw'] = $item_row['draw'];
                                    $row_info['loss'] = $item_row['loss'];
                                    $row_info['goals'] = $item_row['goals'];
                                    $row_info['goals_against'] = $item_row['goals_against'];
                                    $row_info['points'] = $item_row['points'];
                                    $data[] = $row_info;
                                    unset($row_info);
                                }
                                $table_group['group'] = $item_table['group'];
                                $table_group['list'] = $data;
                                $dataT[] = $table_group;
                                unset($table_group);

                            }
                        }
                    }

                    $returnData['data'] = $dataT;
                    $returnData['promotion'] = $promotion;
                    $returnData['competition_describe'] = $competition_describe;

                } else if ($type == 2) {    //比赛


                    $stage = AdminStageList::getInstance()->field(['name_zh', 'stage_id', 'round_count', 'group_count'])->where('season_id', $select_season_id)->all();
                    if ($select_season_id == $competition->cur_season_id) {
                        $stage_id = !empty($this->params['stage_id']) ? $this->params['stage_id'] : $competition->cur_stage_id;
                        $round_id = !empty($this->params['round_id']) ? $this->params['round_id'] : $competition->cur_round;
                    } else {
                        $stage_id = !empty($this->params['stage_id']) ? $this->params['stage_id'] : $stage[0]['stage_id'];
                        $round_id = !empty($this->params['round_id']) ? $this->params['round_id'] : $stage[0]['round_count'];
                    }

                    $group_id = !empty($this->params['group_id']) ? $this->params['group_id'] : 1;

                    $decodeDatas = SeasonMatchList::getInstance()->where('season_id', $select_season_id)->all();

                    foreach ($decodeDatas as $item_match) {

                        $round = json_decode($item_match->round, true);

                        if ($round['stage_id'] == $stage_id && ($round['round_num'] == $round_id || $round['group_num'] == $group_id)) {
                            $decode_home_score = json_decode($item_match['home_scores'], true);
                            $decode_away_score = json_decode($item_match['away_scores'], true);
                            $data['match_id'] = $item_match['id'];
                            $data['match_time'] = date('Y-m-d H:i:s', $item_match['match_time']);
                            $data['home_team_name_zh'] = AdminTeam::getInstance()->where('team_id', $item_match['home_team_id'])->get()->name_zh;
                            $data['away_team_name_zh'] = AdminTeam::getInstance()->where('team_id', $item_match['away_team_id'])->get()->name_zh;
                            $data['status_id'] = $item_match['status_id'];
                            list($data['home_scores'], $data['away_scores']) = AppFunc::getFinalScore($decode_home_score, $decode_away_score);
                            list($data['half_home_scores'], $data['half_away_scores']) = AppFunc::getHalfScore($decode_home_score, $decode_away_score);
                            list($data['home_corner'], $data['away_corner']) = AppFunc::getCorner($decode_home_score, $decode_away_score);
                            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

                        } else {
                            continue;
                        }
                    }

                    $returnData = [
                        'stage' => $stage,
                        'match_list' => isset($match_competition) ? $match_competition : [],
                        'cur_stage_id' => $competition->cur_stage_id,
                        'cur_round' => $competition->cur_round

                    ];

                } else{
                    /************************************************************************
                     * 最佳球员
                     */

                    $decodeDatas = SeasonTeamPlayer::getInstance()->where('season_id', $current_season_id)->get();
                    $players_stats = json_decode($decodeDatas['players_stats'], true);
                    $teams_stats = json_decode($decodeDatas['teams_stats'], true);

                    if ($type == 3) {
                        array_walk($players_stats, function ($value) use(&$tables) {
                            $data['player_id'] = $value['player']['id'];
                            $data['name_zh'] = $value['player']['name_zh'];
                            $data['team_logo'] = FrontService::TEAM_LOGO . $value['team']['logo'];
                            $data['player_logo'] = FrontService::PLAYER_LOGO . $value['player']['logo'];
                            $data['player_id'] = $value['player']['id'];//球员id
                            $data['assists'] = $value['assists'];//助攻
                            $data['shots'] = $value['shots'];//射门
                            $data['shots_on_target'] = $value['shots_on_target'];//射正
                            $data['passes'] = $value['passes'];//传球
                            $data['passes_accuracy'] = $value['passes_accuracy'];//成功传球
                            $data['key_passes'] = $value['key_passes'];//关键传球
                            $data['interceptions'] = $value['interceptions'];//拦截
                            $data['clearances'] = $value['clearances'];//解围
                            $data['yellow_cards'] = $value['yellow_cards'];//黄牌
                            $data['red_cards'] = $value['red_cards'];//红牌
                            $data['minutes_played'] = $value['minutes_played'];//出场时间
                            $data['goals'] = $value['goals'];//出场进球
                            $tables[] = $data;
                            unset($data);

                        });

                        $returnData = $tables;
                    } else if ($type == 4) {
                        /************************************************************************
                         * 最佳球队
                         */

                        //进球
                        if ($teams_stats) {
                            array_walk($teams_stats, function ($value) use(&$team_tables) {
                                $data['team_id'] = $value['team']['id'];
                                $data['name_zh'] = $value['team']['name_zh'];
                                $data['team_logo'] = FrontService::TEAM_LOGO . $value['team']['logo'];
                                $data['goals'] = $value['goals'];
                                $data['goals_against'] = $value['goals_against'];
                                $data['penalty'] = $value['penalty'];
                                $data['shots'] = $value['shots'];
                                $data['shots_on_target'] = $value['shots_on_target'];
                                $data['key_passes'] = $value['key_passes'];
                                $data['interceptions'] = $value['interceptions'];
                                $data['clearances'] = $value['clearances'];
                                $data['yellow_cards'] = $value['yellow_cards'];
                                $data['red_cards'] = $value['red_cards'];
                                $team_tables[] = $data;
                                unset($data);

                            });


                            $returnData = $team_tables;



                        } else {
                            $returnData = [];
                        }

                    } else  {
                        return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

                    }

                }


                $return['competition_info'] = $competition_info;
                $return['data'] = $returnData ?: [];

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
        $hot_competition = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();
        $competitionIds = json_decode($hot_competition['sys_value'], true);
        $return = [];
        foreach ($competitionIds as $k => $competitionId) {
            if (!$competition = AdminCompetition::getInstance()->where('competition_id', $competitionId)->get()) {
                continue;
            } else {
                $data['competition_id'] = $competition['competition_id'];
                $data['logo'] = $competition['logo'];
                $data['short_name_zh'] = $competition['short_name_zh'];
//                $data['seasons'] = $competition->getSeason();
                $return[] = $data;
                unset($data);
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);



    }


    /**
     * 国家分类赛事
     * @return bool
     */
    public function getCompetitionByCountry()
    {

        $type = $this->params['type'];
        if (!$type) {
            $category_id = $this->params['category_id'];

            $country_list = AdminCountryList::getInstance()->where('category_id', $category_id)->field(['id', 'name_zh', 'logo'])->all();
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $country_list);
        } else {
            $category_id = $this->params['category_id'];
            $country_id = $this->params['country_id'];

            if ($category_id && !$country_id) {
                $country_list = AdminCountryList::getInstance()->where('category_id', $category_id)->field('country_id', 'name_zh')->all();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $country_list);

            }

            if ($category_id && $country_id) {
                $competition = AdminCompetition::getInstance()->where('country_id', $country_id)->where('category_id', $category_id)->field(['competition_id', 'short_name_zh', 'logo'])->all();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competition);

            }
        }

        return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
    }

    /**
     * 最新FIFA女子男子排名
     *
     */
    public function FIFAMaleRank()
    {

        //区域id，1-欧洲足联、2-南美洲足联、3-中北美洲及加勒比海足协、4-非洲足联、5-亚洲足联、6-大洋洲足联
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
        $country_id = $this->params['country_id'];
        $competitions = AdminCompetition::getInstance()
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

        $matchSeason = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/competition?user=%s&secret=%s&id=%s', $this->user, $this->secret, 1));
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



    }

    /**
     * 球员详情
     * @return bool
     */
    public function getPlayerInfo()
    {
        $player_id = $this->params['player_id'];
        if (!$player_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }

        //基本信息
        $basic = AdminPlayer::getInstance()->where('player_id', $player_id)->get();
        if (!$basic) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;
        if ($type == 1) {
            $team = $basic->getTeam();
            $country = $basic->getCountry();
            //球员技术能力
            $stat = AdminPlayerStat::getInstance()->where('player_id', $player_id)->get();
            //转会
            $history = AdminPlayerChangeClub::getInstance()->where('player_id', $player_id)->all();
            if ($history) {
                foreach ($history as $k=>$item) {
                    $from_team = $item->fromTeamInfo();
                    if ($from_team) {
                        $from_team_info = ['name_zh' => $from_team->name_zh, 'logo' => $from_team->logo, 'team_id' => $from_team->team_id];
                    }
                    $to_team = $item->ToTeamInfo();
                    if ($to_team) {
                        $to_team_info = ['name_zh' => $to_team->name_zh, 'logo' => $to_team->logo, 'team_id' => $to_team->team_id];

                    }
                    $data['transfer_time'] = date('Y-m-d', $item['transfer_time']);
                    $data['transfer_type'] = $item->transfer_type; //转会类型，1-租借、2-租借结束、3-转会、4-退役、5-选秀、6-已解约、7-已签约、8-未知
                    $data['from_team_info'] = isset($from_team_info) ? $from_team_info : [];
                    $data['to_team_info'] = isset($to_team_info) ? $to_team_info : [];
                    $format_history[] = $data;
                    unset($data);
                }
            }
            $format_player_honor = [];
            if ($player_honor = AdminPlayerHonorList::getInstance()->where('player_id', $basic->player_id)->get()) {
                $honor = json_decode($player_honor->honors, true);
//                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $player_honor);

                $format_player_honor = $honor;
            }

            $player_info = [
                'team_info' => ['name_zh' => $team->name_zh, 'logo' => $team->logo],
                'contract_until' => date('Y-m-d', $basic->contract_until),
                'country_info' => ['name_zh' => $country->name_zh, 'logo' => $country->logo],
                'user_info' => [
                    'name_zh' => $basic->name_zh,
                    'logo' => $basic->logo,
                    'market_value' => AppFunc::changeToWan($basic->market_value),
                    'age' => $basic->age,
                    'weight' => $basic->weight,
                    'height' => $basic->height,
                    'preferred_foot' => $basic->preferred_foot,
                    'position' => $basic->position,
                    'ability' => isset($stat['ability']) ? json_decode($stat['ability'], true) : [],
                    'birthday' => $basic->birthday ? date('Y-m-d', $basic->birthday) : '',
//                    'characteristics' => isset($stat['characteristics']) ? json_decode($stat['characteristics'], true) : [],
                ],
                'change_club_history' => isset($format_history) ? $format_history : [],
                'player_honor' => $format_player_honor
            ];
            $return_data = $player_info;
        } else if ($type == 2) {
            //技术统计
            $return_data = [];
            //获取球员参加的所有赛季
            $season = AppFunc::getPlayerSeasons(json_decode($basic->seasons, true));
            if (!$season) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
            }
            $stat_data = [];

            $select_season_id = !empty($this->params['select_season_id']) ? $this->params['select_season_id'] : $season[0]['season_list'][0]['season_id'];
            $res = SeasonTeamPlayer::getInstance()->where('season_id', $select_season_id)->get();

            if ($res && $players_stats = json_decode($res->players_stats, true)) {
                foreach ($players_stats as $players_stat) {
                    if ($players_stat['player']['id'] == $player_id) {

                        //比赛
                        $stat_data['match']['matches'] = $players_stat['matches']; //出场
                        $stat_data['match']['first'] = $players_stat['first']; //首发
                        $stat_data['match']['minutes_played'] = $players_stat['minutes_played'];//场均时间
                        $stat_data['match']['minutes_played_per_match'] = number_format($players_stat['minutes_played']/$players_stat['matches'],1);//场均时间
                        //进攻
                        $stat_data['goal']['goals'] = $players_stat['goals'];//进球
                        $stat_data['goal']['penalty'] = $players_stat['penalty'];//点球
                        $stat_data['goal']['penalty_per_match'] = number_format($players_stat['penalty']/$players_stat['matches'],1);//场均点球
                        $stat_data['goal']['goals_per_match'] = number_format($players_stat['goals']/$players_stat['matches'],1);//场均进球
                        $stat_data['goal']['cost_time_per_goal'] = number_format($players_stat['minutes_played']/$players_stat['goals'],1);//每球耗时
                        $stat_data['goal']['shots_per_match'] = number_format($players_stat['shots']/$players_stat['matches'],1);//场均射门
                        $stat_data['goal']['shots'] = $players_stat['shots'];//射门总数
                        $stat_data['goal']['was_fouled'] = $players_stat['was_fouled'];//被犯规
                        $stat_data['goal']['shots_on_target_per_match'] = number_format($players_stat['shots_on_target']/$players_stat['matches'],1);//场均射正
                        //组织
                        $stat_data['pass']['assists'] = $players_stat['assists'];//助攻
                        $stat_data['pass']['assists_per_match'] = number_format($players_stat['assists']/$players_stat['matches'],1);//场均助攻
                        $stat_data['pass']['key_passes_per_match'] = number_format($players_stat['key_passes']/$players_stat['matches'],1);//场均关键传球
                        $stat_data['pass']['key_passes'] = $players_stat['key_passes'];//关键传球
                        $stat_data['pass']['passes'] = $players_stat['passes'];//传球
                        $stat_data['pass']['passes_per_match'] = number_format($players_stat['passes']/$players_stat['matches'],1);//传球
                        $stat_data['pass']['passes_accuracy_per_match'] = number_format($players_stat['passes_accuracy']/$players_stat['matches'],1);//场均成功传球
                        $stat_data['pass']['passes_accuracy'] = $players_stat['passes_accuracy'];//成功传球

                        //防守
                        $stat_data['defense']['tackles_per_match'] = number_format($players_stat['tackles']/$players_stat['matches'],1);//场均抢断
                        $stat_data['defense']['tackles'] = $players_stat['tackles'];//场均抢断
                        $stat_data['defense']['interceptions_per_match'] = number_format($players_stat['interceptions']/$players_stat['matches'],1);//场均拦截
                        $stat_data['defense']['interceptions'] = $players_stat['interceptions'];//场均拦截
                        $stat_data['defense']['clearances_per_match'] = number_format($players_stat['clearances']/$players_stat['matches'],1);//场均解围
                        $stat_data['defense']['clearances'] = $players_stat['clearances'];//场均解围
                        $stat_data['defense']['blocked_shots'] = $players_stat['blocked_shots'];//有效阻挡
                        $stat_data['defense']['blocked_shots_per_match'] = number_format($players_stat['blocked_shots']/$players_stat['matches'],1);//场均解围

                        //其他
                        $stat_data['other']['dribble_succ_per_match'] = number_format($players_stat['dribble_succ']/$players_stat['matches'],1); //场均过人成功
                        $stat_data['other']['duels_won_succ_per_match'] = number_format($players_stat['duels_won']/$players_stat['matches'],1); //场均1对1拼抢成功
                        $stat_data['other']['fouls_per_match'] = number_format($players_stat['fouls']/$players_stat['matches'],1); //场均犯规
                        $stat_data['other']['was_fouled_per_match'] = number_format($players_stat['was_fouled']/$players_stat['matches'],1); //场均被犯规
                        $stat_data['other']['yellow_cards_per_match'] = number_format($players_stat['yellow_cards']/$players_stat['matches'],1); //黄牌场均
                        $stat_data['other']['red_cards_per_match'] = number_format($players_stat['red_cards']/$players_stat['matches'],1); //红牌场均
                        break;

                    } else {
                        continue;
                    }
                }
                $return_data['stat_data'] = $stat_data;
                $return_data['season_list'] = $season;
            }
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

    }



    public function teamInfo()
    {
//        Log::getInstance()->info('params' . json_encode($this->params));
        $team_id = $this->params['team_id'];
        if (!$team_id || !$team = AdminTeam::getInstance()->where('team_id', $team_id)->get()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        //赛季  当前赛季
        $competition_id = $this->params['competition_id'];
        $competition = AdminCompetition::getInstance()->where('competition_id', $competition_id)->get();
        //积分  只取该球队默认的赛事
        $select_season_id = !empty($this->params['select_season_id']) ? $this->params['select_season_id'] : $competition->cur_season_id;
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;


        if ($competition) {
            $season = $competition->getSeason();
        } else {
            $season = [];
        }

        $current_season_id = $competition->cur_season_id ?: end($season)['id'];
        if ($type == 1) {
            //球队基本资料
            $basic = [
                'name_zh' => $team['name_zh'],
                'logo' => $team['logo'],
                'foundation_time' => $team['foundation_time'],
                'website' => $team['website'],
                'country' => $team->getCountry()->name_zh,
                'manager_name_zh' => $team->getManager()->name_zh,
                'foreign_players' => $team->foreign_players,
                'national_players' => $team->national_players,
            ];
            $last_match = AdminMatch::getInstance()::create()->func(function ($builder) use ($team_id){
                $builder->raw('select * from `admin_match_list` where (home_team_id = ? or away_team_id = ?) and status_id in (1,2,3,4,5,7) order by `match_time` desc limit 1',[$team_id, $team_id]);
                return true;
            });
            if (!$last_match) {
                $format_last_match = [];
            } else {
                $last_match_home_team = AdminTeam::getInstance()->where('team_id', $last_match[0]['home_team_id'])->get();
                $last_match_away_team = AdminTeam::getInstance()->where('team_id', $last_match[0]['away_team_id'])->get();
                $format_last_match = [
                    'match_id' => $last_match[0]['match_id'],
                    'match_time' => date('Y-m-d', $last_match[0]['match_time']),
                    'home_team_name_zh' => $last_match_home_team['name_zh'],
                    'home_team_logo' => $last_match_home_team['logo'],
                    'away_team_name_zh' => $last_match_away_team['name_zh'],
                    'away_team_logo' => $last_match_away_team['logo'],
                ];
            }




            $done_match = AdminMatch::getInstance()::create()->func(function ($builder) use ($team_id){
                $builder->raw('select * from `admin_match_list` where (home_team_id = ? or away_team_id = ?) and status_id = ? order by `match_time` desc limit 1',[$team_id, $team_id, 8]);
                return true;
            });
            if (!$done_match) {
                $format_done_match = [];
            } else {
                $done_match_home_team = AdminTeam::getInstance()->where('team_id', $done_match[0]['home_team_id'])->get();
                $done_match_away_team = AdminTeam::getInstance()->where('team_id', $done_match[0]['away_team_id'])->get();
                $format_done_match = [
                    'match_id' => $done_match[0]['match_id'],
                    'match_time' => date('Y-m-d', $done_match[0]['match_time']),
                    'home_team_name_zh' => $done_match_home_team['name_zh'],
                    'home_team_logo' => $done_match_home_team['logo'],
                    'away_team_name_zh' => $done_match_away_team['name_zh'],
                    'away_team_logo' => $done_match_away_team['logo'],
                ];
            }

            //转会记录

            $timestamp_one_year_ago = mktime(0, 0, 0, date('m'), date('d'), date('Y')-1);
            $change_in_players = AdminPlayerChangeClub::getInstance()->where('to_team_id', $team_id)->where('transfer_time', $timestamp_one_year_ago, '>')->order('transfer_time', 'DESC')->all();
            $format_change_in_players = FrontService::handChangePlayer($change_in_players);
            $change_out_players = AdminPlayerChangeClub::getInstance()->where('from_team_id', $team_id)->where('transfer_time', $timestamp_one_year_ago, '>')->order('transfer_time', 'DESC')->all();

            $format_change_out_players = FrontService::handChangePlayer($change_out_players);

            //球队荣誉
            $res = AdminTeamHonor::getInstance()->where('team_id', $team_id)->get();
            $honors = json_decode($res['honors'], true);
            $honor_ids = [];
            $data = [];
            foreach ($honors as $honor) {
                $honor_logo = AdminHonorList::getInstance()->where('id')->get();
                $honor['honor']['logo'] = $honor_logo->logo;
                if (!in_array($honor['honor']['id'], $honor_ids)) {

                    $data[$honor['honor']['id']]['honor'] = $honor['honor'];
                    $data[$honor['honor']['id']]['count'] = 1;
                    $honor_ids[] = $honor['honor']['id'];

                } else {
                    $data[$honor['honor']['id']]['count'] += 1;
                }
                $data[$honor['honor']['id']]['season'][] = $honor['season'];
            }

            //赛季

            $return_data = [
                'basic' => $basic,
                'format_last_match' => $format_last_match,
                'format_done_match' => $format_done_match,
                'format_change_in_players' => $format_change_in_players,
                'format_change_out_players' => $format_change_out_players,
                'format_honors' => $data,
                'season' => $season

            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

        } else if ($type == 2) {
            //积分
            $dataT = [];
            if ($seasonAllTableDetail = SeasonAllTableDetail::getInstance()->where('season_id', $select_season_id)->get()) {
                $promotions = json_decode($seasonAllTableDetail->promotions, true);
                $tables = json_decode($seasonAllTableDetail->tables, true);
                if ($promotions) {
                    foreach ($tables['rows'] as $row) {
                        $promotion_name_zh = '';
                        foreach ($promotions as $promotion) {
                            if ($row['promotion_id'] == $promotion['id']) {
                                $promotion_name_zh = $promotion['name_zh'];
                            }
                        }
                        $team = AdminTeam::getInstance()->where('team_id', $row['team_id'])->get();
                        $data['total'] = $row['total'];
                        $data['won'] = $row['won'];
                        $data['draw'] = $row['draw'];
                        $data['loss'] = $row['loss'];
                        $data['goals'] = $row['goals'];
                        $data['goals_against'] = $row['goals_against'];
                        $data['points'] = $row['points'];
                        $data['logo'] = $team['logo'];
                        $data['name_zh'] = $team['name_zh'];
                        $data['team_id'] = $team['team_id'];
                        $data['promotion_id'] = $row['promotion_id'];
                        $data['promotion_name_zh'] = ($row['promotion_id'] == 0) ? '' : $promotion_name_zh;

                        $dataT[] = $data;

                        unset($data);
                    }
                    $promotion = 1;
                } else {
                    $promotion = 0;
                    foreach ($tables as $item_table) {
                        $data = [];
                        foreach ($item_table['rows'] as $item_row) {
                            $team = AdminTeam::getInstance()->where('team_id', $item_row['team_id'])->get();
                            $row_info['team_id'] = $team->team_id;
                            $row_info['name_zh'] = $team->name_zh;
                            $row_info['logo'] = $team->logo;
                            $row_info['total'] = $item_row['total'];
                            $row_info['won'] = $item_row['won'];
                            $row_info['draw'] = $item_row['draw'];
                            $row_info['loss'] = $item_row['loss'];
                            $row_info['goals'] = $item_row['goals'];
                            $row_info['goals_against'] = $item_row['goals_against'];
                            $row_info['points'] = $item_row['points'];
                            $data[] = $row_info;
                            unset($row_info);
                        }
                        $table_group['group'] = $item_table['group'];
                        $table_group['list'] = $data;
                        $dataT[] = $table_group;
                        unset($table_group);

                    }
                }
            } else {
                $promotion = [];
            }


            //赛制说明
            $competition_describe = '';
            if ($competition_rules = AdminCompetitionRuleList::getInstance()->where('competition_id', $team->competition_id)->all()) {
                foreach ($competition_rules as $competition_rule) {
                    if (in_array($select_season_id ,json_decode($competition_rule->season_ids, true))) {
                        $competition_describe = $competition_rule->text;
                    }
                }
            }

            $return_data = [
                'table' => $dataT,
                'competition_describe' => $competition_describe,
                'season' => $season,
                'current_season_id' => $current_season_id,
                'promotion' => $promotion
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

        } else if ($type == 3) {
            //赛程
            $format_data = [];

            $decodeDatas = SeasonMatchList::getInstance()->where('season_id', $select_season_id)->all();

            foreach ($decodeDatas as $decodeData) {
                if ($decodeData['home_team_id'] == $team->team_id || $decodeData['away_team_id'] == $team->team_id) {
                    $data_match[] = $decodeData;
                } else {
                    continue;
                }
            }

            $competition_short_name_zh = $team->getCompetition()->short_name_zh;
            $match = [];
            if (!empty($data_match)) {
                foreach ($data_match as $match_item) {
                    $decode_home_score = json_decode($match_item['home_scores'], true);
                    $decode_away_score = json_decode($match_item['away_scores'], true);
                    $format_data['match_id'] = $match_item['id'];
                    $format_data['match_time'] = date('Y-m-d', $match_item['match_time']);
                    $format_data['competition_short_name_zh'] = $competition_short_name_zh;
                    $format_data['home_team_name_zh'] = AdminTeam::getInstance()->where('team_id', $match_item['home_team_id'])->get()->name_zh;
                    $format_data['away_team_name_zh'] = AdminTeam::getInstance()->where('team_id', $match_item['away_team_id'])->get()->name_zh;
                    list($format_data['home_scores'], $format_data['away_scores']) = AppFunc::getFinalScore($decode_home_score, $decode_away_score);
                    list($format_data['half_home_scores'], $format_data['half_away_scores']) = AppFunc::getHalfScore($decode_home_score, $decode_away_score);
                    list($format_data['home_corner'], $format_data['away_corner']) = AppFunc::getCorner($decode_home_score, $decode_away_score);//角球

                    $match[] = $format_data;
                    unset($format_data);
                }
            }
            $match_time = array_column($match, 'match_time');
            array_multisort($match_time,SORT_DESC,$match);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $match);

        } else if ($type == 4) {
            //数据

            $decodeDatas = SeasonTeamPlayer::getInstance()->where('season_id', $select_season_id)->get();
            $player_stat = $decodeDatas['players_stats'];
            $team_stat = $decodeDatas['teams_stats'];

            foreach ($team_stat as $team_item) {

                if ($team_item['team']['id'] == $team->team_id) {

                    $team_info = $team_item;

                }
            }
            if (!isset($team_info)) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

            }
            $team_match_num = $team_info['matches'];
            $team_data = [
                'goals' => $team_info['goals'], //进球
                'penalty' => $team_info['penalty'],//点球
                'shots_per_match' => !empty($team_info['shots']) ? number_format($team_info['shots']/$team_match_num,1) : 0,//场均射门
                'shots_on_target_per_match' => !empty($team_info['shots_on_target']) ? number_format($team_info['shots_on_target']/$team_match_num,1) : 0,//场均射正
                'penalty_per_match' => number_format($team_info['penalty']/$team_match_num,1),//场均角球
                'passes_per_match' => number_format($team_info['passes']/$team_match_num,1),//场均传球
                'key_passes_per_match' => number_format($team_info['key_passes']/$team_match_num,1),//场均关键传球
                'passes_accuracy_per_match' => number_format($team_info['passes_accuracy']/$team_match_num,1),//场均成功传球
                'crosses_per_match' => number_format($team_info['crosses']/$team_match_num,1),//场均过人
                'crosses_accuracy_per_match' => number_format($team_info['crosses_accuracy']/$team_match_num,1),//场均成功过人
                'goals_against' => $team_info['goals_against'],//失球
                'fouls' => $team_info['fouls'],//犯规
                'was_fouled' => $team_info['was_fouled'],//被犯规
                'assists' => !empty($team_info['assists']) ? $team_info['assists'] : 0,//助攻
                'red_cards' => $team_info['red_cards'],//红牌
                'yellow_cards' => $team_info['yellow_cards'],//黄牌


            ];
            $team_shooter_table = [];
            if ($shooters = $decodeDatas['shooters']) {
                foreach ($shooters as $shooter) {
                    if ($shooter['team']['id'] == $team_id && $shooter['goals'] > 0) {
                        $team_shooter_table[] = $shooter;
                    }
                }
            }
            $goal_table_format = FrontService::handTeamPlayerTable($team_shooter_table, 'goals');

            $returnData = [];
            if (!empty($player_stat)) {
                foreach ($player_stat as $tk => $player_item) {

                    if ($player_item['team']['id'] == $team->team_id) {
                        $players_team[] = $player_item;
                    } else {
                        continue;
                    }
                }
                if (!empty($players_team)) {

                    foreach ($players_team as $player_team) {
                        //助攻
                        if ($player_team['assists']) {
                            $table_assist[] = $player_team;
                        }
                        //射门
                        if ($player_team['shots']) {
                            $table_shots[] = $player_team;
                        }
                        //射正
                        if ($player_team['shots_on_target']) {
                            $table_shots_on_target[] = $player_team;
                        }
                        //传球
                        if ($player_team['passes']) {
                            $table_passes[] = $player_team;
                        }

                        //成功传球
                        if ($player_team['passes_accuracy']) {
                            $table_passes_accuracy[] = $player_team;
                        }

                        //关键传球
                        if ($player_team['key_passes']) {
                            $table_key_passes[] = $player_team;
                        }

                        //拦截
                        if ($player_team['interceptions']) {
                            $table_interceptions[] = $player_team;
                        }

                        //解围
                        if ($player_team['clearances']) {
                            $table_clearances[] = $player_team;
                        }
                        //扑救
                        if ($player_team['saves']) {
                            $table_saves[] = $player_team;
                        }
                        //黄牌
                        if ($player_team['yellow_cards']) {
                            $table_yellow_cards[] = $player_team;
                        }
                        //红牌
                        if ($player_team['red_cards']) {
                            $table_red_cards[] = $player_team;
                        }
                        //上场时间
                        if ($player_team['minutes_played']) {
                            $table_minutes_played[] = $player_team;
                        }
                        //上场时间
                        if ($player_team['minutes_played']) {
                            $table_minutes_played[] = $player_team;
                        }



                    }
                    if (isset($table_assist)) {
                        $assisting = array_column($table_assist, 'assists');
                        array_multisort($assisting,SORT_DESC,$table_assist);
                        $assisting_table_format = FrontService::handTeamPlayerTable($table_assist, 'assists');
                    } else {
                        $assisting_table_format = [];
                    }


                    if (isset($table_shots)) {
                        $shots = array_column($table_shots, 'shots');
                        array_multisort($shots,SORT_DESC,$table_shots);
                        $shots_table_format = FrontService::handTeamPlayerTable($table_shots, 'shots');
                    } else {
                        $shots_table_format = [];
                    }

                    if (isset($table_shots_on_target)) {
                        $shots_on_target = array_column($table_shots_on_target, 'shots_on_target');
                        array_multisort($shots_on_target,SORT_DESC,$table_shots_on_target);
                        $table_shots_on_target_format = FrontService::handTeamPlayerTable($table_shots_on_target, 'shots_on_target');
                    } else {
                        $table_shots_on_target_format = [];
                    }

                    if (isset($table_passes)) {
                        $passes = array_column($table_passes, 'passes');
                        array_multisort($passes,SORT_DESC,$table_passes);
                        $passes_table_format = FrontService::handTeamPlayerTable($table_passes, 'passes');
                    } else {
                        $passes_table_format = [];
                    }

                    if (isset($table_passes_accuracy)) {
                        $passes_accuracy = array_column($table_passes_accuracy, 'passes_accuracy');
                        array_multisort($passes_accuracy,SORT_DESC,$table_passes_accuracy);
                        $passes_accuracy_table_format = FrontService::handTeamPlayerTable($table_passes_accuracy, 'passes_accuracy');
                    } else {
                        $passes_accuracy_table_format = [];
                    }

                    if (isset($table_key_passes)) {
                        $key_passes = array_column($table_key_passes, 'key_passes');
                        array_multisort($key_passes,SORT_DESC,$table_key_passes);
                        $key_passes_table_format = FrontService::handTeamPlayerTable($table_key_passes, 'key_passes');
                    } else {
                        $key_passes_table_format = [];
                    }

                    if (isset($table_clearances)) {
                        $clearances = array_column($table_clearances, 'clearances');
                        array_multisort($clearances,SORT_DESC,$table_clearances);
                        $clearances_table_format = FrontService::handTeamPlayerTable($table_clearances, 'clearances');
                    } else {
                        $clearances_table_format = [];
                    }

                    if (isset($table_saves)) {
                        $saves = array_column($table_saves, 'saves');
                        array_multisort($saves,SORT_DESC,$table_saves);
                        $saves_table_format = FrontService::handTeamPlayerTable($table_saves, 'saves');
                    } else {
                        $saves_table_format = [];
                    }

                    if (isset($table_yellow_cards)) {
                        $yellow_cards = array_column($table_yellow_cards, 'yellow_cards');
                        array_multisort($yellow_cards,SORT_DESC,$table_yellow_cards);
                        $yellow_cards_table_format = FrontService::handTeamPlayerTable($table_yellow_cards, 'yellow_cards');
                    } else {
                        $yellow_cards_table_format = [];
                    }

                    if (isset($table_red_cards)) {
                        $red_cards = array_column($table_red_cards, 'red_cards');
                        array_multisort($red_cards,SORT_DESC,$table_red_cards);
                        $red_cards_table_format = FrontService::handTeamPlayerTable($table_red_cards, 'red_cards');
                    } else {
                        $red_cards_table_format = [];
                    }

                    if (isset($table_minutes_played)) {
                        $minutes_played = array_column($table_minutes_played, 'minutes_played');
                        array_multisort($minutes_played,SORT_DESC,$table_minutes_played);
                        $minutes_played_table_format = FrontService::handTeamPlayerTable($table_minutes_played, 'minutes_played');
                    } else {
                        $minutes_played_table_format = [];
                    }
                    $most_data = [
                        'most_goals' => isset($goal_table_format[0]) ? $goal_table_format[0] : [], //进球
                        'most_assisting' => isset($assisting_table_format[0]) ? $assisting_table_format[0] : [], //助攻
                        'most_shots' => isset($shots_table_format[0]) ? $shots_table_format[0] : [], //射门
                        'most_shots_on_target' => isset($table_shots_on_target_format[0]) ? $table_shots_on_target_format[0] : [],//射正
                        'most_passes' => isset($passes_table_format[0]) ? $passes_table_format[0] : [],//传球
                        'most_passes_accuracy' => isset($passes_accuracy_table_format[0]) ? $passes_accuracy_table_format[0] : [],//成功传球
                        'most_key_passes' => isset($key_passes_table_format[0]) ? $key_passes_table_format[0] : [],//关键传球
                        'most_clearances' => isset($clearances_table_format[0]) ? $clearances_table_format[0] : [],//解围
                        'most_saves' => isset($saves_table_format[0]) ? $saves_table_format[0] : [],//扑球
                        'most_yellow_cards' => isset($yellow_cards_table_format[0]) ? $yellow_cards_table_format[0] : [],//黄牌
                        'most_red_cards' => isset($red_cards_table_format[0]) ? $red_cards_table_format[0] : [],//红牌
                        'most_minutes_played' => isset($minutes_played_table_format[0]) ? $minutes_played_table_format[0] : [],//出场时间
                    ];
                    $returnData = [
                        'key_player' => $most_data,
                        'team_data' => $team_data,
                        'season' => $season,
                        'current_season_id' => $current_season_id
                    ];

                }

            }

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

        } else if ($type == 5) {//阵容

            $player_stat = [];
            if ($decodeDatas = SeasonTeamPlayer::getInstance()->where('season_id', $select_season_id)->get()) {
                $player_stat = $decodeDatas['players_stats'];

            }
            $players_team = [];

            foreach ($player_stat as $tk => $player_item) {

                if ($player_item['team']['id'] == $team->team_id) {
                    $players_team[] = $player_item;
                } else {
                    continue;
                }
            }


            $manager = $team->getManager();
            $manager_info = [
                'name_zh' => $manager->name_zh,
                'logo' => $this->player_logo . $manager->logo,
                'manager_id' => $manager->manager_id
            ];

            $squad = AdminTeamLineUp::getInstance()->where('team_id', $team->team_id)->get();

            foreach (json_decode($squad->squad, true) as $squad_item) {
                foreach ($players_team as $player_team) {

                    if ($squad_item['player']['id'] == $player_team['player']['id']) {
                        $player['matches'] = $player_team['matches'];
                        $player['goals'] = $player_team['goals'];
                        $player['assists'] = $player_team['assists'];
                        break;
                    }

                }
                $player['name_zh'] = $squad_item['player']['name_zh'];
                $player['player_id'] = $squad_item['player']['id'];
                $player['shirt_number'] = $squad_item['shirt_number'];
                $player_info = AdminPlayer::getInstance()->where('player_id', $squad_item['player']['id'])->get();
                $player['logo'] = !empty($player_info->logo) ? $player_info->logo : '';
                $player['format_market_value'] = AppFunc::changeToWan($player_info['market_value']);
                $player_list[$squad_item['position']][] = $player;

            }

            $player_list['C'] = $manager_info; //教练


            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $player_list);

        }




    }

    /**
     * 球队转入转出记录
     * @return bool
     */
    public function teamChangeClubHistory()
    {
        $team_id = $this->params['team_id'];
        $type = $this->params['type'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        if (!AdminTeam::getInstance()->where('team_id', $team_id)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($type == 1) {
            $model = AdminPlayerChangeClub::getInstance()->where('to_team_id', $team_id)->order('transfer_time', 'DESC')->getLimit($page, $size);
            $change_in_players = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            $format_change_in_players = FrontService::handChangePlayer($change_in_players);
            $return_data = ['list' => $format_change_in_players, 'total' => $total];
        } else if ($type == 2) {
            $model = AdminPlayerChangeClub::getInstance()->where('from_team_id', $team_id)->order('transfer_time', 'DESC')->getLimit($page, $size);
            $change_out_players = $model->all(null);

            $total = $model->lastQueryResult()->getTotalCount();
            $format_change_out_players = FrontService::handChangePlayer($change_out_players);
            $return_data = ['list' => $format_change_out_players, 'total' => $total];

        } else {
            $return_data = [];
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);


    }

    /**
     * 热搜赛事
     * @return bool
     */
    public function hotSearchCompetition()
    {
        $val = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_HOT_SEARCH_COMPETITION)->get();
        if (isset($val['sys_value'])) {
            $competition_ids = json_decode($val['sys_value']);
            $competition = AdminCompetition::getInstance()->where('competition_id', $competition_ids, 'in')->field(['competition_id', 'short_name_zh', 'logo'])->all();

        } else {
            $competition = [];
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competition);

    }
    /**
     * 关键词搜索
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function contentByKeyWord()
    {
        if (!$key_word = $this->params['key_word']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $type = $this->params['type'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $list = [];
        $total = 0;
        if ($type == 1) { //赛事
            $model_competition = AdminCompetition::getInstance()->where("name_zh like '%" . $key_word. "%' or short_name_zh like '%" . $key_word . "%'")->field(['competition_id, name_zh, short_name_zh, logo'])->getLimit($page, $size);
            $list = $model_competition->all(null);
            $total = $model_competition->lastQueryResult()->getTotalCount();
        } else if ($type == 2) { //球队

            $team_competition = AdminTeam::getInstance()->where("name_zh like '%" . $key_word. "%'")->field(['team_id, name_zh, logo'])->getLimit($page, $size);
            $list = $team_competition->all(null);
            $total = $team_competition->lastQueryResult()->getTotalCount();
        } else if ($type == 3) { //球员
            $model_player = AdminPlayer::getInstance()->where("name_zh like '%" . $key_word. "%'")->field(['player_id, name_zh, logo'])->getLimit($page, $size);
            $list = $model_player->all(null);
            $total = $model_player->lastQueryResult()->getTotalCount();
        }

        $return_data = [
            'list' => $list,
            'total' => $total
        ];


        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

    }


    /**
     * @return bool
     */
    public function getContinentCompetition()
    {
        if (!$category_id = $this->params['category_id']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $competitent = AdminCompetition::getInstance()->field(['competition_id', 'short_name_zh', 'logo'])->where('category_id', $category_id)->where('country_id', 0)->all();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competitent);

    }




}
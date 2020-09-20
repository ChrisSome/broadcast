<?php

namespace App\HttpController\Match;


use App\Base\FrontUserController;
use App\lib\FrontService;
use App\Model\AdminCompetition;
use App\Model\AdminMatch;
use App\Model\AdminPlayer;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;

class DataApi extends FrontUserController{

    public function getHotCompetition()
    {
        $competitionId = $this->params['competition_id'];
        $dataHotCompetition = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();
        if ($dataHotCompetition) {
            $cid = $competitionId ? $competitionId : json_decode($dataHotCompetition, true)[0]['competition_id'];
            if ($cid) {
                //概况  最近比赛 统计（参赛球队 球员总数）
                $lastMatch = AdminMatch::getInstance()->where('match_time', time(), '<')->order('match_time', 'desc')->limit(1)->get();
                $formatMatch = FrontService::handMatch([$lastMatch], 1);
                $formatLastMatch = $formatMatch ? $formatMatch[0] : [];
                if ($competition = AdminCompetition::getInstance()->find($cid)) {
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

                    //参赛球队
                    $join_team_count = AdminTeam::getInstance()->where('competition_id', $cid)->count();
                    $current_season = $competition->cur_season_id;
                    $matches = AdminMatch::getInstance()->where('season_id', $current_season)->all();
                    //球员总数
                    $player_count = AdminTeam::getInstance()->where('competition_id', $cid)->sum('total_players');
                    //非本土球员数
                    $player_count_foreign = AdminTeam::getInstance()->where('competition_id', $cid)->sum('foreign_players');
                    //球队总市值
                    //基本信息
                    $basic = [
                        'last_match' => $formatLastMatch,
                        'statistics' => [
                            'title_holder' => isset($title_holder_team_info) ? $title_holder_team_info : [],
                            'most_titles' => isset($most_titles_team_info) ? $most_titles_team_info : [],
                            'join_team_count' => $join_team_count,
                            'player_count' => $player_count,
                            'player_count_no_native' => $player_count_foreign
                        ]
                    ];
                }

            }

        }
    }
}
<?php
namespace App\lib;

use App\Common\AppFunc;
use App\HttpController\Match\FootballApi;
use App\Model\AdminInterestMatches;
use App\Model\AdminPlayer;
use App\Model\AdminPostOperate;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPost;
use App\Model\AdminUserSetting;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;

class  FrontService {
    const TEAM_LOGO = 'https://cdn.sportnanoapi.com/football/team/';
    const PLAYER_LOGO = 'https://cdn.sportnanoapi.com/football/player/';
    const ALPHA_LIVE_LIVING_URL = 'https://cdn.sportnanoapi.com/football/player/';

    /** 登录人id
     * @param $posts
     * @param $uid
     * @return array
     */
    public static function handPosts ($posts, $uid) {
        if (!$posts) {
            return [];
        } else {
            $format_post = [];
            foreach ($posts as $item) {
                if ($user_setting = AdminUserSetting::getInstance()->where('user_id', $item['user_id'])->get()) {
                    $user_private_post = json_decode($user_setting->private, true)['see_my_post'];
                    if ($item['user_id'] != $uid) {
                        if ($user_private_post == 2) { //发帖人关注的人
                            if (!$uid || !AppFunc::isFollow($item['user_id'], $uid)) {
                                continue;
                            }

                        } else if ($user_private_post == 3) { //发帖人的粉丝

                            if (!$uid || !AppFunc::isFollow($uid, $item['user_id'])) {
                                continue;
                            }
                        } else if ($user_private_post == 4) {
                            continue;
                        }
                    }

                }
                $user = $item->userInfo();
                $data['id'] = $item->id;
                $data['user_id'] = $item->user_id;
                $data['title'] = $item->title;
                $data['status'] = $item->status;
                $data['content'] = $item->content;
//                $data['img'] = $item->img;
                $data['imgs'] = $item->imgs ? json_decode($item->imgs, true) : [];
                $data['created_at'] = $item->created_at;
                $data['hit'] = $item->hit;
                $data['fabolus_number'] = $item->fabolus_number;
                $data['respon_number'] = $item->respon_number;
                $data['collect_number'] = $item->collect_number;
                //发帖人信息
                $data['user_info'] = ['id' => $user->id, 'photo' => $user->photo, 'nickname' => $user->nickname, 'level' => $user->level, 'is_offical' => $user->is_offical];
                //是否关注发帖人
                $data['is_follow'] = $uid ? AppFunc::isFollow($uid, $item['user_id']) : false;
                //是否收藏该帖子
                $data['is_collect'] = $uid ? ($item->isCollect($uid, $item->id) ? true : false) : false;
                //帖子最新回复
                $data['lasted_resp'] = $item->getLastResTime($item['id']) ?: $item->created_at;
                //是否赞过
                $data['is_fabolus'] = $uid ? ($item->isFablous($uid, $item->id) ? true : false) : false;
                $data['is_me'] = $uid ? ($item->user_id == $uid ? true : false) : false;
                $data['cat_name'] = !in_array($item->cat_id, [1, 2]) ? $item->postCat()->name : '';
                $data['cat_color'] = !in_array($item->cat_id, [1, 2]) ? $item->postCat()->color : [];
                $data['cat_id'] = $item->cat_id;
                $data['is_refine'] = $item->is_refine;
                $format_post[] = $data;
                unset($data);
            }
            return $format_post;
        }
    }


    /**
     * 处理评论
     * @param $comments
     * @param $uid
     * @return array
     */
    public static function  handComments($comments, $uid)
    {
        if (!$comments) {
            return [];
        } else {
            $datas = [];
            foreach ($comments as $item) {
                $parentComment = $item->getParentContent();
                $data['id'] = $item->id;  //当前评论id
                $data['post_id'] = $item->post_id; //帖子id
                $data['post_title'] = $item->postInfo()->title; //帖子标题
                $data['parent_id'] = $item->parent_id; //父评论ID，可能为0
                $data['parent_content'] = $parentComment ? $parentComment->content : ''; //父评论内容 可能为''
                $data['content'] = $item->content; //当前评论内容
                $data['created_at'] = $item->created_at;
                $data['fabolus_number'] = $item->fabolus_number;
                $data['is_fabolus'] = $uid ? ($item->isFabolus($uid, $item->id) ? true : false) : false;
                $data['user_info'] = $item->uInfo();
                $data['is_follow'] = AppFunc::isFollow($uid, $item->user_id); //是否关注该评论人
                $data['respon_number'] = $item->respon_number;
                $data['top_comment_id'] = $item->top_comment_id;
                $data['t_u_info'] = $item->tuInfo();
                $datas[] = $data;
                unset($data);

            }
        }
        return $datas;
    }

    public static function handInformationComment($information_comment, $uid)
    {
        if (!$information_comment) {
            return [];
        }
        $datas = [];
        foreach ($information_comment as $item) {
            //是否点赞
            $operate = AdminUserOperate::getInstance()->where('item_type', 4)->where('type', 1)->where('item_id', $item->id)->where('user_id', $uid)->where('is_cancel', 0)->get();
            $information = $item->getInformation();
            $data['id'] = $item->id;
            $data['information_id'] = $information->id;
            $data['information_title'] = $information->title;
            $data['content'] = $item->content;
            $data['created_at'] = $item->created_at;
            $data['respon_number'] = $item->respon_number;
            $data['fabolus_number'] = $item->fabolus_number;
            $data['is_fabolus'] = $operate ? true: false;
            $data['user_info'] = $item->getUserInfo();
            $data['t_u_info'] = $item->getTUserInfo();
            $data['parent_id'] = $item->parent_id;
            $data['is_follow'] = AppFunc::isFollow($uid, $item->user_id);
            $datas[] = $data;
            unset($data);
        }
        return $datas;
    }

    /**
     * @param $uid
     * @return mixed
     */
    public static function myPostsCount($uid)
    {
        return AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->count();

    }


    public static function ifFabolus($uid, $cid) {
        return AdminPostOperate::getInstance()->get(['comment_id' => $cid, 'user_id' => $uid, 'action_type' => 1]);

    }


    /**
     * 今天及未来七天的日期
     * @param string $time
     * @param string $format
     * @return array
     */
    static function getWeek($time = '', $format='Ymd')
    {

        $time = $time != '' ? $time : time();
        //组合数据
        $date = [];
        for ($i=1; $i<=7; $i++){
            $date[$i] = date($format ,strtotime( '+' . $i .' days', $time));
        }
        return $date;

    }

    /**
     * 比赛格式化
     * @param $matches
     * @param $uid
     * @param bool $showWhenUserNotInterestCompetition
     * @param bool $isLiving
     * @param bool $is_show  强制显示
     * @return array
     */
    static function handMatch($matches, $uid, $showWhenUserNotInterestCompetition = false, $isLiving = false, $is_show = false)
    {

        if (!$matches) {
            return [];
        } else {
            //用户关注赛事
            $userInterestCompetitiones = [];
            if ($competitiones = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
                $userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
            }
//            $userInterestCompetitiones = $competitiones ? json_decode($competitiones['competition_ids'], true) : [];
            //用户关注比赛
            $userInterestMatchIds = [];

            if ($userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
                $userInterestMatchIds = json_decode($userInterestMatchRes->match_ids, true);
            }
//            $userInterestMatchIds = $userInterestMatchRes ? json_decode($userInterestMatchRes->match_ids, true) : [];


            foreach ($matches as $match) {

                $match_data_info = Cache::get('match_data_info' . $match->match_id);
                $home_team = isset($match->home_team_id) ? $match->homeTeamName() : '';
                $away_team = isset($match->away_team_id) ? $match->awayTeamName() : '';
                $competition = $match->competitionName();
                if (!$home_team || !$away_team || !$competition) {
                    continue;
                }
                $has_living = 0;
                $living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
                if ($isLiving && $living_match = AppFunc::getAlphaLiving(isset($home_team->name_en) ? $home_team->name_en : '', isset($away_team->name_en) ? $away_team->name_en : '')) {
                    $has_living = $living_match['liveStatus'];
                    if ($living_match['liveUrl'] || $living_match['liveUrl2'] || $living_match['liveUrl3']) {
                        $living_url = [
                            'liveUrl' => $living_match['liveUrl'],
                            'liveUrl2' => $living_match['liveUrl2'],
                            'liveUrl3' => $living_match['liveUrl3']
                        ];
                    }

                }

                if (!$is_show) {
                    if ($uid && !$showWhenUserNotInterestCompetition && !in_array($match->competition_id, $userInterestCompetitiones)) {
                        continue;

                    }
                    if (!AppFunc::isInHotCompetition($match->competition_id)) {
                        continue;
                    }
                }



                $is_start = false;
                if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
                    $is_start = false;
                } else if (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
                    $is_start = true;
                } else if (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
                    $is_start = false;
                }

                $item['home_team_name'] = $home_team['name_zh'];
                $item['home_team_logo'] = $home_team['logo'];
                $item['away_team_name'] = $away_team['name_zh'];
                $item['away_team_logo'] = $away_team['logo'];
                $item['group_num'] = json_decode($match->round, true)['group_num']; //第几组
                $item['round_num'] = json_decode($match->round, true)['round_num']; //第几轮
//                $item['competition_type'] = $match->competitionName['type'];
                $item['competition_name'] = $competition['short_name_zh'];
                $item['competition_color'] = $competition['primary_color'];
                $item['match_time'] = date('H:i', $match['match_time']);
                $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
                $item['user_num'] = mt_rand(20, 50);
                $item['match_id'] = $match->match_id;
                $item['is_start'] = $is_start;
                $item['status_id'] = $match->status_id;
                $item['is_interest'] = in_array($match->match_id, $userInterestMatchIds) ? true : false;
                $item['neutral'] = $match->neutral;  //1中立 0否
//                $item['competition_id'] = $match->competition_id;  //1中立 0否
                $item['note'] = $match->note;  //备注   欧青连八分之一决赛
                $item['home_scores'] = $match->home_scores;  //主队比分
                $item['away_scores'] = $match->away_scores;  //主队比分
                $item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '' ;  //直播地址
                $item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
                $item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画
//                $item['home_win'] = $homeWin;  //比赛输赢
                $item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
                $item['matching_info'] = json_decode($match_data_info, true);
                $item['has_living'] = $has_living;
                $item['living_url'] = $living_url;

                $data[] = $item;

                unset($item);


            }
            return isset($data) ? $data : [];
        }
    }


    static function formatMatch($matches, $uid)
    {
        if (!$matches) return [];
        $data = [];
        foreach ($matches as $match) {
            if (!AppFunc::isInHotCompetition($match->competition_id)) {
                continue;
            }


            //用户关注赛事
            $userInterestCompetitiones = [];
            if ($competitiones = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
                $userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
            }
            //用户关注比赛
            $userInterestMatchIds = [];

            if ($userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
                $userInterestMatchIds = json_decode($userInterestMatchRes->match_ids, true);
            }

            if ($uid && !in_array($match->competition_id, $userInterestCompetitiones)) {
                continue;
            }
            $home_team = $match->homeTeamName();
            $away_team = $match->awayTeamName();
            $competition = $match->competitionName();
            if (!$home_team || !$away_team || !$competition) {
                continue;
            }

            $is_start = false;
            if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
                $is_start = false;
            } else if (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
                $is_start = true;
            } else if (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
                $is_start = false;
            }
            $has_living = 0;
            $living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
//            if ($living_match = AppFunc::getAlphaLiving(isset($home_team->name_en) ? $home_team->name_en : '', isset($away_team->name_en) ? $away_team->name_en : '')) {
//                $has_living = $living_match['liveStatus'];
//                if ($living_match['liveUrl'] || $living_match['liveUrl2'] || $living_match['liveUrl3']) {
//                    $living_url = [
//                        'liveUrl' => $living_match['liveUrl'],
//                        'liveUrl2' => $living_match['liveUrl2'],
//                        'liveUrl3' => $living_match['liveUrl3']
//                    ];
//                }
//
//            }
            $match_data_info = Cache::get('match_data_info' . $match->match_id);

            $item['home_team_name'] = $home_team['name_zh'];
            $item['home_team_logo'] = $home_team['logo'];
            $item['away_team_name'] = $away_team['name_zh'];
            $item['away_team_logo'] = $away_team['logo'];
            $item['competition_name'] = $competition['short_name_zh'];
            $item['competition_color'] = $competition['primary_color'];
            $item['match_time'] = date('H:i', $match['match_time']);
            $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
            $item['user_num'] = mt_rand(20, 50);
            $item['match_id'] = $match->match_id;
            $item['is_start'] = $is_start;
            $item['status_id'] = $match->status_id;
            $item['is_interest'] = in_array($match->match_id, $userInterestMatchIds) ? true : false;
            $item['neutral'] = $match->neutral;  //1中立 0否
            $item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
            $item['matching_info'] = json_decode($match_data_info, true);
            $item['has_living'] = $has_living;
            $item['living_url'] = $living_url;
            $item['note'] = $match->note;  //备注   欧青连八分之一决赛
            $item['home_scores'] = $match->home_scores;  //主队比分
            $item['away_scores'] = $match->away_scores;  //主队比分
            $item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '' ;  //直播地址
            $item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
            $item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画



            $data[] = $item;

            unset($item);
        }
        return $data;
    }




    static function formatMatchOne($matches, $uid)
    {
        if (!$matches) return [];
        $data = [];
        foreach ($matches as $match) {
            if (!AppFunc::isInHotCompetition($match->competition_id)) {
                continue;
            }


            //用户关注赛事

            if ($competitiones = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
                $userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
                if ($uid && !in_array($match->competition_id, $userInterestCompetitiones)) {
                    continue;
                }
            }

            //用户关注比赛
            $is_interest = false;

            if ($userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
                $userInterestMatchIds = json_decode($userInterestMatchRes->match_ids, true);
                if (in_array($match->match_id, $userInterestMatchIds)) {
                    $is_interest = true;
                }
            }

            $home_team = $match->homeTeamName();
            $away_team = $match->awayTeamName();
            $competition = $match->competitionName();
            if (!$home_team || !$away_team || !$competition) {
                continue;
            }

            $is_start = false;
            if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
                $is_start = false;
            } else if (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
                $is_start = true;
            } else if (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
                $is_start = false;
            }
            $has_living = 0;
            $living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
//            if ($living_match = AppFunc::getAlphaLiving(isset($home_team->name_en) ? $home_team->name_en : '', isset($away_team->name_en) ? $away_team->name_en : '')) {
//                $has_living = $living_match['liveStatus'];
//                if ($living_match['liveUrl'] || $living_match['liveUrl2'] || $living_match['liveUrl3']) {
//                    $living_url = [
//                        'liveUrl' => $living_match['liveUrl'],
//                        'liveUrl2' => $living_match['liveUrl2'],
//                        'liveUrl3' => $living_match['liveUrl3']
//                    ];
//                }
//
//            }
            $match_data_info = Cache::get('match_data_info' . $match->match_id);

            $item['home_team_name'] = $home_team['name_zh'];
            $item['home_team_logo'] = $home_team['logo'];
            $item['away_team_name'] = $away_team['name_zh'];
            $item['away_team_logo'] = $away_team['logo'];
            $item['competition_name'] = $competition['short_name_zh'];
            $item['competition_color'] = $competition['primary_color'];
            $item['match_time'] = date('H:i', $match['match_time']);
            $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
            $item['user_num'] = mt_rand(20, 50);
            $item['match_id'] = $match->match_id;
            $item['is_start'] = $is_start;
            $item['status_id'] = $match->status_id;
            $item['is_interest'] = $is_interest;
            $item['neutral'] = $match->neutral;  //1中立 0否
            $item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
            $item['matching_info'] = json_decode($match_data_info, true);
            $item['has_living'] = $has_living;
            $item['living_url'] = $living_url;
            $item['note'] = $match->note;  //备注   欧青连八分之一决赛
            $item['home_scores'] = $match->home_scores;  //主队比分
            $item['away_scores'] = $match->away_scores;  //主队比分
            $item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '' ;  //直播地址
            $item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
            $item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画



            $data[] = $item;

            unset($item);
        }
        return $data;
    }
    /**
     * @return array
     */
    public static function getHotCompetitionIds(){
        $competitiones = FootballApi::hotCompetition;
        $competitioneids = [];
        foreach ($competitiones as $competitione) {
            foreach ($competitione as $item) {
                $competitioneids[] = $item['competition_id'];
            }
        }

        return $competitioneids;
    }



    static function formatMatchTwo($matches, $uid)
    {
        if (!$matches) return [];
        $data = [];
        foreach ($matches as $match) {
            if (!AppFunc::isInHotCompetition($match->competition_id)) {
                continue;
            }


            //用户关注赛事



            if ($competitiones = Cache::get('user_interest_competition')) {
                $userInterestCompetitiones = json_decode($competitiones, true);
                if ($uid && !in_array($match->competition_id, $userInterestCompetitiones)) {
                    continue;
                }
            }


            //用户关注比赛
            $is_interest = false;

            if ($userInterestMatchRes = Cache::get('user_interest_match')) {
                $userInterestMatchIds = json_decode($userInterestMatchRes, true);
                if ($uid && in_array($match->match_id, $userInterestMatchIds)) {
                    $is_interest = true;
                }

            }


//            $home_team = $match->homeTeamName();

//            $away_team = $match->awayTeamName();
//            $competition = $match->competitionName();
//            if (!$home_team || !$away_team || !$competition) {
//                continue;
//            }

            $is_start = false;
            if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
                $is_start = false;
            } else if (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
                $is_start = true;
            } else if (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
                $is_start = false;
            }
            $has_living = 0;
            $living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];

            $match_data_info = Cache::get('match_data_info' . $match->match_id);

//            $item['home_team_name'] = $home_team['name_zh'];
            $item['home_team_name'] = '';
//            $item['home_team_logo'] = $home_team['logo'];
            $item['home_team_logo'] = '';
//            $item['away_team_name'] = $away_team['name_zh'];
            $item['away_team_name'] = '';
//            $item['away_team_logo'] = $away_team['logo'];
            $item['away_team_logo'] = '';
            $item['competition_name'] = '';
//            $item['competition_name'] = $competition['short_name_zh'];
//            $item['competition_color'] = $competition['primary_color'];
            $item['competition_color'] = '';
            $item['match_time'] = date('H:i', $match['match_time']);
            $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
            $item['user_num'] = mt_rand(20, 50);
            $item['match_id'] = $match->match_id;
            $item['is_start'] = $is_start;
            $item['status_id'] = $match->status_id;
            $item['is_interest'] = $is_interest;
            $item['neutral'] = $match->neutral;  //1中立 0否
            $item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
            $item['matching_info'] = json_decode($match_data_info, true);
            $item['has_living'] = $has_living;
            $item['living_url'] = $living_url;
            $item['note'] = $match->note;  //备注   欧青连八分之一决赛
            $item['home_scores'] = $match->home_scores;  //主队比分
            $item['away_scores'] = $match->away_scores;  //主队比分
            $item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '' ;  //直播地址
            $item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
            $item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画



            $data[] = $item;

            unset($item);
        }
        return $data;
    }

    /**
     * 处理球员数据 得到各种榜单
     * @param $players
     * @param $column
     * @return array
     */
    public static function handBestPlayerTable($players, $column)
    {
        if (!$players) {
            $table = [];
        }
        $i = 0;
        foreach ($players as $k => $player) {
            if ($player[$column] == 0) {
                continue;
            }
            if ($i == 100) {
                break;
            }
            $data['player_id'] = $player['player']['id'];
            $data['name_zh'] = $player['player']['name_zh'];
            $data['team_logo'] = self::TEAM_LOGO . $player['team']['logo'];
            $data['player_logo'] = self::PLAYER_LOGO . $player['player']['logo'];
            $data['total'] = $player[$column];
            $table[] = $data;
            unset($data);
            $i++;
        }
        return isset($table) ? $table : [];
    }

    /**
     * 球队最佳
     * @param $items
     * @param $column
     * @return array
     */
    public static function handTeamPlayerTable($items, $column)
    {
        if (!$items) {
            return [];
        } else {
            $datas = [];
            foreach ($items as $item) {
                $data['player_id'] = $item['player']['id'];
                $data['name_zh'] = $item['player']['name_zh'];
                $data['team_logo'] = self::TEAM_LOGO . $item['team']['logo'];
                $data['player_logo'] = self::PLAYER_LOGO . $item['player']['logo'];
                $data['total'] = $item[$column];
                $datas[] = $data;
                unset($data);
            }

            return $datas;
        }
    }

    /**
     * 处理球员数据 得到各种榜单
     * @param $data
     * @param $column
     * @return array
     */
    public static function handBestTeamTable($data, $column)
    {
        if (!$data || !isset($data['teams_stats'])) {
            $table = [];
        }
        foreach ($data as $k => $player) {
//            $data['position'] = $k+1;
            $return['team_id'] = $player['team']['id'];
            $return['name_zh'] = $player['team']['name_zh'];
            $return['team_logo'] = self::TEAM_LOGO . $player['team']['logo'];
            if (isset($player[$column])) {
                $return['total'] = $player[$column];

            } else {
                $return['total'] = [];
            }
            $table[] = $return;
            unset($return);
        }

        return isset($table) ? $table : [];
    }




    /**
     * 转会球员
     */
    public static function handChangePlayer($res)
    {
        if (!$res) {
            return [];
        }
        $return = [];
        foreach ($res as $item) {
            if (!$player = AdminPlayer::getInstance()->where('player_id', $item['player_id'])->get()) {
                continue;
            }
            $from_team = $item->fromTeamInfo();
            $to_team = $item->ToTeamInfo();
//            if(!$to_team = AdminTeam::getInstance()->where('team_id', $item['to_team_id'])->get()) {
//                continue;
//            }

            $data['player_id'] = $item['player_id'];
            $data['player_position'] = $player['position'];
            $data['transfer_time'] = date('Y-m-d', $item['transfer_time']);
            $data['transfer_type'] = $item['transfer_type'];
            $data['transfer_fee'] = AppFunc::changeToWan($item['transfer_fee']);
            $data['name_zh'] = $player['name_zh'];
            $data['logo'] = $player['logo'];
            $data['from_team_name_zh'] = isset($from_team['name_zh']) ? $from_team['name_zh'] : '';
            $data['from_team_logo'] = isset($from_team['logo']) ? $from_team['logo'] : '';
            $data['from_team_id'] = isset($from_team['team_id']) ? $from_team['team_id'] : 0;
            $data['to_team_name_zh'] = isset($to_team['name_zh']) ? $to_team['name_zh'] : '';
            $data['to_team_logo'] = isset($to_team['logo']) ? $to_team['logo'] : '';
            $data['to_team_id'] = isset($to_team['team_id']) ? $to_team['team_id'] : 0;
            $return[] = $data;
            unset($data);
        }
        return $return;
    }

    /**
     * @param $informations
     * @return array
     */
    public static  function handInformation($informations, $uid)
    {
        if (!$informations) {
            return [];
        }
        $format = [];
        foreach ($informations as $item)
        {
            if ($item->created_at > date('Y-m-d H:i:s')) continue;
            $user = AdminUser::getInstance()->where('id', $item['user_id'])->get();
            if (!$user) continue;
            $operate = AdminUserOperate::getInstance()->where('user_id', $uid)->where('item_id', $item['id'])->where('item_type', 3)->where('type', 1)->where('is_cancel', 0)->get();
            $competition = $item->getCompetition();
            $data['id'] = $item['id'];
            $data['title'] = $item['title'];
            $data['img'] = $item['img'];
            $data['status'] = $item['status'];
            $data['is_fabolus'] = $uid ? ($operate ? true : false) : false;
            $data['fabolus_number'] = $item['fabolus_number'];
            $data['respon_number'] = $item['respon_number'];
            $data['competition_id'] = $item['competition_id'];
            $data['created_at'] = $item['created_at'];
            $data['is_title'] = ($item['type'] == 1) ? true : false;
            $data['competition_short_name_zh'] = isset($competition->short_name_zh) ? $competition->short_name_zh : '';
            $data['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo, 'level' => $user->level, 'is_offical' => $user->is_offical];

            $format[] = $data;
            unset($data);
        }

        return $format;
    }

    /**
     * @param $users
     * @param $uid
     */
    public static function handUser($users, $uid)
    {
        if (!$users) {
            return [];
        }
        $format_users = [];
        foreach ($users as $user) {
            $data['id'] = $user['id'];
            $data['nickname'] = $user['nickname'];
            $data['is_offical'] = $user['is_offical'];
            $data['level'] = $user['level'];
            $data['photo'] = $user['photo'];
            $data['fans_count'] = count(AppFunc::getUserFans($user->id));
            $data['is_follow'] = AppFunc::isFollow($uid, $user['id']);
            $format_users[] = $data;
            unset($data);
        }

        return $format_users;
    }




}
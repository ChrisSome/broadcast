<?php
namespace App\lib;

use App\Common\AppFunc;
use App\HttpController\Match\FootballApi;
use App\lib\pool\User as UserRedis;
use App\Model\AdminInterestMatches;
use App\Model\AdminPlayer;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminTeam;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserPost;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use mysql_xdevapi\Exception;

class  FrontService {
    const TEAM_LOGO = 'https://cdn.sportnanoapi.com/football/team/';
    const PLAYER_LOGO = 'https://cdn.sportnanoapi.com/football/player/';

    /** 登录人id
     * @param $posts
     * @param $uid
     * @return array
     */
    public static function handPosts ($posts, $uid) {
        if (!$posts) {
            return [];
        } else {
            $datas = [];
            foreach ($posts as $item) {
                $data['id'] = $item->id;
                $data['user_id'] = $item->user_id;
                $data['title'] = $item->title;
                $data['content'] = $item->content;
                $data['img'] = $item->img;
                $data['imgs'] = $item->imgs ? json_decode($item->imgs, true) : [];
                $data['created_at'] = $item->created_at;
                $data['hit'] = $item->hit;
                $data['fabolus_number'] = $item->fabolus_number;
                $data['respon_number'] = $item->respon_number;
                $data['collect_number'] = $item->collect_number;
                //发帖人信息
                $data['userInfo'] = $item->userInfo();
                //是否关注发帖人
                $data['is_follow'] = $uid ? UserRedis::getInstance()->isFollow($uid, $item['user_id']) : false;
                //是否收藏该帖子
                $data['is_collect'] = $uid ? ($item->isCollect($uid, $item->id) ? true : false) : false;
                //帖子最新回复
                $data['lasted_resp'] = $item->getLastResTime($item['id']) ?: $item->created_at;
                //是否赞过
                $data['is_fabolus'] = $uid ? ($item->isFablous($uid, $item->id) ? true : false) : false;
                $data['is_me'] = $uid ? ($item->user_id == $uid ? true : false) : false;
                $data['cat_name'] = $item->postCat()->name;
                $data['cat_id'] = $item->cat_id;

                $datas[] = $data;
                unset($data);

            }
        }
        return $datas;
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
            foreach ($comments as $item) {
                $parentComment = $item->getParentComment();
                $data['id'] = $item->id;  //当前评论id
                $data['post_id'] = $item->post_id; //帖子id
                $data['post_title'] = $item->postInfo()->title; //帖子标题
                $data['post_content'] = $item->postInfo()->content; //帖子内容
                $data['parent_id'] = $item->parent_id; //父评论ID，可能为0
                $data['parent_content'] = $parentComment ? $parentComment->content : ''; //父评论内容 可能为''
                $data['content'] = $item->content; //当前评论内容
                $data['created_at'] = $item->created_at;
                $data['fabolus_number'] = $item->fabolus_number;
                $data['is_fabolus'] = $uid ? ($item->isFabolus($uid, $item->id) ? true : false) : false;
                $data['userInfo'] = $item->uInfo();
                $data['is_follow'] = $uid ? (UserRedis::getInstance()->isFollow($uid, $item->user_id)) : false; //是否关注该评论人
                $data['respon_number'] = $item->respon_number;
                $data['top_comment_id'] = $item->top_comment_id;
                $data['tuInfo'] = $item->tuInfo();
                $datas[] = $data;
                unset($data);

            }
        }
        return $datas ?: [];
    }


    /**
     * 点赞等操作
     * @param $opdata
     */
    public static function execOperate($opdata)
    {


        try{

            DbManager::getInstance()->startTransaction();
            if ($opdata['post_id']) {
                $model = AdminUserPost::getInstance();
                $id = $opdata['post_id'];
            } else {
                $model = AdminPostComment::getInstance();
                $id = $opdata['comment_id'];
            }


            if (in_array($opdata['action_type'], [1, 2, 3])) {

                if (!$opdata['op_id']) {
                    //新增
                    $insertData = [
                        'action_type' => $opdata['action_type'],
                        'user_id' => $opdata['user_id'],
                        'img' => $opdata['img'],
                        'content' => $opdata['content'],
                        'status' => 0,
                        'author_id' => $opdata['author_id'],
                        'post_id' => $opdata['post_id'],
                        'comment_id' => $opdata['comment_id'],
                    ];
                    AdminPostOperate::getInstance()->insert($insertData);
                } else {
                    $res = AdminPostOperate::getInstance()->update([
                        'action_type' => $opdata['action_type']
                    ],
                        [
                            'id' => $opdata['op_id']
                        ]
                    );
//                    $sql = AdminPostOperate::getInstance()->lastQuery()->getLastQuery();

                }


                //修改点赞收藏数
                if ($opdata['action_type'] == 1) {
                    //增加消息数  type=3
                    (new UserRedis())->userMessageAddUnread(3, $opdata['author_id']);
                    $model->update([
                        'fabolus_number' => QueryBuilder::inc(1)
                    ], [
                        'id' => $id
                    ]);
                    $sql = $model->lastQuery()->getLastQuery();
                    Log::getInstance()->info('点赞结果' . $sql);
                } else if($opdata['action_type'] == 2) {
                    $model->update([
                        'collect_number' => QueryBuilder::inc(1)
                    ], [
                        'id' => $opdata['post_id']
                    ]);

                } else {
                    //举报
                    Log::getInstance()->info('点赞或收藏失败');
                }




            } else if(in_array($opdata['action_type'], [4, 5, 6])) {

                //修改点赞收藏数
                if ($opdata['action_type'] == 4) {
                    $model->update([
                        'fabolus_number' => QueryBuilder::dec(1)
                    ], [
                        'id' => $id
                    ]);

                } else if ($opdata['action_type'] == 5) {
                    //收藏数
                    $model->update([
                        'collect_number' => QueryBuilder::dec(1)
                    ], [
                        'id' => $opdata['post_id']
                    ]);
                }
                Log::getInstance()->info('task data is' . json_encode($opdata));
                AdminPostOperate::getInstance()->update([
                    'action_type' => $opdata['action_type']
                ],
                    [
                        'id' => $opdata['op_id']
                    ]
                );


                $sql = AdminPostOperate::getInstance()->lastQuery()->getLastQuery();
            } else {

                throw new Exception('type类型错误');

            }

            DbManager::getInstance()->commit();
            return true;
        } catch (\Exception $e) {
            DbManager::getInstance()->rollback();
            Log::getInstance()->info('操作错误 ' . $e->getMessage());
            return false;

        }
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
     * 格式化比赛
     * @param $matches
     * @param $uid
     * @param $showWhenUserNotInterestCompetition
     * @return array
     */
    static function handMatch($matches, $uid, $showWhenUserNotInterestCompetition = false)
    {

        if (!$matches) {
            return [];
        } else {
            //用户关注赛事
            $competitiones = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get();
            $userInterestCompetitiones = $competitiones ? json_decode($competitiones['competition_ids'], true) : [];
            //用户关注比赛
            $userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get();
            $userInterestMatchIds = $userInterestMatchRes ? json_decode($userInterestMatchRes->match_ids, true) : [];

            foreach ($matches as $match) {
                $is_start = false;
                if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
                    $is_start = false;
                } else if (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
                    $is_start = true;
                } else if (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
                    $is_start = false;
                }
                if ($match->status_id != 8) {
                    $homeWin = 0;
                } else {

                    if ($match->home_scores && $match->away_scores) {
                        $homeTotalScore = json_decode($match->home_scores, true);
                        $awayTotalScore = json_decode($match->away_scores, true);
                        if ($homeTotalScore[0] > $awayTotalScore[0]) {
                            $homeWin = 1;
                        } else if ($homeTotalScore[0] < $awayTotalScore[0]) {
                            $homeWin = 2;
                        } else {
                            $homeWin = 3;
                        }
                    } else {
                        $homeWin = 0;
                    }
                }
                $home_team = $match->homeTeamName();
                $away_team = $match->awayTeamName();
                $competition = $match->competitionName();
                $item['home_team_name'] = $home_team['name_zh'];
                $item['home_team_logo'] = $home_team['logo'];
                $item['away_team_name'] = $away_team['name_zh'];
                $item['away_team_logo'] = $away_team['logo'];
                $item['group_num'] = json_decode($match->round, true)['group_num']; //第几组
                $item['round_num'] = json_decode($match->round, true)['round_num']; //第几轮
                $item['competition_type'] = $match->competitionName['type'];
                $item['competition_name'] = $competition['short_name_zh'];
                $item['competition_color'] = $competition['primary_color'];
                $item['match_time'] = date('H:i', $match['match_time']);
                $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']);
                $item['user_num'] = mt_rand(20, 50);
                $item['match_id'] = $match->match_id;
                $item['is_start'] = $is_start;
                $item['status_id'] = $match->status_id;
                $item['is_interest'] = in_array($match->match_id, $userInterestMatchIds) ? true : false;
                $item['neutral'] = $match->neutral;  //1中立 0否
                $item['competition_id'] = $match->competition_id;  //1中立 0否
                $item['note'] = $match->note;  //备注   欧青连八分之一决赛
                $item['home_scores'] = $match->home_scores;  //主队比分
                $item['away_scores'] = $match->away_scores;  //主队比分
                $item['steamLink'] = $match->steamLink()['mobile_link'] ? $match->steamLink()['mobile_link'] : '' ;  //直播地址
                $item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
                $item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画
                $item['home_win'] = $homeWin;  //比赛输赢

                if ($uid) {
                    if ($showWhenUserNotInterestCompetition) {
                        $data[] = $item;
                    } else {
                        if (!in_array($match->competition_id, $userInterestCompetitiones)) {
                            continue;
                        } else {
                            $data[] = $item;
                        }

                    }
                } else {
                    $data[] = $item;
                }



                unset($item);


            }
            return isset($data) ? $data : [];
        }
    }

    /**
     * @return array
     */
    public static function getHotCompetitionIds(){
        $competitiones = FootballApi::hotCompetition;
        foreach ($competitiones as $competitione) {
            foreach ($competitione as $item) {
                $competitioneids[] = $item['competition_id'];
            }
        }

        return $competitioneids;
    }


    /**
     * 处理球员数据 得到各种榜单
     * @param $players
     * @param $column
     * @return array
     */
    public static function handBestPlayerTable($players, $column)
    {
        if (!$players || !isset($players['players_stats'])) {
            $table = [];
        }
        foreach ($players['players_stats'] as $k => $player) {
            $data['position'] = $k+1;
            $data['player_id'] = $player['player']['id'];
            $data['name_zh'] = end(explode('·', $player['player']['name_zh']));
            $data['team_logo'] = self::TEAM_LOGO . $player['team']['logo'];
            $data['player_logo'] = self::PLAYER_LOGO . $player['player']['logo'];
            $data['total'] = $player[$column];
            $table[] = $data;
            unset($data);
        }
        return isset($table) ? $table : [];
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
            $return['total'] = $player[$column];
            $table[] = $return;
            unset($return);
        }

        return isset($table) ? $table : [];
    }

    public function handInformation($information, $uid)
    {
        if (!$information) {
            return [];
        } else {
            $datas = [];
            foreach ($information as $item) {
                $data['id'] = $item->id;
                $data['user_id'] = $item->user_id;
                $data['title'] = $item->title;
                $data['content'] = $item->content;
                $data['img'] = $item->img;
                $data['created_at'] = $item->created_at;
                $data['hit'] = $item->hit;
                $data['fabolus_number'] = $item->fabolus_number;
                $data['respon_number'] = $item->respon_number;
                $data['collect_number'] = $item->collect_number;
                //发帖人信息
                $data['userInfo'] = $item->userInfo();
                //是否关注发帖人
                $data['is_follow'] = $uid ? UserRedis::getInstance()->isFollow($uid, $item['user_id']) : false;
                //是否收藏该资讯
                $data['is_collect'] = $uid ? ($item->isCollect($uid, $item->id) ? true : false) : false;
                //是否赞过
                $data['is_fabolus'] = $uid ? ($item->isFablous($uid, $item->id) ? true : false) : false;
                $data['is_me'] = $uid ? ($item->user_id == $uid ? true : false) : false;

                $datas[] = $data;
                unset($data);

            }
        }
        return $datas;
    }


    /**
     * 转会球员
     */
    public function handChangePlayer($res)
    {
        if (!$res) {
            return [];
        }
        $return = [];
        foreach ($res as $item) {
            if (!$player = AdminPlayer::getInstance()->where('player_id', $item['player_id'])->get()) {
                return [];
            }
            if(!$from_team = AdminTeam::getInstance()->where('team_id', $item['from_team_id'])->get()) {

            }

            $data['player_id'] = $item['player_id'];
            $data['transfer_time'] = date('Y-m-d', $item['transfer_time']);
            $data['transfer_type'] = $item['transfer_type'];
            $data['transfer_fee'] = AppFunc::changeToWan($item['transfer_fee']);
            $data['transfer_fee'] = AppFunc::changeToWan($item['transfer_fee']);
            $data['name_zh'] = end(explode('·', $player['name_zh']));
            $data['logo'] = $player['logo'];
            $data['from_team_name_zh'] = $item->fromTeamInfo()['name_zh'];
            $data['from_team_id'] = $item->fromTeamInfo()['team_id'];
            $data['to_team_name_zh'] = $item->ToTeamInfo()['name_zh'];
            $data['to_team_id'] = $item->ToTeamInfo()['team_id'];
            $return[] = $data;
            unset($data);
        }
        return $return;
    }

}
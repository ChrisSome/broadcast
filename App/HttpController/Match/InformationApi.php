<?php

namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\Common\Time;
use App\lib\FrontService;
use App\lib\Tool;
use App\Model\AdminCompetition;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserOperate;
use App\Task\SerialPointTask;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Validate\Validate;

/**
 * 资讯中心
 * Class InformationApi
 * @package App\HttpController\Match
 */
class InformationApi extends FrontUserController
{

    protected $trend_detail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s'; //获取比赛趋势详情
    private $url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
    private $user = 'mark9527';
    private $secret = 'dbfe8d40baa7374d54596ea513d8da96';


    /**
     * 标题栏
     */
    public function titleBar()
    {
        $return = [];

        $data_competitions = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();

        if (!$data_competitions || !$data_competitions['sys_value']) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

        } else {
            $data_competitions_info = json_decode($data_competitions['sys_value'], true);

        }



        $head = [
            'competition_id' => 0,
            'short_name_zh' => '头条',
            'type' => 1
        ];
        $return[] = $head;
        $changeClub = [
            'competition_id' => 0,
            'short_name_zh' => '转会',
            'type' => 2
        ];
        $return[] = $changeClub;

        $normal_competition = $data_competitions_info;
        foreach ($normal_competition as $item) {

            if ($competition = AdminCompetition::getInstance()->where('competition_id', $item)->get()) {
                $data['competition_id'] = $competition->competition_id;
                $data['short_name_zh'] = $competition->short_name_zh;
                $data['type'] = 3;
                $return[] = $data;
                unset($data);
            } else {
                continue;
            }

        }


        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }



    /**
     * 获取个分类的内容
     * @return bool
     */
    public function getCategoryInformation()
    {


        //资讯文章
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;
        if ($type == 1) {
            //头条banner
            $setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_TITLE_BANNER)->get();
            $decode = json_decode($setting->sys_value, true);
            $banner_list = [];

            if ($banner = $decode['banner']) {
                $sort = array_column($banner, 'sort');
                array_multisort($banner,SORT_DESC,$sort);
                foreach ($banner as $item_banner) {
                    if (Time::isBetween($item_banner['start_time'], $item_banner['end_time'])) {
                        $banner_list[] = $item_banner;
                    } else {
                        continue;
                    }
                }
            }

            $matches = AdminMatch::getInstance()->where('match_id', $decode['match'], 'in')->all();
            $formatMatches = FrontService::handMatch($matches, 0, true);

            $model = AdminInformation::getInstance()->where('type', 1)->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);

            $list = $model->all(null);
            $format_information = FrontService::handInformation($list, $this->auth['id']);

            $count = $model->lastQueryResult()->getTotalCount();
            $return_data = [
                'banner' => $banner_list,
                'matches' => $formatMatches,
                'information' => ['list' => $format_information, 'count' => $count]
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);
        } else if ($type == 2) {
            //转会
            $model = AdminInformation::getInstance()->where('type', 2)->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);
            $list = $model->all(null);
            $count = $model->lastQueryResult()->getTotalCount();
            $format_information = FrontService::handInformation($list, $this->auth['id']);

            $return_data = [
                'banner' => [],
                'matches' => [],
                'information' => ['list' => $format_information, 'count' => $count]
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);


        } else {
            //普通赛事
            if ($competition_id = $this->params['competition_id']) {

                $matches = AdminMatch::getInstance()->where('competition_id', $competition_id)->where('status_id', FootballApi::STATUS_NO_START)->order('match_time', 'ASC')->limit(2)->all();
                $format_matches = FrontService::handMatch($matches, 0, true);

                $page = $this->params['page'] ?: 1;
                $size = $this->params['size'] ?: 10;
                $model = AdminInformation::getInstance()->where('competition_id', $competition_id)->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);
                $list = $model->all(null);
                $format_information = FrontService::handInformation($list, $this->auth['id']);

                $count = $model->lastQueryResult()->getTotalCount();

                $title_content = [
                    'banner' => [],
                    'matches' => $format_matches,
                    'information' => ['list' => $format_information, 'count' => $count]
                ];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $title_content);

            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }
        }


    }
    /**
     * 赛事比赛及相关资讯文章
     * @return bool
     */
    public function competitionContent()
    {
        if ($competition_id = $this->params['competition_id']) {

            $matches = AdminMatch::getInstance()->where('competition_id', $competition_id)->where('status_id', FootballApi::STATUS_NO_START)->order('match_time', 'ASC')->limit(2)->all();
            $format_matches = FrontService::handMatch($matches, 0, true);

            $page = $this->params['page'] ?: 1;
            $size = $this->params['size'] ?: 10;
            $model = AdminInformation::getInstance()->where('competition_id', $competition_id)->field(['id', 'title', 'fabolus_number', 'respon_number', 'img'])->where('status', AdminInformation::STATUS_NORMAL)->where('created_at', date('Y-m-d H:i:s', '>='))->getLimit($page, $size);
            $list = $model->all(null);
            $informations = [];
            if ($list) {
                foreach ($list as $k => $item) {
                    $data['id'] = $item['id'];
                    $data['title'] = $item['title'];
                    $data['img'] = $item['img'];
                    $data['respon_number'] = $item['respon_number'];
                    $data['fabolus_number'] = $item['fabolus_number'];
                    $data['competition_id'] = $item['competition_id'];
                    $data['competition_short_name_zh'] = $item->getCompetition()['short_name_zh'];
                    $informations[] = $data;
                    unset($data);
                }
            }
            $count = $model->lastQueryResult()->getTotalCount();
            if ($list) {
                foreach ($list as $item) {
                    $data['id'] = $item['id'];
                    $data['title'] = $item['title'];
                }
            }
            $title_content = [
                'matches' => $format_matches,
                'information' => ['list' => $informations ?: [], 'count' => $count]
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $title_content);

        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

    }

    /**
     * 资讯详情
     */
    public function informationInfo()
    {
        $uid = isset($this->auth['id']) ? $this->auth['id'] : 0;
        $information_id = $this->params['information_id'];
        if (!$information = AdminInformation::getInstance()->where('id', $information_id)->get()) {
           return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else if ($information->status == AdminInformation::STATUS_DELETE) {
           return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        } else {

           $information->user_info = $information->user_info();
           $operete_fabolus = AdminUserOperate::getInstance()->where('item_type', 3)
               ->where('type', 1)->where('is_cancel', 0)
               ->where('item_id', $information->id)->where('user_id', $this->auth['id'])->get();
           $information->is_fabolus = $operete_fabolus ? true : false;
           $operate_collect = AdminUserOperate::getInstance()->where('item_type', 3)
               ->where('type', 2)->where('is_cancel', 0)
               ->where('item_id', $information->id)->where('user_id', $this->auth['id'])->get();
           $information->is_collect = $operate_collect ? true : false;
           $order_type = $this->params['order_type'] ?: 0; //0:最热 1:最早 2:最新
           $page = $this->params['page'] ?: 1;
           $size = $this->params['size'] ?: 10;
           switch ($order_type){
               case 0:
                   $model = AdminInformationComment::getInstance()->where('information_id', $information_id)->where('top_comment_id', 0)
                       ->where('parent_id', 0)->order('fabolus_number', 'DESC')
                       ->limit(($page - 1) * $size, $size)
                       ->withTotalCount();

                   break;
               case 1:
                   $model = AdminInformationComment::getInstance()->where('information_id', $information_id)->where('top_comment_id', 0)
                       ->where('parent_id', 0)->order('created_at', 'ASC')
                       ->limit(($page - 1) * $size, $size)
                       ->withTotalCount();
                   break;
               case 2:
                   $model = AdminInformationComment::getInstance()->where('information_id', $information_id)->where('top_comment_id', 0)
                       ->where('parent_id', 0)->order('created_at', 'DESC')
                       ->limit(($page - 1) * $size, $size)
                       ->withTotalCount();
                   break;

           }
           $list = $model->all(null);
           $count = $model->lastQueryResult()->getTotalCount();
           if ($list) {
               foreach ($list as $comment) {
                   $childs = AdminInformationComment::getInstance()->where('top_comment_id', $comment->id)->where('information_id', $information_id)->where('status', AdminInformationComment::STATUS_NORMAL)->order('created_at', 'DESC')->limit(3)->all();
                   $child_count = AdminInformationComment::getInstance()->where('top_comment_id', $comment->id)->where('information_id', $information_id)->where('status', AdminInformationComment::STATUS_NORMAL)->count('id');
                   $operete = AdminUserOperate::getInstance()->where('item_type', 4)->where('item_id', $comment->id)->where('type', 1)->where('user_id', $this->auth['id'])->where('is_cancel', 0)->get();
                   $data['id'] = $comment['id'];
                   $data['content'] = $comment['content'];
                   $data['created_at'] = $comment['created_at'];
                   $data['respon_number'] = $comment['respon_number'];
                   $data['fabolus_number'] = $comment['fabolus_number'];
                   $data['user_info'] = $comment->getUserInfo();
                   $data['child_comment_list'] = FrontService::handInformationComment($childs, $uid);
                   $data['child_comment_count'] = $child_count;
                   $data['is_fabolus'] = $operete ? true : false;
                   $data['is_follow'] = AppFunc::isFollow($this->auth['id'], $comment->user_id);

                   $top_comment[] = $data;
                   unset($data);


               }
           }

           $match_id = $information->match_id;
           $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
           if ($match) {
               $format_match = FrontService::handMatch([$match], 0, true);
               if (isset($format_match[0])) {

                   $return_match = [
                       'match_id' => $format_match[0]['match_id'],
                       'competition_id' => $format_match[0]['competition_id'],
                       'competition_name' => $format_match[0]['competition_name'],
                       'home_team_name' => $format_match[0]['home_team_name'],
                       'away_team_name' => $format_match[0]['away_team_name'],
                       'format_match_time' => $format_match[0]['format_match_time'],
                   ];
               } else {
                   $return_match = [];
               }
           } else {
               $return_match = [];
           }


           $return = [
               'information_info' => $information,
               'comments' => $top_comment,
               'count' => $count,
               'relate_match' => $return_match
           ];
           return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

        }
    }






    /**
     * 发表评论
     * @return bool
     */
    public function informationComment()
    {

        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
        } else if ($this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
            return $this->writeJson(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
        }

        if (Cache::get('user_comment_information_' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
        }


        $validator = new Validate();
        $validator->addColumn('information_id')->required();
        $validator->addColumn('top_comment_id')->required();
        $validator->addColumn('parent_id')->required();
        $validator->addColumn('t_u_id')->required();
        $validator->addColumn('content')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $information_id = $this->params['information_id'];

        //发布
        if ($sensitiveWords = AdminSensitive::getInstance()->where('status', AdminSensitive::STATUS_NORMAL)->field(['word'])->all()) {
            foreach ($sensitiveWords as $sword) {
                if (strstr($this->params['content'], $sword['word'])) {
                    return $this->writeJson(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $sword['word']));
                }
            }
        }
        if (!$information = AdminInformation::getInstance()->find($information_id)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else {
            $data = [
                'information_id' => $this->params['information_id'],
                'content' => base64_encode(addslashes(htmlspecialchars($this->params['content']))),
                'top_comment_id' => $this->params['top_comment_id'],
                'user_id' => $this->auth['id'],
                'parent_id' => $this->params['parent_id'],
                't_u_id' => $this->params['t_u_id'],
            ];

            $rs = AdminInformationComment::getInstance()->insert($data);
            $parent_id = $this->params['parent_id'];
            TaskManager::getInstance()->async(function () use($parent_id, $information_id) {
                if ($parent_id) {
                    AdminInformationComment::getInstance()->update(
                        ['respon_number' => QueryBuilder::inc(1)],
                        ['id' => $parent_id]
                    );
                }
                AdminInformation::getInstance()->update(
                    ['respon_number' => QueryBuilder::inc(1)],
                    ['id' => $information_id]
                );

            });

        }
        $data_task['task_id'] = 4;
        $data_task['user_id'] = $this->auth['id'];
        TaskManager::getInstance()->async(new SerialPointTask($data_task));
        Cache::set('user_comment_information_' . $this->auth['id'], 1, 5);
        $data['id'] = $rs;


        if ($parent_id) {
            $message_data = [
                'status' => AdminMessage::STATUS_UNREAD,
                'type' => 3,
                'item_type' => 4,
                'item_id' => $rs,
                'title' => '资讯回复通知',
                'did_user_id' => $this->auth['id']
            ];
            $message_data['user_id'] = AdminInformationComment::getInstance()->where('id', $this->params['parent_id'])->get()->user_id;
            AdminMessage::getInstance()->insert($message_data);

        }
        if ($comment_info = AdminInformationComment::getInstance()->where('id', $rs)->get()) {
            $format = FrontService::handInformationComment([$comment_info], $this->auth['id']);

            $comment_info_format = !empty($format[0]) ? $format[0] : [];
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comment_info_format);

    }

    /**
     * 二级评论列表
     */
    public function informationChildComment()
    {
        $top_comment_id = $this->params['top_comment_id'];
        $page = isset($this->params['page']) ? $this->params['page'] : 1;
        $size = isset($this->params['size']) ? $this->params['size'] : 1;
        if (!$father_comment = AdminInformationComment::getInstance()->find($top_comment_id)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $format_father = FrontService::handInformationComment([$father_comment], $this->auth['id']);

        $model = AdminInformationComment::getInstance()->where('top_comment_id', $top_comment_id)->getLimit($page, $size);
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();

        $format_information_child_comments = FrontService::handInformationComment($list, $this->auth['id']);

        $return = [
            'fatherComment' => isset($format_father[0]) ? $format_father[0] : [],
            'childComment' => $format_information_child_comments,
            'count' => $total
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }


}
<?php

namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\lib\FrontService;
use App\Model\AdminCompetition;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMatch;
use App\Model\AdminPostComment;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPost;
use App\Task\SerialPointTask;
use App\Utility\Log\Log;
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

        $title_competition_id = $data_competitions_info['title_competition'];

        $title_competition = AdminCompetition::getInstance()->where('competition_id', $title_competition_id, 'in')->get();
        $head = [
            'competition_id' => $title_competition->competition_id,
            'short_name_zh' => '头条',
            'type' => 1
        ];
        $return[] = $head;
        $normal_competition = $data_competitions_info['normal_competition'];
        foreach ($normal_competition as $item) {

            if ($competition = AdminCompetition::getInstance()->where('competition_id', $item)->get()) {
                $data['competition_id'] = $competition->competition_id;
                $data['short_name_Zh'] = $competition->short_name_zh;
                $data['type'] = 0;
                $return[] = $data;
                unset($data);
            } else {
                continue;
            }

        }


        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }


    /**
     * 头条banner及推荐赛事及文章
     * @return bool
     */
    public function titleContent()
    {
        //头条banner
        $banner = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_TITLE_BANNER)->get();
        $sys_value = json_decode($banner['sys_value'], true);
        foreach ($sys_value as $value) {
            if ($information = AdminInformation::getInstance()->find($value['information_id'])) {
                $data['img'] = $value['img'];
                $data['title'] = $information->title;
                $data['information_id'] = $information->id;
                $title_banner[] = $data;
                unset($data);
            } else {
                continue;
            }
        }

        //推荐比赛
        $com = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();
        $title_competition_id = json_decode($com['sys_value'], true)['title_competition'];

        if ($competition = AdminCompetition::getInstance()->where('competition_id', $title_competition_id, 'in')->get()) {
            $matches = AdminMatch::getInstance()->where('competition_id', $title_competition_id, 'in')->where('status_id', FootballApi::STATUS_NO_START)->order('match_time', 'ASC')->limit(2)->all();
            $formatMatches = FrontService::handMatch($matches, 0, true);

        } else {
            $formatMatches = [];
        }

        //资讯文章
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminInformation::getInstance()->where('competition_id', $title_competition_id, 'in')->where('status', AdminInformation::STATUS_NORMAL)->field(['id', 'title', 'img', 'respon_number', 'hit', 'fabolus_number', 'competition_id'])->getLimit($page, $size);
        $list = $model->all(null);
        if ($list) {
            foreach ($list as $k => $item) {
                $data['id'] = $item['id'];
                $data['title'] = $item['title'];
                $data['img'] = $item['img'];
                $data['respon_number'] = $item['respon_number'];
                $data['hit'] = $item['hit'];
                $data['fabolus_number'] = $item['fabolus_number'];
                $data['competition_id'] = $item['competition_id'];
                $data['competition_short_name_zh'] = $item->getCompetition()['short_name_zh'];
                $informations[] = $data;
                unset($data);
            }
        }
        $count = $model->lastQueryResult()->getTotalCount();
        $title_content = [
            'banner' => $title_banner ?: [],
            'matches' => $formatMatches,
            'information' => ['list' => isset($informations) ? $informations : [], 'count' => $count]
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $title_content);

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
            $model = AdminInformation::getInstance()->where('competition_id', $competition_id)->field(['id', 'title', 'fabolus_number', 'respon_number', 'img'])->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);
            $list = $model->all(null);
            if ($list) {
                foreach ($list as $k => $item) {
                    $data['id'] = $item['id'];
                    $data['title'] = $item['title'];
                    $data['img'] = $item['img'];
                    $data['respon_number'] = $item['respon_number'];
                    $data['hit'] = $item['hit'];
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
       $information_id = $this->params['information_id'];
       if (!$information = AdminInformation::getInstance()->field(['title', 'content', 'fabolus_number', 'collect_number'])->find($information_id)) {

           return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

       } else if ($information->status == AdminInformation::STATUS_DELETE) {
           return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

       } else {
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
           if ($list) {
               foreach ($list as $comment) {
                   $data['id'] = $comment['id'];
                   $data['content'] = $comment['content'];
                   $data['respon_number'] = $comment['respon_number'];
                   $data['fabolus_number'] = $comment['fabolus_number'];
                   $data['user_info'] = $comment->getUserInfo();

                   $childs = AdminInformationComment::getInstance()->where('top_comment_id', $comment->id)->where('information_id', $information_id)->where('status', AdminInformationComment::STATUS_NORMAL)->order('created_at', 'DESC')->all();

                   if ($childs) {
                       foreach ($childs as $child) {
                           $child_data['id'] = $child['id'];
                           $child_data['content'] = $child['content'];
                           $child_data['user_info'] = $child->getUserInfo();
                           $child_data['t_u_info'] = $child->getTUserInfo();
                           $child_datas[] = $child_data;
                           unset($child_data);
                       }
                   } else {
                       $child_datas = [];
                   }
                   $data['child_data'] = $child_datas;
                   unset($child_datas);


               }
           }

           $count = $model->lastQueryResult()->getTotalCount();


           TaskManager::getInstance()->async(function () use($information_id) {
               AdminInformation::getInstance()->update(
                   ['hit' => QueryBuilder::inc(1)],
                   ['id' => $information_id]
               );
           });

           $match_id = $information->match_id;
           $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
           $format_match = FrontService::handMatch([$match], 0, true);
           if ($format_match[0]) {
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
           $return = [
               'information_info' => $information,
               'comments' => $data,
               'count' => $count,
               'relate_match' => $return_match
           ];
           return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

       }
    }


    /**
     * 用户点赞 收藏 举报    帖子 评论 资讯评论 用户
     * @return bool
     */
    public function informationOperate()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);

        }

        if (Cache::get('user_operate_information_' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
        }


        $validate = new Validate();
        //1. 点赞   2收藏， 3， 举报，   4， 5， 6 对应取消
        $validate->addColumn('type')->required()->inArray(["1", "2", "3", "4", "5" , "6"]);
        $validate->addColumn('item_type')->required()->inArray([1,2,3,4,5]); //1帖子 2帖子评论 3资讯 4资讯评论 5用户
        $validate->addColumn('item_id')->required();
        $validate->addColumn('author_id')->required();
        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_ERR, $validate->getError()->__toString());
        }

        $item_id = $this->params['item_id'];
        $type = $this->params['type'];
        $item_type = $this->params['item_type'];
        if ($operate = AdminUserOperate::getInstance()->where('item_id', $this->params['item_id'])->where('type', $this->params['type'])->get()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            if (($this->params['type'] <= 3 && ($operate = AdminUserOperate::getInstance()->where('item_id', $this->params['item_id'])->where('type', $this->params['type']+3)->get()))
                || ($this->params['type'] > 3 && ($operate = AdminUserOperate::getInstance()->where('item_id', $this->params['item_id'])->where('type', $this->params['type']-3)->get()))
            ) {
                $operate->type = $this->params['type'];
                $operate->update();
            } else {
                $data = [
                    'user_id' => $this->auth['id'],
                    'item_type' => $this->params['item_type'],
                    'type' => $this->params['type'],
                    'content' => addslashes(htmlspecialchars(trim($this->params['content']))),
                    'report_cat' => $this->params['report_cat'] ?: 0,
                    'item_id' => $this->params['item_id'] ?: 0,
                    'author_id' => $this->params['author_id'] ?: 0
                ];
                AdminUserOperate::getInstance()->insert($data);
            }

        }


        TaskManager::getInstance()->async(function () use($item_type, $type, $item_id) {
            if ($item_type == 1) {
                $model = AdminUserPost::getInstance();
                $status_report = AdminUserPost::NEW_STATUS_REPORTED;
            } else if ($item_type == 2) {
                $model = AdminPostComment::getInstance();
                $status_report = AdminPostComment::STATUS_REPORTED;

            } else if ($item_type == 3) {
                $model = AdminInformation::getInstance();
                $status_report = AdminInformation::STATUS_REPORTED;

            } else if ($item_type == 4) {
                $model = AdminInformationComment::getInstance();
                $status_report = AdminInformationComment::STATUS_REPORTED;

            } else if ($item_type == 5) {
                $model = AdminUser::getInstance();
                $status_report = AdminUser::STATUS_REPORTED;


            } else {
                return false;
            }
            switch ($type) {
                case 1:
                    $model->update(['fabolus_number' => QueryBuilder::inc(1)], ['id' => $item_id]);
                    break;
                case 2:

                    $model->update(['collect_number' => QueryBuilder::inc(1)], ['id' => $item_id]);

                    break;
                case 3:
                    $model->update(['status', $status_report], ['id' => $item_id]);
                    break;
                case 4:
                    $model->update(['fabolus_number' => QueryBuilder::dec(1)], ['id' => $item_id]);
                    break;
                case 5:
                    $model->update(['collect_number' => QueryBuilder::dec(1)], ['id' => $item_id]);
                    break;
                case 6:
                    break;

            }
        });


        Cache::set('user_operate_information_' . $this->auth['id'], 1, 5);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

    }



    /**
     * 发表评论
     * @return bool
     */
    public function informationComment()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
        }
        if (Cache::get('user_comment_information_' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);

        }


        $validator = new Validate();
        $validator->addColumn('information_id')->required();
        $validator->addColumn('top_comment_id')->required();
        $validator->addColumn('parent_id')->required();
        $validator->addColumn('t_u_id')->required();
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
                'content' => addslashes(htmlspecialchars($this->params['content'])),
                'top_comment_id' => $this->params['top_comment_id'],
                'user_id' => $this->params['user_id'],
                'parent_id' => $this->params['parent_id'],
                't_u_id' => $this->params['t_u_id']
            ];
            AdminInformationComment::getInstance()->insert($data);
            $parent_id = $this->params['parent_id'];
            if ($this->params['parent_id']) {
                TaskManager::getInstance()->async(function () use($parent_id) {
                    AdminInformationComment::getInstance()->update(
                        ['respon_number' => QueryBuilder::inc(1)],
                        ['id' => $parent_id]
                    );
                });

            }

        }
        $data['task_id'] = 4;
        $data['user_id'] = $this->auth['id'];
        TaskManager::getInstance()->async(new SerialPointTask($data));
        Cache::set('user_comment_information_' . $this->auth['id'], 1, 5);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

    }

    /**
     * 二级评论列表
     */
    public function informationChildComment()
    {
        $information_id = $this->params['information_id'];
        $top_comment_id = $this->params['top_comment_id'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['sie'] ?: 10;
        $model = AdminInformationComment::getInstance()->where('information_id', $information_id)->where('top_comment_id', $top_comment_id)->getLimit($page, $size);
        $list = $model->all(null);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);

        $count = $model->lastQueryResult()->getTotalCount();
        if ($list) {
            foreach ($list as $item) {
                $data['content'] = $item['content'];
                $data['user_info'] = $item->getUserInfo();
                $data['t_u_info'] = $item->getTUserInfo();
                $comments[] = $data;
                unset($data);
            }
        } else {
            $comments = [];
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['comments' => $comments, 'count' => $count]);

    }
}
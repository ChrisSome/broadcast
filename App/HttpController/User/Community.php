<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\Common\Time;
use App\lib\FrontService;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminNormalProblems;
use App\Model\AdminPostComment;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Model\AdminUserFeedBack;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
use App\Task\SerialPointTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status as Statuses;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use App\Utility\Message\Status;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Validate\Validate;


class Community extends FrontUserController
{
    /**
     * 社区板块
     * @var bool
     */
    protected $isCheckSign = false;
    public $needCheckToken = false;



    //关于回复的评论
    public function getPostChildComments()
    {

        if (!isset($this->params['comment_id']) || empty($this->params['comment_id'])) {
            $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $pId = $this->params['pid'];
        $comment_id = $this->params['comment_id'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        //展示最新评论
        $model = AdminPostComment::getInstance();
        $model = $model->where('parent_id', $comment_id)
            ->where('post_id', $pId)
            ->where('status', 1)
            ->order('created_at', 'DESC')
            ->getAll($page, $size);

        $list = $model->all(null);

        $count = $model->lastQueryResult()->getTotalCount();
        $return['data'] = $list;
        $return['count'] = $count;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
    }

    /**
     * 帖子二级评论列表
     */

    public function getAllChildComments()
    {
        if (!$this->params['comment_id']) {
            $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else {
            $page = $this->params['page'] ?: 1;
            $size = $this->params['size'] ?: 10;
            $cId = $this->params['comment_id'];
            $comment = AdminPostComment::getInstance()->find($cId);
            if (!$comment) {
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

            }

            if ($comment->top_comment_id == 0) {
                $fatherComment = [$comment];
            } else {
                $fatherComment = AdminPostComment::getInstance()->where('id', $comment->top_comment_id)->where('status', AdminUserPost::NEW_STATUS_DELETED, '<>')->all();

            }
            $childCommentModel = AdminPostComment::getInstance()->where('top_comment_id', $fatherComment[0]['id'])->where('status', AdminUserPost::STATUS_DEL, '<>')->getAll($page, $size);
            $childComments = $childCommentModel->all();

            $count = $childCommentModel->lastQueryResult()->getTotalCount();
            $formatFComments = FrontService::handComments($fatherComment, $this->auth['id'] ?: 0);
            $formatCComments = FrontService::handComments($childComments, $this->auth['id'] ?: 0);
            $return = [
                'fatherComment' => $formatFComments ? $formatFComments[0] : [],
                'childComment' => $formatCComments,
                'count' => $count
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

        }

    }


    /**
     * 评论内容详情
     * @return bool
     */
    public function commentInfo()
    {
        $comment_id = $this->params['comment_id'];
        if (!$comment_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $comment = AdminPostComment::getInstance()->find($comment_id);
        if (!$comment) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        } else if ($comment->status == AdminPostComment::STATUS_DEL) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }
        $commentInfo = FrontService::handComments([$comment], $this->auth['id'] ?: 0);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $commentInfo[0]);

    }

    /**
     * 帖子详情
     */
    public function detail()
    {

        $id = $this->request()->getRequestParam('post_id');
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $info = AdminUserPost::getInstance()->get(['id'=>$id]);

        if (!$info) {
            return $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
        }
        if ($this->auth['id'] != $info['user_id']) {
            //增加逻辑，点击率增加
            $info->update([
                'hit' => QueryBuilder::inc(1)
            ],
                ['id'=>$id]);

        }
        $uid = $this->auth['id'] ? $this->auth['id'] : 0;
        $only_author = $this->params['only_author'] ? $this->params['only_author'] : 0;
        $order_type = $this->params['order_type'] ? $this->params['order_type'] : 1;
        $postInfo = FrontService::handPosts([$info], $this->auth['id'] ?: 0)[0];

        //展示最新评论
        $commentModel = AdminPostComment::getInstance();
        $commentModel = $commentModel->where('post_id', $id)
            ->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')
            ->where('top_comment_id', 0);

        if ($only_author) {
            $commentModel = $commentModel->where('user_id', $info->user_id);
        }
        switch ($order_type){
            case 1: //热度  回复数
                $comments = $commentModel->order('fabolus_number', 'DESC')->order('created_at', 'ASC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 2://最新回复
                $comments = $commentModel->order('created_at', 'DESC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 3://最早回复
                $comments = $commentModel->order('created_at', 'ASC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            default:
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);


        }

        $list = $comments->all(null);
        $count = $comments->lastQueryResult()->getTotalCount();
        $format_comments = [];

        if ($list) {
            foreach ($list as $item) {

                $child_comments = AdminPostComment::getInstance()->where('top_comment_id', $item['id'])->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')->order('created_at', 'DESC')->limit(3)->all();
                $child_comments_count = AdminPostComment::getInstance()->where('top_comment_id', $item['id'])->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')->order('created_at', 'DESC')->count('id');
                $data['id'] = $item->id;
                $data['user_info'] = $item->uInfo();
                $data['is_follow'] = AppFunc::isFollow($this->auth['id'], $item['user_id']);
                $data['content'] = $item['content'];
                $data['child_comment_list'] = FrontService::handComments($child_comments, $uid);
                $data['child_comment_count'] = $child_comments_count;
                $data['is_fabolus'] = $uid ? ($item->isFabolus($uid, $item->id) ? true : false) : false;
                $data['fabolus_number'] = $item['fabolus_number'];
                $data['respon_number'] = $item['respon_number'];
                $data['created_at'] = $item['created_at'];
                $format_comments[] = $data;
                unset($data);

            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], [
            'basic' => $postInfo,
            'comment' => $format_comments,
            'count' => $count
        ]);
    }



    /**
     * 社区分类主页
     * @return bool
     */
    public function getContent()
    {

        $validator = new Validate();
        $validator->addColumn('category_id')->required();
        $validator->addColumn('order_type')->required();
        $validator->addColumn('is_refine')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 15;
        $order_type = $this->params['order_type'] ?: 1;
        $is_refine = $this->params['is_refine'] ?: 0;
        $cat_id = !empty($this->params['category_id']) ? $this->params['category_id'] : 1;

        $format_banner = [];
        $title = AdminUserPostsCategory::getInstance()->field(['id', 'name'])->where('status', AdminUserPostsCategory::STATUS_NORMAL)->all();



        if ($cat_id == 2) {//关注的人 帖子列表
            if (!$this->auth['id']) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);
            }
            $followUids = AppFunc::getUserFollowing($this->auth['id']);
            /**
             * var $followUsers AdminUser
             */

            if (!$followUids) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);

            }


            $model = AdminUserPost::getInstance()->where('status', [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in')->where('user_id', $followUids, 'in');
            if ($is_refine == 1) {  //精华
                $model = $model->where('is_refine', AdminUserPost::IS_REFINE);
            }
            switch ($order_type){
                case 1: //热度  回复数

                    $normal_posts = $model->getLimit($page, $size, 'respon_number', 'DESC');
                    break;
                case 2://最新发帖
                    $normal_posts = $model->getLimit($page, $size, 'created_at', 'DESC');

                    break;
                case 3://最早发帖

                    $normal_posts = $model->getLimit($page, $size, 'created_at', 'ASC');

                    break;
                case 4://最新回复

                    $normal_posts = $model->getLimit($page, $size, 'last_respon_time', 'DESC');

                    break;
                default:
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);


            }
            $list = $normal_posts->all(null);
            $total = $normal_posts->lastQueryResult()->getTotalCount();
            $datas = FrontService::handPosts($list, $this->auth['id']);
            $data = ['normal_posts' => $datas, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        }
        if ($banner = AdminUserPostsCategory::getInstance()->field(['id', 'dispose'])->where('id', $cat_id)->get()) {

            foreach ($banner->dispose as $item) {

                if (Time::isBetween($item['start_time'], $item['end_time'])) {

                    $format_banner[] = $item;
                }
            }
        }


        //置顶帖子
        $top_posts = AdminUserPost::getInstance()->where('cat_id', $cat_id)->where('status', [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in')
            ->where('is_top', AdminUserPost::IS_TOP)
            ->field(['id', 'title'])
            ->order('created_at', 'DESC')
            ->all();

        //普通帖子


        $model = AdminUserPost::getInstance()->where('status', [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in');

        if ($is_refine) {
            $model = $model->where('is_refine', AdminUserPost::IS_REFINE);
        }
        if ($cat_id != 1) {
            $model = $model->where('cat_id', $cat_id);
        }
        switch ($order_type){
            case 1: //热度  回复数

                $normal_posts = $model->getLimit($page, $size, 'respon_number', 'DESC');
                break;
            case 2://最新发帖
                $normal_posts = $model->getLimit($page, $size, 'created_at', 'DESC');

                break;
            case 3://最早发帖

                $normal_posts = $model->getLimit($page, $size, 'created_at', 'ASC');

                break;
            case 4://最新回复

                $normal_posts = $model->getLimit($page, $size, 'last_respon_time', 'DESC');

                break;
            default:
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $list = $normal_posts->all(null);

        $format_posts = FrontService::handPosts($list, $this->auth['id'] ?  $this->auth['id'] : 0);
        $count = $model->lastQueryResult()->getTotalCount();
        $data = [
            'title' => $title,
            'banner' => $format_banner,
            'top_posts' => $top_posts,
            'normal_posts' => $format_posts,
            'count' => $count,
        ];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);




    }

    /**
     * 前端模糊搜索  后期要改ES
     * @return bool
     */
    public function getContentByKeyWord()
    {

       if (!isset($this->params['key_word']) || !$key_word = $this->params['key_word']) {
           return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

       }

        $page = !empty($this->params['page']) ? $this->params['page'] : 1;
        $size = !empty($this->params['size']) ? $this->params['size'] : 10;
        //帖子
        $posts = AdminUserPost::getInstance()->where('status', [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in')
            ->where('title', '%' . $key_word . '%', 'like')->getLimit($page, $size);
        $format_posts = FrontService::handPosts($posts->all(null), $this->auth['id']);
        $post_count = $posts->lastQueryResult()->getTotalCount();

        //资讯
        $information = AdminInformation::getInstance()->where('status', AdminInformation::STATUS_NORMAL)->where('title',  '%' . $key_word . '%', 'like')->getLimit($page, $size);
        $format_information = FrontService::handInformation($information->all(null), $this->auth['id']);
        $information_count = $information->lastQueryResult()->getTotalCount();
        //比赛
        $team = AdminTeam::getInstance()->where('name_zh', '%' . $key_word . '%', 'like')->all();


        if ($team) {
            $team_ids = array_column($team, 'team_id');
            if ($team_ids) {
                $team_ids_str = AppFunc::changeArrToStr($team_ids);
                $matches = AdminMatch::getInstance()->where('home_team_id in ' . $team_ids_str . ' or away_team_id in ' . $team_ids_str)->getLimit($page, $size);
                $match_list = $matches->all(null);
//                $sql = AdminMatch::getInstance()->lastQuery()->getLastQuery();

            } else {
                $match_list = [];

            }
            $format_match = FrontService::handMatch($match_list, 0, true);
            $match_count = count($format_match);

        } else {
            $format_match = [];
            $match_count = 0;
        }


        //用户
        $users = AdminUser::getInstance()->where('nickname',  '%' . $key_word . '%', 'like')->where('status', AdminUser::STATUS_NORMAL)->getLimit($page, $size);
        if (!$users) {
            $format_users = [];
            $user_count = 0;
        } else {
            $format_users = FrontService::handUser($users->all(null), $this->auth['id']);
            $user_count = $users->lastQueryResult()->getTotalCount();
        }
        $data = [
            'format_posts' => ['data' => $format_posts, 'count' => $post_count],
            'format_matches' => ['data' => $format_match, 'count' => $match_count],
            'information' =>['data' => $format_information, 'count' => $information_count],
            'users' => ['data' => $format_users, 'count' => $user_count]
        ];
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;//1：全部 2帖子 3资讯 4赛事 5用户
        if ($type == 1) {
            $return_data = $data;
        } else if ($type == 2) {
            $return_data = ['data' => $format_posts, 'count' => $post_count];
        } else if ($type == 3) {
            $return_data = ['data' => $format_information, 'count' => $information_count];

        } else if ($type == 4) {
            $return_data = ['data' => $format_match, 'count' => $match_count];

        } else {
            $return_data = ['data' => $format_users, 'count' => $user_count];

        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);



    }

    /**
     * 热搜榜
     * @return bool
     */
    public function hotSearch()
    {
        if (!$hot_search = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_HOT_SEARCH)->get()) {
            $res = [];
        } else {
            $res = json_decode($hot_search->sys_value, true);
        }

        if (!$default_search = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_HOT_SEARCH_CONTENT)->get()) {
            $content = '';
        } else {
            $content = $default_search->sys_value;
        }
        $return = [
            'hot_search' => $res,
            'default_search_content' => $content
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

    /**
     * 我关注的人的帖子列表
     * @return bool
     */
    public function myFollowUserPosts()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;

        $followUids = AppFunc::getUserFollowing($this->auth['id']);
        /**
         * var $followUsers AdminUser
         */

        if (!$followUids) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);

        }


        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('user_id', $followUids, 'in')->field(['id', 'cat_id', 'user_id',  'title', 'imgs', 'created_at', 'hit', 'fabolus_number', 'content', 'respon_number', 'collect_number'])->getLimit($page, $size);

        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        $datas = FrontService::handPosts($list, $this->auth['id'] ?: 0);
        $data = ['data' => $datas, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 发帖
     * @return bool
     */
    public function postAdd()
    {
        if (Cache::get('user_publish_post_' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
        }
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);

        }


        $request = $this->request();
        $data = $request->getRequestParam('content', 'title', 'cat_id');

        $validate = new Validate();
        $validate->addColumn('cat_id')->required();
        $validate->addColumn('title')->required()->lengthMin(1);
        $validate->addColumn('content')->required()->lengthMin(1);
        if (!$validate->validate($data)) {
            return $this->writeJson(Status::CODE_W_PARAM, $validate->getError()->__toString());
        } else if (AppFunc::have_special_char($this->params['title'])) {
            return $this->writeJson(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);

        }
        $data = [
            'title' => $data['title'],
            'content' => base64_encode(addslashes(htmlspecialchars($data['content']))),
            'cat_id' => $data['cat_id'],
            'user_id' => $this->auth['id'],
        ];

        if (!empty($this->params['imgs'])) {
            $data['imgs'] = $this->params['imgs'];
        }
        Cache::set('user_publish_post_' . $this->auth['id'], 1, 10);
        if (!$this->params['is_save']) {

            if ($this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
                return $this->writeJson(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);

            }
            //发布

            $sensitiveWords = AdminSensitive::getInstance()->where('status', AdminSensitive::STATUS_NORMAL)->field(['word'])->all();

            if ($sensitiveWords) {
                foreach ($sensitiveWords as $sword) {
                    if (!$sword['word']) continue;
                    if (strstr($this->params['content'], $sword['word']) || strstr($this->params['title'], $sword['word'])) {
                        if ($this->params['pid']) {
                            return $this->writeJson(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $sword['word']));

                        }
                        //发送站内信
                        $data['status'] = AdminUserPost::NEW_STATUS_SAVE;
                        $id = AdminUserPost::getInstance()->insert($data);
                        $message = [
                            'title' => '帖子未通过审核',
                            'content' => sprintf('您发布的帖子【%s】包含敏感词【%s】，未发送成功，已移交至草稿箱，请检查修改后再提交', $data['title'], $sword['word']),
                            'status' => 0,
                            'user_id' => $this->auth['id'],
                            'type' => 1,
                            'post_id' => $id,

                        ];
                        AdminMessage::getInstance()->insert($message);
                        return $this->writeJson(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $sword['word']));

                    } else {
                        $data['status'] = AdminUserPost::NEW_STATUS_NORMAL;
                    }
                }
            } else {
                $data['status'] = AdminUserPost::NEW_STATUS_NORMAL;

            }
            if (!empty($this->params['pid'])) {
                AdminUserPost::getInstance()->update($data, ['id' => $this->params['pid']]);
                $pid = $this->params['pid'];
            } else {
                $pid = AdminUserPost::getInstance()->insert($data);
            }
            $data['task_id'] = 2;
            $data['user_id'] = $this->auth['id'];
            \EasySwoole\EasySwoole\Task\TaskManager::getInstance()->async(new SerialPointTask($data));
            $post = AdminUserPost::getInstance()->where('id', $pid)->all();
            $format_post = FrontService::handPosts($post, $this->auth['id'])[0];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $format_post);

        } else {

            //保存
            $data['status'] = AdminUserPost::NEW_STATUS_SAVE;

            if (!$this->params['pid']) {
                if (AdminUserPost::getInstance()->insert($data)) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                } else {
                    return $this->writeJson(Status::CODE_ADD_POST, Status::$msg[Status::CODE_ADD_POST]);

                }
            } else {
                if (AdminUserPost::getInstance()->update($data, ['id'=>$this->params['pid']])) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                } else {
                    return $this->writeJson(Status::CODE_ADD_POST, Status::$msg[Status::CODE_ADD_POST]);

                }
            }
        }


    }




    /**
     * 用户基本资料
     * @return bool
     */
    public function userInfo()
    {
        if (!$uid = $this->params['user_id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
        }

        $user_info = AdminUser::getInstance()->where('id', $uid)->field(['id', 'nickname', 'photo', 'level', 'point', 'is_offical'])->get();
        $user_info['fans_count'] = count(AppFunc::getUserFans($uid));
        $user_info['follow_count'] = count(AppFunc::getUserFollowing($uid));
        if ($this->auth['id']) {
            $user_info['is_me'] = ($this->auth['id'] == $uid) ? true : false;
            $user_info['is_follow'] = AppFunc::isFollow($this->auth['id'], $uid);
        } else {
            $user_info['is_me'] = false;
            $user_info['is_follow'] = false;
        }

        $total = [
            'post_total' => AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::NEW_STATUS_DELETED, '<>')->count('id'),
            'comment_total' => AdminPostComment::getInstance()->where('user_id', $uid)->where('status', AdminPostComment::STATUS_DEL, '<>')->count('id'),
            'information_comment_total' => AdminInformationComment::getInstance()->where('user_id', $uid)->where('status', AdminInformationComment::STATUS_DELETE, '<>')->count(),
        ];

        $user_info['item_total'] = $total;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $user_info);

    }
    /**
     * 1发帖 2回帖 3资讯评论 列表
     * @return bool
     */
    public function userFirstPage()
    {

        $type = isset($this->params['type']) ? $this->params['type'] : 1; //1发帖 2回帖 3资讯评论
        $mid = $this->auth['id'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $uid = !empty($this->params['user_id']) ? $this->params['user_id'] : $this->auth['id'];

        if ($type == 1) { //发帖
            $model = AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::SHOW_IN_FRONT, 'in')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            $format_post = FrontService::handPosts($list, $this->auth['id']);
            $return_data = ['data' => $format_post, 'count' => $total];
        } else if ($type == 2) {//回帖
            $comment_model = AdminPostComment::getInstance()->where('user_id', $uid)->where('status', AdminPostComment::SHOW_IN_FRONT, 'in')->getAll($page, $size);
            $list= $comment_model->all(null);
            $total = $comment_model->lastQueryResult()->getTotalCount();
            $format_comment = FrontService::handComments($list, $this->auth['id']);
            $return_data = ['data' => $format_comment, 'count' => $total];

        } else if ($type == 3) {
            $information_comment_model = AdminInformationComment::getInstance()->where('user_id', $uid)->where('status', AdminInformationComment::SHOW_IN_FRONT, 'in')->getLimit($page, $size);
            $list = $information_comment_model->all(null);
            $total = $information_comment_model->lastQueryResult()->getTotalCount();
            $format_comment = FrontService::handInformationComment($list, $this->auth['id']);
            $return_data = ['data' => $format_comment, 'count' => $total];

        } else {
            $return_data = ['data' => [], 'count' => 0];

        }


        $is_me = ($uid == $mid) ? true : false;
        $is_follow = AppFunc::isFollow($this->auth['id'], $uid);
        $return_info = ['is_me' => $is_me, 'is_follow' => $is_follow, 'list' => $return_data];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_info);

    }


    /**
     * 关注及粉丝列表
     * @return bool
     */
    public function myFollowings()
    {

        if (!$this->params['type']) {
            return $this->writeJson(Status::CODE_W_PARAM, Statuses::$msg[Status::CODE_W_PARAM]);

        }
        $uid = isset($this->params['uid']) ? $this->params['uid'] : $this->auth['id'];
        if (!$uid) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->params['type'] == 1) { //关注列表
            $ids = AppFunc::getUserFollowing($uid);
        } else {
            $ids = AppFunc::getUserFans($uid);
        }
        if (!$ids) {
            $users = [];
        } else {
            $users = AdminUser::getInstance()->where('id', $ids, 'in')->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->all();

        }
        $data = [];
        if ($users) {
            foreach ($users as $user) {
                $item['is_follow'] = AppFunc::isFollow($this->auth['id'], $user['id']);
                $item['is_me'] = ($user['id'] == $this->auth['id']) ? true : false;
                $item['id'] = $user['id'];
                $item['nickname'] = $user['nickname'];
                $item['photo'] = $user['photo'];
                $item['level'] = $user['level'];
                $item['is_offical'] = $user['is_offical'];
                $data[] = $item;
            }
        }
        $count = count($data);
        $returnData['data'] = $data;
        $returnData['count'] = $count;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }


    public function normalProblemList()
    {
        $normal_problems = AdminNormalProblems::getInstance()->all();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $normal_problems);

    }




}
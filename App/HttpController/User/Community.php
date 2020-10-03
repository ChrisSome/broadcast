<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\pool\User as UserRedis;
use App\Model\AdminCategory;
use App\Model\AdminCompetition;
use App\Model\AdminInformation;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminSensitive;
use App\Model\AdminSystemAnnoucement;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
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

    public function pLists()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminUserPost::getInstance();

        $model = $model->where('status', AdminUserPost::STATUS_DEL, '<>')->where('is_top', AdminUserPost::IS_TOP)->field(['id', 'user_id',  'title', 'img', 'created_at', 'hit', 'fabolus_number', 'content', 'respon_number', 'cat_id', 'imgs'])->getLimit($page, $size);
        $list = $model->all(null);
        $result = $model->lastQueryResult();
        $total = $result->getTotalCount();

        $datas = FrontService::handPosts($list, 0);

        $returnData['posts'] = ['data'=>$datas, 'count'=>$total];
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $sql);

        //帖子分类
        $catModel = AdminUserPostsCategory::getInstance();
        $lists = $catModel->where('status', AdminUserPostsCategory::STATUS_NORMAL)->field(['id', 'name', 'img'])->all(null);

        $count = $catModel->count();
        $returnData['cats'] = ['data'=>$lists, 'count'=>$count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);


    }




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
        $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
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
                $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

            }
            if ($comment->top_comment_id == 0) {
                $fatherComment = [$comment];
            } else {
                $fatherComment = AdminPostComment::getInstance()->where('id', $comment->top_comment_id)->where('status', AdminUserPost::STATUS_DEL, '<>')->all();

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
        $id = $this->request()->getRequestParam('id');
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $info = AdminUserPost::getInstance()->get(['id'=>$id]);
//        $this->writeJson(Status::CODE_ERR, '对应帖子不存在', $info);
        if (!$info) {
            $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
            return;
        }

        if ($info['user_id'] != $this->auth['id'] && $info['status'] != AdminUserPost::STATUS_EXAMINE_SUCC) {
            $this->writeJson(Status::CODE_ERR, '未审核通过的帖子不允许查看');
            return;
        }
        if ($info['status'] == AdminUserPost::STATUS_EXAMINE_SUCC && $this->auth['id'] != $info['user_id']) {
            //增加逻辑，点击率增加
            $info->update([
                'hit' => QueryBuilder::inc(1)
            ],
                ['id'=>$id]);

        }

        $postInfo = FrontService::handPosts([$info], $this->auth['id'] ?: 0)[0];

        //展示最新评论
        $commentModel = AdminPostComment::getInstance();
        $commentModel = $commentModel->where('post_id', $id)
            ->where('status', [0, 1], 'in')
            ->where('parent_id', 0)
            ->field(['content', 'created_at', 'fabolus_number', 'user_id', 'id', 'post_id', 'respon_number', 't_u_id'])
            ->getAll($page, $size);

        $list = $commentModel->all(null);
        $count = $commentModel->lastQueryResult()->getTotalCount();

        if ($list) {
            $comments = FrontService::handComments($list, $this->auth['id'] ?: 0);
        } else {
            $comments = [];
        }

        $commentData = ['data' => $comments, 'count' => $count];

        $this->writeJson(Status::CODE_OK, 'ok', [
            'basic' => $postInfo,
            'comment' => $commentData
        ]);
    }


    /**
     * 个人中心
     * @return bool
     */
    public function myCenter() {

        $uid = $this->params['uid'] ?: $this->auth['id'];
        $type = $this->params['type'] ?: 1;  //1:帖子 2：评论 3：收藏
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $isMe = false;

        if ($uid == $this->auth['id']) {
            $isMe = true;
        }
        $user = AdminUser::getInstance()->field(['id', 'nickname', 'photo'])->get(['id'=>$uid]);
        if ($user) {
            //发帖数
            if ($type == 1) {
                $pmodel = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('user_id', $uid)->getLimit($page, $size);
                $plist = $pmodel->all(null);
                $pDatas = FrontService::handPosts($plist, $this->auth['id'] ?: 0);
            }

            //评论
            if ($type == 2) {
                $cmodel = AdminPostComment::getInstance()->where('status', AdminPostComment::STATUS_DEL, '<>')->where('user_id', $uid)->getAll($page, $size);
                $clist = $cmodel->all(null);

                $cDatas = FrontService::handComments($clist, $this->auth['id'] ?: 0);
            }

            //收藏
            if ($type == 3) {
                $omodel = AdminPostOperate::getInstance()->where('action_type',2)
                    ->where('user_id', $uid)
                    ->where('comment_id', 0)
                    ->getLimit($page, $size);
                $olist = $omodel->all(null);

                if (!$olist) {
                    $opDatas = [];
                } else {
                    $opids = array_column($olist, 'post_id');
                    $opmodel = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('id', $opids, 'in')->all();
                    $opDatas = FrontService::handPosts($opmodel, $this->auth['id'] ?: 0);
                }

            }

            //我的粉丝数
            $fansCount = UserRedis::getInstance()->myFansCount($user->id);

            //我的关注数
            $followCount = UserRedis::getInstance()->myFollowings($user->id);

            $pCount = $user->postCount();  //发帖数
            $cCount = $user->commentCount(); //评论数
            $coCount = $user->collectCount(); //收藏数
            $data['id']         = $user->id;
            $data['nickname']   = $user->nickname;
            $data['photo']      = $user->photo;
            $data['fansCount']  = $fansCount;
            $data['followingCount'] = $followCount;

            $data['postCount'] =  $pCount ? count($pCount) : 0;  //帖子数量
            $data['postList']  =  $type == 1 ? $pDatas : [];


            $data['commentCount'] = $cCount ? count($cCount) : 0;   //此人回复数量
            $data['commentsList'] = $type == 2 ? $cDatas : [];

            $data['collectCount']   = $coCount ? count($coCount) : 0;
            $data['collectList']    = $type == 3 ? $opDatas : [];


            $data['isMe'] = $isMe;
            if (!$isMe) {
                $data['is_follow'] = UserRedis::getInstance()->isFollow($this->auth['id'], $this->params['uid']);
            } else {
                $data['is_follow'] = false;
            }
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);

        }

    }

    /**
     * 分类帖子列表
     * @return bool
     */
    public function postCat()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $validator = new Validate();
        $validator->addColumn('cat_id')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('cat_id', $this->params['cat_id']);
        if (!empty($this->params['type'])) {
            $model = $model->where('is_refine', AdminUserPost::IS_REFINE);
        }
        $model = $model->getLimit($page, $size);
        $list = $model->all(null);

        $count = $model->lastQueryResult()->getTotalCount();
        $formatData = FrontService::handPosts($list, $this->auth['id'] ?: 0);

        $cat = AdminUserPostsCategory::getInstance()->get(['id' => $this->params['cat_id'], 'status' => AdminUserPostsCategory::STATUS_NORMAL]);
        if ($cat) {
            $category['imgs'] = isset($cat->content_imgs) ? json_decode($cat->content_imgs, true) : [];  //帖子内部置顶图片
            $category['id'] = $cat->id;
            $category['name'] = $cat->name;
            $annoucement_ids = $cat->annoucement_id ? json_decode($cat->annoucement_id, true) : 0;
            if ($annoucement_ids) {
                $category['annoucement'] = AdminSystemAnnoucement::getInstance()->where('id', $annoucement_ids, 'in')->orderBy('created_at', 'DESC')->field(['id', 'title', 'content', 'url'])->all(null);
            } else {
                $category['annoucement'] = [];
            }
        } else {
            $category = [];
        }
        $posts['postList']  = $formatData;
        $posts['pcount']    = $count;
        $posts['catInfo']   = $category;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $posts);

    }



    //置顶帖子
    public function messAndRefinePosts()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminUserPost::getInstance();

        $model = $model->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('is_refine', AdminUserPost::IS_REFINE)->getLimit($page, $size);

        $list = $model->all(null);
//        $sql = $model````;

        $total = $model->lastQueryResult()->getTotalCount();
        $datas = FrontService::handPosts($list, $this->auth['id'] ?: 0);
        $returnData['refine_posts'] = ['data' => $datas, 'count' => $total];
        //公告
        $mess = AdminSystemAnnoucement::getInstance()->where('status', AdminSystemAnnoucement::STATUS_NORMAL)->field(['title', 'content', 'created_at'])->order('created_at', 'DESC')->limit(1)->get();

        if ($mess) {
            $returnData['mess'] = $mess;

        } else {
            $returnData['mess'] = [];

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }



    /**
     * 社区默认主页
     * @return bool
     */
    public function getContent()
    {


        $validator = new Validate();
        $validator->addColumn('category_id')->required();
        $validator->addColumn('order_type')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $title = AdminUserPostsCategory::getInstance()->where('status', AdminUserPostsCategory::STATUS_NORMAL)->all();
        $cat_id = $this->params['category_id'] ? $this->params['category_id'] : 1;
        if ($category = AdminUserPostsCategory::getInstance()->where('id', $cat_id)->get()) {
            $banner = json_decode($category->content_imgs, true);
        } else {
            $banner = [];
        }


        //置顶帖子
        $top_posts = AdminUserPost::getInstance()->where('cat_id', $cat_id)->where('status', AdminUserPost::NEW_STATUS_NORMAL)
            ->where('is_top', AdminUserPost::IS_TOP)
            ->field(['id', 'title'])
            ->order('created_at', 'DESC')
            ->all();

        //普通帖子
        $order_type = $this->params['order_type'] ?: 1;
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 15;
        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_NORMAL)->where('cat_id', $cat_id)
            ->field(['id', 'title', 'imgs', 'content', 'fabolus_number', 'collect_number', 'respon_number', 'created_at']);

        switch ($order_type){
            case 1: //热度  回复数
                $normal_posts = $model->order('respon_number', 'DESC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 2://最新发帖
                $normal_posts = $model->order('created_at', 'DESC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 3://最早发帖
                $normal_posts = $model->order('created_at', 'ASC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 4://最新回复
                $normal_posts = $model->order('last_respon_time', 'DESC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            default:
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);


        }
        $list = $normal_posts->all(null);
        $count = $model->lastQueryResult()->getTotalCount();
        $data = [
            'title' => $title,
            'banner' => $banner,
            'top_posts' => $top_posts,
            'normal_posts' => $list,
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

        if ($sensitiveWords = AdminSensitive::getInstance()->where('status', AdminSensitive::STATUS_NORMAL)->field(['word'])->all()) {
            foreach ($sensitiveWords as $sword) {
                if (strstr($key_word, $sword['word'])) {
                    return $this->writeJson(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $sword['word']));
                }
            }
        }

        $posts = AdminUserPost::getInstance()->where('status', [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED], 'in')
            ->where('title', '%' . $key_word . '%', 'like')->all();
        $format_posts = FrontService::handPosts($posts, $this->auth['id']);

        $information = AdminInformation::getInstance()->where('status', AdminInformation::STATUS_NORMAL)->where('title', '%' . $key_word . '%', 'like')->all();

        //比赛
        $matches = AdminMatch::getInstance()->func(function ($builder) use($key_word){
            $builder->raw('select m.match_id, m.competition_id, m.home_team_id, m.away_team_id, m.match_time from `admin_match_list` m left join `admin_team_list` t on (m.home_team_id=t.team_id or m.away_team_id=t.team_id) where m.status_id in (1,2,3,4,5,7) and t.name_zh like ?',['%' . $key_word . '%']);
            return true;
        });

        if ($matches) {
            foreach ($matches as $match) {

                $data['match_id'] = $match['match_id'];
                $data['competition_short_name_zh'] = AdminCompetition::getInstance()->where('competition_id', $match['competition_id'])->get()['short_name_zh'];
                $data['home_team_name_zh'] = AdminTeam::getInstance()->where('team_id', $match['home_team_id'])->get()['name_zh'];
                $data['away_team_name_zh'] = AdminTeam::getInstance()->where('team_id', $match['away_team_id'])->get()['name_zh'];
                $data['match_time'] = date('Y-m-d H:i:s', $data['match_time']);
                $format_matches[] = $data;
                unset($data);
            }
        }


        //用户
        $users = AdminUser::getInstance()->where('nickname',  '%' . $key_word . '%', 'like')->where('status', AdminUser::STATUS_NORMAL)->field(['id', 'nickname'])->all();
        foreach ($users as $k=>$user) {
            $users[$k]['fans_count'] = UserRedis::getInstance()->myFansCount($user->id);
        }
        $data = [
            'format_posts' => $format_posts,
            'format_matches' => $format_matches,
            'information' => $information,
            'users' => $users
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);



    }

    /**
     * 我关注的人的帖子列表
     * @return bool
     */
    public function myFollowUserPosts()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;

        $followUids = UserRedis::getInstance()->getUserFollowings(sprintf(UserRedis::USER_FOLLOWS, $this->auth['id']));
        /**
         * var $followUsers AdminUser
         */

        if (!$followUids) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);

        }


        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('user_id', $followUids, 'in')->field(['id', 'cat_id', 'user_id',  'title', 'img', 'imgs', 'created_at', 'hit', 'fabolus_number', 'content', 'respon_number', 'collect_number'])->getLimit($page, $size);

        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
//        $sql = $model->lastQuery()->getLastQuery();
        $datas = FrontService::handPosts($list, $this->auth['id'] ?: 0);
        $data = ['data' => $datas, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }


}
<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\pool\User as UserRedis;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminSystemAnnoucement;
use App\Model\AdminUser;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
use App\Task\LoginTask;
use App\Utility\Log\Log;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use App\Utility\Message\Status;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Validate\Validate;
use function FastRoute\TestFixtures\all_options_cached;

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
        if ($info['status'] == 1 && $this->auth['id'] != $info['user_id']) {
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
            Log::getInstance()->info('auth id' . $this->auth['id']);
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
        if (isset($this->params['type']) && isset($this->params['is_refine']) && $this->params['is_refine'] == 1) {
            $model = $model->where('is_fine', AdminUserPost::IS_REFINE);
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
     * 关注及粉丝列表
     * @return bool
     */
    public function myFollowings()
    {

        if (!$this->params['type']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $userRedis = new UserRedis();
        $uid = isset($this->params['uid']) ? $this->params['uid'] : $this->auth['id'];
        if ($this->params['type'] == 1) {
            $key = sprintf(UserRedis::USER_FOLLOWS, $uid);

        } else {
            $key = sprintf(UserRedis::USER_FANS, $uid);

        }
        $myFollowings = $userRedis->getUserFollowings($key);
        if (!$myFollowings) {
            $users = [];
        } else {
            $users = AdminUser::getInstance()->where('id', $myFollowings, 'in')->field(['id', 'nickname', 'photo'])->all();

        }
//        $sql = AdminUser::getInstance()->lastQuery()->getLastQuery();

        if ($users) {
            foreach ($users as $user) {
                $item['is_follow'] = UserRedis::getInstance()->isFollow($this->auth['id'], $user['id']);
                $item['is_me'] = ($user['id'] == $this->auth['id']) ? true : false;
                $item['id'] = $user['id'];
                $item['nickname'] = $user['nickname'];
                $item['photo'] = $user['photo'];
                $data[] = $item;
            }
        } else {
            $data = [];
        }
        $count = $userRedis->scard($key);
        $returnData['data'] = $data;
        $returnData['count'] = $count;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }



}
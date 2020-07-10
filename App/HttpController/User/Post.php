<?php


namespace App\HttpController\User;


use App\HttpController\Admin\User\Comment;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminUserPost;
use App\Base\FrontUserController;
use App\Task\CommentTask;
use App\Task\PostTask;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Validate\Validate;
use EasySwoole\Mysqli\QueryBuilder;
use App\Utility\Log\Log;


class Post extends FrontUserController
{
    public $needCheckToken = true;
    protected $isCheckSign = true;

    public function index()
    {
        $this->render('front.post.index');
    }

    /**
     * 获取已审核通过的帖子
     * @return bool
     */
    public function getList()
    {
        $params = $this->params;
        $model = AdminUserPost::getInstance();
        $count = $model->where('status', AdminUserPost::STATUS_DEL, '<>')->count();
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $size = isset($params['size']) ? intval($params['size']) : 10;
        $data = $model->where('status', AdminUserPost::STATUS_DEL, '<>')->orderBy('is_top', 'desc')->orderBy('created_at', 'desc')->findAll($page, $size);


        return $this->writeJson(Status::CODE_OK, 'ok', [
            'count' => $count,
            'data' => $data
        ]);

    }

    private function bindQuery($params)
    {
        $query = AdminUserPost::getInstance();
        $query->where('user_id', $this->auth['id']);
        if (isset($params['status']) && (!empty($params['status']) || preg_match('/^0$/', $params['status']))) {
            $query->where('status', $params['status']);
        }
        if (isset($params['start_time']) && (!empty($params['start_time']))) {
            $query->where('created_at', $params['start_time'], '>=');
        }
        if (isset($params['stop_time']) && (!empty($params['stop_time']))) {
            $query->where('created_at', $params['stop_time'], '<=');
        }

        return $query;
    }

    /**
     * 获取自己发布的帖子
     * @return bool
     */
    public function getMineList()
    {
        $params = $this->params;
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $size = isset($params['size']) ? intval($params['size']) : 10;
        $query = $this->bindQuery($params);
        $data = $query->findAll($page, $size);
        $count = $this->bindQuery($params)->count();


        return $this->writeJson(Status::CODE_OK, 'ok', [
            'count' => $count,
            'data' => $data
        ]);


    }

    /**
     * 发布帖子
     * @return bool|void
     */
    public function addPost()
    {
        //1.验证参数
        {
            $data = $this->fieldInfo();
            if (!$data) {
                return;
            }
            $data['content'] = addslashes(htmlspecialchars($data['content'])); //防sql注入以及xss等
            $data['user_id'] = $this->auth['id'];
            if ($id = AdminUserPost::getInstance()->insert($data)) {
                return $this->writeJson(Status::CODE_OK, 'OK', ['id' => $id]);
            } else {
                var_dump(AdminUserPost::getInstance()->getLastError());
                return $this->writeJson(Status::CODE_ERR, '保存失败');
            }
        }
    }

    /**
     * 帖子详情
     */
    public function detail()
    {
        $id = $this->request()->getRequestParam('id');
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $info = AdminUserPost::getInstance()->field(['title', 'content', 'created_at', 'hit', 'fabolus_number', 'status'])->get(['id'=>$id]);
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
        //展示最新评论
        $commentModel = AdminPostComment::getInstance();
        $commentModel = $commentModel ->where('post_id', $id)
            ->where('status', 1)
            ->where('parent_id', 0)
            ->field(['content', 'created_at', 'fabolus_number'])
            ->getAll($page, $size);

        $list = $commentModel->all();
        $count = $commentModel->lastQueryResult()->getTotalCount();
//
        $commentData = ['data' => $list, 'count' => $count];
        $isCollect = AdminPostOperate::getInstance()
            ->where('post_id', $id)
            ->where('user_id', $this->auth['id'])
            ->where('action_type', 2)
            ->where('status', 1)
            ->get();
        $info['is_collect'] = intval($isCollect);

        $this->writeJson(Status::CODE_OK, 'ok', [
            'basic' => $info,
            'comment' => $commentData
        ]);
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
            ->getAll($page, $size);

        $list = $model->all(null);
        $count = $model->lastQueryResult()->getTotalCount();
        $return['data'] = $list;
        $return['count'] = $count;
        $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
    }

    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo($isEdit = false)
    {
        $request = $this->request();
        $data = $request->getRequestParam('content', 'title', 'cat_id');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('content')->required();
        $validate->addColumn('title')->required();
        $validate->addColumn('cat_id')->required()->lengthMin(1024);

        if (!$validate->validate($data)) {
            var_dump($validate->getError()->__toString());
            $this->writeJson(\App\Utility\Message\Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }

    /**
     * 用户评论
     * @return bool
     */
    public function comment()
    {
        $id = $this->request()->getRequestParam('post_id');
        $validate = new Validate();
        $validate->addColumn('content')->required()->lengthMin(64, '最少20个汉字');

        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, $validate->getError()->__toString());
        }

        $info = AdminUserPost::getInstance()->find($id);
        if (!$info) {
            return $this->writeJson(Status::CODE_WRONG_RES, '对应帖子不存在');
        }

        if ($info['status'] != AdminUserPost::STATUS_EXAMINE_SUCC) {
            return $this->writeJson(Status::CODE_WRONG_RES, '帖子未通过审核，暂时未审核无法进行评论');
        }

        $iParentId = isset($this->params['comment_id']) ? $this->params['comment_id'] : 0;
        $old = [];
        if ($iParentId) {
            $old = AdminPostComment::getInstance()->where('id', $iParentId)->where('post_id', $id)->get();
            if (!$old || $old['status'] != AdminPostComment::STATUS_NORMAL) {
                return $this->writeJson(Status::CODE_WRONG_RES, '原始评论参数不正确');
            }
        }
        $taskData = [
            'user_id' => $this->auth['id'],
            'post_id' => $this->params['post_id'],
            'post_title' => $info['title'],
            'content' => htmlspecialchars(addslashes($this->params['content'])),
            'parent_id' => $iParentId,
            'path' => !$old ? 0 : ($old->path ? $old->path . ',' . $iParentId : $iParentId),
            't_u_id' => $old ? $old->user_id : 0,
            'type' => 'insert',
        ];
        TaskManager::getInstance()->async(new CommentTask($taskData));


        return $this->writeJson(Status::CODE_OK, '操作成功');
    }


    /**
     * 帖子相关操作
     * @return bool
     */
    public function operate()
    {
        $id = $this->request()->getRequestParam('id');
        $info = AdminUserPost::getInstance()->find($id);
//        return $this->writeJson(Status::CODE_ERR, '对应帖子不存在', $info);

        if (!$info) {
            return $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
        }

        if ($info['status'] != AdminUserPost::STATUS_EXAMINE_SUCC) {
            return $this->writeJson(Status::CODE_ERR, '帖子未通过审核，无法进行操作');
        }

        $validate = new Validate();
        //1. 点赞   2收藏， 3， 举报，   4， 5， 6 对应取消
        $validate->addColumn('type')->required();
        $validate->addColumn('type')->inArray(["1", "2", "3", "4", "5" , "6"]);

        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_ERR, $validate->getError()->__toString());
        }

        $hasAlready = AdminPostOperate::getInstance()
            ->where('post_id', $id)
            ->where('user_id', $this->auth['id'])
            ->where('action_type', $this->params['type'] > 3  ?  $this->params['type'] - 3 : $this->params['type'])
            ->get();
        if (in_array($this->params['type'], [1, 2, 3]) && $hasAlready && $hasAlready['status'] == 1) {
            return $this->writeJson(Status::CODE_ERR, '您已完成操作');
        }
        if ($this->params['type'] > 3) {
            if (!$hasAlready) {
                return $this->writeJson(Status::CODE_ERR, '操作失误');
            }
            if ($hasAlready['status'] == 0) {
                return $this->writeJson(Status::CODE_ERR, '参数不正确');
            }
        }


        $actionTypes = [
            1 => 'fabolus',
            2 => 'collect',
            3 => 'warning',
            4 => 'unbind_fabolus',
            5 => 'unbind_collect',
            6 => 'unbind_warning',
        ];
        $taskData = [
            'type' => $actionTypes[$this->params['type']],
            'user_id' => $this->auth['id'],
            'mobile' => $this->auth['mobile'],
            'photo' => $this->auth['photo'],
            'post_id' => $this->params['id'],
            'post_title' => $info['title'],
            'basic' => $hasAlready ? 1 : 0
        ];
        TaskManager::getInstance()->async(new PostTask($taskData));

        return $this->writeJson(Status::CODE_OK, '操作成功');
    }

    /**
     * 用户评论列表  该用户所有评论
     */
    public function commentList()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminPostComment::getInstance();
        $count = $model->where('user_id', $this->auth['id'])->where('status', AdminPostComment::STATUS_DEL, '<>')->count();

        $model->where('user_id', $this->auth['id'])->where('status', AdminUserPost::STATUS_DEL, '<>');
        if ($this->params['pid']) {
            $model->where('post_id', $this->params['uid']);
        }
        $data = $model->orderBy('is_top', 'desc')->orderBy('created_at', 'desc')->findAll($page, $size);
        $sql = $model->lastQuery()->getLastQuery();

        /**
         * 开发者脑子里装的是💩吗，orm relation写这么烂,必须手动注册关联关系
         */
        foreach ($data as $v) {
            $v->uInfo();
            if (!$v->parent_id){
                $v->tuInfo();

            }
        }

        $returnData = ['count'=>$count, 'data'=>$data];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

    /**
     * 用户删除帖子
     */
    public function del()
    {
        $params = $this->params;
        $validator = new Validate();
        $validator->addColumn('post_id')->required();
        $uid = $this->auth['id'];
        if (!$validator->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $post_id = $this->params['post_id'];
        $post = AdminUserPost::getInstance()->where('user_id', $uid)->where('id', $post_id)->get();

        $post->status = AdminUserPost::STATUS_DEL;
        if ($post->update()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

    }

    /**
     * 帖子二级评论列表
     */
    public function childComment() {
        $validator = new Validate();
        $validator->addColumn('comment_id')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, $validator->getError()->__toString());
        }

        $comment_id = $this->params['comment_id'];
        $comment = AdminPostComment::getInstance()->where('id', $comment_id)->all();
        if (!$comment) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $commentModel = AdminPostComment::getInstance();
        $commentModel = $commentModel->where('parent_id', $comment_id)
            ->where('status', 1)
            ->field(['content', 'created_at', 'fabolus_number', 'user_id', 't_u_id'])
            ->getAll($page, $size);

        $list = $commentModel->all();
        if ($list) {
            foreach ($list as $item) {
                $item->uInfo();
                $item->tuInfo();
            }
            $count = $commentModel->lastQueryResult()->getTotalCount();
//
        } else {
            $count = 0;
            $list = [];
        }
        $commentData = ['data' => $list, 'count' => $count];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $commentData);


    }

    //用户转载
    public function rePrint()
    {
        $valitor = new Validate();
        $valitor->addColumn('post_id')->required();
        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $post_id = $this->params['post_id'];
        $postInfo = AdminUserPost::getInstance()->findByPk($post_id);
        if (!$postInfo) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
//        return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], $postInfo);

        $data['user_id']        = $this->auth['id'];
        $data['title']          = $postInfo->title;
        $data['status']         = AdminUserPost::STATUS_NORMAL;
        $data['is_top']         = AdminUserPost::IS_UNTOP;
        $data['content']        = $postInfo['content'];
        $data['hit']            = 0;
        $data['fabolus_number'] = 0;
        $data['is_reprint']     = AdminUserPost::IS_REPRINT;
        $data['cat_id']         = $postInfo['cat_id'];
        $data['is_refine']      = AdminUserPost::IS_UNREFINE;
        if (AdminUserPost::getInstance()->insert($data)) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }


    }



}
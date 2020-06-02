<?php


namespace App\HttpController\User;


use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminUserPost;
use App\Base\FrontUserController;
use App\Task\CommentTask;
use App\Task\PostTask;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Validate\Validate;

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
        $count = $model->where('status', 1)->count();
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $size = isset($params['size']) ? intval($params['size']) : 10;
        $data = $model->where('status', 1)->orderBy('is_top', 'desc')->orderBy('created_at', 'desc')->findAll($page, $size);


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
            $data['nickname'] = $this->auth['nickname'];
            //$data['mobile'] = $this->auth['mobile'];
            $data['head_photo'] = $this->auth['photo'];
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
        $info = AdminUserPost::getInstance()->find($id);
        if (!$info) {
            $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
            return;
        }

        if ($info['user_id'] != $this->auth['id'] && $info['status'] != 1) {
            $this->writeJson(Status::CODE_ERR, '未审核通过的帖子不允许查看');
            return;
        }
        if ($info['status'] == 1 && $this->auth['id'] != $info['user_id']) {
            //增加逻辑，点击率增加
        }
        //展示最新评论
        $comments = AdminPostComment::getInstance()->where('post_id', $id)
            ->where('status', 1)
            ->where('parent_id', 0)
            ->orderBy('created_at', 'desc')
            ->findAll(1, 10);

        $isCollect = AdminPostOperate::getInstance()
            ->where('post_id', $id)
            ->where('user_id', $this->auth['id'])
            ->where('action_type', 2)
            ->where('status', 1)
            ->get();
        $info['is_collect'] = intval($isCollect);
        //写异步task记录已读
        /*$auth = $this->auth;
        TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($info, $auth){
            $messageTask = new MessageTask([
                'message_id' => $info['id'],
                'message_title' => $info['title'],
                'user_id' => $auth['id'],
                'mobile' => $auth['mobile'],
            ]);
            $messageTask->execData();
        });*/
        $this->writeJson(Status::CODE_OK, 'ok', [
            'basic' => $info,
            'comment' => $comments
        ]);
    }

    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo($isEdit = false)
    {
        $request = $this->request();
        $data = $request->getRequestParam('content', 'title');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('content')->required();
        $validate->addColumn('title')->required();

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
        $id = $this->request()->getRequestParam('id');
        $validate = new Validate();
        $validate->addColumn('content')->required();

        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_ERR, $validate->getError()->__toString());
        }

        $info = AdminUserPost::getInstance()->find($id);
        if (!$info) {
            return $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
        }

        if ($info['status'] != 1) {
            return $this->writeJson(Status::CODE_ERR, '帖子未通过审核，暂时未审核无法进行评论');
        }

        $iParentId = isset($this->params['comment_id']) ? $this->params['comment_id'] : 0;
        $old = [];
        if ($iParentId) {
            $old = AdminPostComment::getInstance()->where('id', $iParentId)->where('post_id', $id)->get();
            if (!$old || $old['status'] != 1) {
                return $this->writeJson(Status::CODE_ERR, '原始评论参数不正确');
            }
        }
        $taskData = [
            'user_id' => $this->auth['id'],
            'mobile' => $this->auth['mobile'],
            'nickname' => $this->auth['nickname'],
            'photo' => $this->auth['photo'],
            'post_id' => $this->params['id'],
            'post_title' => $info['title'],
            'content' => htmlspecialchars(addslashes($this->params['content'])),
            'parent_id' => $iParentId,
            'parent_user_id' => $old ? $old['user_id'] : 0,
            'parent_user_nickname' => $old ? $old['nickname'] : '',
            'path' => !$old ? 0 : ($old->path ? $old->path . ',' . $iParentId : $iParentId)
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
        if (!$info) {
            return $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
        }

        if ($info['status'] != 1) {
            return $this->writeJson(Status::CODE_ERR, '帖子未通过审核，无法进行操作');
        }

        $validate = new Validate();
        //1. 点赞   2收藏， 3， 举报，   4， 5， 6 对应取消
        $validate->addColumn('type')->required();
        $validate->addColumn('type')->inArray([1, 2, 3, 4, 5 , 6]);

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

}
<?php


namespace App\HttpController\User;


use App\lib\FrontService;
use App\lib\pool\User as UserRedis;
use App\lib\pool\PhoneCodeService;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminSensitive;
use App\Model\AdminSystemAnnoucement;
use App\Model\AdminUserPost;
use App\Base\FrontUserController;
use App\Model\AdminUserPostsCategory;
use App\Task\SerialPointTask;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Validate\Validate;
use EasySwoole\Mysqli\QueryBuilder;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;

//use App\lib\Pool\User;

class Post extends FrontUserController
{
    public $needCheckToken = true;
    protected $isCheckSign = false;

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

        $request = $this->request();
        $data = $request->getRequestParam('content', 'title', 'cat_id');

        $validate = new \EasySwoole\Validate\Validate();
//        $validate->addColumn('content')->required()->lengthMin(2048);
//        $validate->addColumn('title')->required()->lengthMin(32);
        $validate->addColumn('cat_id')->required();
//        $validate->addColumn('is_save')->required();

        if (!$validate->validate($data)) {
            return $this->writeJson(Status::CODE_W_PARAM, $validate->getError()->__toString());
        }


        $data = [
            'title' => $data['title'],
            'content' => addslashes(htmlspecialchars($data['content'])),
            'cat_id' => $data['cat_id'],
            'user_id' => $this->auth['id'],
        ];

        if (!empty($this->params['imgs'])) {
            $data['imgs'] = $this->params['imgs'];
        }
        if (!$this->params['is_save']) {
            //发布
            $sensitiveWords = AdminSensitive::getInstance()->where('status', AdminSensitive::STATUS_NORMAL)->field(['word'])->all();

            if ($sensitiveWords) {
                foreach ($sensitiveWords as $sword) {
                    if (strstr($data['content'], $sword['word'])) {
                        //发送站内信
                        $data['status'] = 3;
                        $id = AdminUserPost::getInstance()->insert($data);
                        $message = [
                            'title' => '帖子未通过审核',
                            'content' => sprintf('您发布的帖子【%s】包含敏感词，未发送成功，已移交至草稿箱，请检查修改后再提交', $data['title']),
                            'status' => 0,
                            'user_id' => $this->auth['id'],
                            'type' => 1,
                            'post_id' => $id,

                        ];
                        AdminMessage::getInstance()->insert($message);
                        return $this->writeJson(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $sword['word']));

                    } else {
                        $data['status'] = AdminUserPost::STATUS_EXAMINE_SUCC;
                    }
                }
            } else {
                $data['status'] = AdminUserPost::STATUS_EXAMINE_SUCC;

            }
            if ($this->params['pid']) {
                $res = AdminUserPost::getInstance()->update($data, ['id' => $this->params['pid']]);
            } else {
                $res = AdminUserPost::getInstance()->insert($data);
            }
            $data['task_id'] = 2;
            $data['user_id'] = $this->auth['id'];
            TaskManager::getInstance()->async(new SerialPointTask($data));
            if ($res) {
                return $this->writeJson(Status::CODE_OK, '发布成功，请等待管理员审核');
            } else {
                return $this->writeJson(Status::CODE_ADD_POST, '发布失败，请联系管理员');

            }
        } else {

            //保存
            $data['status'] = AdminUserPost::STATUS_SAVE;

            if (!$this->params['pid']) {
                if (AdminUserPost::getInstance()->insert($data)) {
                    return $this->writeJson(Status::CODE_OK, '保存成功');

                } else {
                    return $this->writeJson(Status::CODE_ADD_POST, '保存失败');

                }
            } else {
                if (AdminUserPost::getInstance()->update($data, ['id'=>$this->params['pid']])) {
                    return $this->writeJson(Status::CODE_OK, '保存成功');

                } else {
                    return $this->writeJson(Status::CODE_ADD_POST, '保存失败');

                }
            }
        }


    }







    /**
     * 用户评论
     * @return bool
     */
    public function comment()
    {

        if (Cache::get('userCom' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);

        }
        $id = $this->request()->getRequestParam('post_id');
        $validate = new Validate();
        $validate->addColumn('content')->required();

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

        $commentId = isset($this->params['comment_id']) ? $this->params['comment_id'] : 0;
        $top_comment_id = isset($this->params['top_comment_id']) ? $this->params['top_comment_id'] : 0; //一级回复的id
        if ($commentId) {
            $parentComment = AdminPostComment::getInstance()->get(['id'=>$commentId]);
            if (!$parentComment || $parentComment['status'] != AdminPostComment::STATUS_NORMAL) {
                return $this->writeJson(Status::CODE_WRONG_RES, '原始评论参数不正确');
            }
        }

        $taskData = [
            'user_id' => $this->auth['id'],
            'post_id' => $this->params['post_id'],
            'post_title' => $info['title'],
            'content' => htmlspecialchars(addslashes($this->params['content'])),
            'parent_id' => $commentId,
            't_u_id' => $commentId ? $parentComment->user_id : $info->user_id,
            'top_comment_id' => $top_comment_id,
        ];
        //插入一条评论
        $model = AdminPostComment::getInstance()->create($taskData);
        $insertId = $model->save();
        if ($top_comment_id) {

            AdminPostComment::create()->update([
                'respon_number' => QueryBuilder::inc(1)
            ],[
                'id' => $top_comment_id
            ]);

        }



        AdminUserPost::create()->update([
            'respon_number' => QueryBuilder::inc(1)
        ],[
            'id' => $this->params['post_id']
        ]);

        //给被回复人增加一条未读消息 type=4

        (new UserRedis())->userMessageAddUnread(4, $taskData['t_u_id']);
        $data['task_id'] = 3;
        $data['user_id'] = $this->auth['id'];
        TaskManager::getInstance()->async(new SerialPointTask($data));

        Cache::set('userCom' . $this->auth['id'], 1, 2);
        //格式化
        $comment = AdminPostComment::getInstance()->find($insertId);
        $info = FrontService::handComments([$comment], $this->auth['id']);
        return $this->writeJson(Status::CODE_OK, '操作成功', $info);
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
            if (!isset($v->parent_id)){
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
//        $uid = $this->auth['id'];
        $uid = 4;
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM], $params);

        }
        $post_id = $this->params['post_id'];
        $post = AdminUserPost::getInstance()->get(['user_id' => $uid, 'id' => $post_id]);

        if ($post && $post_id->status == AdminUserPost::STATUS_DEL) {
            return $this->writeJson(Status::CODE_WRONG_RES, '该帖子不存在或已被删除');
        } else if (!$post) {
            return $this->writeJson(Status::CODE_WRONG_RES, '该帖子不存在或已被删除');

        }
        $post->status = AdminUserPost::STATUS_DEL;
        if ($post->update()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

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

    /**
     * 草稿箱列表
     * @return bool
     */
    public function drafts()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 20;

        $model = AdminUserPost::getInstance()->where('status', [AdminUserPost::STATUS_SAVE, AdminUserPost::STATUS_EXAMINE_FAIL], 'in')->where('user_id', $this->auth['id'])->getLimit($page, $size);

        $list = $model->all(null);
        $count = $model->lastQueryResult()->getTotalCount();
        $returnData = ['data' => $list, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);
    }





}
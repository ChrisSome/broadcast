<?php


namespace App\HttpController\Admin\User;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\lib\PasswordTool;
use App\Model\AdminUser;
use App\Model\AdminUser as UserModel;
use App\Model\AdminPostComment;
use App\Task\CommentTask;
use App\Task\PostTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;

class Comment extends AdminController
{
    private $rule_rule = 'user.post';
    private $rule_rule_view = 'user.post.list';
    private $rule_rule_add = 'user.post.add';
    private $rule_rule_set = 'user.post.set';
    private $rule_rule_del = 'user.post.del';


    public function index()
    {
        if (!$this->hasRuleForGet($this->rule_rule_view)) return;

        $id = $this->request()->getRequestParam('id');
        $this->render('admin.comment.index', [
            'id' => $id
        ]);
    }

    public function getAll()
    {
        if (!$this->hasRuleForPost($this->rule_rule_view)) return;
        $id = $this->request()->getRequestParam('id');
        $params = $this->request()->getRequestParam();
        $page = isset($params['page']) ? $params['page'] : 1;
        $offset = isset($params['offset']) ? $params['offset'] : 10;
        $where = [];
        $query = AdminPostComment::getInstance()->where('post_id', $id);
        if (isset($params['nickname']) && !empty($params['nickname'])) {
            $query->where('nickname', $params['nickname']);
        }

        if (isset($params['time']) && !empty($params['time'])) {
            $times = explode(' - ', $params['time']);
            $query->where('created_at', $times, 'between');
        }
        $data = $query->findAll($page, $offset, $where);
        $count = $query->count();
        $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count, 'params' => $params];

        $this->dataJson($data);
    }



    public function del()
    {
        if (!$this->hasRuleForPost($this->rule_rule_del)) return;

        $request = $this->request();
        $id = $request->getRequestParam('id');
        $status =  $request->getRequestParam('status');
        $info = AdminPostComment::getInstance()->find($id);
        $old = $info['status'];
        $bool = AdminPostComment::getInstance()->setValue('status', $status, ['id' => $id]);
        if ($bool) {
            if ($status != $old && !empty($info['parent_id'])) {
                TaskManager::getInstance()->async(new CommentTask([
                    'type' => 'update',
                    'path' => $info['path'],
                    'status' => $status
                ]));
            }
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("post--del:" . $id . "没有删除失败");
        }
    }
}
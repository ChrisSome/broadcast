<?php


namespace App\HttpController\Admin\User;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\lib\PasswordTool;
use App\Model\AdminUser;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserPost;
use App\Utility\Log\Log;
use App\Utility\Message\Status;

class Post extends AdminController
{
    private $rule_rule = 'user.post';
    private $rule_rule_view = 'user.post.list';
    private $rule_rule_add = 'user.post.add';
    private $rule_rule_set = 'user.post.set';
    private $rule_rule_del = 'user.post.del';
    private $rule_rule_accusation = 'user.post.accusation';
    private $rule_rule_examine = 'user.post.examine';


    public function index()
    {
        if (!$this->hasRuleForGet($this->rule_rule_view)) return;

        $this->render('admin.post.index');
    }

    public function getAll()
    {
        if (!$this->hasRuleForPost($this->rule_rule_view)) return;
        $params = $this->request()->getRequestParam();
        $page = isset($params['page']) ? $params['page'] : 1;
        $offset = isset($params['offset']) ? $params['offset'] : 10;
        $where = [];
        $query = AdminUserPost::getInstance();
        if (isset($params['title']) && !empty($params['title'])) {
            $query->where('title', $params['title'], 'like');

        }
        if (isset($params['nickname']) && !empty($params['nickname'])) {
            $query->where('nickname', $params['nickname'], 'like');
        }
        if (isset($params['time']) && !empty($params['time'])) {
            $times = explode(' - ', $params['time']);
            $query->where('created_at', $times, 'between');
        }
        if (isset($params['where']) && !empty($params['where'])) {
            if ($params['where'] == 'accusation') {
                $query->where(AppFunc::getWhereArray('status', AdminUserPost::$statusAccusation));
            } else if ($params['where'] = 'examine') {
                $query->where(AppFunc::getWhereArray('status', AdminUserPost::$statusExamine));

            }
        } else {
            $query->where('status', AdminUserPost::STATUS_EXAMINE_SUCC);
        }
        $data = $query->findAll($page, $offset, $where);
        $count = $query->count();
//        $sql = $query->lastQuery()->tQuery();

        $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count, 'params' => $params, 'sql'=>$sql];
        $this->dataJson($data);
    }


    /**
     * 帖子举报
     */
    public function postAccusation()
    {

        if (!$this->hasRuleForGet($this->rule_rule_view)) return;

        $this->render('admin.post.accusation');
    }

    /**
     * 帖子审核
     */
    public function postExamine()
    {

        if (!$this->hasRuleForGet($this->rule_rule_examine)) return;

        $this->render('admin.post.examine');
    }
    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo($isEdit = false)
    {
        $request = $this->request();
        $data = $request->getRequestParam('status', 'remark');
        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('status')->required();

        if (!$validate->validate($data)) {
            var_dump($validate->getError()->__toString());
            $this->writeJson(Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }

    

    // 修改数据的页面
    public function edit()
    {
        if (!$this->hasRuleForGet($this->rule_rule_set)) return;

        $id = $this->request()->getRequestParam('id');

        if (!$id) {
            $this->show404();
            return;
        }

        $info = AdminUserPost::getInstance()->find($id);
        if (!$info) {
            $this->show404();
            return;
        }
        $this->render('admin.post.edit', ['info' => $info]);
    }

    // 修改数据
    public function editData()
    {
        if (!$this->hasRuleForPost($this->rule_rule_set)) return;
        $request = $this->request();

        $data = $request->getRequestParam();
        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('status')->required();
        $validate->addColumn('title')->required();
        $validate->addColumn('content')->required();

        //hash加密
        $id = $this->request()->getRequestParam('id');
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (AdminUserPost::getInstance()->saveIdData($id, $data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '保存失败');
            Log::getInstance()->error("post--editData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "编辑保存失败");
        }
    }

    // 单字段修改
    public function set()
    {
        if (!$this->hasRuleForPost($this->rule_rule_set)) return;

        $request = $this->request();
        $data = $request->getRequestParam('id', 'key', 'value');
        $validate = new \EasySwoole\Validate\Validate();

        $validate->addColumn('key')->required()->func(function ($params, $key) {
            return $params instanceof \EasySwoole\Spl\SplArray
                && 'key' == $key && in_array($params[$key], ['status', 'node']);
        }, '请勿乱操作');

        $validate->addColumn('id')->required();
        $validate->addColumn('value')->required();

        if (!$validate->validate($data)) {
            $this->writeJson(Status::CODE_ERR, '请勿乱操作');
            return;
        }

        $bool = UserModel::getInstance()->where('id', $data['id'], '=')
            ->setValue($data['key'], $data['value']);

        if ($bool) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '设置失败');
            Log::getInstance()->error("post--set:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "没有设置成功");
        }
    }

    public function del()
    {
        if (!$this->hasRuleForPost($this->rule_rule_del)) return;

        $request = $this->request();
        $id = $request->getRequestParam('id');
        $bool = AdminUserPost::getInstance()->setValue('status', AdminUserPost::STATUS_DEL, ['id' => $id]);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("post--del:" . $id . "删除失败");
        }
    }

    public function confirm()
    {
        if (!$this->hasRuleForPost($this->rule_rule_del)) return;

        $request = $this->request();
        $id = $request->getRequestParam('id');
        $bool = AdminUserPost::getInstance()->setValue('status', AdminUserPost::STATUS_EXAMINE_SUCC, ['id' => $id]);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '通过失败');
            Log::getInstance()->error("post--confirm:" . $id . "举报处理失败");
        }
    }

    /**
     * 置顶
     */
    public function setTop()
    {
        if (!$this->hasRuleForPost($this->rule_rule_del)) return;

        $request = $this->request();
        $id = $request->getRequestParam('id');
        $bool = AdminUserPost::getInstance()->setValue('is_top', AdminUserPost::IS_TOP, ['id'=>$id]);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '置顶成功');
            Log::getInstance()->error("post--setTop:" . $id . "置顶失败");
        }
    }
}
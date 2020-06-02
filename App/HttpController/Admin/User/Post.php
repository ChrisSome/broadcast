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

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }
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
        $bool = AdminUserPost::getInstance()->where('id', $id, '=')->setValue('status', 3);;
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("post--del:" . $id . "没有删除失败");
        }
    }
}
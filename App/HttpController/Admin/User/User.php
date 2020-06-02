<?php


namespace App\HttpController\Admin\User;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\lib\PasswordTool;
use App\Model\AdminUser as UserModel;
use App\Utility\Log\Log;
use App\Utility\Message\Status;

class User extends AdminController
{
    private $rule_rule = 'user.user';
    private $rule_rule_view = 'user.user.list';
    private $rule_rule_add = 'user.user.add';
    private $rule_rule_set = 'user.user.set';
    private $rule_rule_del = 'user.user.del';


    public function index()
    {
        if (!$this->hasRuleForGet($this->rule_rule_view)) return;

        $this->render('admin.user.index');
    }

    public function getAll()
    {
        if (!$this->hasRuleForPost($this->rule_rule_view)) return;
        $params = $this->request()->getRequestParam();
        $page = isset($params['page']) ? $params['page'] : 1;
        $offset = isset($params['offset']) ? $params['offset'] : 10;
        $where = [];
        $query = UserModel::getInstance();
        if (isset($params['mobile']) && !empty($params['mobile'])) {
            $query->where('mobile', $params['mobile']);
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
        $data = $request->getRequestParam('nickname', 'status', 'mobile');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('nickname')->required();
        $validate->addColumn('mobile')->required();
        $validate->addColumn('status')->required();

        if (!$validate->validate($data)) {
            var_dump($validate->getError()->__toString());
            $this->writeJson(Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }

    public function add()
    {
        if (!$this->hasRuleForGet($this->rule_rule_add)) return;

        $this->render('admin.user.userAdd');
    }


    public function checkIsRepeat()
    {
        $request = $this->request();
        $data = $request->getRequestParam('type', 'value', 'id');
        $query = UserModel::getInstance()->where($data['type'], $data['value']);
        if (isset($data['id']) && !empty($data['id'])) {
            $query->where('id', intval($data['id']), '<>');
        }

        $count = $query->count();

        if ($count) {
            return $this->writeJson(Status::CODE_ERR, $data['value'].'已存在，请更换');
        } else {
            return $this->writeJson(Status::CODE_OK, '');
        }

    }

    public function addData()
    {
        if (!$this->hasRuleForPost($this->rule_rule_add)) return;

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }
        //hash加密
        $data['password_hash'] = PasswordTool::getInstance()->generatePassword($data['pwd']);
        unset($data['pwd'], $data['verify_pwd']);
        if (UserModel::getInstance()->insert($data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '添加失败');
            var_dump("user--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "添加失败");
            Log::getInstance()->error("user--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "添加失败");
        }
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

        $info = UserModel::getInstance()->find($id);
        if (!$info) {
            $this->show404();
            return;
        }
        $this->render('admin.user.userEdit', ['info' => $info]);
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
        $data['password_hash'] = PasswordTool::getInstance()->generatePassword($data['pwd']);
        unset($data['pwd'], $data['verify_pwd']);
        $id = $this->request()->getRequestParam('id');

        if (UserModel::getInstance()->saveIdData($id, $data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '保存失败');
            Log::getInstance()->error("rule--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "编辑保存失败");
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
            Log::getInstance()->error("rule--set:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "没有设置成功");
        }
    }

    public function del()
    {
        if (!$this->hasRuleForPost($this->rule_rule_del)) return;

        $request = $this->request();
        $id = $request->getRequestParam('id');
        $bool = UserModel::getInstance()->delId($id, true);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("rule--del:" . $id . "没有删除失败");
        }
    }
}
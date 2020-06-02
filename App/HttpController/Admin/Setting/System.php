<?php


namespace App\HttpController\Admin\Setting;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\lib\pool\Login;
use App\Model\AdminSysSettings as SystemModel;
use App\Utility\Log\Log;
use App\Utility\Message\Status;

class System extends AdminController
{
    private $rule_rule      = 'auth.rule';
    private $rule_rule_view = 'auth.setting.system.view';
    private $rule_rule_add  = 'auth.setting.system.add';
    private $rule_rule_set  = 'auth.setting.system.set';
    private $rule_rule_del  = 'auth.setting.system.del';

    public function index()
    {
        if(!$this->hasRuleForGet($this->rule_rule_view)) return ;

        $this->render('admin.setting.system.list');
    }

    public function getAll()
    {
        if(!$this->hasRuleForPost($this->rule_rule_view)) return ;
        $request = $this->request();
        $params    = $request->getRequestParam('page', 'limit');
        $page = isset($params['page']) ? $params['page'] : 1;
        $size = isset($params['limit']) ? $params['limit'] : 10;
        $data = SystemModel::getInstance()->findAll($page, $size);
        $count = SystemModel::getInstance()->count();

        $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count];
        $this->dataJson($data);
    }

    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo()
    {
        $request = $this->request();
        $data    = $request->getRequestParam('sys_key', 'sys_value');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('sys_key')->required();
        $validate->addColumn('sys_value')->required();

        if (!$validate->validate($data)) {
            var_dump($validate->getError()->__toString());
            $this->writeJson(Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }

    /**
     * 添加分类
     */
    public function add()
    {
        if(!$this->hasRuleForGet($this->rule_rule_add)) return ;

        $this->render('admin.setting.system.add');
    }

    /**
     * post添加数据
     */
    public function addData()
    {
        if(!$this->hasRuleForPost($this->rule_rule_add)) return ;

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }
        if (SystemModel::getInstance()->insert($data)) {
            //建立redis
            $key = sprintf(SystemModel::SYSTEM_SETTING_KEY, $data['sys_key']);
            Login::getInstance()->set($key, $data['sys_value']);
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '添加失败');
            Log::getInstance()->error("system--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "添加失败");
        }
    }

    // 修改数据的页面
    public function edit()
    {
        if(!$this->hasRuleForGet($this->rule_rule_set)) return ;

        $id = $this->request()->getRequestParam('id');

        if (!$id) {
            $this->show404();
            return;
        }

        $info = SystemModel::getInstance()->find($id);
        if (!$info) {
            $this->show404();
            return;
        }
        $this->render('admin.setting.system.edit', ['info' => $info]);
    }

    // 修改数据
    public function editData()
    {
        if(!$this->hasRuleForPost($this->rule_rule_set)) return ;

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }

        unset($data['status']);
        $id = $this->request()->getRequestParam('id');
        if (SystemModel::getInstance()->saveIdData($id, $data)) {
            //建立redis
            $key = sprintf(SystemModel::SYSTEM_SETTING_KEY, $data['sys_key']);
            Login::getInstance()->set($key, $data['sys_value']);
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '保存失败');
            Log::getInstance()->error("system--editData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "编辑保存失败");
        }
    }


    public function del()
    {
        if(!$this->hasRuleForPost($this->rule_rule_del)) return ;

        $request = $this->request();
        $id      = $request->getRequestParam('id');
        $bool    = SystemModel::getInstance()->delId($id, true);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("system--del:" . $id . "没有删除失败");
        }
    }
}
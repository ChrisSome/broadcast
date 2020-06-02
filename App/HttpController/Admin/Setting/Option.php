<?php


namespace App\HttpController\Admin\Setting;


use App\Base\AdminController;
use App\Model\AdminCategory as CategoryModel;
use App\Model\AdminOption;
use App\Utility\Log\Log;
use App\Utility\Message\Status;

class Option extends AdminController
{
    public $rule_rule_view = 'setting.option';
    public $rule_rule_set = 'setting.option.edit';

    public function index()
    {
        $this->render('admin.setting.option.index');
    }


    public function getList()
    {
        if (!$this->hasRuleForPost($this->rule_rule_view)) return;

        $params = $this->request()->getQueryParams();
        $page = isset($params['page']) ? $params['page'] : '1';
        $offset = isset($params['offset']) ? $params['offset'] : '10';
        $data = AdminOption::getInstance()->findAll($page, $offset);
        $count = AdminOption::getInstance()->count();
        $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count];

        $this->dataJson($data);
    }

    public function edit()
    {
        if (!$this->hasRuleForGet($this->rule_rule_set)) return;

        $id = $this->request()->getRequestParam('id');

        if (!$id) {
            $this->show404();
            return;
        }

        $info = AdminOption::getInstance()->find($id);
        if (!$info) {
            $this->show404();
            return;
        }
        $this->render('admin.setting.option.edit', ['info' => $info]);
    }


    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo()
    {
        $request = $this->request();
        $data = $request->getRequestParam('reply', 'content');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('reply')->required();
        $validate->addColumn('content')->required();

        if (!$validate->validate($data)) {
            $this->writeJson(Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }

    // 修改数据
    public function editData()
    {
        if (!$this->hasRuleForPost($this->rule_rule_set)) return;

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }
        $data['status'] = 1;
        $data['admin_id'] = $this->auth['id'];
        $data['admin_name'] = $this->auth['uname'];
        $id = $this->request()->getRequestParam('id');

        if (AdminOption::getInstance()->saveIdData($id, $data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '保存失败');
            Log::getInstance()->error("option--replay:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "编辑保存失败");
        }
    }


    public function del()
    {

    }

}
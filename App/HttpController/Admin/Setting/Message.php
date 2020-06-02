<?php


namespace App\HttpController\Admin\Setting;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\Model\AdminCategory as CategoryModel;
use App\Model\AdminMessage as MessageModel;
use App\Utility\Log\Log;
use App\Utility\Message\Status;

class Message extends AdminController
{
    private $rule_rule      = 'auth.rule';
    private $rule_rule_view = 'auth.setting.message.view';
    private $rule_rule_add  = 'auth.setting.massage.add';
    private $rule_rule_set  = 'auth.setting.message.set';
    private $rule_rule_del  = 'auth.setting.message.del';

    public function index()
    {
        if(!$this->hasRuleForGet($this->rule_rule_view)) return ;

        $this->render('admin.setting.message.list');
    }

    public function getAll()
    {
        if(!$this->hasRuleForPost($this->rule_rule_view)) return ;
        $params = $this->request()->getRequestParam();
        $page = isset($params['page']) ? $params['page'] : 1;
        $offset = isset($params['offset']) ? $params['offset'] : 10;
        $data = MessageModel::getInstance()->findAll($page, $offset);

        $data = ['code' => Status::CODE_OK, 'data' => $data];
        $this->dataJson($data);
    }

    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo()
    {
        $request = $this->request();
        $data    = $request->getRequestParam('title', 'cate_id', 'content', 'status');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('title')->required();
        $validate->addColumn('cate_id')->required();
        $validate->addColumn('content')->required();
        $validate->addColumn('status')->required();

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
        $rule_data = CategoryModel::getInstance()->all();

        $tree_data = AppFunc::arrayToTree($rule_data, 'pid');
        $data      = [];
        AppFunc::treeRules($tree_data, $data);
        $this->render('admin.setting.message.add', [
            'cates' => $data
        ]);
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
        $data['admin_id'] = $this->auth['id'];
        $data['admin_name'] = $this->auth['uname'];
        $cate = CategoryModel::getInstance()->find($data['cate_id']);
        if (!$cate) {
            $this->show404();
            return;
        }
        $data['cate_name'] = $cate['name'];
        if (MessageModel::getInstance()->insert($data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '添加失败');
            Log::getInstance()->error("message--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "添加失败");
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

        $info = MessageModel::getInstance()->find($id);
        if (!$info) {
            $this->show404();
            return;
        }

        $rule_data = CategoryModel::getInstance()->all();

        $tree_data = AppFunc::arrayToTree($rule_data, 'pid');
        $data      = [];
        AppFunc::treeRules($tree_data, $data);

        $this->render('admin.setting.message.edit', ['cates' => $data, 'info' => $info]);
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
        $cate = CategoryModel::getInstance()->find($data['cate_id']);
        if (!$cate) {
            $this->show404();
            return;
        }
        $data['cate_name'] = $cate['name'];

        if (MessageModel::getInstance()->saveIdData($id, $data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '保存失败');
            Log::getInstance()->error("rule--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "编辑保存失败");
        }
    }

    // 单字段修改
    public function set()
    {
        if(!$this->hasRuleForPost($this->rule_rule_set)) return ;

        $request  = $this->request();
        $data     = $request->getRequestParam('id', 'key', 'value');
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

        $bool = MessageModel::getInstance()->where('id', $data['id'], '=')
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
        if(!$this->hasRuleForPost($this->rule_rule_del)) return ;

        $request = $this->request();
        $id      = $request->getRequestParam('id');
        $bool    = MessageModel::getInstance()->delId($id, true);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("rule--del:" . $id . "没有删除失败");
        }
    }
}
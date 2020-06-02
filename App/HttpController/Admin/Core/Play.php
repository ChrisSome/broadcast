<?php


namespace App\HttpController\Admin\Core;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\Model\AdminPlay as PlayModel;
use App\Utility\Log\Log;
use App\Utility\Message\Status;

class Play extends AdminController
{
    private $rule_rule      = 'core.play';
    private $rule_rule_view = 'core.play.list';
    private $rule_rule_add  = 'core.play.add';
    private $rule_rule_set  = 'core.play.set';
    private $rule_rule_del  = 'core.play.del';


    public function index()
    {
        if(!$this->hasRuleForGet($this->rule_rule_view)) return ;

        $this->render('admin.play.index');
    }

    public function getAll()
    {
        if(!$this->hasRuleForPost($this->rule_rule_view)) return ;

        $data = PlayModel::getInstance()->all();
        $data = ['code' => Status::CODE_OK, 'data' => $data];

        $this->dataJson($data);
    }

    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo()
    {
        $request = $this->request();
        $data = $request->getRequestParam('name', 'func', 'url', 'status');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('name')->required();
        $validate->addColumn('url')->required();
        $validate->addColumn('func')->required();
        $validate->addColumn('status')->required();

        if (!$validate->validate($data)) {
            $this->writeJson(Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }

    public function add()
    {
        if(!$this->hasRuleForGet($this->rule_rule_add)) return ;

        $this->render('admin.play.playAdd');
    }

    public function addData()
    {
        if(!$this->hasRuleForPost($this->rule_rule_add)) return ;

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }
        $data['uid'] = $this->auth['id'];
        if (PlayModel::getInstance()->insert($data)) {
            $this->writeJson(Status::CODE_OK);
        } else {
            $this->writeJson(Status::CODE_ERR, '添加失败');
            Log::getInstance()->error("play--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "添加失败");
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

        $info = PlayModel::getInstance()->find($id);
        if (!$info) {
            $this->show404();
            return;
        }
        $this->render('admin.play.playEdit', ['info' => $info]);
    }

    // 修改数据
    public function editData()
    {
        if(!$this->hasRuleForPost($this->rule_rule_set)) return ;

        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }

        $id = $this->request()->getRequestParam('id');

        if (PlayModel::getInstance()->saveIdData($id, $data)) {
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

        $bool = PlayModel::getInstance()->where('id', $data['id'], '=')
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
        $bool    = PlayModel::getInstance()->delId($id, true);
        if ($bool) {
            $this->writeJson(Status::CODE_OK, '');
        } else {
            $this->writeJson(Status::CODE_ERR, '删除失败');
            Log::getInstance()->error("rule--del:" . $id . "没有删除失败");
        }
    }
}
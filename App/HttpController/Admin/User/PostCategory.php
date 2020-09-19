<?php


namespace App\HttpController\Admin\User;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\lib\PasswordTool;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\ORM\DbManager;


class PostCategory extends AdminController
{
    private $rule_rule = 'user.user';
    private $rule_rule_view = 'user.user.list';
    private $rule_rule_add = 'user.user.add';
    private $rule_rule_set = 'user.user.set';
    private $rule_rule_del = 'user.user.del';

    private $rule_user_apply = 'user.user.apply';

    const PRE_STATUS_HANDING = 1;       //申请处理中
    const PRE_STATUS_SUCC = 2;          //申请成功
    const PRE_STATUS_FAIL = 3;          //申请失败
    public function index()
    {
        if (!$this->hasRuleForGet($this->rule_rule_view)) return;

        $this->render('admin.user.index');
    }

    public function getAll()
    {
        $category = AdminUserPostsCategory::getInstance()->where('status', AdminUserPostsCategory::STATUS_NORMAL)->all();

        $this->dataJson($category);
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

    /**
     * 用户申请
     */
    public function apply()
    {
        if ($this->request()->getMethod() == 'GET') {
            if (!$this->hasRuleForPost($this->rule_user_apply)) return;
            $this->render('admin.user.apply');
        } else {
            if (!$this->hasRuleForPost($this->rule_user_apply)) return;
            $params = $this->request()->getRequestParam();
            $page = isset($params['page']) ? $params['page'] : 1;
            $offset = isset($params['offset']) ? $params['offset'] : 10;
            $where = [];
            $query = UserModel::getInstance();
            if (isset($params['nickname']) && !empty($params['nickname'])) {
                $query->where('nickname', $params['nickname'], 'like');
            }
            if (isset($params['pre_status']) && !empty($params['pre_status'])) {
                $query->where('pre_status', $params['pre_status']);
            }
            if (isset($params['updated_at']) && !empty($params['updated_at'])) {
                $times = explode(' - ', $params['updated_at']);
                $query->where('updated_at', $times, 'between');
            }
            $data = $query->findAll($page, $offset, $where);
            $count = $query->count();
            $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count, 'params' => $params];

            $this->dataJson($data);
        }
    }
    public function applyIndex()
    {
        if (!$this->hasRuleForPost($this->rule_user_apply)) return;
        $this->render('admin.user.apply');
    }

    public function userApply()
    {
        $request = $this->request();
        $id = $request->getQueryParam('id');
        $pre_status = $request->getQueryParam('pre_status');

        $data = UserModel::create()->get($id);
        if ($pre_status == self::PRE_STATUS_SUCC) {
            if (!$data) {
                $this->writeJson(Status::CODE_ERR, '操作失败');

            } else {
                if ($data['pre_photo']) {
                    $updataData['photo'] = $data['pre_photo'];
                }

                if ($data['pre_nickname']) {
                    $updataData['nickname'] = $data['pre_nickname'];
                }

            }
            try{
                DbManager::getInstance()->startTransaction();
                UserModel::getInstance()->saveIdData($id, $updataData);
                $updataPrestatus = ['pre_status' => self::PRE_STATUS_SUCC];
                UserModel::getInstance()->saveIdData($id, $updataPrestatus);
            } catch (\Throwable  $e) {
                //回滚事务
                DbManager::getInstance()->rollback();
                Log::getInstance()->error("id :" . $id . " pre_status:" . $pre_status . "修改失败");

            } finally {
                //提交事务
                DbManager::getInstance()->commit();
                $this->writeJson(Status::CODE_OK, '');

            }
        } else {
            $updataPrestatus = ['pre_status' => self::PRE_STATUS_FAIL];
            $bool = UserModel::getInstance()->saveIdData($id, $updataPrestatus);
            if ($bool) {
                $this->writeJson(Status::CODE_OK, '');
            } else {
                $this->writeJson(Status::CODE_ERR, '操作失败');
            }

        }


    }

}
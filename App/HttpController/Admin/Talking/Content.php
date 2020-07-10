<?php

namespace App\HttpController\Admin\Talking;

use App\Base\AdminController;
use App\lib\pool\Login;
use App\Model\AdminAccusation as AccusationModel;
use App\lib\pool\BaseRedis as Redis;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Config;


class Content extends AdminController
{

    private $rule_rule = 'talk.content';
    private $rule_rule_view = 'talk.content.list';

    public function index()
    {



        if (!$this->hasRuleForGet($this->rule_rule_view)) return;

        $this->render('admin.talking.index');

    }

    public function getAll()
    {

        $params = $this->request()->getRequestParam();
        $page = isset($params['page']) ? $params['page'] : 1;
        $offset = isset($params['offset']) ? $params['offset'] : 10;
        $where = [];
        $query = AccusationMOdel::getInstance();
        if (!empty($page['passive_nicname'])) {
            $query->where('passive_nicname', $params['passive_nicname']);
        }
        if (!empty(($params['initiative_nicname']))) {
            $query->where('initiative_nicname', $params['initiative_nicname']);

        }
        if (!empty(($params['status']))) {
            $query->where('status', $params['status']);

        }
        if (!empty($params['create'])) {
            $query->where('create_at', $params['created_at']);
        }
        $data = $query->findAll($page, $offset, $where);
        //上线前要保证数据，利用redis保存举报次数
//        foreach ($data as $k=>$v) {
//
//        }
        $count = $query->count();
        $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count, 'params' => $params];
        $this->dataJson($data);
    }
}
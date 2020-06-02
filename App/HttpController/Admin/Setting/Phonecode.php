<?php


namespace App\HttpController\Admin\Setting;


use App\Base\AdminController;
use App\Model\AdminUserPhonecode as PhonecodeModel;
use App\Utility\Message\Status;

class Phonecode extends AdminController
{
    public function index()
    {
        if ($this->request()->getMethod() == 'POST') {
            $params = $this->request()->getRequestParam();
            $page = isset($params['page']) ? $params['page'] : 1;
            $size = isset($params['limit']) ? $params['limit'] : 10;
            $data = PhonecodeModel::getInstance()->findAll($page, $size);
            $count = PhonecodeModel::getInstance()->count();

            $data = ['code' => Status::CODE_OK, 'data' => $data, 'count' => $count, 'limit' => $size];
            return $this->dataJson($data);
        } else {
            $this->render('admin.setting.phonecode.index');
        }
    }

}
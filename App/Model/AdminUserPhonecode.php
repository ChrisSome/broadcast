<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\BaseRedis;
use App\lib\Tool;

class AdminUserPhonecode extends BaseModel
{
    protected $tableName = "admin_user_phonecode";


    public function findAll($page, $limit)
    {
        return $this
            ->order('created_at', 'desc')
            ->limit(($page - 1) * $page, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}

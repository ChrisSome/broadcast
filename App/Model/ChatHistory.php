<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\Login;
use App\lib\Tool;

class ChatHistory extends BaseModel
{
    protected $tableName = "admin_messages";

    /**
     * 获取用户详情
     * @param $id
     * @return mixed
     */
    public function findOne($id)
    {
        $message = $this->where('id', $id)->getOne();

        return $message;
    }
}

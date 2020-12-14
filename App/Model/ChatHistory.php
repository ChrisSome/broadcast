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

    public function getSenderNickname()
    {
        return $this->hasOne(AdminUser::class, null, 'sender_user_id', 'id');
    }

    public function getAtNickname()
    {
        return $this->hasOne(AdminUser::class, null, 'at_user_id', 'id');

    }


    protected function getContentAttr($value, $data)
    {
        return base64_decode($value);
    }
}

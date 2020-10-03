<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\Login;
use App\lib\Tool;

class AdminInformationComment extends BaseModel
{
    const STATUS_REPORTED = 2;
    const STATUS_NORMAL = 0;
    const STATUS_DELETE = 1;

    protected $tableName = "admin_information_comments";

    public function getLimit($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    public function getUserInfo()
    {
        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['id', 'photo', 'nickname']);
    }

    public function getTUserInfo()
    {
        return $this->hasOne(AdminUser::class, null, 't_u_id', 'id')->field(['id', 'photo', 'nickname']);

    }

    public function getParent()
    {
        return $this->hasOne(AdminUser::class, null, 'id', 'parent_id')->field(['user_id', 'id']);

    }
}

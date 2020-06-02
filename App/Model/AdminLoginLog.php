<?php

namespace App\Model;

use App\Base\BaseModel;

// 登录日志记录
class AdminLoginLog extends BaseModel
{
    protected $tableName = "admin_login_log";

    public function add($uname, $status = 0)
    {
        return $this->insert(['uname' => $uname, 'status' => $status]);
    }

    public function findAll($page, $limit)
    {
        $data = $this->orderBy('created_at', 'DESC')
            ->limit(($page - 1) * $page,  $limit)
            ->all();

        return $data;
    }
}

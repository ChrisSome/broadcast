<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;

class AdminUserPost extends BaseModel
{
    protected $tableName = "admin_user_posts";


    public function findAll($page, $limit)
    {
        return $this->orderBy('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}

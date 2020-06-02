<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;

class AdminPostComment extends BaseModel
{
    protected $tableName = "admin_user_post_comments";


    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}

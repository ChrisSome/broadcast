<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminMessage extends BaseModel
{
    protected $tableName = "admin_system_message_lists";

    public function findAll($page, $limit, $where = [])
    {
        return $this->orderBy('created_at', 'ASC')
            ->limit(($page - 1) * $page, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

}

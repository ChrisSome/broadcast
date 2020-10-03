<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserSerialPoint extends BaseModel
{
    const TASK_STATUS_NORMAL = 1;
    const USER_TASK = [
        1 => ['id' => 1, 'name' => '每日签到', 'status' => 1, 'times_per_day' => 1, 'points_per_time' => 100],
        2 => ['id' => 2, 'name' => '社区发帖', 'status' => 1, 'times_per_day' => 5, 'points_per_time' => 5],
        3 => ['id' => 3, 'name' => '评论回帖', 'status' => 1, 'times_per_day' => 5, 'points_per_time' => 5],
        4 => ['id' => 4, 'name' => '分享好友', 'status' => 1, 'times_per_day' => 5, 'points_per_time' => 10],
    ];

    protected $tableName = "admin_user_serial_point";


}

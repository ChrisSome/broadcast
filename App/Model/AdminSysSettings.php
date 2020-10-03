<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\BaseRedis;
use App\lib\pool\Login;
use App\lib\Tool;

class AdminSysSettings extends BaseModel
{
    protected $tableName = "admin_sys_setting";

    const SYSTEM_SETTING_KEY = 'admin:system:%s';
    const SETTING_DATA_COMPETITION = 'data_competition';
    const SETTING_TITLE_BANNER = 'information_title_banner';

    public function findAll($page, $limit)
    {
        return $this
            ->order('created_at', 'desc ')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        //需要修改对应配置项的值，更新redis
        return $this->where('id', $id)->update($data);
    }

    /**
     * 获取配置项的值
     * @param $sys_key
     * @return array|null
     */
    public function getSysKey($sys_key)
    {
        $key = sprintf(self::SYSTEM_SETTING_KEY, $sys_key);
        $value = Login::getInstance()->get($key);
        if (!$value) {
            $value = $this->where('sys_key', $sys_key)->get('sys_value');
            $value = $value ? $value['sys_value'] : false;
            Login::getInstance()->set($key, $value);
        }

        return $value;
    }
}

<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\Login;
use App\lib\Tool;

class AdminUser extends BaseModel
{
    protected $tableName = "admin_user";

    const USER_TOKEN_KEY = 'user:token:%s';

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


    /**
     * 通过微信token以及openid获取用户信息
     * @param $access_token
     * @param $openId
     * @return bool|string
     */
    public function getWxUser($access_token , $openId)
    {
        $url = sprintf("https://api.weixin.qq.com/sns/userinfo?access_token=%s&openid=%s&lang=zh_CN", $access_token, $openId);

        return Tool::getInstance()->postApi($url);
    }

    public function getOneByToken($token)
    {
        //头部传递access_token
        $tokenKey = sprintf(self::USER_TOKEN_KEY, $token);
        $json = Login::getInstance()->get($tokenKey);
        if ($json) {
            Login::getInstance()->setEx($tokenKey,  7200, $json);
        }
        return $json ? json_decode($json, true) : [];
    }

    /**
     * 获取用户详情
     * @param $id
     * @return mixed
     */
    public function findOne($id)
    {
        if (Login::getInstance()->exists('hash:user:'.$id)) {
            $user = Login::getInstance()->hgetall('hash:user:'.$id);
        } else {
            $user = $this->where('id', $id)->get()->toArray();
            unset($user['password_hash']);
        }

        return $user;
    }
}

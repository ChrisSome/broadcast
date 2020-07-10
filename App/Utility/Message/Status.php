<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午3:20
 */

namespace App\Utility\Message;

class Status
{
                                // Informational 1xx
    const CODE_OK         = 0;  // 成功
    const CODE_ERR        = -1; // 失败
    const CODE_VERIFY_ERR = -2; // 验证码错误
    const CODE_RULE_ERR   = -3; // 权限不足

    //用户错误 以3开头
    const CODE_USER_DATA_CHG   = 301;    //用户更改资料错误
    const CODE_USER_DATA_EXIST = 302;  //用户昵称重复
    const CODE_LOGIN_ERR = 303;   //登录出错 重新登录
    const CODE_BINDING_ERR = 304;   //用户绑定错误
    const CODE_LOGIN_W_PASS = 305;   //用户名或密码错误
    const CODE_USER_FOLLOW = 306;   //关注失败



    //系统错误
    const CODE_W_PARAM = 401;   //参数错误

    //数据错误
    const CODE_WRONG_USER = 501; //未查询到有效用户
    const CODE_WRONG_RES = 502; //未查询到有效数据


    public static $msg = [
        self::CODE_OK => 'ok',

        self::CODE_VERIFY_ERR => '登录失败',
        self::CODE_LOGIN_ERR  => '登录失败，请重新登录',
        self::CODE_BINDING_ERR  => '绑定错误，请重新操作',
        self::CODE_LOGIN_W_PASS  => '用户名或密码错误',



        self::CODE_USER_DATA_CHG  => '提交数据非法',
        self::CODE_USER_DATA_EXIST  => '用户昵称存在，请重新编辑',
        self::CODE_W_PARAM  => '系统错误，请联系客服',
        self::CODE_USER_FOLLOW  => '用户关注失败',

        self::CODE_WRONG_USER  => '未查询到有效用户',
        self::CODE_WRONG_RES  => '未查询到有效数据',

    ];
}

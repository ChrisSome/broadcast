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
    const CODE_W_PHONE = 307;   //手机号错误
    const CODE_W_FORMAT_PASS = 310;   //密码格式错误
    const CODE_W_STATUS = 311;   //用户禁用
    const CODE_W_FORMAT_NICKNAME = 312;   //用户名不合规
    const CODE_W_PHONE_CODE = 313;   //用户名不合规
    const CODE_PHONE_EXIST = 314;   //手机号存在
    const CODE_FAIL_LOGON = 315;   //注册失败
    const CODE_USER_NOT_EXIST = 316;   //注册失败

    //帖子
    const CODE_ADD_POST = 308;      //用户发布帖子失败
    const CODE_ADD_POST_SENSITIVE = 309;      //用户发布帖子包含敏感词





    //系统错误
    const CODE_W_PARAM = 401;   //参数错误

    const CODE_WRONG_MATCH_ORIGIN = 402; //纳米数据解析错误
    const CONDE_WRONG_RESPONSE = 403; //比赛列表请求失败
    const CODE_MATCH_FOLLOW_ERR = 404; //比赛关注失败
    const CODE_MATCH_LINE_UP_ERR = 405; //比赛首发阵容获取失败
    //数据错误
    const CODE_WRONG_USER = 501; //未查询到有效用户
    const CODE_WRONG_RES = 502; //未查询到有效数据
    const CODE_WRONG_LIMIT = 503; //频繁操作



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
        self::CODE_WRONG_LIMIT  => '请勿频繁操作',
        self::CODE_W_PHONE  => '手机号码错误',
        self::CODE_W_FORMAT_PASS => '密码6-16位字符（英文/数字/符号）三种组合，请修改',
        self::CODE_W_STATUS => '该账号已被禁用,详情请联系客服',
        self::CODE_W_FORMAT_NICKNAME => '用户名由2-16位数字或字母、汉字、下划线组成,请修改',
        self::CODE_W_PHONE_CODE => '验证码不存在或错误',
        self::CODE_PHONE_EXIST => '手机号已存在',
        self::CODE_FAIL_LOGON => '注册异常，请稍后重试',
        self::CODE_USER_NOT_EXIST => '用户不存在',




        self::CODE_ADD_POST_SENSITIVE  => '帖子内容包含敏感词：%s',

        self::CODE_WRONG_MATCH_ORIGIN => '比赛源错误',
        self::CODE_MATCH_FOLLOW_ERR => '关注比赛失败',
        self::CODE_MATCH_LINE_UP_ERR => '阵容获取失败',
    ];
}

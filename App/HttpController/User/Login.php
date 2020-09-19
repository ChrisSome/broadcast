<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\lib\Tool;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserSetting;
use App\Storage\OnlineUser;
use App\Task\TestTask;
use App\Utility\Gravatar;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\Message\Status;
use EasySwoole\Validate\Validate;
use App\Utility\Message\Status as Statuses;
use App\lib\pool\Login as LoginRedis;


class Login extends FrontUserController
{
    protected $isCheckSign = false;
    public $needCheckToken = false;

    public function index()
    {
        return $this->render('front.user.login');
    }

    public function doLogin()
    {

        Log::getInstance()->info('login param' . json_encode($this->params));

        //参数验证
        $valitor = new Validate();
        $valitor->addColumn('mobile', '手机号码')->required('手机号不为空')
            ->regex('/^1\d{10}/', '手机号格式不正确');
        $valitor->addColumn('code')->required('验证码不能为空');

        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Status::CODE_BAD_REQUEST, $valitor->getError()->__toString());
        }
        //数据库增加校验， 同一IP错误次数， 或者邮箱错误次数超出配置需要加入验证码逻辑
        $sIp = $this->request()->getServerParams()['remote_addr'];
        $sMobile = $this->params['mobile'];
        $code = $this->params['code'];
        $params = $this->params;
        $isExists = UserModel::getInstance()->where('mobile', $sMobile)->get();

        $phoneCodeIsExists = AdminUserPhonecode::getInstance()->where('mobile', $sMobile)->where('code', $code)->orderBy('created_at', 'desc')->get();

        if (!$phoneCodeIsExists || $phoneCodeIsExists['status'] == 1 || $phoneCodeIsExists['code'] != $code) {
//            return $this->writeJson(Statuses::CODE_ERR, '验证码不存在或者验证码错误');
        }

        //var_dump($isExists, $sUserModel->Sql());
        $isSuccess = FALSE;
        $aUserData = [];
        try {
            if (!$isExists) {
                //直接号码注册
                $nickname = Tool::getInstance()->makeRandomString(6);
                $userData = [
                    'nickname' => $nickname,
                    'password_hash' => PasswordTool::getInstance()->generatePassword('1234qwer'),
                    'mobile' => $sMobile,
                    'photo' => Gravatar::makeGravatar($nickname),
                    'sign_at' => date('Y-m-d H:i:s'),
                    'cid' => isset($params['cid']) ? $params['cid'] : '',
                ];
                $rs = AdminUser::getInstance()->insert($userData);
                TaskManager::getInstance()->async(function () use($rs){
                   $settingData = [
                       'user_id'    => $rs,
                       'goatNotice' => 0,
                       'goatPopup'  => 0,
                       'redCardNotice' => 0,
                       'followUser'    => 0,
                       'followMatch'   => 1,
                       'nightModel'    => 0,
                   ];
                   AdminUserSetting::getInstance()->insert($settingData);

                    $competitionIds = FrontService::getHotCompetitionIds();
                    $insertCompetition = [
                        'user_id' => $rs,
                        'competition_ids' => json_encode($competitionIds),
                    ];
                    AdminUserInterestCompetition::getInstance()->insert($insertCompetition);
                });

                $isExists = AdminUser::getInstance()->find($rs);
            }
            //修改cid

            AdminUser::getInstance()->update(['cid'=>$params['cid']], ['id'=>$isExists['id']]);
            $time = time();
            $token = md5($isExists['id'] . Config::getInstance()->getConf('app.token') . $time);
            $isExists['userSetting'] = $isExists->userSetting();
            $aUserData = $isExists;

            unset($aUserData['password_hash']);
            $aUserData['token'] = $token;
            $isSuccess = true;
            $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $token);
            if ($sOldToken = Cache::get($sUserKey)) {
                LoginRedis::getInstance()->del(sprintf(UserModel::USER_TOKEN_KEY, $sOldToken));
            }
            Cache::set($sUserKey, $token);

            AdminUserPhonecode::getInstance()->update(['status' => 1], ['id' => $phoneCodeIsExists['id']]);
            $tokenKey = sprintf(AdminUser::USER_TOKEN_KEY, $token);
            LoginRedis::getInstance()->set($tokenKey,  $sMobile);
        } catch (\Exception $e) {
            //异步任务写入异常表
            return $this->dataJson([
                'code' => 409,
                'message' => '登陆失败，请稍后重试'
            ]);
        }


        if ($isSuccess) {
            $this->response()->setCookie('front_id', $isExists['id']);
            $this->response()->setCookie('front_time', $time);
            $this->response()->setCookie('front_token', $token);
            return $this->writeJson(Statuses::CODE_OK, 'OK', $aUserData);
        } else {
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');
        }
    }

    /**
     * 退出登陆
     */
    public function doLogout()
    {
        $fd = $this->params['fd'];
        $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $this->auth['front_token']);
        OnlineUser::getInstance()->delete($fd);

        LoginRedis::getInstance()->del(sprintf(UserModel::USER_TOKEN_KEY, Cache::get($sUserKey)));
        $this->response()->setCookie('front_token', '');
        $this->response()->setCookie('front_id', '');
        $this->response()->setCookie('front_time', '');

//        $this->response()->redirect("/api/user/login");
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

    }


    /**
     * 获取手机验证码
     * @return bool
     */
    /**
     * 用户短信验证码
     */
    public function userSendSmg()
    {


        $valitor = new Validate();
        $valitor->addColumn('type')->required();  //1登录 2更改手机号
        $valitor->addColumn('mobile', '手机号码')->required('手机号不为空')
            ->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');


        if ($valitor->validate($this->params)) {

            if ($this->params['type'] == 1) {
                $mobile = $this->params['mobile'];

            } else if ($this->params['type'] == 2) {
                if ($this->params['mobile'] != $this->auth['mobile']) {
                    return $this->writeJson(Statuses::CODE_W_PHONE, Statuses::$msg[Statuses::CODE_W_PHONE]);

                }
                $mobile = $this->params['mobile'];

            }

        } else {
            return $this->writeJson(Statuses::CODE_W_PARAM, $valitor->getError()->__toString());

        }

        $code = Tool::getInstance()->generateCode();
        //异步task

        TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($code, $mobile) {
            $phoneTask = new TestTask([
                'code' => $code,
                'mobile' => $mobile,
                'name' => '短信验证码'
            ]);
            $phoneTask->insert();
        });
        return $this->writeJson(Statuses::CODE_OK, '验证码以发送至尾号' . substr($mobile, -4) .'手机');

    }



    /**
     * 微信绑定接口
     * @return bool
     */
    public function thirdLogin()
    {
        $params = $this->params;
        $valitor = new Validate();
        //验证参数
        $valitor->addColumn('access_token')->required('access_token不能为空');
        $valitor->addColumn('open_id')->required('open_id不能为空');
        $uid = $this->request()->getCookieParams('front_id');
        $user = AdminUser::create()->get(['id'=>$uid]);
        if (!$user) {
            return $this->writeJson(Statuses::CODE_LOGIN_ERR, Statuses::$msg[Statuses::CODE_LOGIN_ERR]);

        }
        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Statuses::CODE_ERR, $valitor->getError()->__toString());
        }

        //获取三方微信账户信息
        $mThirdWxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
        $aWxInfo = json_decode($mThirdWxInfo, true);
        if (json_last_error()) {
            return $this->writeJson(Statuses::CODE_ERR, 'json parse error');
        }
        if (isset($aWxInfo['errcode'])) {
            return $this->writeJson(Statuses::CODE_ERR, $aWxInfo['errmsg']);
        } else {
            $wxInfo = [
                'wx_photo' => $aWxInfo['headimgurl'],
                'wx_name'  => $aWxInfo['nickname'],
                'third_wx_unionid' => base64_encode($aWxInfo['unionid'])
            ];
            $bool = AdminUser::create()->update($wxInfo, ['id'=>$user['id']]);
            if (!$bool) {
                return $this->writeJson(Statuses::CODE_BINDING_ERR, Statuses::$msg[Statuses::CODE_BINDING_ERR]);
            } else {
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $wxInfo);

            }


        }
        //wx_openid 是否绑定会员
        //未绑定直接返回wx_用户信息
        //绑定了，更新用户微信头像以及昵称， 设置用户登陆token，写入用户登陆日志等

    }

    /**
     * 注册
     * @return bool
     * @throws \Exception
     */
    public function logon()
    {
        $validator = new Validate();
        $validator->addColumn('nickname')->required();
        $validator->addColumn('mobile')->required();
        $validator->addColumn('password')->required();
        $validator->addColumn('phone_code')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);

        }
        if (AdminUser::getInstance()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_PHONE_EXIST, Statuses::$msg[Statuses::CODE_PHONE_EXIST]);

        }
        if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fa5}]+$/u',$this->params['nickname'])) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_NICKNAME, Statuses::$msg[Statuses::CODE_W_FORMAT_NICKNAME]);

        } else if (AdminUser::getInstance()->where('nickname', $this->params['nickname'])->get()) {
            return $this->writeJson(Statuses::CODE_USER_DATA_EXIST, Statuses::$msg[Statuses::CODE_USER_DATA_EXIST]);

        }

        $password = $this->params['password'];
        if (!preg_match('/(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).{8,16}/', $password)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_PASS, Statuses::$msg[Statuses::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);

        $phoneCode = AdminUserPhonecode::getInstance()->where('phone', $this->params['phone'])->get();
        if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['phone_code']) {

            return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);

        }
        $logon = false;
        try{
            $userData = [
                'nickname' => $this->params['nickname'],
                'password_hash' => $password_hash,
                'mobile' => $this->params['mobile'],
                'photo' => Gravatar::makeGravatar($this->params['nickname']),
                'sign_at' => date('Y-m-d H:i:s'),
                'cid' => isset($this->params['cid']) ? $this->params['cid'] : '',
            ];
            $rs = AdminUser::getInstance()->insert($userData);

            $time = time();
            $token = md5($rs . Config::getInstance()->getConf('app.token') . $time);
            $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $token);
            LoginRedis::getInstance()->set($sUserKey,  $this->params['mobile']);
            $logon = true;
            TaskManager::getInstance()->async(function () use($rs){
               //写用户设置
                $settingData = [
                    'user_id'    => $rs,
                    'goatNotice' => 0,
                    'goatPopup'  => 0,
                    'redCardNotice' => 0,
                    'followUser'    => 0,
                    'followMatch'   => 1,
                    'nightModel'    => 0,
                ];
                AdminUserSetting::getInstance()->insert($settingData);
                //写用户关注赛事
                if ($competitions = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get()) {
                    foreach ($competitions as $item) {
                        foreach ($item as $value) {
                            $competitionIds[] = $value['competition_id'];
                        }
                    }
                    $userInterestComData = [
                        'competition_ids' => json_encode($competitionIds),
                        'user_id' => $rs
                    ];
                    AdminUserInterestCompetition::getInstance()->insert($userInterestComData);
                }

            });
        } catch (\Exception $e) {
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');

        }
        $user = AdminUser::getInstance()->find($rs);
        if ($logon) {
            $this->response()->setCookie('front_id', $rs);
            $this->response()->setCookie('front_time', $time);
            $this->response()->setCookie('front_token', $token);
            return $this->writeJson(Statuses::CODE_OK, 'OK', $user);
        } else {
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');
        }


    }


    public function forgetPass()
    {
        if ($user = AdminUser::getInstance()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_USER_NOT_EXIST, Statuses::$msg[Statuses::CODE_USER_NOT_EXIST]);

        }
        $phoneCode = AdminUserPhonecode::getInstance()->where('phone', $this->params['phone'])->get();
        if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['phone_code']) {

            return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);

        }

        $password = $this->params['password'];
        if (!preg_match('/(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).{8,16}/', $password)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_PASS, Statuses::$msg[Statuses::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        $user->password_hash = $password_hash;
        $user->update();
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

    }



}
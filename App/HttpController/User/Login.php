<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\PasswordTool;
use App\lib\Tool;
use App\Model\AdminUser;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserPhonecode;
use App\Task\LoginTask;
use App\Task\PhoneTask;
use App\Utility\Gravatar;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\Message\Status;
use EasySwoole\Validate\Validate;
use App\Utility\Message\Status as Statuses;


class Login extends FrontUserController
{
    protected $isCheckSign = true;

    public function index()
    {
        return $this->render('front.user.login');
    }

    public function doLogin()
    {
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

        if (!$phoneCodeIsExists || $phoneCodeIsExists['status'] == 1 && false) {
            return $this->writeJson(Statuses::CODE_ERR, '验证码不存在或者验证码错误');
        }
        //var_dump($isExists, $sUserModel->getLastSql());
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
                    'sign_at' => date('Y-m-d H:i:s')
                ];
                $rs = AdminUser::getInstance()->insert($userData);
                $isExists = AdminUser::getInstance()->find($rs);
            }
            $time = time();
            $token = md5($isExists['id'] . Config::getInstance()->getConf('app.token') . $time);
            $aUserData = $isExists;
            unset($aUserData['password_hash']);
            $aUserData['token'] = $token;
            $taskData = [
                'type' => 'success',
                'data' => $aUserData,
                'token' => $token
            ];
            $isSuccess = true;
            $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $sMobile);
            if ($sOldToken = Cache::get($sUserKey)) {
                \App\lib\pool\Login::getInstance()->del(sprintf(UserModel::USER_TOKEN_KEY, $sOldToken));
            }
            Cache::set($sUserKey, $token);
        } catch (\Exception $e) {
            //异步任务写入异常表
            var_dump($e->getMessage(), $e->getTraceAsString());
            return $this->dataJson([
                'code' => 409,
                'message' => '登陆失败，请稍后重试'
            ]);
        }

        $loginTask = new LoginTask($taskData);
        TaskManager::getInstance()->async(function ($taskId,$workerIndex)  use ($loginTask, $params, $isSuccess){
            if ($isSuccess) {
                AdminUserPhonecode::getInstance()->where('code', $params['code'])
                    ->where('mobile', $params['mobile'])
                    ->update(['status' => 1]);
            }

            $loginTask->execData();
        });
        if ($isSuccess) {
            return $this->writeJson(Statuses::CODE_OK, 'OK', [
                'data' => $aUserData
            ]);
        } else {
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');
        }
    }

    /**
     * 退出登陆
     */
    public function doLogout()
    {
        $this->response()->setCookie('front_token', '');
        $this->response()->redirect("/api/user/login");
        return;
    }


    /**
     * 获取手机验证码
     * @return bool
     */
    public function getPhoneCode()
    {
        $params = $this->params;
        $valitor = new Validate();
        $valitor->addColumn('mobile', '手机号码')->required('不为空')
            ->regex('/^1\d{10}/', '格式不合法');

        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Status::CODE_BAD_REQUEST, $valitor->getError()->__toString());
        }
        $mobile = $params['mobile'];
        $code = Tool::getInstance()->generateCode();
        //异步task
        TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($code, $mobile) {
            $phoneTask = new PhoneTask([
                'code' => $code,
                'mobile' => $mobile
            ]);
            $phoneTask->insert();
        });

        return $this->writeJson(Statuses::CODE_OK, '验证码已成功发送到你手机');

    }


    public function thirdLogin()
    {
        $params = $this->params;
        $valitor = new Validate();
        //验证参数
        $valitor->addColumn('access_token', 'token')->required('不能为空');
        $valitor->addColumn('open_id', 'openid')->required('不能为空');
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
        }
        //wx_openid 是否绑定会员
        //未绑定直接返回wx_用户信息
        //绑定了，更新用户微信头像以及昵称， 设置用户登陆token，写入用户登陆日志等

    }

}
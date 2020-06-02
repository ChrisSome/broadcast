<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\HttpController\Admin\User\User;
use App\lib\PasswordTool;
use App\Model\AdminUser as UserModel;
use App\Task\LoginTask;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Http\Message\Status;
use EasySwoole\Validate\Validate;

class Broad extends FrontUserController
{
    public $needCheckToken = true;

    public function index()
    {
      return $this->render('front.broad.list');
    }


    public function getList()
    {

    }
}
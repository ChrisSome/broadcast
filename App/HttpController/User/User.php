<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\pool\User as UserRedis;
use App\lib\Tool;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserPost;
use App\Task\PhoneTask;
use App\Utility\Gravatar;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;
use App\Task\UserTask;
use App\lib\pool\PhoneCodeService as PhoneCodeService;
use App\Task\TestTask;
use Illuminate\Filesystem\Cache;

/**
 * 前台用户控制器
 * Class User
 * @package App\HttpController\User
 */
class User extends FrontUserController
{
    public $needCheckToken = true;
    public $isCheckSign = true;

    /**
     * 返回用户信息
     */
    public function info()
    {
        return $this->writeJson(Status::CODE_OK, 'ok', AdminUser::getInstance()->findOne($this->auth['id']));
    }


    /**
     * 用户更新相关操作
     */
    public function operate()
    {
        $actionType = isset($this->params['action_type']) ? $this->params['action_type'] : 'chg_nickname';
        //only check data
        $validate = new Validate();
        switch ($actionType){
            case 'chg_nickname':
                $validate->addColumn('value', '昵称')->required()->lengthMax(64)->lengthMin(4);
                break;
            case 'chg_photo':
                $validate->addColumn('value', '头像')->required()->lengthMax(128)->lengthMin(6);
                break;
        }
        //昵称去重，头像判断存不存在
        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_VERIFY_ERR, $validate->getError()->__toString());
        }

        if ($actionType == 'chg_nickname') {
            $isExists = AdminUser::create()->where('nickname', $this->params['value'])
                ->where('id', $this->auth['id'], '<>')
                ->count();

            if ($isExists) {
                return $this->writeJson(Status::CODE_ERR, '该昵称已存在，请重新设置');
            }
        }
        $this->params['action_type'] = $actionType;
        TaskManager::getInstance()->async(new UserTask([
            'user_id' => $this->auth['id'],
            'params' => $this->params
        ]));

        return $this->writeJson(Status::CODE_OK, '修改成功');

    }

    /**用户资料编辑
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function editUser()
    {

        $params = $this->params;
        $uid = $this->auth['id'];
        $validate = new Validate();
        $updataData = ['pre_status' => AdminUser::STATUS_PRE_INIT];
        if ($params['pre_nickname']) {
            $isExists = AdminUser::create()->where('nickname', $this->params['pre_nickname'])
                ->where('id', $this->auth['id'], '<>')
                ->count();

            if ($isExists) {
                return $this->writeJson(Status::CODE_USER_DATA_EXIST, Status::$msg[Status::CODE_USER_DATA_EXIST]);
            }
            $validate->addColumn('pre_nickname', '申请昵称')->required()->lengthMax(64)->lengthMin(4);
            $updataData = ['pre_nickname' => $params['pre_nickname']];
        }
        if ($params['pre_photo']) {
            $validate->addColumn('pre_photo', '申请头像')->required()->lengthMax(128);
            $updataData = ['pre_photo' => $params['pre_photo']];

        }
        if ($params['pre_mobile']) {
            $validate->addColumn('pre_mobile', '手机号码')->required('手机号不为空')
                ->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');
            if(!$validate->validate($this->params)) {
                return $this->writeJson(Status::CODE_W_PARAM, $validate->getError()->__toString());

            }
            //获取验证码
            $codeInfo = AdminUserPhonecode::getInstance()->getLastCodeByMobile($this->auth['mobile']);
            if (!$codeInfo || $params['code'] !== $codeInfo['code']) {
                return $this->writeJson(Status::CODE_LOGIN_W_PASS, Status::$msg[Status::CODE_LOGIN_W_PASS]);

            } else {
                $updataData['pre_mobile'] = $params['pre_mobile'];
            }

        }
        $bool = AdminUser::getInstance()->saveIdData($uid, $updataData);
        if (!$bool) {
            return $this->writeJson(Status::CODE_USER_DATA_CHG, Status::$msg[Status::CODE_USER_DATA_CHG]);

        } else {
            return $this->writeJson(Status::CODE_OK, '用户信息编辑成功，请等待工作人员审核');

        }



    }


    //用户帖子操作列表   用户点赞等操作的帖子或评论
    public function userOperatePosts()
    {

        $params = $this->params;
        $page = $params['page'] ?: 1;
        $size = $params['size'] ?: 10;
        $valitor = new Validate();

        $valitor->addColumn('action_type')->required()->inArray(["1","2","3","4","5","6"], '参数错误');
        $valitor->addColumn('type')->required()->inArray(["1","2"], '参数错误');
        if (!$valitor->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, $valitor->getError()->__toString());

        }
        $model = AdminPostOperate::create();
        $query = $model->where('action_type', $params['action_type'])->where('user_id', $this->auth['id']);
        if ($params['type'] == 1) {  //帖子
            $query = $query->where('comment_id', 0);
        } else {            //评论
            $query = $query->where('post_id', 0);
        }

        $query = $query->findAll($page, $size)->withTotalCount();
        //列表数据
        $data = $query->all(null);
        //总条数
        $result = $query->lastQueryResult();

        $count = $result->getTotalCount();
//        $sql = $model->lastQuery()->getLastQuery();

        foreach ($data as $item) {
            $nickname = $item->userInfo()->nickname;
            $photo = $item->userInfo()->photo;
            if ($item['post_id']) {
                $p_info['user_nickname']    = $nickname;
                $p_info['user_photo']       = $photo;
                $p_info['post_title']       = $item->postInfo()->title;
                $p_info['post_content']     = mb_substr($item->postInfo()->content, 0, 30, 'utf-8');
                $p_info['p_create_at']      = $item->postInfo()->created_at;
                $p_info['created_at']       = $item->created_at;
                $p_data[] = $p_info;
                unset($r_info);
            } else {
                $c_info['user_nickname']    = $nickname;
                $c_info['user_photo']       = $photo;
                $c_info['content']          = mb_substr($item->commentInfo()->content, 0, 30, 'utf-8');
                $c_info['c_created_at']     = $item->commentInfo()->created_at;
                $c_info['created_at']       = $item->created_at;
                $c_data[] = $c_info;
                unset($c_info);
            }
        }
        $returnData = ['count'=>$count, 'data'=>$p_data ?: $c_data];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }


    //关注用户 / 取消关注
    public function userFollowings()
    {
        $params = $this->params;
        $valitor = new Validate();
        $valitor->addColumn('follow_id')->required();
        $valitor->addColumn('action_type')->required()->inArray(['add', 'del']);
        if (!$valitor->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $userRedis = new UserRedis();
        $uid = $this->auth['id'];
        $followUid = $params['follow_id'];
        $user = AdminUser::getInstance()->field(['id', 'nickname', 'photo'])->get(['id'=>$followUid]);
        $user->follow_time = date('Y-m-d');
        if (!$user) {
            return $this->writeJson(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER]);

        }
        $key = sprintf(UserRedis::USER_FOLLOWS, $uid);
        if ($params['action_type'] == 'add') {
            $bool = $userRedis->addUserFollows($key, $user);
        } else {
            $bool = $userRedis->delUserFollows($key, $user);
        }
        if ($bool) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
        } else {
            return $this->writeJson(Status::CODE_USER_FOLLOW, Status::$msg[Status::CODE_USER_FOLLOW]);
        }

    }


    /**
     * 用户被点赞列表 包括帖子与评论
     */

    public function myFabolusInfo()
    {
        $params = $this->params;
        $page = $params['page'] ?: 1;
        $size = $params['size'] ?: 10;
        $valitor = new Validate();

        $valitor->addColumn('action_type')->required()->inArray(["1","2","3","4","5","6"], '参数错误');
        $valitor->addColumn('type')->required()->inArray(["1","2"], '参数错误');
        if (!$valitor->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $model = AdminPostOperate::create();
        $query = $model->where('action_type', $params['action_type'])->where('author_id', $this->auth['id']);
        if ($params['type'] == 1) {  //帖子
            $query = $query->where('comment_id', 0);
        } else {            //评论
            $query = $query->where('post_id', 0);
        }

        $query = $query->findAll($page, $size)->withTotalCount();
        //列表数据
        $data = $query->all(null);
        //总条数
        $result = $query->lastQueryResult();

        $count = $result->getTotalCount();
//        $sql = $model->lastQuery()->getLastQuery();

        foreach ($data as $item) {
            $nickname = $item->uInfo()->nickname;
            $photo = $item->uInfo()->photo;
            if ($item['post_id']) {
                $r_info['nickname']    = $nickname;
                $r_info['photo']       = $photo;
                $r_info['title']       = $item->postInfo()->title;
                $r_info['content']     = mb_substr($item->postInfo()->content, 0, 30, 'utf-8');
                $r_info['c_create_at'] = $item->postInfo()->created_at;
                $r_info['created_at']  = $item->created_at;
                $r_info['type']        = 1;
                $c_data[] = $r_info;
                unset($c_data);
            } else {
                $c_info['nickname']     = $nickname;
                $c_info['photo']        = $photo;
                $c_info['title']        = '';
                $c_info['content']      = mb_substr($item->commentInfo()->content, 0, 30, 'utf-8');
                $c_info['c_created_at'] = $item->commentInfo()->created_at;
                $c_info['created_at']   = $item->created_at;
                $c_info['type']         = 2;
                $c_data[] = $c_info;
                unset($c_info);
            }
        }
        $returnData = ['count'=>$count, 'data'=>$c_data ?? []];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);
    }

    //回复我的列表  帖子和评论
    public function myReplys()
    {

        $params = $this->params;
        $page = $params['page'] ?: 1;
        $size = $params['size'] ?: 10;

        $model = AdminPostComment::create();
        $query = $model->where('t_u_id', $this->auth['id'])->where('status', AdminPostComment::STATUS_DEL, '<>')->orderBy('created_at', 'DESC');
        $list = $query->getAll($page, $size)->all(null);

        if ($list) {
            foreach ($list as $item) {
                if (!$item['parent_id']) {
                    //我的帖子的回复
                    $p_info['title']        = $item->postInfo()->title;
                    $p_info['p_created_at'] = $item->postInfo()->created_at;
                    $p_info['content']      = mb_substr($item->content, 0, 30, 'utf-8');
                    $p_info['nickname']     = $item->uInfo()->nickname;
                    $p_info['photo']        = $item->uInfo()->photo;
                    $p_info['created_at']   = $item->created_at;
                    $r_data['posts'][]      = $p_info;
                    unset($p_info);

                } else {
                    //我的评论的回复
                    $c_info['title']        = '';
                    $c_info['p_created_at'] = '';
                    $c_info['content']      = mb_substr($item->content, 0, 30, 'utf-8');
                    $c_info['nickname']     = $item->uInfo()->nickname;
                    $c_info['photo']        = $item->uInfo()->photo;
                    $c_info['created_at']   = $item->created_at;
                    $r_data['comments']     = $c_info;
                    unset($c_info);

                }
            }
            //总条数
            $result = $query->lastQueryResult();

            $count = $result->getTotalCount();
        } else {
            $count = 0;
        }

        $returnData = ['count'=>$count, 'data'=>$r_data ?? []];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }





}
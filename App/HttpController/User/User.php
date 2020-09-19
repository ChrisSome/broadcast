<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\lib\pool\User as UserRedis;
use App\lib\Tool;
use App\Model\AdminCompetition;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserFeedBack;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserPost;
use App\Model\AdminUserSetting;
use App\Task\PhoneTask;
use App\Task\PostTask;
use App\Utility\Gravatar;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use function Composer\Autoload\includeFile;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;
use App\Task\UserTask;
use App\lib\pool\PhoneCodeService as PhoneCodeService;
use App\Task\TestTask;
use easySwoole\Cache\Cache;

/**
 * 前台用户控制器
 * Class User
 * @package App\HttpController\User
 */
class User extends FrontUserController
{
    public $needCheckToken = true;
    public $isCheckSign = false;

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
        $updataData = [];
        if ($params['nickname']) {
            $isExists = AdminUser::create()->where('nickname', $this->params['nickname'])
                ->where('id', $this->auth['id'], '<>')
                ->count();

            if ($isExists) {
                return $this->writeJson(Status::CODE_USER_DATA_EXIST, Status::$msg[Status::CODE_USER_DATA_EXIST]);
            }
            $validate->addColumn('nickname', '申请昵称')->required()->lengthMax(32)->lengthMin(4);
            $updataData = ['nickname' => $params['nickname']];
        }
        if ($params['photo']) {
            $validate->addColumn('photo', '申请头像')->required()->lengthMax(128);
            $updataData = ['photo' => $params['photo']];

        }
        if ($params['mobile']) {
            $validate->addColumn('mobile', '手机号码')->required('手机号不为空')
                ->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');
            if(!$validate->validate($this->params)) {
                return $this->writeJson(Status::CODE_W_PARAM, $validate->getError()->__toString());

            }
            //获取验证码
            $codeInfo = AdminUserPhonecode::getInstance()->getLastCodeByMobile($this->auth['mobile']);
            if (!$codeInfo || $params['code'] !== $codeInfo['code']) {
                $updataData['mobile'] = $params['mobile'];

//                return $this->writeJson(Status::CODE_LOGIN_W_PASS, Status::$msg[Status::CODE_LOGIN_W_PASS]);

            } else {
                $updataData['mobile'] = $params['mobile'];
            }

        }

        $bool = AdminUser::getInstance()->saveIdData($uid, $updataData);
        $res = AdminUser::getInstance()->find($uid);
        $uInfo = [
            'id' => $uid,
            'nickname' => $res->nickname,
            'photo' => $res->photo,
            'mobile' => $res->mobile,
        ];
        $this->auth = $res;
        if (!$bool) {
            return $this->writeJson(Status::CODE_USER_DATA_CHG, Status::$msg[Status::CODE_USER_DATA_CHG]);

        } else {
            return $this->writeJson(Status::CODE_OK, '', $uInfo);

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
    /**
     * 帖子相关操作
     * @return bool
     */
    public function cpOperate()
    {


        $key = $this->auth['id'] . $this->params['type'] . time();

        if ($cache = Cache::get($key)) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);

        }
        //帖子id 与 评论id不可同时存在
        if ($this->params['post_id'] && $this->params['comment_id']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->params['post_id']) {
            $id = $this->request()->getRequestParam('post_id');
            $column = 'post_id';
            $info = AdminUserPost::getInstance()->find($id);
            if (!$info) {
                return $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
            }

            if ($info['status'] != AdminUserPost::STATUS_EXAMINE_SUCC) {
                return $this->writeJson(Status::CODE_ERR, '帖子未通过审核，无法进行操作');
            }
        } else if ($this->params['comment_id']) {
            $id = $this->request()->getRequestParam('comment_id');
            $column = 'comment_id';
            if (!$comment = AdminPostComment::getInstance()->find($id)) {
                return $this->writeJson(Status::CODE_ERR, '对应评论不存在');
            }
        }

        $validate = new Validate();
        //1. 点赞   2收藏， 3， 举报，   4， 5， 6 对应取消
        $validate->addColumn('type')->required();
//        $validate->addColumn('author_id')->required();
        $validate->addColumn('type')->inArray(["1", "2", "3", "4", "5" , "6"]);

        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_ERR, $validate->getError()->__toString());
        }
        if ($this->params['type'] > 3) {
            list($maxType, $minType) = [$this->params['type'], $this->params['type'] - 3];
        } else {
            list($maxType, $minType) = [$this->params['type'], $this->params['type'] + 3];

        }
        $whereActionType = '(action_type=' . $maxType . ' or action_type=' . $minType . ')';

        $isExists = AdminPostOperate::getInstance()->where($column, $id)
            ->where('user_id', $this->auth['id'])
            ->where($whereActionType)
            ->get();




        $sql = AdminPostOperate::getInstance()->lastQuery()->getLastQuery();
        Log::getInstance()->info('sql ' . $sql);
        if ($isExists && ($isExists['action_type'] == $this->params['type'])) {
            return $this->writeJson(Status::CODE_WRONG_RES, '请勿重复操作');

        }

        if (!$isExists && $this->params['type'] > 3) {
            return $this->writeJson(Status::CODE_WRONG_RES, '操作错误');

        }

//        $sql = AdminPostOperate::getInstance()->lastQuery()->getLastQuery();
        if ($this->params['type'] == 3) {
            if (!isset($this->params['report_type']) || !isset($this->params['report_content'])) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }
            $report['type'] = isset($this->params['report_type']) ? json_decode($this->params['report_type']) : [];
            $report['content'] = isset($this->params['report_content']) ? $this->params['report_content'] : '';
        }
        $taskData = [
            'action_type' => $this->params['type'],
            'user_id' => $this->auth['id'],
            'post_id' => $this->params['post_id'] ?: 0,
            'content' => isset($report) ? json_encode($report) : '',
            'basic' => $isExists ? 1 : 0,
            'comment_id' => isset($this->params['comment_id']) ? $this->params['comment_id'] : 0,
            'author_id' => $this->params['post_id'] ? $info->user_id : $comment->user_id,
            'img' => '',
            'op_id' => $isExists ? $isExists['id'] : 0,
        ];

//        TaskManager::getInstance()->async(new PostTask($taskData));
        if (FrontService::execOperate($taskData)) {
            Cache::set($key, 1, 2);

            return $this->writeJson(Status::CODE_OK, '操作成功');

        } else {
            return $this->writeJson(Status::CODE_ERR, '操作失败');

        }
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
        if (!$user) {
            return $this->writeJson(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER]);

        }
        $key = sprintf(UserRedis::USER_FOLLOWS, $uid);

        //增加粉丝
        $fanInfo = AdminUser::getInstance()->get(['id'=>$this->auth['id']]);
        if ($params['action_type'] == 'add') {
            $bool = $userRedis->addUserFollows($key, $user->id);
            $boolFan = $userRedis->addFans(sprintf(UserRedis::USER_FANS, $followUid), $fanInfo->id);
        } else {
            $bool = $userRedis->delUserFollows($key, $user->id);
            $boolFan = $userRedis->delFans(sprintf(UserRedis::USER_FANS, $followUid), $fanInfo->id);
        }


        if ($bool && $boolFan) {
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





    /**
     * 我关注的人的帖子列表
     * @return bool
     */
    public function myFollowUserPosts()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;

        $followUids = UserRedis::getInstance()->getUserFollowings(sprintf(UserRedis::USER_FOLLOWS, $this->auth['id']));
        /**
         * var $followUsers AdminUser
         */

        if (!$followUids) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);

        }


        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('user_id', $followUids, 'in')->field(['id', 'cat_id', 'user_id',  'title', 'img', 'imgs', 'created_at', 'hit', 'fabolus_number', 'content', 'respon_number', 'collect_number'])->getLimit($page, $size);

        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
//        $sql = $model->lastQuery()->getLastQuery();
        $datas = FrontService::handPosts($list, $this->auth['id'] ?: 0);
        $data = ['data' => $datas, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }


    /**
     * 用户消息列表
     * @return bool
     */
    public function userMessageList()
    {
        $uid = $this->auth['id'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminMessage::getInstance()->where('status', AdminMessage::STATUS_DEL, '<>')->where('user_id', $uid)->orderBy('status', 'ASC')->getLimit($page, $size);
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        $returnData = ['data' => $list, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

    /**
     * 用户消息详情
     * @return bool
     */
    public function userMessageInfo()
    {
        $validator = new Validate();
        $validator->addColumn('mid')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $mid = $this->params['mid'];

        $res = AdminMessage::getInstance()->get($mid);
        $returnData['title'] = $res['title'];
        $returnData['content'] = $res['content'];
        $returnData['created_at'] = $res['created_at'];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);


    }


    /**
     * 用户消息中心
     * @return bool
     */
    public function messageCenter()
    {
        $uid = $this->auth['id'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $type = $this->params['type'] ?: 1;
        //清空该类型的未读消息数量
        $userRedis = new UserRedis();
        $userRedis->deleteTypeUnreadCount($type, $uid);
        if (!in_array($type, [1, 2, 3, 4])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        switch ($type) {
            case 1:
                //我的通知

            case 2:
                $model = AdminMessage::getInstance()->where('status', AdminMessage::STATUS_DEL, '<>')->where('user_id', $uid)->where('type', $type)->order('status', 'DESC')->order('created_at', 'ASC')->getLimit($page, $size);
                $formatData = $model->all(null);
                $total = $model->lastQueryResult()->getTotalCount();
                break;
            case 3:
                //赞我的
                $model = AdminPostOperate::getInstance()->where('action_type', AdminPostOperate::ACTION_TYPE_FABOLUS)
                    ->where('author_id', $uid)
                    ->getLimit($page, $size);
                $list = $model->all(null);
                $total = $model->lastQueryResult()->getTotalCount();
                foreach ($list as $fitem) {
                    $data['uid'] = $fitem->user_id;
                    $data['nickname'] = $fitem->uInfo()->nickname;
                    $data['photo'] = $fitem->uInfo()->photo;
                    $data['title'] = $fitem->post_id ? $fitem->postInfo()->title : '';
                    $data['parent_id'] = !$fitem->post_id ? $fitem->commentInfo()->parent_id : 0;
                    $data['content'] = $fitem->post_id ? $fitem->postInfo()->content : $fitem->commentInfo()->content;
                    $data['post_id'] = $fitem->post_id;
                    $data['comment_id'] = $fitem->comment_id;
                    $data['parent_id'] = $fitem->commentInfo()['parent_id'];
                    $data['top_comment_id'] = $fitem->commentInfo()['top_comment_id'];
                    $data['created_at'] = $fitem->created_at;
                    $formatData[] = $data;
                    unset($data);
                }
                break;


            case 4:
                //回复我的
                $model = AdminPostComment::getInstance()->where('t_u_id', $this->auth['id'])
                    ->where('status', AdminPostComment::STATUS_DEL, '<>')
                    ->getAll($page, $size);
                $list = $model->all(null);
                $total = $model->lastQueryResult()->getTotalCount();

                $formatData = FrontService::handComments($list, $uid);
                break;

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data'=>isset($formatData) ? $formatData : [], 'count' => $total]);



    }





    /**
     * 用户反馈
     */
    public function userFeedBack()
    {
        $validator = new Validate();
        $validator->addColumn('content')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $data['content'] = addslashes(htmlspecialchars($this->params['content']));
        $data['user_id'] = $this->auth['id'];
        if (!$this->params['mobile']) {
            $data['mobile'] = $this->auth['mobile'];
        } else {
            $data['mobile'] = $this->params['mobile'];

        }
        if (AdminUserFeedBack::getInstance()->insert($data)) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_ERR, '提交失败，请联系客服');

        }

    }

    public function userSetting(){
        $params = $this->params;
        $validator = new Validate();
        $validator->addColumn('goatNotice')->required()->inArray([0, 1]);
        $validator->addColumn('goatPopup')->required()->inArray([0, 1]);
        $validator->addColumn('redCardNotice')->required()->inArray([0, 1]);
        $validator->addColumn('followUser')->required()->inArray([0, 1]);
        $validator->addColumn('followMatch')->required()->inArray([0, 1]);
        $validator->addColumn('nightModel')->required()->inArray([0, 1]);
        if (!$validator->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        AdminUserSetting::getInstance()->update(
            [
                'goatNotice'    => $params['goatNotice'],
                'goatPopup'     => $params['goatPopup'],
                'redCardNotice' => $params['redCardNotice'],
                'followUser'    => $params['followUser'],
                'followMatch'   => $params['followMatch'],
                'nightModel'    => $params['nightModel'],
            ],
            ['user_id' => $this->auth['id']]
        );

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

    }



    /**
     * 用户消息总数
     * @return bool
     */
    public function userMessTotal()
    {
        $typeTotal = UserRedis::getInstance()->userMessageCountInfo($this->auth['id']);
        $data['total'] = array_sum($typeTotal);
        $typesCount = UserRedis::getInstance()->userUnReadTypes($this->auth['id']);
        $data['types'] = $typesCount;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    public function userInterestCompetition()
    {
        if (!isset($this->params['competition_id']) || !$this->params['competition_id']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);

        }
        $uComs = AdminUserInterestCompetition::getInstance()->where('user_id', $this->auth['id'])->get();
        if ($uComs) {

            $bool = AdminUserInterestCompetition::getInstance()->update(['competition_ids' => $this->params['competition_id']],['id' => $uComs['id']]);

        } else {
            $data = [
                'competition_ids' => $this->params['competition_id'],
                'user_id' => $this->auth['id']
            ];
            $bool = AdminUserInterestCompetition::getInstance()->insert($data);
        }

        if (!$bool) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        }

    }

    /**
     * 用户用户注册完的首次密码设定
     */
    public function setPassword()
    {
        $user = AdminUser::getInstance()->find($this->auth['id']);
        if (!$user || $user->status == 0) {
            return $this->writeJson(Status::CODE_W_STATUS, Status::$msg[Status::CODE_W_STATUS]);

        }
        $password = $this->params['password'];
        $res = preg_match('/(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).{8,16}/', $password);
        if (!$res) {
            return $this->writeJson(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        $user->password_hash = $password_hash;
        if (!$user->update()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        }
    }


    public function changePassword()
    {
        if (!isset($this->params['new_pass'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $password = $this->params['new_pass'];
        $res = preg_match('/(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).{8,16}/', $password);
        if (!$res) {
            return $this->writeJson(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
        }
        $user = AdminUser::getInstance()->find($this->auth['id']);
        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        $user->password_hash = $password_hash;
        if ($user->update()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }

    }


}
<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\lib\pool\User as UserRedis;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminUser;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserPost;
use App\Model\AdminUserSerialPoint;
use App\Model\AdminUserSetting;
use App\Utility\Message\Status;
use EasySwoole\Validate\Validate;


/**
 * 用户个人中心
 * Class UserCenter
 * @package App\HttpController\User
 */
class UserCenter   extends FrontUserController{

    public $needCheckToken = true;
    public $isCheckSign = false;
    /**
     *
     */
    public function userInfo()
    {

        $uid = $this->auth['id'];
        $user_info = AdminUser::getInstance()->where('id', $uid)->field(['id', 'nickname', 'photo', 'level'])->get();
        //我的粉丝数
        $fansCount = UserRedis::getInstance()->myFansCount($this->auth['id']);


        //我的关注数
        $followCount = UserRedis::getInstance()->myFollowings($this->auth['id']);

        //我的获赞数
        $post_count = AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::STATUS_NORMAL)->sum('fabolus_number');
        $post_comment_count = AdminPostComment::getInstance()->where('user_id', $uid)->where('status', AdminPostComment::STATUS_NORMAL)->sum('fabolus_number');
        $information_comments = AdminInformationComment::getInstance()->where('user_id', $uid)->where('status', AdminInformationComment::STATUS_NORMAL)->sum('fabolus_number');
        $fabolus_number = ($post_count+$post_comment_count+$information_comments);

        $data = [
            'user_info' => $user_info,
            'fans_count' => AppFunc::changeToWan($fansCount),
            'follow_count' => AppFunc::changeToWan($followCount),
            'fabolus_count' => AppFunc::changeToWan($fabolus_number),
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);


    }

    /**
     * 收藏夹
     * @return bool
     */
    public function userBookMark()
    {

        $uid = $this->auth['id'];
        if (!$uid) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $key_word = $this->params['key_word'];
        $operate = AdminUserOperate::getInstance()->where('user_id', $uid)->where('type', AdminUserOperate::TYPE_BOOK_MARK)->all();
        if ($operate) {
            foreach ($operate as $item) {

               if ($item['item_type'] == 1) {//帖子
                   if (!$key_word) {
                       $post = AdminUserPost::getInstance()->find($item['item_id']);
                   } else {
                       $post = AdminUserPost::getInstance()->where('title', '%' .$key_word . '%', 'like')->find($item['item_id']);

                   }
                    $posts[] = $post;
               } else if ($item['report_type'] == 3) {//资讯
                   if (!$key_word) {
                       $information = AdminInformation::getInstance()->find($item['item_id']);
                   } else {
                       $information = AdminInformation::getInstance()->where('title', '%' . $key_word . '%', 'like')->find($item['item_id']);
                   }
                   $informations[] = $information;

               } else {
                   continue;
               }


            }
            $format_posts = FrontService::handPosts($posts, $this->auth['id'] ?: 0);
            $format_informations = FrontService::handInformation($informations, $this->auth['id'] ?: 0);
            $data = ['posts' => $format_posts, 'informations' => $format_informations];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

        }
    }


    /**
     * 草稿箱列表
     * @return bool
     */
    public function drafts()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 20;

        $model = AdminUserPost::getInstance()->where('status', [AdminUserPost::STATUS_SAVE, AdminUserPost::STATUS_EXAMINE_FAIL], 'in')->where('user_id', $this->auth['id'])->getLimit($page, $size);

        $list = $model->all(null);
        $count = $model->lastQueryResult()->getTotalCount();
        $returnData = ['data' => $list, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);
    }

    /**
     * 黑名单
     * @return bool
     */
    public function myBlackList()
    {
        $uids = UserRedis::getInstance()->smembers(sprintf(UserRedis::USER_BLACK_LIST, $this->auth['id']));
        $users = AdminUser::getInstance()->where('id', json_decode($uids, true), 'in')->field(['id', 'nickname', 'photo'])->all();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $users);

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
        if (!$bool) {
            return $this->writeJson(Status::CODE_USER_DATA_CHG, Status::$msg[Status::CODE_USER_DATA_CHG]);

        } else {
            return $this->writeJson(Status::CODE_OK, '', $uInfo);

        }



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


                break;
            case 2:
                //我的通知
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
     * 关注及粉丝列表
     * @return bool
     */
    public function myFollowings()
    {

        if (!$this->params['type']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $userRedis = new UserRedis();
        $uid = isset($this->params['uid']) ? $this->params['uid'] : $this->auth['id'];
        if ($this->params['type'] == 1) {
            $key = sprintf(UserRedis::USER_FOLLOWS, $uid);

        } else {
            $key = sprintf(UserRedis::USER_FANS, $uid);

        }
        $myFollowings = $userRedis->getUserFollowings($key);
        if (!$myFollowings) {
            $users = [];
        } else {
            $users = AdminUser::getInstance()->where('id', $myFollowings, 'in')->field(['id', 'nickname', 'photo'])->all();

        }

        if ($users) {
            foreach ($users as $user) {
                $item['is_follow'] = UserRedis::getInstance()->isFollow($this->auth['id'], $user['id']);
                $item['is_me'] = ($user['id'] == $this->auth['id']) ? true : false;
                $item['id'] = $user['id'];
                $item['nickname'] = $user['nickname'];
                $item['photo'] = $user['photo'];
                $data[] = $item;
            }
        } else {
            $data = [];
        }
        $count = $userRedis->scard($key);
        $returnData['data'] = $data;
        $returnData['count'] = $count;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

    public function userSetting()
    {
        if ($this->request()->getMethod() == 'GET') {
            $setting = AdminUserSetting::getInstance()->where('user_id', $this->auth['id'])->get();
            $data = [
                'notice' => json_decode($setting->notice, true),
                'push' => json_decode($setting->push, true),
                'private' => json_decode($setting->private, true),
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {

            $notice = json_decode($this->params['notice'], true);
            $private = json_decode($this->params['private'], true);
            $push = json_decode($this->params['push'], true);
            if (!isset($notice['start']) || !isset($notice['goal']) || !isset($notice['over'])
            || !isset($private['start']) || !isset($private['goal']) || !isset($private['over'])
                || !isset($push['start']) || !isset($push['goal']) || !isset($push['over'])
            ) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            $data = [
                'user_id' => $this->auth['id'],
                'notice' => $this->params['notice'],
                'push' => $this->params['push'],
                'private' => $this->params['private'],
            ];
            if (!$user_setting = AdminUserSetting::getInstance()->where('user_id', $this->auth['id'])->get()) {

                AdminUserSetting::getInstance()->insert($data);
            } else {
                AdminUserSetting::getInstance()->update($data, ['user_id' => $this->auth['id']]);
            }
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);


        }
    }

    /**
     * 修改密码
     * @return bool
     * @throws \Exception
     */
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


    /**
     * 用户被点赞列表 包括帖子与评论
     */

    public function myFabolusInfo()
    {

        $params = $this->params;
        $valitor = new Validate();

        $valitor->addColumn('type')->required()->inArray(["1", "2", "3", "4", "5" , "6"]);
        $valitor->addColumn('item_type')->required()->inArray([1,2,4]); //1帖子 2帖子评论 4资讯评论
        if (!$valitor->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $uid = $this->auth['id'];
        //帖子
        $posts = AdminUserOperate::getInstance()->func(function ($builder) use($uid){
            $builder->raw('select o.created_at, o.item_type, o.user_id, p.id, p.title from `admin_user_operates` o left join `admin_user_posts` p on o.author_id=p.user_id where o.item_id=p.id and o.type=? and o.item_type=?   and o.author_id=? ',[1, 1, $uid]);
            return true;
        });
        if ($posts) {
            foreach ($posts as $k=>$post) {
                $user = AdminUser::getInstance()->find($post['user_id']);
                $posts[$k]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo];
            }
        }
        //帖子评论
        $post_comments = AdminUserOperate::getInstance()->func(function ($builder) use($uid){
            $builder->raw('select m.*, o.user_id, o.created_at, o.item_type from `admin_user_operates` o left join (select c.id, c.content, p.title from `admin_user_post_comments` c left join `admin_user_posts` p on  c.post_id=p.id) m on o.item_id=m.id where o.type=? and o.item_type=? and o.author_id=?',[1, 2, $uid]);
            return true;
        });
        if ($post_comments) {
            foreach ($post_comments as $kc=>$comment) {
                $user = AdminUser::getInstance()->find($comment['user_id']);

                $post_comments[$kc]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->nickname];
            }
        }

        //资讯评论
        $information_comments = AdminUserOperate::getInstance()->func(function ($builder) use($uid){
            $builder->raw('select m.*, o.user_id, o.created_at, o.item_type from `admin_user_operates` o left join (select c.id, c.content, i.title from `admin_information_comments` c left join `admin_information` i on  c.information_id=i.id) m on o.item_id=m.id where o.type=? and o.item_type=? and o.author_id=?',[1, 4, $uid]);
            return true;
        });

        if ($information_comments) {
            foreach ($information_comments as $ic=>$icomment) {
                $user = AdminUser::getInstance()->find($icomment['user_id']);

                $information_comments[$kc]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->nickname];
            }
        }
        $result = array_merge($posts, $post_comments, $information_comments);
        $creates_at = array_column($result, 'created_at');
        array_multisort($creates_at, SORT_DESC, $result);



        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }
}
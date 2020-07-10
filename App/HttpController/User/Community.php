<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\lib\pool\User as UserRedis;
use App\Model\AdminMessage;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
use App\Task\LoginTask;
use App\Utility\Log\Log;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use App\Utility\Message\Status;
use function FastRoute\TestFixtures\all_options_cached;

class Community extends FrontUserController
{
    /**
     * 社区板块
     * @var bool
     */
    protected $isCheckSign = true;
    public $needCheckToken = true;

    public function pLists()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminUserPost::getInstance();

        $model = $model->where('status', AdminUserPost::STATUS_DEL, '<>')->field(['title', 'img', 'created_at'])->order('is_top', 'desc')->getLimit($page, $size);
        $list = $model->all(null);
        $result = $model->lastQueryResult();
        $total = $result->getTotalCount();
//        $sql = $model->lastQuery()->getLastQuery();
        $returnData['posts'] = ['data'=>$list, 'count'=>$total];
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $sql);

        //帖子分类
        $catModel = AdminUserPostsCategory::getInstance();
        $data = $catModel->where('status', AdminUserPostsCategory::STATUS_NORMAL)->all(null);
        $count = $catModel->count();
        $returnData['cats'] = ['data'=>$data, 'count'=>$count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);


    }

    //公告和精华帖子
    public function messAndRefinePosts()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminUserPost::getInstance();
        $model = $model->where('status', AdminUserPost::STATUS_DEL, '<>')->where('is_refine', AdminUserPost::IS_REFINE)->field(['title', 'img', 'created_at'])->getLimit($page, $size);

        $list = $model->all(null);

        $total = $model->lastQueryResult()->getTotalCount();
        $returnData['refine_posts'] = ['data' => $list, 'count' => $total];
        //公告
        $mess = AdminMessage::getInstance()->where('status', AdminMessage::STATUS_NORMAL)->field(['title', 'content', 'created_at'])->order('created_at', 'DESC')->limit(1)->get();

        if ($mess) {
            $returnData['mess'] = $mess;

        } else {
            $returnData['mess'] = [];

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }


    // 关注列表
    public function myFollowings()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);

        }

        $userRedis = new UserRedis();
        $uid = $this->auth['id'];
        $key = sprintf(UserRedis::USER_FOLLOWS, $uid);
        $myFollowings = $userRedis->getUserFollowings($key);

        if ($myFollowings) {
            foreach ($myFollowings as $item) {
                $data[] = json_decode($item, true);
            }
        } else {
            $data = [];
        }
        $count = $userRedis->scard($key);
        $returnData['data'] = $data;
        $returnData['count'] = $count;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

}
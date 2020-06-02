<?php


namespace App\HttpController\User;

use App\Model\AdminCategory;
use App\Model\AdminMessage as MessageModel;
use App\Base\FrontUserController;
use App\Task\MessageTask;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;

class System extends FrontUserController
{
    public $needCheckToken = true;
    public $isCheckSign = true;
    /**
     * 获取系统公告
     */
    public function index()
    {
        $params = $this->request()->getQueryParams();
        $query = MessageModel::getInstance()->where('cate_id', AdminCategory::CATEGORY_ANNOCEMENT)
            ->where('status', 1);

        $count = $query->count();
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['offset']) ? $params['offset'] : 10;
        $data = $query->field('id, title, cate_name, status,created_at')->order('created_at', 'desc')->findAll($page, $limit);
        $this->writeJson(Status::CODE_OK, 'ok', [
            'data' => $data,
            'count' => $count
        ]);
    }

    /**
     * 公告详情
     * @param $id
     */
    public function detail()
    {
        $id = $this->request()->getRequestParam('id');
        $info = MessageModel::getInstance()->find($id);
        if (!$info) {
            $this->writeJson(Status::CODE_ERR, '对应公告不存在');
            return ;
        }
        //写异步task记录已读
        $auth = $this->auth;
        TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($info, $auth){
            $messageTask = new MessageTask([
                'message_id' => $info['id'],
                'message_title' => $info['title'],
                'user_id' => $auth['id'],
                'mobile' => $auth['mobile'],
            ]);
            $messageTask->execData();
        });
        $this->writeJson(Status::CODE_OK, 'ok', $info);
    }

}
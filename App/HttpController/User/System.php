<?php


namespace App\HttpController\User;

use App\Model\AdminAdvertisement;
use App\Model\AdminCategory;
use App\Model\AdminMessage as MessageModel;
use App\Base\FrontUserController;
use App\Model\AdminSysSettings;
use App\Model\AdminSystemAnnoucement;
use App\Task\MessageTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;

class System extends FrontUserController
{
    public $needCheckToken = false;
    public $isCheckSign = false;

    const SYS_KEY_HOT_RELOAD = 'hot_reload';
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

    function hotreload()
    {
        if (isset($this->params['version'])) {
            $package = AdminSysSettings::getInstance()->order('created_at', 'DESC')->where('sys_key', self::SYS_KEY_HOT_RELOAD)->limit(1)->get();
            if (!$package) {
                $data['is_new'] = 1;
            } else {
                $value = json_decode($package->sys_value, true);
                $version = $this->params['version'];
                $sysVer = $value['version'];
                $idff = version_compare($version, $sysVer);
                $data['is_new'] = $idff;
                $accountment = AdminSystemAnnoucement::getInstance()->field(['id', 'title', 'content', 'created_at'])->where('id', $value['accoucement_id'])->get();
                $sql = AdminSystemAnnoucement::getInstance()->lastQuery()->getLastQuery();

                $data['accoucement'] = $accountment;

            }
            $data['wgt_url'] = 'http://download.ymtyadmin.com/WGT/__UNI__0AC1311.wgt';
            $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
    }

    public function adImgs()
    {
        $data = [
            'img' => 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/e44e1023d520f507.jpg',
            'url' => 'https://www.baidu.com',
            'countDown' => 3,
            'is_force' => false,
            'is_open' => true,
        ];
        $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 广告列表
     */
    public function advertisement()
    {
        if (!isset($this->params['cat_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $ads = AdminAdvertisement::getInstance()->where('status', AdminAdvertisement::STATUS_NORMAL)->where('cat_id', $this->params['cat_id'])->all();
        $sql = AdminAdvertisement::getInstance()->lastQuery()->getLastQuery();
        $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $ads);

    }

}
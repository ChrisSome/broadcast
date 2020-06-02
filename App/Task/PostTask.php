<?php


namespace App\Task;


use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminUserPost;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class PostTask implements TaskInterface
{
    protected $taskData;
    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    public $type = 1;
    public $action_type = 'fabolus';

    function run(int $taskId, int $workerIndex)
    {
        // TODO: Implement run() method.
        $type = isset($this->taskData['type']) ? $this->taskData['type'] : 'insert';
        $this->action_type = $type;
        switch ($type) {
            case 'update':
                break;
            case 'fabolus':
                //点赞
                $this->type = 1;
                break;
            case 'collect':
                $this->type = 2;
                //收藏
                break;
            case 'waring':
                $this->type = 2;
                //举报
                break;
            case 'unbind_fabolus':
                //点赞
                $this->type = 1;
                break;
            case 'unbind_collect':
                $this->type = 2;
                //收藏
                break;
            case 'unbind_waring':
                $this->type = 3;
                //举报
                break;
            default:

                break;
        }
        $this->execData();
    }


    private function execData()
    {
        try {
            if (in_array($this->type, [1, 2, 3])) {
                if (!empty($this->taskData['basic'])) {
                    var_dump( preg_match('/^unbind/', $this->action_type) ? 0 : 1);
                    $rs = AdminPostOperate::getInstance()->where('user_id', $this->taskData['user_id'])
                    ->where('post_id', $this->taskData['post_id'])
                    ->where('action_type', $this->type)
                    ->update([
                        'status' => preg_match('/^unbind/', $this->action_type) ? 0 : 1,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $insertData = [
                        'action_type' => $this->type,
                        'user_id' => $this->taskData['user_id'],
                        'mobile' => $this->taskData['mobile'],
                        'post_id' => $this->taskData['post_id'],
                        'post_title' => $this->taskData['post_title'],
                        'status' => 1
                    ];
                    $rs = AdminPostOperate::getInstance()->insert($insertData);
                }
                if ($rs) {
                    if  ($this->action_type == 'fabolus') {
                        $basic = AdminUserPost::getInstance()->find($this->taskData['post_id']);
                        AdminUserPost::getInstance()->where('id', $this->taskData['post_id'])
                            ->update([
                                'fabolus_number' => $basic['fabolus_number'] + 1,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    } else if ($this->action_type == 'unbind_fabolus') {
                        $basic = AdminUserPost::getInstance()->find($this->taskData['post_id']);
                        AdminUserPost::getInstance()->where('id', $this->taskData['post_id'])
                            ->update([
                                'fabolus_number' => $basic['fabolus_number'] - 1,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    }
                }
            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }

    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}
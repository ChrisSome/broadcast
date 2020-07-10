<?php


namespace App\Task;


use App\Model\AdminPostComment;
use App\Model\AdminUserPost;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

class CommentTask implements TaskInterface
{
    protected $taskData;
    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    function run(int $taskId, int $workerIndex)
    {
        // TODO: Implement run() method.
        $type = isset($this->taskData['type']) ? $this->taskData['type'] : 'insert';
        switch ($type) {
            case 'update':
                $this->updateSome();
                break;
            default:
                $this->insertComment();
                break;
        }
    }

    /**
     * 更新评论统计
     */
    public function updateSome()
    {
        $task = $this->taskData;
        try {
            if($task['status'] == 1) {
                $data =  AdminPostComment::create()->func(function ($builder) use ($task) {
                    $builder->raw('update  admin_user_post_comments set next_count = next_count+1 where id in(?)',[
                        $task['path']
                    ]);
                });
            } else {
                $data =  AdminPostComment::create()->func(function ($builder) use ($task) {
                    $builder->raw('update  admin_user_post_comments set next_count = next_count-1 where id in(?)',[
                        $task['path']
                    ]);
                });
            }
          /*  AdminPostComment::getInstance()->where('find_in_set(?, path)', $this->taskData['parent_id'])
                ->update(['next_count' => $this->taskData['status'] == 1 ? "next_count + 1" : "next_count - 1"]);*/
        } catch (\Exception $e) {
            var_dump($e->getTraceAsString(), $e->getMessage());
        }


    }

    /**
     * @return mixed
     */
    private function insertComment()
    {
        $res['status'] = 'succ';
        $res['msg'] = 'msg';
        try {
            unset($this->taskData['type']);
            $rs = AdminPostComment::getInstance()->insert($this->taskData);
            if (!$rs) {
                $res['status'] = 'fail';
                $res['msg'] = AdminPostComment::getInstance()->tError();

            }
        } catch (\Exception $e) {
            $res['status'] = 'fail';
            $res['msg'] = AdminPostComment::getInstance()->tError();
        }
        Log::getInstance()->info('用户评论 data:' . json_encode($this->taskData) . ',res : ' . json_encode($res));
        return $res;

    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}
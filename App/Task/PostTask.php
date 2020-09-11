<?php


namespace App\Task;


use App\lib\pool\User as UserRedis;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminUser;
use App\Utility\Log\Log;
use App\Model\AdminPostOperate;
use App\Model\AdminUserPost;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use mysql_xdevapi\Exception;
use EasySwoole\ORM\DbManager;


class PostTask implements TaskInterface
{
    protected $taskData;
    protected $author_id;
    protected $cpData;
    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    public $type = 1;

    function run(int $taskId, int $workerIndex)
    {
        // TODO: Implement run() method.

        $this->exec();
//        if ($this->taskData['action_type'] == 1) {
//            $this->noticeUser(); //通知用户
//
//        }

    }

    /**
     * 对帖子的操作
     */
    private function exec()
    {


        try{
//            DbManager::getInstance()->startTransaction();
            if ($this->taskData['post_id']) {
                $model = AdminUserPost::getInstance();
                $id = $this->taskData['post_id'];
            } else {
                $model = AdminPostComment::getInstance();
                $id = $this->taskData['comment_id'];
            }
            //帖子或评论内容
            $data = $model->find($id);

            if (in_array($this->taskData['action_type'], [1, 2, 3])) {

                if (!$this->taskData['op_id']) {
                    //新增
                    $insertData = [
                        'action_type' => $this->taskData['action_type'],
                        'user_id' => $this->taskData['user_id'],
                        'img' => $this->taskData['img'],
                        'content' => $this->taskData['content'],
                        'status' => 0,
                        'author_id' => $data->user_id,
                        'post_id' => $this->taskData['post_id'],
                        'comment_id' => $this->taskData['comment_id'],
                    ];
                    AdminPostOperate::getInstance()->insert($insertData);
                    $this->author_id = $data->user_id;
                } else {
                    $res = AdminPostOperate::getInstance()->update([
                        'action_type' => $this->taskData['action_type']
                    ],
                        [
                            'id' => $this->taskData['op_id']
                        ]
                    );
                    $sql = AdminPostOperate::getInstance()->lastQuery()->getLastQuery();
                    Log::getInstance()->info('operate sql ' . $sql . ' ope res ' . $res);

                }


                //修改点赞收藏数
                if ($this->taskData['action_type'] == 1) {
                    //增加消息数  type=3
                    (new UserRedis())->userMessageAddUnread(3, $this->taskData['author_id']);
                    $model->update([
                        'fabolus_number' => QueryBuilder::inc(1)
                    ], [
                        'id' => $id
                    ]);
                    $sql = $model->lastQuery()->getLastQuery();
                    Log::getInstance()->info('点赞结果' . $sql);
                } else if($this->taskData['action_type'] == 2) {
                    $model->update([
                        'collect_number' => QueryBuilder::inc(1)
                    ], [
                        'id' => $this->taskData['post_id']
                    ]);

                } else {
                    //举报
                    Log::getInstance()->info('点赞或收藏失败');
                }




            } else if(in_array($this->taskData['action_type'], [4, 5, 6])) {

                //修改点赞收藏数
                if ($this->taskData['action_type'] == 4) {
                    $model->update([
                        'fabolus_number' => QueryBuilder::dec(1)
                    ], [
                        'id' => $id
                    ]);

                } else if ($this->taskData['action_type'] == 5) {
                    //收藏数
                    $model->update([
                        'collect_number' => QueryBuilder::dec(1)
                    ], [
                        'id' => $this->taskData['post_id']
                    ]);
                }
                Log::getInstance()->info('task data is' . json_encode($this->taskData));
                AdminPostOperate::getInstance()->update([
                    'action_type' => $this->taskData['action_type']
                ],
                    [
                        'id' => $this->taskData['op_id']
                    ]
                );


                $sql = AdminPostOperate::getInstance()->lastQuery()->getLastQuery();
                Log::getInstance()->info('sql1111' . $sql);
            } else {

                throw new Exception('type类型错误');

            }

//            DbManager::getInstance()->commit();
        } catch (\Exception $e) {
//            DbManager::getInstance()->rollback();

            Log::getInstance()->info('操作错误 ' . $e->getMessage());
        }
    }

    /**
     * 点赞 收藏 举报中，只有点赞会通知用户
     * 通知用户
     */
    public function noticeUser()
    {
        if ($user = AdminUser::getInstance()->find($this->author_id)) {

            $data['content'] = $user->nickname .'赞了您的' . $this->taskData['post_id'] ? ('帖子<<' . $this->cpData['title'] . '>>') : '回复';
            $data['type'] = AdminMessage::TYPE_FABOLUS;
        }
    }

    /**
     * 对评论的操作
     * @return bool
     *
     */

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Log::getInstance()->info('code ' . $throwable->getMessage());
    }
}
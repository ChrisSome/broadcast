<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;
use EasySwoole\Mysqli\QueryBuilder;
use App\Utility\Log\Log;

class AdminUserPost extends BaseModel
{
    protected $tableName = "admin_user_posts";
    const STATUS_NORMAL     = 0;        //用户发布成功/审核处理中
    const STATUS_HANDING    = 1;        //处理中（举报）
    const STATUS_DEL        = 2;        //删除
    const STATUS_EXAMINE_SUCC        = 4;        //审核成功（展示）
    const STATUS_EXAMINE_FAIL        = 3;        //审核失败
    const STATUS_SAVE = 5; //保存草稿箱

    const IS_TOP        = 1; //置顶
    const IS_UNTOP      = 0; //非置顶
    const IS_REFINE     = 1; //加精
    const IS_UNREFINE   = 0; //非加精
    const IS_REPRINT    = 1; //转载
    public static $statusAccusation = [self::STATUS_HANDING, self::STATUS_DEL];        //举报状态
    public static $statusExamine = [self::STATUS_NORMAL, self::STATUS_EXAMINE_SUCC, self::STATUS_EXAMINE_FAIL];//审核状态

    const NEW_STATUS_NORMAL = 1;//正常
    const NEW_STATUS_REPORTED = 2; //被举报
    const NEW_STATUS_DELETED = 3;//删除
    const NEW_STATUS_SAVE = 4; //保存

    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function getLimit($page, $limit, $order = 'created_at', $desc = 'DESC')
    {
        return $this->order($order, $desc)
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }
    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    /**
     * 转换where or条件
     * @param string $col
     * @param array $where
     * @return string
     */
    public function getWhereArray(string $col, array $where)
    {
        if (!$where) return '';
        foreach ($where as $v) {
            $col .= ('=' . $v . ' or ');
        }
        return '(' . rtrim($col, 'or ') . ')';
    }


    //发帖人信息
    public function userInfo() {
        $res = $this->hasOne(AdminUser::class, null, 'user_id', 'id');
        if ($res) {
            $data = $res->field(['id', 'photo', 'nickname']);
            return $data;
        } else {
            return [];
        }
//        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['id', 'photo', 'nickname']);

    }

    //用户是否收藏
    public function isCollect($uid, $pid) {

        return $this->hasOne(AdminPostOperate::class, function(QueryBuilder $queryBuilder) use($uid, $pid) {
            $queryBuilder->where('action_type', 2);
            $queryBuilder->where('user_id', $uid);
            $queryBuilder->where('post_id', $pid);
        }, 'id', 'post_id');

    }

    //是否赞过
    public function isFablous($uid, $pid) {
        return $this->hasOne(AdminPostOperate::class, function (QueryBuilder $queryBuilder) use($uid, $pid) {
            $queryBuilder->where('action_type', 1);
            $queryBuilder->where('user_id', $uid);
            $queryBuilder->where('post_id', $pid);
        }, 'id', 'post_id');

    }

    /**
     * 帖子所属板块信息
     * @return mixed|null
     * @throws \Throwable
     */
    public function postCat()
    {
        return $this->hasOne(AdminUserPostsCategory::class, null, 'cat_id', 'id');
    }

    /**
     * 帖子回复数
     * @return mixed|null
     */
    public function commentCountForPost()
    {
        return $this->hasMany(AdminPostComment::class, function(QueryBuilder $queryBuilder){
            $queryBuilder->where('action_type', 1);
        }, 'id', 'post_id');
    }

    //帖子最新回复
    public function getLastResTime($pid) {
        $data = $this->hasOne(AdminPostComment::class, function(QueryBuilder $queryBuilder) use($pid) {
            $queryBuilder->where('post_id', $pid);
            $queryBuilder->orderBy('created_at', 'DESC');
            $queryBuilder->limit(1);
        }, 'id', 'post_id');

        if ($data) {
            return $data->created_at;
        } else {
            return '';
        }
    }



    //根据主键id传
    public function findByPk($id) {
        if (!$id) {
            return false;
        }
        $where = ['id'=>$id, 'status'=>self::STATUS_EXAMINE_SUCC];
        return $this->get($where);
//        return $this->where('id', $id)->all();
    }




}

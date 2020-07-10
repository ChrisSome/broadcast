<?php
/**
 * @property boolean isRookie   是否新手资产
 */
namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;
use EasySwoole\Mysqli\QueryBuilder;
use App\Utility\Log\Log;
use think\db\Query;


class AdminPostComment extends BaseModel
{
    protected $tableName = "admin_user_post_comments";
    protected $relationT = "admin_user";

    const STATUS_NORMAL = 0;        //正常
    const STATUS_EXAMING = 1;       //审核中
    const STATUS_DEL = 2;           //删除


    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit)
            ->all();
    }

    public function getAll($page, $limit)
    {
        return $this->orderBy('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit)
            ->withTotalCount();
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    //回复用户信息
    public function uInfo()
    {
        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['mobile', 'photo', 'nickname']);
    }

    //被回复用户信息
    public function tuInfo()
    {
        return $this->hasOne(AdminUser::class, null, 't_u_id', 'id')->field(['mobile', 'photo', 'nickname']);

    }

    public function postInfo()
    {
        return $this->hasOne(AdminUserPost::class, null, 'post_id', 'id')->field(['title', 'content', 'created_at']);

    }

    public function setting()
    {
        return $this->belongsToMany(AdminUser::class, 'admin_user', 'id', 'id');
        $query = new QueryBuilder();
        Log::getInstance()->info('222' . !$query->getLastPrepareQuery());
        $res = $this->hasOne(AdminUser::class, function($query){
            $query->where('id', 1);
        });
        return $res;
    }


    public function belong()
    {
        return $this->belongsToMany(AdminUser::class, 'admin_user','id', 'id', function(QueryBuilder $query){
            $query->limit(10);
        });
    }


}

<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;

class AdminUserPost extends BaseModel
{
    protected $tableName = "admin_user_posts";
    const STATUS_NORMAL     = 0;        //用户发布成功/审核处理中
    const STATUS_HANDING    = 1;        //处理中（举报）
    const STATUS_DEL        = 2;        //删除
    const STATUS_EXAMINE_SUCC        = 4;        //审核成功（展示）
    const STATUS_EXAMINE_FAIL        = 3;        //审核失败

    const IS_TOP        = 1; //置顶
    const IS_UNTOP      = 0; //非置顶
    const IS_REFINE     = 1; //加精
    const IS_UNREFINE   = 0; //非加精
    const IS_REPRINT    = 1; //转载
    public static $statusAccusation = [self::STATUS_HANDING, self::STATUS_DEL];        //举报状态
    public static $statusExamine = [self::STATUS_NORMAL, self::STATUS_EXAMINE_SUCC, self::STATUS_EXAMINE_FAIL];//审核状态
    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit)
            ->all();
    }

    public function getLimit($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $page, $limit)
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


    public function userInfo(){

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

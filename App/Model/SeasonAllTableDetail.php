<?php

namespace App\Model;
use App\Base\BaseModel;
use App\Base\FatherModel;
use EasySwoole\Mysqli\QueryBuilder;

class SeasonAllTableDetail  extends BaseModel
{
    protected $tableName = "season_all_table_detail";


    public function getLimit($page, $limit)
    {
        return $this->order('match_time', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }






}
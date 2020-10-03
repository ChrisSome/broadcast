<?php
namespace App\Model;

use App\Base\BaseModel;

class AdminTeam  extends BaseModel
{
    protected $tableName = "admin_team_list";

    public function getCountry()
    {
        return $this->hasOne(AdminCountryList::class, null, 'country_id', 'country_id');
    }
}
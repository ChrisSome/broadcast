<?php


namespace App\HttpController\Admin\Core;


use App\Base\AdminController;
use App\Common\AppFunc;
use App\Model\AdminCompetition;
use App\Model\AdminInterestMatches;
use App\Model\AdminPlay as PlayModel;
use App\Model\AdminSysSettings;
use App\Model\AdminUserInterestCompetition;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;

class Competition extends AdminController
{
    private $rule_rule      = 'core.play';
    private $rule_rule_view = 'core.play.list';
    private $rule_rule_add  = 'core.play.add';
    private $rule_rule_set  = 'core.play.set';
    private $rule_rule_del  = 'core.play.del';


    public function index()
    {
        if(!$this->hasRuleForGet($this->rule_rule_view)) return ;

        $this->render('admin.setting.competition.index');
    }

    public function getAll()
    {
        if(!$this->hasRuleForPost($this->rule_rule_view)) return ;

        $data = PlayModel::getInstance()->all();
        $data = ['code' => Status::CODE_OK, 'data' => $data];

        $this->dataJson($data);
    }

    public function list()
    {
        $recommondComs = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get();
        $competitions = json_decode($recommondComs['sys_value'], true);

        foreach ($competitions as $item) {
            foreach ($item as $value) {
                $competitiones[] = $value;
            }

        }
        $data = ['code' => Status::CODE_OK, 'data' => $competitiones];

        $this->dataJson($data);

    }

    public function info()
    {
        $short_name_zh = $this->request()->getRequestParam('short_name_zh');
        $is_hot = $this->request()->getRequestParam('is_hot');
        $competition = AdminCompetition::getInstance()->where('short_name_zh', $short_name_zh)->get();
        if ($competition) {
            $data = ['code' => Status::CODE_OK, 'data' => $competition, 'is_hot' => isset($is_hot) ? $is_hot : 0];

        } else {
            $data = ['code' => Status::CODE_ERR];

        }
        $this->dataJson($data);

    }

    public function add()
    {
        $cid = $this->request()->getRequestParam('cid');
        $is_hot = $this->request()->getRequestParam('is_hot');
        $competition = AdminCompetition::getInstance()->where('competition_id', $cid)->get();
        if (!$competition) {
            $data = ['code' => Status::CODE_ERR];
            $this->dataJson($data);
            return;
        }
        $recommondComs = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get();
        $competitions = json_decode($recommondComs['sys_value'], true);

        if (isset($is_hot) && $is_hot == 1) {
            $arr = ['competition_id' => $cid, 'short_name_zh' => $competition->short_name_zh];
            if ($competitions && $competitions['hot']) {
                $cids = array_column($competitions['hot'], 'competition_id');
                if (in_array($cid, $cids)) {
                    $data = ['code' => Status::CODE_OK];
                    $this->dataJson($data);
                    return;
                }
                array_push($competitions['hot'], $arr);
            } else {
                $competitions['hot'][] = $arr;
            }
            $recommondComs->sys_value = json_encode($competitions);
            $recommondComs->update();
            $data = ['code' => Status::CODE_OK];
            $this->dataJson($data);
        }
        $firstCharter = AppFunc::getFirstCharters($competition->short_name_zh);
        $arr = ['competition_id' => $cid, 'short_name_zh' => $competition->short_name_zh];
        foreach ($competitions as $k=>$val) {
            if ($k == $firstCharter) {
                $cids = array_column($val, 'competition_id');
                if (in_array($cid, $cids)) {
                    $data = ['code' => Status::CODE_OK];
                    $this->dataJson($data);return;
                }
                $competitions[$k][] = $arr;
                break;
            }
        }
        AdminCompetition::getInstance()->update(['sys_value' => json_encode($competitions)], ['sys_key' => 'recommond_com']);
        $data = ['code' => Status::CODE_OK];
        $this->dataJson($data);



    }




    public function del()
    {
        $cid = $this->request()->getRequestParam('cid');
        $competition = AdminCompetition::getInstance()->where('competition_id', $cid)->get();
        if (!$competition) {
            $data = ['code' => Status::CODE_ERR];
            $this->dataJson($data);
            return;
        }

        $recommondComs = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get();
        $competitions = json_decode($recommondComs['sys_value'], true);
        foreach ($competitions as $k=>$val) {
            foreach ($val as $ki=>$item) {
                if ($item['competition_id'] == $cid) {
                    unset($competitions[$k][$ki]);
                } else {
                    continue;
                }
            }
            $competitions[$k] = array_merge($competitions[$k]);

        }
        $recommondComs->sys_value = json_encode($competitions);
        $recommondComs->update();

        $data = ['code' => Status::CODE_OK];
        $this->dataJson($data);
        return;
    }


    public function save()
    {
        $recommondComs = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get();
        $competitions = json_decode($recommondComs['sys_value'], true);
        $competitionids = [];
        foreach ($competitions as $item) {
            foreach ($item as $val) {
                $competitionids[] = $val['competition_id'];
            }
        }
        if ($competitionids) {

            //异步改变所有用户关注的比赛
            TaskManager::getInstance()->async(function () use($competitionids){
                AdminUserInterestCompetition::getInstance()->update(['competition_ids' => json_encode($competitionids)], null, true);

            });
        }
        $data = ['code' => Status::CODE_OK];
        $this->dataJson($data);
    }
}
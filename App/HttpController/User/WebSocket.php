<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\HttpController\Match\FootballApi;
use App\lib\FrontService;
use App\lib\pool\User as UserRedis;
use App\lib\Tool;
use App\Model\AdminMatch;
use App\Model\AdminUser;
use App\Storage\MatchLive;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use \Swoole\Coroutine\Http\Client;

class WebSocket extends FrontUserController
{

    public $needCheckToken = false;
    protected $isCheckSign = false;

    protected $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
    protected $user = 'mark9527';
    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    public function index()
    {
        $this->render('front.websocket.index', [
//            'server' => 'ws://192.168.254.103:9504'
            'server' => 'ws://8.210.195.192:9504'
        ]);
    }




    public function test()
    {
//        $res = MatchLive::getInstance()->table();
        $res = Tool::getInstance()->postApi(sprintf($this->uriM, $this->user, $this->secret, '20200907'));
        $resDecode = json_decode($res, true);

//        $resp = MatchLive::getInstance()->get(3398343);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resDecode);

        //        $todayMatch =
        $todayTime = strtotime(date('Y-m-d', time()));
        $tomorrowTime = strtotime(date('Y-m-d',strtotime('+1 day')));
        $afterTomorrowTime = strtotime(date('Y-m-d',strtotime('+2 day')));
        $hotCompetition = FrontService::getHotCompetitionIds();
        $playingMatch = AdminMatch::getInstance()->where('status_id', FootballApi::STATUS_PLAYING, 'in')->where('match_time', $todayTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();

        $todayMatch = AdminMatch::getInstance()->where('match_time', $todayTime, '>')->where('match_time', $tomorrowTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $sql = AdminMatch::getInstance()->lastQuery()->getLastQuery();

        $tomorrowMatch = AdminMatch::getInstance()->where('match_time', $tomorrowTime, '>')->where('match_time', $afterTomorrowTime, '<')->where('competition_id', $hotCompetition, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $playing = FrontService::handMatch($playingMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $today = FrontService::handMatch($todayMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $tomorrow = FrontService::handMatch($tomorrowMatch, isset($this->auth['id']) ? $this->auth['id'] : 0);
        $resp = [
            'playing' => $playing,
            'today' => $today,
            'tomorrow' => $tomorrow
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resp);





        $key = sprintf(UserRedis::USER_INTEREST_MATCH, 1);
//        $match = AdminMatch::getInstance()->find(1);
        $prepareNoticeUserIds = UserRedis::getInstance()->smembers($key);
        $cids = AdminUser::getInstance()->where('id', $prepareNoticeUserIds, 'in')->field(['cid', 'id'])->all();
        $cidsarr = array_column($cids, 'id');
        return $this->writeJson(Status::CODE_OK, '操作成功', $cidsarr);


    }

    function callback($instance, $channelName, $message) {
        $info = json_encode([$channelName, $message]);
        Log::getInstance()->info($info);
    }

}
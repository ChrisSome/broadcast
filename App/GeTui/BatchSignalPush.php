<?php
namespace App\GeTui;
require_once __DIR__ . '/IGt.Batch.php';
include_once __DIR__ . '/IGt.Push.php';
use App\Model\AdminNoticeMatch;
use App\Utility\Log\Log;


class BatchSignalPush{
    const APPKEY = '1pzc0QJtjG6UyddwH404c9';
    const APPID = 'LE2EByDFzB8t1zhfgTZdk8';
    const MASTERSECRET = '0QMqcC8YkF6xiQNA0sGdM2';
    const HOST = 'http://sdk.open.api.igexin.com/apiex.htm';
    public function __construct()
    {
        //批量单推Demo
//        header("Content-Type: text/html; charset=utf-8");


    }

    /**
     * 批量单推  用户通知用户比赛开始
     * @param array $cids
     * @param $match_id
     * @param $rs
     * @param $homeTName
     * @param $awayTName
     * @param string $competition
     * @param int $type
     * @param string $title
     * @throws \Exception
     */
    function pushMessageToSingleBatch(array $cids, $info)
    {


                //type=1:开赛通知
        foreach ($cids as $cid) {
            $notice['cid'] = $cid;
            $notice['title'] = $info['title'];
            $notice['content'] = $info['content'];
            $notice['type'] = $info['type'];
            $notice['transmissionParams'] = ['match_id' => $info['match_id'],'type' => $info['type']];
            $notices[] = $notice;
            unset($notice);
        }
//        $cids = [
//            [
//                'cid' => '7645b2d25d0ca893d1c8a5be200148bb',
//                'title' => '开赛通知',
//                'content' => '您关注的【XX联赛】XXX-XXX将于15分钟后开始比赛，不要忘了哦',
//                'type' => 1,
//                'transmissionParams' => ['match_id'=>1,'type'=>1]
//            ],
//            [
//                'cid' => 'd82c244dfdbbbfb9942fa6261d16cc52',
//                'title' => '开赛通知',
//                'content' => '您关注的【XX联赛】AAA-AAA将于15分钟后开始比赛，不要忘了哦',
//                'type' => 1,
//                'transmissionParams' => ['match_id'=>2,'type'=>1]
//
//
//            ],
//
//        ];
        $igt = new \IGeTui(self::HOST, self::APPKEY, self::MASTERSECRET);
        $batch = new \IGtBatch(self::APPKEY, $igt);
        $batch->setApiUrl(self::HOST);
        //$template = IGtNotyPopLoadTemplateDemo();
        foreach ($notices as $item) {
            $templateNoti = $this->IGtNotificationTemplateDemo($item);
            //个推信息体
            $messageNoti = new \IGtSingleMessage();
            $messageNoti->set_isOffline(true);//是否离线
            $messageNoti->set_offlineExpireTime(12 * 1000 * 3600);//离线时间
            $messageNoti->set_data($templateNoti);//设置推送消息类型
            $targetNoti = new \IGtTarget();
            $targetNoti->set_appId(self::APPID);
            $targetNoti->set_clientId($item['cid']);
            $batch->add($messageNoti, $targetNoti);

        }


        try {
            $rep = $batch->submit();
            AdminNoticeMatch::getInstance()->update([
                'title' => $info['title'],
                'content' => $info['content'],
                'is_notice' => 1
            ], ['id' => $info['rs']]);
            Log::getInstance()->info('submit res succ' . json_encode($rep));
            return $rep;


        }catch(Exception $e){
            $rep = $batch->submit();
            Log::getInstance()->info('submit res fail' . json_encode($rep));

            return $rep;

        }
    }

    function IGtNotificationTemplateDemo(array $val){
        $template =  new \IGtNotificationTemplate();
        $template->set_appId(self::APPID);//应用appid
        $template->set_appkey(self::APPKEY);//应用appkey
        $template->set_transmissionType(1);//透传消息类型
        $transmissionParams = $val['transmissionParams'];

        $template->set_transmissionContent(json_encode($transmissionParams));//透传内容
        $template->set_title($val['title']);//通知栏标题
        $template->set_text($val['content']);//通知栏内容
        $template->set_logo("http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg");//通知栏logo
        $template->set_isRing(true);//是否响铃
        $template->set_isVibrate(true);//是否震动
        $template->set_isClearable(true);//通知栏是否可清除
        //$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
        return $template;
    }



}


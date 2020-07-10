<?php


class PhoneCodeService{

    const STATUS_SUCCESS = "0";   //发送成功

    const STATE_CODE    = 1;    //验证码短信
    const STATE_MARKING = 2;    //营销短信
    const STATE_VOICE   = 3;    //语音短信

    const REPEAT_NUM    = 3;    //重复次数


    private $url = 'https://api.paasoo.cn/voice?key=%s&secret=%s&from=85299998888&to=%s&lang=zh-cn&text=%s&repeat=%s';              //语音地址
    private $codeUrl = 'https://api.paasoo.com/json?key=%s&secret=%s&from=sdfknsdf&to=86%s&text=%s';    //短息地址
    public  $maxCount = 100;                        //每日最大发送量，后续验证

    private $API_KEY    = 'ybqxenxy';               //语音
    private $API_KEY_MESS = 'taihv6tw';             //短信

    private $API_SERECT = 'bBn2ebt3';               //语音
    private $API_SERECT_MESS = 'vvd4gWnb';          //短信

    public static $copying = '【竹语】尊敬的用户，您好，您本次的验证码是: %s';     //短信模板





    /**
     * 发送短信验证码
     * @param $mobile
     * @param $content
     * @return mixed
     */

    public  function sendMess($mobile,$content){

        $url = sprintf($this->codeUrl, $this->API_KEY_MESS, $this->API_SERECT_MESS, $mobile, urlencode(self::$copying . $content));

        return self::postApi($url);


    }

    public function postApi($url, $method = 'GET', $params = [], $headers = [])
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HEADER, $headers);
            }
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);         //发送POST类型数据
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  //SSL 报错时使用
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  //SSL 报错时使用
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            return curl_exec($ch);
        } catch (\Exception $e) {
            return false;
        }
    }


}

$phone = new PhoneCodeService();
$xsend = $phone->sendMess('15670660962', '123123');
var_dump($xsend);

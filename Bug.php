<?php

function postApi($url, $method = 'GET', $params = [], $headers = [])
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
function get_tag_data($str, $regex)
{ //返回值为数组 ,查找到的标签内的内容



    preg_match_all($regex, $str, $matches, PREG_PATTERN_ORDER);
    return $matches[0];
}

$url = 'https://bbs.hupu.com/all-soccer';
$res = postApi($url);
//print_r($res);


/*$regex = "/<$tag.*?$attrname=\".*?$value.*?\".*?>(.*?)<\/$tag>/is";*/
$regex = "<a href=\"/.*.html\" target=\"_blank\" title=\".*\">";

$result = get_tag_data($res, $regex);
//foreach ($result as $item) {
//    $uri = mb_substr($item, 9, 13);
//    $url = 'https://bbs.hupu.com/' . $uri;
//    $content = postApi($url);
//    var_dump($url);die;
//    var_dump($content);die;
//
//
//}

$content = postApi('https://bbs.hupu.com/37923545.html');
file_put_contents('./content.log', $content);
var_dump($content);


<?php




$a = '{params:{"roomId":3392661,"type":"text","user_id":4,"match_id":3392661},event:"match-enter" }';
$arr = [
    'params' => ['roomId' => 3392661, 'type' => 'text', 'user_id' => 4, 'match_id' => 3392661],
    'event' => 'match-enter'
];

$json = json_encode($arr);
var_dump($json);
//$b = json_decode($a, true);
//var_dump($b);
<?php

if (!file_exists('./aaa.txt')) {
    file_put_contents('./aaa.txt', '22222');

}
return;
$a = file_get_contents('./task.txt');
$b = asc_unserialize($a);
var_dump($b);

function mb_unserialize($serial_str) {
    $serial_str= preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
    $serial_str= str_replace("\r", "", $serial_str);
    return unserialize($serial_str);
}

function asc_unserialize($serial_str) {
    $serial_str = preg_replace('!s:(\d+):"(.*?)";!se', '"s:".strlen("$2").":\"$2\";"', $serial_str );
    $serial_str= str_replace("\r", "", $serial_str);
    return unserialize($serial_str);
}

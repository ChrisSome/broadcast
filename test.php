<?php



$txt = file_get_contents('./task.txt');
$arr = json_decode($txt, true);
var_dump($arr['data']);
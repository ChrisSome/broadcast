<?php

for ($i = 0; $i <= 20; $i++) {
    $arr[] = $i;
}
$m = array_slice($arr, 0, 10);
var_dump($m);
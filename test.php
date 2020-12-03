<?php

$arr = [
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12073, 'workerIndex' => 0],
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12074, 'workerIndex' => 1],
    ['running' => 0, 'success' => 2, 'fail' => 0, 'pid' => 12075, 'workerIndex' => 2],
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12076, 'workerIndex' => 3],
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12077, 'workerIndex' => 4],
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12078, 'workerIndex' => 5],
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12079, 'workerIndex' => 6],
    ['running' => 0, 'success' => 3, 'fail' => 0, 'pid' => 12080, 'workerIndex' => 7],
];

//mt_srand();
if(true){
    $info = $arr;
    if(!empty($info)){
        array_multisort(array_column($info,'running'),SORT_ASC,$info);
        $index = $info[0]['workerIndex'];
    }
}
//$re = rand(0,7);
var_dump($index);
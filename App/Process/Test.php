<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-26
 * Time: 23:18
 */


use App\Storage\MatchLive;


$res = MatchLive::getInstance()->table();
print_r($res);
<?php

namespace App\Common;


use easySwoole\Cache\Cache;

class AppFunc
{
    // 二维数组 转 tree
    public static function arrayToTree($list, $pid = 'pid')
    {
        $map = [];
        if (is_array($list)) {
            foreach ($list as $k => $v) {
                $map[$v[$pid]][] = $v; // 同一个pid 放在同一个数组中
            }
        }

        return self::makeTree($map);
    }

    private static function makeTree($list, $parent_id = 0)
    {
        $items = isset($list[$parent_id]) ? $list[$parent_id] : [];
        if (!$items) {
            return null;
        }

        $trees = [];
        foreach ($items as $k => $v) {
            $children = self::makeTree($list, $v['id']); // 找到以这个id 为pid 的数据
            if ($children) {
                $v['children'] = $children;
            }
            $trees[] = $v;
        }

        return $trees;
    }

    /**
     * / 规则 |--- 就分的
     * @param  [type] $tree_list [树 数组]
     * @param  [type] &$tree     [返回的二维数组]
     * @param  [type] $name      [那个字段进行 拼接|-- ]
     * @param  string $pre       [前缀]
     * @param  string $child     [树 的子分支]
     * @return [type]            [description]
     */
    public static function treeRule($tree_list, &$tree, $pre = '', $name = 'name', $child = 'children')
    {
        if (is_array($tree_list)) {
            foreach ($tree_list as $k => $v) {
                $v[$name] = $pre . $v[$name];
                $tree[]    = $v;
                if (isset($v[$child])) {
                    self::treeRule($v[$child], $tree, $pre . '&nbsp;|------&nbsp;');
                }
            }
        }
    }

    /**
     * / 规则 |--- 就分的
     * @param  [type] $tree_list [树 数组]
     * @param  [type] &$tree     [返回的二维数组]
     * @param  [type] $name      [那个字段进行 拼接|-- ]
     * @param  string $pre       [前缀]
     * @param  string $child     [树 的子分支]
     * @return [type]            [description]
     */
    public static function treeRules($tree_list, &$tree, $pre = '', $name = 'name', $child = 'children')
    {

        if (is_array($tree_list)) {
            foreach ($tree_list as $k => $v) {
                $v[$name] = $pre . $v[$name];
                $tree[$v['id']]    = $v['name'];
                if (isset($v[$child])) {
                    self::treeRules($v[$child], $tree, $pre . '|-----|------');
                }
            }
        }
    }

    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
    public static function getRandomStr($len, $special = true)
    {
        $chars = [
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        ];

        if ($special) {
            $chars = array_merge($chars, [
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ]);
        }

        $charsLen = count($chars) - 1;
        shuffle($chars); //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)]; //随机取出一位
        }
        return $str;
    }

    /**
     * easyswoole where 条件不支持or，此处改造
     * @param string $col
     * @param array $where
     * @return string
     */
    public static function getWhereArray(string $col, array $where)
    {
        if (!$where) return '';

        $str = '';
        foreach ($where as $v) {
            $str .= ($col . '=' . $v . ' or ');
        }
        return '(' . rtrim($str, 'or ') . ')';
    }

    /**
     * 验证必须存在
     * @param $col
     */
    public static function validateRequired($col)
    {
        if (is_array($col)) {
            foreach ($col as $item) {

            }
        }
    }

    /**
     * @param $col
     * @param $item
     * @return string
     */
    public static function whereLike($col, $item)
    {
        return $col .  "like '%" . $item . "%'";
    }


    /**
     * 获取汉字字符串首字母
     * @param $str
     * @return string|null
     */
    public static function  getFirstCharters($str)
    {
        if (empty($str)) {
            return '';
        }
        //取出参数字符串中的首个字符
        $temp_str = substr($str,0,1);
        if(ord($temp_str) > 127){
            $str = substr($str,0,3);
        }else{
            $str = $temp_str;
            $fchar = ord($str{0});
            if ($fchar >= ord('A') && $fchar <= ord('z')){
                return strtoupper($temp_str);
            }else{
                return null;
            }
        }
        $s1 = iconv('UTF-8', 'gb2312//IGNORE', $str);
        if(empty($s1)){
            return null;
        }
        $s2 = iconv('gb2312', 'UTF-8', $s1);
        if(empty($s2)){
            return null;
        }
        $s = $s2 == $str ? $s1 : $str;
        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
        if ($asc >= -20319 && $asc <= -20284)
            return 'A';
        if ($asc >= -20283 && $asc <= -19776)
            return 'B';
        if ($asc >= -19775 && $asc <= -19219)
            return 'C';
        if ($asc >= -19218 && $asc <= -18711)
            return 'D';
        if ($asc >= -18710 && $asc <= -18527)
            return 'E';
        if ($asc >= -18526 && $asc <= -18240)
            return 'F';
        if ($asc >= -18239 && $asc <= -17923)
            return 'G';
        if ($asc >= -17922 && $asc <= -17418)
            return 'H';
        if ($asc >= -17417 && $asc <= -16475)
            return 'J';
        if ($asc >= -16474 && $asc <= -16213)
            return 'K';
        if ($asc >= -16212 && $asc <= -15641)
            return 'L';
        if ($asc >= -15640 && $asc <= -15166)
            return 'M';
        if ($asc >= -15165 && $asc <= -14923)
            return 'N';
        if ($asc >= -14922 && $asc <= -14915)
            return 'O';
        if ($asc >= -14914 && $asc <= -14631)
            return 'P';
        if ($asc >= -14630 && $asc <= -14150)
            return 'Q';
        if ($asc >= -14149 && $asc <= -14091)
            return 'R';
        if ($asc >= -14090 && $asc <= -13319)
            return 'S';
        if ($asc >= -13318 && $asc <= -12839)
            return 'T';
        if ($asc >= -12838 && $asc <= -12557)
            return 'W';
        if ($asc >= -12556 && $asc <= -11848)
            return 'X';
        if ($asc >= -11847 && $asc <= -11056)
            return 'Y';
        if ($asc >= -11055 && $asc <= -10247)
            return 'Z';
        return 'hot';
    }


    /**
     * 将整数转化为 x亿x千万
     * @param $number
     * @return mixed
     */
    public static function formatValue($number)
    {

        $wan_int = substr($number, -8);

        $length = strlen($number);  //数字长度
        if($length > 8){ //亿单位
            $yi = substr_replace(strstr($number,substr($number,-7),' '),'.',-1,0);
            $yi_str = floor($yi) . '亿';
            $wan = substr_replace(strstr($wan_int,substr($wan_int,-3),' '),'.',-1,0);
            $wan_str = floor($wan) . '万';
            return $yi_str . $wan_str . '欧';

        }elseif($length >4){ //万单位
            $wan = substr_replace(strstr($wan_int,substr($wan_int,-3),' '),'.',-1,0);
            $wan_str = floor($wan) . '万欧';
            return $wan_str;
        }else{
            return '';

        }


    }
}

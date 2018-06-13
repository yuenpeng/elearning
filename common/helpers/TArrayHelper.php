<?php
/**
 * Created by PhpStorm.
 * User: tangming
 * Date: 4/5/2015
 * Time: 12:24 PM
 */

namespace common\helpers;


use yii\helpers\ArrayHelper;
use yii\helpers\BaseArrayHelper;

class TArrayHelper extends BaseArrayHelper
{
    public static function array_minus($arrayA, $arrayB)
    {
        $countA = count($arrayA);
        $countB = count($arrayB);
        $No_same = 0;

        for ($i = 0; $i < $countA; $i++) {
            for ($j = 0; $j < $countB; $j++) {
                if ($arrayA[$i] == $arrayB[$j])
                    $No_same = 1;
            }

            if ($No_same == 0)
                $rest_array[] = $arrayA[$i];
            else
                $No_same = 0;
        }

        if (!isset($rest_array))
            $rest_array = [];

        return $rest_array;
    }

    /**
     * 对象数组转数组
     * @param array $array 对象数组
     * @param string $key 属性
     * @return array
     */
    public static function get_array_key($array, $key)
    {
        if (isset($array) && $array !== null && count($array) > 0) {
            $result = ArrayHelper::map($array, $key, $key);
            $result = array_keys($result);
        }

        return $result;
    }

    /**
     * 二维数组排序
     * @param $array
     * @param $on
     * @param int $order
     * @return array
     */
    public static function array_sort($array, $on, $order=SORT_ASC)
    {
        $new_array = array();
        $sortable_array = array();

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }

            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }

            foreach ($sortable_array as $k => $v) {
                $new_array[$k] = $array[$k];
            }
        }

        return $new_array;
    }

    /**
     * @param $array
     * @return array|mixed
     */
    public static function getArrayVal($array){
        if (!is_array($array)){
            return $array;
        }
        $new_array = [];
        foreach ($array as $item){
            if (is_array($item)){
                $new_array = !empty($new_array) ? self::merge($new_array, $item) : $item;
            }else{
                array_push($new_array, $item);
            }
        }
        return $new_array;
    }
}
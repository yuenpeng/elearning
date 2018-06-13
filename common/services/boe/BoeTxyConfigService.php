<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\base\BoeBase;
use common\models\boe\BoeTxyNews;
use common\models\boe\BoeTxyConfig;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 特训营配置信息相关
 * @author xinpeng
 */
class BoeTxyConfigService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_txy_';

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isNoCacheMode() {
        return Yii::$app->request->get('no_cache') == 1 ? true : false;
    }

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isDebugMode() {
        return Yii::$app->request->get('debug_mode') == 1 ? true : false;
    }

    /**
     * 读取缓存的封装
     * @param type $cache_name
     * @param type $debug
     * @return type
     */
    private static function getCache($cache_name) {
        if (self::isNoCacheMode()) {
            return NULL;
        }
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        $sult = Yii::$app->cache->get($new_cache_name);
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From Cache,Cache Name={$new_cache_name}\n";
            if ($sult) {
                print_r($sult);
            } else {
                print_r("Cache Not Hit");
            }
            echo "\n</pre>";
        }
        return $sult;
    }

    /**
     * 修改缓存的封装
     * @param type $cache_name
     * @param type $data
     * @param type $time
     * @param type $debug
     */
    private static function setCache($cache_name, $data = NULL, $time = 0) {
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        $time = $time ? $time : self::$cacheTime;
        Yii::$app->cache->set($new_cache_name, $data, $time); // 设置缓存 
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }
	
	/*
     * 获取特训营基础配置信息
     */
    static function getBoeTxyAllConfig($create_mode = 1) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $dbObj = new BoeTxyConfig();
			$db_data = $dbObj->getAll($create_mode);
            $data = array();
            foreach ($db_data as $key => $a_info) {
                if (is_array($a_info['content'])) {
                    foreach ($a_info['content'] as $key2 => $a_sub_info) {
						if (isset($a_sub_info['image_info'])) {
                            $a_info['content'][$key2]['image_url'] = BoeBase::getFileUrl($a_sub_info['image_info'], 'txyConfig');
                        }
                    }
                }
                $sult[$key] = $a_info['content'];
            }
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;	
    }
	
	/**
     * 获取特训营基础信息 
     * @return type
     */
    static function getBoeTxyBasicConfig() {
        $all_config = self::getBoeTxyAllConfig();
        $basic_info = array(
			'txy_short_name' =>isset($all_config['txy_short_name'])?$all_config['txy_short_name']:'',
			'txy_full_name'  =>isset($all_config['txy_full_name'])?$all_config['txy_full_name']:'',
			'txy_begin_date' =>isset($all_config['txy_begin_date'])?$all_config['txy_begin_date']:'',
			'txy_end_date'   =>isset($all_config['txy_end_date'])?$all_config['txy_end_date']:'',
		);
        return $basic_info;
    }
	
	/**
     * 获取每日课程安排信息 
     * @return type
     */
    static function getBoeTxyCourseConfig($area_id = "") {
        $all_config = self::getBoeTxyAllConfig();
        $sult = array();
        $config = $all_config['course_info'];
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = $a_info['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'txyConfig');
                }
                $config[$key]['course_link_text'] = $a_info['course_link_text'] = BoeBase::left($a_info['course_link_text'], 20, '');
                if ($area_id == $a_info['area_id']) {
                    $sult[] = $a_info;
                }
            }
        }
        $config = $area_id ? $sult : $config;
        return $config;
    }
	
	 /**
     * 获取通栏信息 
     * @return type
     */
    static function getBoeTxyBannerConfig() {
		$all_config = self::getBoeTxyAllConfig();
        $config = $all_config['banner_info'];
		$new_config = array();
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    //$config[$key]['image_url'] 	= BoeBase::getFileUrl($a_info['image_info'], 'txyConfig');
					$a_info['image_info']		= BoeBase::getFileUrl($a_info['image_info'], 'txyConfig');
					$new_config[$a_info['banner_link_type']][] = $a_info;	
                }
            }
        }
		$new_config[1]	= self::arraySort($new_config[1],'banner_link_order','asc');
		$new_config[2]	= self::arraySort($new_config[2],'banner_link_order','asc');
        return $new_config;
    }
	
	//$array 要排序的数组
	//$row  排序依据列
	//$type 排序类型[asc or desc]
	//return 排好序的数组
	function arraySort($array,$row,$type){
		$array_temp = array();
		foreach($array as $v_key=>$v){
		  	$array_temp[$v[$row]."_".$v_key] = $v;
		}
		if($type == 'asc'){
		  	ksort($array_temp);
		}elseif($type='desc'){
		  	krsort($array_temp);
		}else{
			
		}
		return $array_temp;
	}
	
	
	

}

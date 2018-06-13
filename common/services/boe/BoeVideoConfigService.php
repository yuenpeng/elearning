<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\base\BoeBase;
use common\models\boe\BoeVideoConfig;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 视频配置信息相关
 * @author xinpeng
 */
class BoeVideoConfigService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_video_';

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
     * 获取视频基础配置信息
     */
    static function getBoeVideoAllConfig($create_mode = 1) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $dbObj = new BoeVideoConfig();
			$db_data = $dbObj->getAll($create_mode);
            $data = array();
            foreach ($db_data as $key => $a_info) {
                if (is_array($a_info['content'])) {
                    foreach ($a_info['content'] as $key2 => $a_sub_info) {
						if (isset($a_sub_info['image_info'])) {
                            $a_info['content'][$key2]['image_url'] = BoeBase::getFileUrl($a_sub_info['image_info'], 'videoConfig');
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
     * 获取通栏信息 
     * @return type
     */
    static function getBoeVideoBannerConfig() {
		$all_config = self::getBoeVideoAllConfig();
        $config = $all_config['banner_info'];
		$config	= self::arraySort($config,'banner_link_order','asc');
		return $config;
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

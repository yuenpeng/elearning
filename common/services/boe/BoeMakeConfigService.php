<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\base\BoeBase;
use common\models\boe\BoeMakeConfig;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 配置信息相关
 * @author xinpeng
 */
class BoeMakeConfigService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_make_';

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
    static function getBoeMakeAllConfig($create_mode = 1) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $dbObj = new BoeMakeConfig();
			$db_data = $dbObj->getAll($create_mode);
            $data = array();
            foreach ($db_data as $key => $a_info) {
                if (is_array($a_info['content'])) {
                    foreach ($a_info['content'] as $key2 => $a_sub_info) {
						if (isset($a_sub_info['image_info'])) {
                            $a_info['content'][$key2]['image_url'] = BoeBase::getFileUrl($a_sub_info['image_info'], 'makeConfig');
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
     * 获取基础信息 
     * @return type
     */
    static function getBoeMakeBasicConfig() {
        $all_config = self::getBoeMakeAllConfig();
        $basic_info = array(
		  'monitor_name' =>isset($all_config['monitor_name'])?$all_config['monitor_name']:'',
		  'subject_name'  =>isset($all_config['subject_name'])?$all_config['subject_name']:'',
		  'subject_begin_date' =>isset($all_config['subject_begin_date'])?$all_config['subject_begin_date']:'',
		  'subject_end_date'   =>isset($all_config['subject_end_date'])?$all_config['subject_end_date']:'',
		);
        return $basic_info;
    }
	
	
	 /**
     * 获取通栏信息 
     * @return type
     */
    static function getBoeMakeBannerConfig() {
		$all_config = self::getBoeMakeAllConfig();
        $config = $all_config['banner_info'];
		$config	= self::arraySort($config,'banner_link_order','asc');
		return $config;
    }
	
	/**
     * 获取学员组信息 
     * @return type
     */
    static function getBoeMakeGroupConfig() {
		$all_config = self::getBoeMakeAllConfig();
        $config = $all_config['group_info'];
		$config	= self::arraySort($config,'group_no_text','asc');
		return $config;
    }
	
	/**
     * 获取公共课信息 
     * @return type
     */
    static function getBoeMakeCourseConfig() {
		$all_config = self::getBoeMakeAllConfig();
        $config = $all_config['course_info'];
		$config	= self::arraySort($config,'course_link_date','asc');
		return $config;
    }
	
	/**
     * 获取专业课信息 
     * @return type
     */
    static function getBoeMakeCourseZyConfig($tag = NULL) {
		$tag_array = Yii::t('boe', 'course_zy_type_check');
		$tag_array = array_keys($tag_array);
		//return $tag_array;
		if(!$tag || !in_array($tag,$tag_array))
		{
			return NULL;
		}
		$all_config = self::getBoeMakeAllConfig();
        $config = $all_config['course_zy_info'];
		$new_config = array();
		foreach($config as $c_key=>$c_value)
		{
			if($tag == $c_value['type_id'])
			{
				$new_config[]	= $c_value;
			}
		}
		$new_config	= self::arraySort($new_config,'course_zy_link_date','asc');
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

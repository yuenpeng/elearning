<?php

namespace common\services\boe;

use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\framework\FwOrgnization;
use common\models\boe\BoeBp;
use common\services\framework\DictionaryService;
use yii\db\Query;
use Yii;

/**

 * User: xinpeng
 * Date: 2017/02/22
 * Time: 16:25
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeOrgnizationService {
	
	static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $cacheNameFix = 'boe_orgnization_';

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
	
	public static function getOrgPath($user_id = NULL,$org_id = NULL,$user_no = NULL){
		
		if($user_id)
		{
			$user_info			= FwUser::findOne(array('kid'=>$user_id,'is_deleted'=>'0'));
			if(!isset($user_info['kid']))
			{
				return -99;
			}
			$orgnization_id		= $user_info['orgnization_id'];
		}
		elseif($user_no)
		{
			$user_info			= FwUser::findOne(array('user_no'=>$user_no,'is_deleted'=>'0'));
			if(!isset($user_info['kid']))
			{
				return -98;
			}
			$orgnization_id		= $user_info['orgnization_id'];
		}elseif($org_id)
		{
			$orgnization_id		= $org_id;
		}else
		{
			return -97;;
		}
		$orgnization_info		= FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
		if(!isset($orgnization_info['kid']))
		{
			return -96;
		}

		$parent_orgnization_id 	=$orgnization_info['parent_orgnization_id'];
		$org_data				= array();
		$org_data['id'][0]		= $orgnization_id;
		$org_data['name'][0]	= $orgnization_info['orgnization_name'];
		$i					= 1;
		while ($parent_orgnization_id){
				$orgnization_id 		= $parent_orgnization_id;
				$orgnization_info		= FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
				if(!isset($orgnization_info['kid']))
				{
					return -95;
				}
				$org_data['id'][$i]		= $orgnization_id;
				$org_data['name'][$i]	= $orgnization_info['orgnization_name'];
				$parent_orgnization_id 	=$orgnization_info['parent_orgnization_id'];
				$i++;
                if($orgnization_info['parent_orgnization_id']==$orgnization_info['kid']){
                    break;
                }//修改PS系统组织传错产生死循环
		}
		$org_id_path	= $org_data['id'];
		$org_name_path	= $org_data['name'];
		krsort($org_id_path);
		krsort($org_name_path);
		$org_id_path	= implode("/",$org_id_path);
		$org_name_path	= implode("/",$org_name_path);
		$org_data['org_id_path']	= $org_id_path;
		$org_data['org_name_path']	= $org_name_path;
		return 	$org_data;
	}
	
	public static function getUserBp($user_id = NULL,$org_id = NULL,$user_no = NULL){
		
		$orgArray		= $user_bp	= array();
		if($user_id)
		{
			$orgArray	= self::getOrgPath($user_id);
		}
		elseif($user_no)
		{
			$orgArray	= self::getOrgPath(NULL,NULL,$user_no);
		}elseif($org_id)
		{
			$orgArray	= self::getOrgPath(NULL,$org_id,NULL);
		}else
		{
			return -94;
		}
		if(!is_array($orgArray)&&$orgArray<0)
		{
			return $orgArray;
		}
		$org_id	= $orgArray['id'];
		krsort($org_id);
		$org_id	= "'".implode("','",$org_id)."'";
		$sql_bp ="
		SELECT b.kid,b.orgnization_id,b.orgnization_name_path,b.user_id,u.user_no,u.user_name,u.real_name 
		FROM eln_boe_bp b 
		INNER JOIN eln_fw_user u ON u.kid = b.user_id AND u.is_deleted = '0'
		INNER JOIN eln_fw_orgnization o ON o.kid = b.orgnization_id AND o.is_deleted = '0'
		WHERE b.is_deleted = '0' and b.orgnization_id in ({$org_id})
		ORDER BY o.orgnization_level desc;  
		";
		$connection		= Yii::$app->db;
		$user_bp		= $connection->createCommand($sql_bp)->queryAll();
		if(!$user_bp)
		{
			return $orgArray['org_name_path'];
		}
		return $user_bp;
	}
	
	
    
	
	
}

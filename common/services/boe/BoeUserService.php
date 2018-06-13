<?php

namespace common\services\boe;

use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseTeacher;
use common\models\learning\LnTeacher;
use common\models\framework\FwUserPosition;
use common\models\framework\FwRolePermission;
use common\models\framework\FwPermission;
use common\services\framework\DictionaryService;
use yii\db\Query;
use Yii;

/**

 * User: xinpeng
 * Date: 2016/8/23
 * Time: 14:10
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeUserService {

    static $loadedObject = array();
    static $initedLog = array();
    static $currentLog = array();
    private static $cacheTime = 0;
    private static $cacheNameFix = 'boe_';
    private static $tableConfig = array(//
        'LnCourseCategory' => array(
            'namespace' => '\common\models\learning\LnCourseCategory',
            'order_by' => 'category_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => 'parent_category_id',
            'field' => 'kid,tree_node_id,parent_category_id,company_id,category_code,category_name,description'
        ),
        'FwOrgnization' => array(
            'namespace' => '\common\models\framework\FwOrgnization',
            'order_by' => 'orgnization_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => 'parent_orgnization_id',
            'field' => 'kid,tree_node_id,parent_orgnization_id,company_id,domain_id,orgnization_code,description,orgnization_manager_id,orgnization_level,is_make_org,is_service_site,status,orgnization_name'
        ),
        'FwCompany' => array(
            'namespace' => '\common\models\framework\FwCompany',
            'order_by' => 'company_name asc',
            'primary_key' => 'kid',
            'parent_key_name' => 'parent_company_id',
            'field' => 'kid,company_code,company_name'
        ),
        'FwDomain' => array(
            'namespace' => '\common\models\framework\FwDomain',
            'order_by' => 'domain_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,tree_node_id,parent_domain_id,company_id,domain_code,share_flag,domain_name,description,status,data_from'
        ),
        'FwPosition' => array(
            'namespace' => '\common\models\framework\FwPosition',
            'order_by' => 'position_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,company_id,position_code,position_name,position_type,position_level,share_flag,data_from'
        ),
        'FwTreeNode' => array(
            'namespace' => '\common\models\treemanager\FwTreeNode',
            'order_by' => 'tree_node_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,tree_node_code,tree_node_name,node_name_path,node_id_path'
        ),
    );

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
    private static function setCache($cache_name, $data = NULL) {
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        Yii::$app->cache->set($new_cache_name, $data, self::$cacheTime); // 设置缓存 
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }
	
	
	/**
     * 读取多个角色的权限信息
     * @param type $role_id
     * @return type
     */
    static function getRolesInfo($role_id = NULL, $expand_info = 1, $field = '') {
        if (empty($role_id)) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status', FwRolePermission::STATUS_FLAG_STOP);
        $where[] = array(is_array($role_id) ? 'in' : '=', 'role_id', $role_id);
        $field = $field ? $field : 'kid,role_id,permission_id';

        $role_model = FwRolePermission::find(false)->select($field);
        $role_info 	= $role_model->where($where)->indexby('kid')->asArray()->all();
		$permission	= array();
		foreach($role_info as $r_key=>$r_value ){
			$permission[$r_value['permission_id']]	= $r_value['permission_id'];
		}
		$permission	= self::getPermissionInfo($permission);
		
        if ($expand_info) {
            return self::getPermissionInfo($permission);
        } else {
            $permission_new	= array();
			foreach($permission as $p_key=>$p_value )
			{
				$permission_new[] = $p_value['permission_code'];
			}
			return $permission_new;
        }
    }
	
	/**
     * 读取多个权限的信息
     * @param type $permission_id
     * @return type
     */
	static function getPermissionInfo($permission_id = NULL,$permission_type = 2,$field = '') {
        if (empty($permission_id)) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
		$where[] = array('permission_type' => $permission_type);//权限分类--功能
		$where[] = array('system_flag' => 'eln_frontend');//权限所属--前台
        $where[] = array(is_array($permission_id) ? 'in' : '=', 'kid', $permission_id);
        $field = $field ? $field : 'kid,permission_code,permission_type,permission_name';

        $permission_model = FwPermission::find(false)->select($field);
        $permission_info  = $permission_model->where($where)->indexby('kid')->asArray()->all();
		return $permission_info;
    }
	
    

}

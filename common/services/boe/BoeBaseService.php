<?php

namespace common\services\boe;

use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseTeacher;
use common\models\learning\LnTeacher;
use common\models\framework\FwUserPosition;
//use common\models\framework\FwOrgnization;
// use common\models\framework\FwDomain;
// use common\models\framework\FwCompany;
// use common\models\treemanager\FwTreeNode;
// use common\models\learning\LnCourseCategory;
use common\services\framework\DictionaryService;
use yii\db\Query;
use Yii;

/**

 * User: Zheng lk
 * Date: 2016/2/26
 * Time: 14:10
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeBaseService {

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
     * 读取多个用户信息
     * @param type $user_no
     * @return type
     */
    static function getMoreUserInfoFromUserNo($user_no = NULL, $expand_info = 1, $field = '') {
        if (empty($user_no)) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
        $where[] = array(is_array($user_no) ? 'in' : '=', 'user_no', $user_no);
        $field = $field ? $field : 'real_name,nick_name,user_name,kid,email,user_no,orgnization_id,domain_id,company_id';

        $user_model = FwUser::find(false)->select($field);
        $user_info = $user_model->where($where)->indexby('user_no')->asArray()->all();
        if ($expand_info) {
            return self::parseUserListInfo($user_info);
        } else {
            return $user_info;
        }
    }

    /**
     * 读取多个用户信息
     * @param type $user_id
     * @return type
     */
    static function getMoreUserInfo($user_id = NULL, $expand_info = 1, $field = '') {
        if (empty($user_id)) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
        $where[] = array(is_array($user_id) ? 'in' : '=', 'kid', $user_id);
        $field = $field ? $field : 'real_name,nick_name,user_name,kid,email,user_no,orgnization_id,domain_id,company_id';

        $user_model = FwUser::find(false)->select($field);
        $user_info = $user_model->where($where)->indexby('kid')->asArray()->all();
        if ($expand_info) {
            return self::parseUserListInfo($user_info);
        } else {
            return $user_info;
        }
    }

    static function parseUserListInfo($user_info = NULL) {
        if (empty($user_info)) {
            return array();
        }
        if ($user_info && is_array($user_info)) {//有结果的时候S
            foreach ($user_info as $key => $a_info) {//找出用户名称S
                $a_info = BoeBase::parseUserListName($a_info);
                $a_info['orgnization_path'] = self::getOrgnizationPath($a_info['orgnization_id']);
                $a_info['domain_name'] = self::getDomainName($a_info['domain_id']);
                $a_info['company_name'] = self::getCompanyName($a_info['company_id']);
                $user_info[$key] = $a_info;
            }//找出用户名称E
        }//有结果的时候E  
        return $user_info;
    }

    /**
     * 根据用户ID，获取期对应的岗位信息
     * @param type $userid
     * @param type $return_name_str 
     */
    static function getUserPositonInfo($userid, $return_name_str = 0) {
        $s_where = array('and',
            ['=', 'user_id', $userid],
            ['=', 'status', 1],
            ['=', 'is_deleted', 0]
        );
        $list = FwUserPosition::find(false)->select('position_id')->where($s_where)->asArray()->all();
        if ($list && is_array($list)) {
            $sult = array();
            foreach ($list as $a_info) {
                $sult[$a_info['position_id']] = self::getTableOneInfo('FwPosition', $a_info['position_id'], $return_name_str ? 'position_name' : '*');
            }
            if ($return_name_str) {
                return implode(' ', $sult);
            }
            return $sult;
        }
        return NULL;
    }

    /**
     * 根据课程的ID，获取其对应的名称
     * @param type $oid 
     * @return string
     */
    static function getCourseCategoryPath($oid) {
        $log_key_name = __METHOD__ . '_' . $oid;
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时  
            $tree_node_id = self::getTableOneInfo('LnCourseCategory', $oid, 'tree_node_id');
            $path_info = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'node_name_path');
            $c_name = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'tree_node_name');
            self::$currentLog[$log_key_name] = trim(str_replace('/', '\\', $path_info), '\\') . '\\' . trim($c_name, '/');
            self::$currentLog[$log_key_name] = trim(self::$currentLog[$log_key_name], '\\');
        }
        return self::$currentLog[$log_key_name];
    }

    /**
     * 根据组织的ID，获取其对应的路径
     * @param type $oid 
     * @return string
     */
    static function getOrgnizationPath($oid) {
        $log_key_name = __METHOD__ . '_' . $oid;
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时  
            $tree_node_id = self::getTableOneInfo('FwOrgnization', $oid, 'tree_node_id');
            $path_info = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'node_name_path');
            $c_name = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'tree_node_name');
            self::$currentLog[$log_key_name] = trim(str_replace('/', '\\', $path_info), '\\') . '\\' . trim($c_name, '/');
            self::$currentLog[$log_key_name] = trim(self::$currentLog[$log_key_name], '\\');
        }
        return self::$currentLog[$log_key_name];
    }

    /**
     * 根据关键词获取相关的组织信息
     * @param type $keyword
     * @param type $other_info
     */
    static function getOrgnizationListInfo($keyword = '', $other_info = array()) {
        $limit = BoeBase::array_key_is_numbers($other_info, array('limit', 'limit_num', 'limitNum'), 5);
        $filter_company = BoeBase::array_key_is_nulls($other_info, 'filter_company', '');
        $table_name = "FwOrgnization";
        $table_all_name = $table_name . '_all';
        $tmp_sult = array();
        $tmp_match_num_log = $tmp_level_num_log = array();
        if (!isset(self::$currentLog[$table_all_name])) {//未初始化全部分类信息时S
            self::$currentLog[$table_all_name] = self::getTableAll($table_name);
        }
        $tmp_key = 0;
//        BoeBase::debug(__METHOD__);
//        BoeBase::debug(self::$currentLog[$table_all_name], 1);
        if (self::$currentLog[$table_all_name] && is_array(self::$currentLog[$table_all_name])) {//有数据并且是个数组时S
            $tmp_key = 0;
            if ($keyword && strpos($keyword, '\\') !== false) {
                $tmp_keyword = explode('\\', trim($keyword, '\\'));
                $keyword = end($tmp_keyword);
            }
            foreach (self::$currentLog[$table_all_name] as $a_info) {
                $tmp_match = true;
                $tmp_match_num = 0;
                if ($filter_company) {
                    if (is_array($filter_company)) {
                        $tmp_match = in_array($a_info['company_id'], $filter_company);
                    } else {
                        $tmp_match = $a_info['company_id'] == $filter_company;
                    }
                }
                if ($tmp_match && $keyword) {
                    $tmp_name = str_ireplace($keyword, '', $a_info['orgnization_name']);
                    $tmp_match_num = strlen($a_info['orgnization_name']) - strlen($tmp_name);
                    $tmp_match = $tmp_match_num > 0;
                }
                if ($tmp_match) {
                    $a_info['orgnization_path'] = self::getOrgnizationPath($a_info['kid']);
                    $a_info['match_num'] = $tmp_match_num;
                    $tmp_name = str_replace('\\', '', $a_info['orgnization_path']);
                    $a_info['level_num'] = strlen($a_info['orgnization_path']) - strlen($tmp_name) + 1;

                    $tmp_sult[$tmp_key] = $a_info;
                    $tmp_level_num_log[$tmp_key] = $a_info['level_num'];
                    $tmp_match_num_log[$tmp_key] = $a_info['match_num'];
                    $tmp_key++;
                }
            }

            if ($tmp_key) {//有结果了 S
//                BoeBase::debug(__METHOD__);
//                BoeBase::debug('keyword:' . $keyword);
//                BoeBase::debug('$tmp_key:' . $tmp_key);
//                BoeBase::debug($filter_company);
//                BoeBase::debug($tmp_level_num_log);
//                BoeBase::debug('排序前：');
//                BoeBase::debug($tmp_sult);
                array_multisort($tmp_level_num_log, SORT_ASC, $tmp_match_num_log, SORT_DESC, $tmp_sult);
                //array_multisort($tmp_level_num_log, SORT_ASC, $tmp_sult);
//                BoeBase::debug('排序后：');
//                BoeBase::debug($tmp_sult);
                if ($limit) {
                    $tmp_sult = array_slice($tmp_sult, 0, $limit);
                }
//                BoeBase::debug($tmp_sult, 1);
                return $tmp_sult;
            }//有结果了 E
        }//有数据并且是个数组时E
        return NULL;
    }

    /**
     * 根据域的ID，获取其对应的名称
     * @param type $oid 
     * @return string
     */
    static function getDomainName($oid) {
        return self::getTableOneInfo('FwDomain', $oid, 'domain_name');
    }

    /**
     * 根据公司的ID，获取其对应的名称
     * @param type $oid 
     * @return string
     */
    static function getCompanyName($oid) {
        return self::getTableOneInfo('FwCompany', $oid, 'company_name');
    }

    /**
     * 得到课时单位
     * @author baoxianjian 15:12 2016/1/14
     * @param int $type
     */
    static function getCoursePeriodUnits($unitVal = 0) {
        $log_key_name = __METHOD__ . '_' . $unitVal;
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时 
            if (!isset(self::$loadedObject['ln_course'])) {
                self::$loadedObject['ln_course'] = new LnCourse();
            }
            self::$currentLog[$log_key_name] = self::$loadedObject['ln_course']->getCoursePeriodUnits($unitVal);
        }
        return self::$currentLog[$log_key_name];
    }

    static function getCourseCover($url) {
        return $url ? $url : '/static/frontend/images/course_theme_big.png';
    }

    /*
     * 根据字典分类与值获取字典详细信息
     * @return string
     */

    static function getDictionaryText($cate_code, $val) {
        if (empty($cate_code)) {
            return "";
        } else {
            $log_key_name = __METHOD__ . '_' . $cate_code . "_" . $val;
            if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时 
                if (!isset(self::$loadedObject['dictionaryService'])) {
                    self::$loadedObject['dictionaryService'] = new DictionaryService();
                }
                self::$currentLog[$log_key_name] = self::$loadedObject['dictionaryService']->getDictionaryNameByValue($cate_code, $val);
            }
            return self::$currentLog[$log_key_name];
        }
    }

    /**
     * 货币单位
     * @param $code
     */
    static function getPriceUnit($dictionaryCode = null) {
        $log_key_name = __METHOD__ . '_' . $dictionaryCode;
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时 
            if (!isset(self::$loadedObject['dictionaryService'])) {
                self::$loadedObject['dictionaryService'] = new DictionaryService();
            }
            self::$currentLog[$log_key_name] = self::$loadedObject['dictionaryService']->getDictionaryValueByCode('currency_symbol', $dictionaryCode);
        }
        return self::$currentLog[$log_key_name];
    }

    /**
     * 根据课程ID找出对应的老师信息
     * @param type $cource_list
     * @param type $array_mode 是否为多维数组模式
     * @param type $return_list 只返回列表，不返回SQL
     * @return type
     */
    static function getCourseListTeacherInfo($cource_list = array(), $array_mode = 1) {
        if (is_array($cource_list)) {
            $kid_info = $array_mode ? array_keys($cource_list) : $cource_list; //课程ID
        } else {
            $kid_info = $cource_list; //课程ID
        }
        if (!$kid_info) {
            return NULL;
        }
        $course_teacher_table_name = LnCourseTeacher::realTableName();
        $teacher_table_name = LnTeacher::realTableName();
        $base_where = array('and');
        $base_where[] = array(is_array($kid_info) ? 'in' : '=', $course_teacher_table_name . '.course_id', $kid_info);

        $filed = array(
            $course_teacher_table_name => array('course_id', 'teacher_id'),
            $teacher_table_name => array('teacher_name'),
        );

        $filed_arr = array();
        foreach ($filed as $key => $a_info) {
            if (!is_array($a_info)) {
                $filed_arr[] = $a_info;
            } else {
                foreach ($a_info as $a_sub_info) {
                    $filed_arr[] = "{$key}.{$a_sub_info} as {$a_sub_info}";
                }
            }
        }
        $filed_str = implode(',', $filed_arr);

        $query = new Query();
        $query->from($course_teacher_table_name);
        $query->select($filed_str);
        $query->orderBy($teacher_table_name . ".teacher_name asc");
        $query->andFilterWhere($base_where);
        $query->join('INNER JOIN', $teacher_table_name, "{$course_teacher_table_name}.teacher_id={$teacher_table_name}.kid");
        $teacher_command = $query->createCommand();
        return array(
            'teacher_sql' => $teacher_command->getRawSql(),
            'teacher_list' => $query->all(),
        );
    }

    /**
     * 根据列表信息和课程ID得到相应的老师信息
     * @param type $c_id
     * @param type $teach_link_info
     * @return type array()
     */
    static function getCourseTeacherNameArr($c_id, $teach_link_info) {
        $sult = array();
        $i = 1;
        foreach ($teach_link_info as $a_teach_link_info) {
            if ($a_teach_link_info['course_id'] == $c_id) {
                $sult[] = $a_teach_link_info['teacher_name'];
            }
        }
//        BoeBase::debug(__METHOD__);
//        BoeBase::debug($c_id);
//        BoeBase::debug($teach_link_info);
//        BoeBase::debug($sult,1);
        return $sult;
    }

    /**
     * getUserAllRank 获取的账号的职级汇总信息
     * @param type $create_mode 是否强制从数据库读取 
     */
    static function getUserAllRank($create_mode = 0) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $model = FwUser::find(false);
            $where = array('and',
                array('=', 'is_deleted', 0),
                array('<>', 'status', 2),
                array('<>', 'rank', ''),
            );
            $model->andFilterWhere($where);
            $model->select(['rank', new \yii\db\Expression('count(*) as num')]);
            $model->groupBy(['rank']);
            $sult = $model->orderBy('num desc')->asArray()->all();
//            BoeBase::debug($model->createCommand()->getRawSql(),1);
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /**
     * getTableAll获取某个表的全部数据
     * @param type $create_mode 是否强制从数据库读取 
     */
    static function getTableAll($table_name, $create_mode = 0) {
        if (!$table_name) {
            return NULL;
        }
        $cache_name = __METHOD__ . $table_name;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $class_name = !empty(self::$tableConfig[$table_name]['namespace']) ? self::$tableConfig[$table_name]['namespace'] : $table_name;
            $parent_id = !empty(self::$tableConfig[$table_name]['parent_key_name']) ? self::$tableConfig[$table_name]['parent_key_name'] : 'parent_id';


            $db_obj = new $class_name();
            $sult = $db_obj->find(false)->select(self::$tableConfig[$table_name]['field'])->orderBy(self::$tableConfig[$table_name]['order_by'])->asArray()->indexBy(self::$tableConfig[$table_name]['primary_key'])->all();
            if ($sult && is_array($sult)) {
                foreach ($sult as $key => $a_info) {
                    $a_info[$parent_id] = trim($a_info[$parent_id]);
                    if ($a_info[$parent_id] === '' || $a_info[$parent_id] === NULL) {
                        $sult[$key][$parent_id] = '0';
                    }
                }
            }
//            BoeBase::debug(__METHOD__);
//            BoeBase::debug($sult,1);
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /**
     * getTableOneInfo
     * 根据ID获取分类的详细或是某个字段的信息
     * @param type $id 分类的ID
     * @param type $key 
     */
    static function getTableOneInfo($table_name = '', $id = 0, $key = '*') {
        if (!$table_name) {
            return NULL;
        }
        if (!$id) {
            return NULL;
        }
        $log_key_name = __METHOD__ . $table_name . '_' . $id;
        $table_all_name = $table_name . '_all';
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时 
            if (!isset(self::$currentLog[$table_all_name])) {//未初始化全部分类信息时
                self::$currentLog[$table_all_name] = self::getTableAll($table_name);
                // BoeBase::debug(self::$currentLog[$table_all_name], 1);
            }
            self::$currentLog[$log_key_name] = (isset(self::$currentLog[$table_all_name][$id])) ? self::$currentLog[$table_all_name][$id] : false;
//            BoeBase::debug(__METHOD__);
//            BoeBase::debug(self::$currentLog[$table_all_name]);
//            BoeBase::debug($id);
//            BoeBase::debug(self::$currentLog[$log_key_name], 1);
        }
        if ($key != "*" && $key != '') {//返回某一个字段的值，比如名称
            return BoeBase::array_key_is_nulls(self::$currentLog[$log_key_name], $key, NULL);
        }
        return self::$currentLog[$log_key_name];
    }

//-------------------------------------------------------------------------和树形有关的代码开始------------------------------------------------------ 

    /**
     * getSubId
     * 根据ID获取分类的子子孙孙信息，例如：获取江苏的所有的城市时，将会把南京、苏州、工业园区、无锡等信息全部读取出来
     * @param type $id
     * @param type $return_key 如果只返回特定的数组内容，可指定该项
     * @param type $debug
     * @return type
     */
    static function getTreeSubId($table_name, $id = 0, $return_key = 0) {
        $log_key_name = __METHOD__ . '_' . $table_name . '_' . $id;
        if (isset(self::$currentLog[$log_key_name])) {//当前线程已有相关的数据时直接返回
            return self::$currentLog[$log_key_name];
        } else {
            self::$currentLog[$log_key_name] = self::getCache($table_name, $log_key_name);
        }
        if (self::$currentLog[$log_key_name] === NULL || self::$currentLog[$log_key_name] === false) {//没有数据的时候
            $base_tree_name = $table_name . '_baseTree';
            if (!isset(self::$currentLog[$base_tree_name])) {
                self::$currentLog[$base_tree_name] = self::getTreeBase($table_name);
            }
            self::$currentLog[$log_key_name] = array();
            $tmp_info = self::getTableOneInfo($table_name, $id);
            if ($tmp_info) {
                self::$currentLog[$log_key_name][$id] = $tmp_info;
                self::$currentLog[$log_key_name] = array_merge(self::$currentLog[$log_key_name], self::ParseTreeToBaseArray($table_name, $id));
            } else {
                self::$currentLog[$log_key_name] = array();
            }
            self::setCache($log_key_name, self::$currentLog[$log_key_name]); // 设置缓存
        }
        if ($return_key && is_array(self::$currentLog[$log_key_name]) && self::$currentLog[$log_key_name]) {//如果只返回特定的数组内容if ($return_key && is_array(self::$log[$log_key_name]) && self::$log[$log_key_name]) {//如果只返回特定的数组内容
            $tmp_sult = array();
            foreach (self::$currentLog[$log_key_name] as $key => $a_info) {
                $tmp_sult[] = isset($a_info[$return_key]) ? $a_info[$return_key] : $key;
            }
            return $tmp_sult;
        }
        return self::$currentLog[$log_key_name];
    }

    /**
     * ParseTreeToBaseArray
     *  根据传入分类ID，得到其子子孙孙的组成的平行数组，
     * @param int $id
     * @return array
     */
    private function ParseTreeToBaseArray($table_name, $parent_id = 0) {
        if (!$table_name) {
            return NULL;
        }
        $base_tree_name = $table_name . '_baseTree';
        $sult = array();
        if (isset(self::$currentLog[$base_tree_name][$parent_id])) {//有下一级的 
            $tmp_sult = self::$currentLog[$base_tree_name][$parent_id];
            foreach ($tmp_sult as $key => $a_info) {
                $tmp_sult = array_merge($tmp_sult, call_user_func_array(__METHOD__, array($table_name, $key)));
            }
            $sult = $tmp_sult;
        }
        return $sult;
    }

    /**
     * getParentId
     * 根据ID获取分类的祖祖辈辈的上级信息，例如：获取工业园区的所有上级时，将会把 华中 江苏， 苏州，  工业园区
     * @param type $id
     * @param type $create_mode 是否去缓存
     * @return type
     */
    static function getTreeParentId($table_name, $id = 0, $create_mode = 0) {
        if (!$table_name) {
            return NULL;
        }
        $log_key_name = __METHOD__ . '_' . $table_name . '_' . $id;
        if ($create_mode) {
            self::$currentLog[$log_key_name] = NULL;
        } else {
            if (!isset(self::$currentLog[$log_key_name])) {//当前线程已有相关的数据时直接返回
                self::$currentLog[$log_key_name] = self::getCache($log_key_name);
            }
        }
        if (self::$currentLog[$log_key_name] === NULL || self::$currentLog[$log_key_name] === false) {//没有数据的时候
            $base_tree_name = $table_name . '_baseTree';
            if (!self::$currentLog[$base_tree_name]) {
                self::$currentLog[$base_tree_name] = self::getTreeBase($table_name);
            }
            self::$currentLog[$log_key_name] = array();
            $tmp_info = self::getTableOneInfo($table_name, $id);
            if ($tmp_info) {//信息是存在的
                self::$currentLog[$log_key_name][] = $tmp_info;
                $loop_i = 0;
                $max_level = BoeBase::array_key_is_numbers(self::$tableConfig[$table_name], 'max_level', 20);
                while ($tmp_info[self::$tableConfig[$table_name]['parent_key_name']] != '0' && $loop_i < $max_level) {
                    $tmp_info = self::getTableOneInfo($table_name, $tmp_info[self::$tableConfig[$table_name]['parent_key_name']]);
                    if ($tmp_info) {
                        $kid = $tmp_info[self::$tableConfig[$table_name]['primary_key']];
                        self::$currentLog[$log_key_name][] = $tmp_info;
                    } else {
                        break;
                    }
                    $loop_i++;
                }
            } else {
                self::$currentLog[$log_key_name] = array();
            }
            self::setCache($log_key_name, self::$currentLog[$log_key_name], 14400); // 设置缓存
        }
        if (!is_array(self::$currentLog[$log_key_name])) {
            self::$currentLog[$log_key_name] = array();
        }
        return self::$currentLog[$log_key_name];
    }

    /**
     * getHasClidTree
     * 根据ID判断某个分类是否有子分类
     * @param type $id
     * @return boolean
     */
    static function getTreeHasClid($table_name, $id) {
        if (!$table_name) {
            return NULL;
        }
        $base_tree_name = $table_name . '_baseTree';
        $sult = false;
        if (!self::$currentLog[$base_tree_name]) {
            self::$currentLog[$base_tree_name] = self::getTreeBase($table_name);
        }
        $sult = isset(self::$currentLog[$base_tree_name][$id]);
        return $sult;
    }

    /**
     * getBaseTree 获取最简单的分类树形结构 
     * @param type $create_mode 是否强制从数据库读取

     */
    static function getTreeBase($table_name, $create_mode = 0) {
        if (!$table_name) {
            return NULL;
        }
        $cache_name = __METHOD__ . $table_name;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {
            $table_all_name = $table_name . '_all';
            if (!isset(self::$currentLog[$table_all_name])) {
                self::$currentLog[$table_all_name] = self::getTableAll($table_name, $create_mode);
            }
            $sult = array();
            if (is_array(self::$currentLog[$table_all_name])) {
                foreach (self::$currentLog[$table_all_name] as $key => $a_info) {
                    if (!isset($sult[$a_info[self::$tableConfig[$table_name]['parent_key_name']]])) {
                        $sult[$a_info[self::$tableConfig[$table_name]['parent_key_name']]] = array();
                    }
                    $sult[$a_info[self::$tableConfig[$table_name]['parent_key_name']]][$key] = $a_info;
                }
            }
            self::setCache($cache_name, $sult);
        }
        return $sult;
    }

    /**
     * getDetailTree 获取完整的分类的树形
     * @param type $create_mode
     * @param type $debug
     */
    static function getTreeDetail($table_name, $kid = '', $create_mode = 0) {
        $cache_name = __METHOD__ . '_' . $table_name . '_kid_' . $kid;
        if (!$table_name) {
            return NULL;
        }
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//拼接成读取 
            $base_tree_name = $table_name . '_baseTree';
            if (!self::$currentLog[$base_tree_name] || $create_mode) {
                self::$currentLog[$base_tree_name] = self::getTreeBase($table_name, $create_mode);
            }
            $sult = self::ParseTree($table_name, $kid);
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /**
     * ParseTree
     *  根据传入分类ID，得到子树
     * @param int $id
     * @return array
     */
    private function ParseTree($table_name, $parent_id = 0) {
        if (!$table_name) {
            return NULL;
        }
        $sult = array();
        $base_tree_name = $table_name . '_baseTree';
        if (isset(self::$currentLog[$base_tree_name][$parent_id])) {//有下一级的 
            $tmp_sult = self::$currentLog[$base_tree_name][$parent_id];
            foreach ($tmp_sult as $key => $a_info) {
                $tmp_sult[$key]['sub_cate'] = call_user_func_array(__METHOD__, array($table_name, $key));
            }
            $sult = $tmp_sult;
        }

        return $sult;
    }

    /**
     * 获取一个Opitons的树形数组，将kid的子子分类过滤掉
     * @param type $kid
     * @return type
     */
    static function getTreeScatterArrayAdv($table_name, $kid = 0) {
        if (!$table_name) {
            return NULL;
        }
        $tree_info = self::getTreeScatterArray($table_name);
        if ($kid) {//传递了分类ID时，就需要将其子子孙孙的ID过滤掉
            $pkid = self::$tableConfig[$table_name]['primary_key'];
            $allSubInfo = self::getTreeSubId($table_name, $kid, $pkid);
            foreach ($tree_info as $key => $a_info) {
                if (in_array($a_info[$pkid], $allSubInfo)) {//解决自己将自己设定父分类的问题，或将自己的子分类设定自己的父分类
                    unset($tree_info[$key]);
                }
            }
        }
        return $tree_info;
    }

    /**
     * getScatterTreeArray
     * 将getDetailTree生成折叠的树形菜单全部打开为一个平行数组，一般用在表单select时使用Options
     */
    static function getTreeScatterArray($table_name, $kid = '', $create_mode = 0) {
        if (!$table_name) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_' . $table_name;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//拼接成读取  
            $detail_tree = self::getTreeDetail($table_name, $kid);
            $sult = self::parseTreeToScatter($table_name, $detail_tree);
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /**
     * 将生成折叠的树形菜单全部打开
     */
    private function parseTreeToScatter($table_name, $tree = array(), $add_text = '[tab]') {
        if (!$table_name) {
            return NULL;
        }
        $sult = array();
        if (is_array($tree) && !empty($tree)) {
            foreach ($tree as $key => $a_info) {
                $a_info['name'] = $add_text . $a_info['name'];
                $new_add_text = $add_text . $add_text;
                $sult[$key] = $a_info;
                unset($sult[$key]['sub_cate']);
                if (isset($a_info['sub_cate']) && is_array($a_info['sub_cate'])) {//读取下级分类S
                    $sult = array_merge($sult, call_user_func_array(__METHOD__, array($table_name, $a_info['sub_cate'], $new_add_text)));
                }//读取下级分类E
            }
        }
        return $sult;
    }

}

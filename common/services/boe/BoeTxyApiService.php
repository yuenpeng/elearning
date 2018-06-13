<?php

namespace common\services\boe;

use common\helpers\TNetworkHelper;
use common\services\boe\BoeBaseService;
use common\base\BoeBase;
use common\models\boe\BoeTxyStudentIntegral;
use common\models\boe\BoeTxyStudentIntegralSummary;
use common\models\boe\BoeTxyClassIntegral;
use common\models\boe\BoeTxyClassIntegralSummary;
use common\models\boe\BoeTxyStudentBadge;
use common\models\boe\BoeTxyClassBadge;
use common\models\boe\BoeTxyClassRank;
use yii\db\Expression;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 2017特训营积分与勋章接口相关
 * @author Zhenglk
 * @email zhenglk@cg789.com
 */
class BoeTxyApiService {

   static $loadedObject = array();
   static $_env = array();
   private static $commonInfoCacheTime = 3600;
   private static $statsInfoCacheTime = 18000; //统计内信息的缓存时间 
   private static $writingStatusCacheTime = 300; //写状态的缓存时间
   private static $treatedCacheTime = 600; //已经处理过的相应操作的缓存时间 
   private static $cacheNameFix = 'boe_txy_';

   /**
    * isNoCacheMode当前是否处于重建缓存的状态
    * @return type
    */
   private static function isNoCacheMode() {
      self::initMaxMem();
      return Yii::$app->request->get('no_cache') == 1 ? true : false;
   }

   /**
    * isNoCacheMode当前是否处于重建缓存的状态
    * @return type
    */
   private static function isDebugMode() {
      self::initMaxMem();
      return Yii::$app->request->get('debug_mode') == 1 ? true : false;
   }

   /**
    * 读取缓存的封装
    * @param type $cache_name
    * @param type $debug
    * @return type
    */
   private static function getCache($cache_name) {
      self::initMaxMem();
      if (self::isNoCacheMode()) {
         return NULL;
      }
      $new_cache_name = self::$cacheNameFix . md5(!is_scalar($cache_name) ? serialize($cache_name) : $cache_name);
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
      $new_cache_name = self::$cacheNameFix . md5(!is_scalar($cache_name) ? serialize($cache_name) : $cache_name);
      Yii::$app->cache->set($new_cache_name, $data, $time); // 设置缓存 
      $debug = self::isDebugMode();
      if ($debug) {
         echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
         print_r($data);
         echo "\n</pre>";
      }
   }

   /**
    * 以事务方式 执行多条sql
    * @param type $sql
    * @return boolean
    */
   private static function transactionSql($sql = array()) {
      self::initMaxMem();
      if (!$sql || !is_array($sql)) {
         return false;
      }
      $dbObj = Yii::$app->db;
      if (count($sql) == 1) {
         try {
            $dbObj->createCommand(current($sql))->execute();
            $db_sult = true;
         } catch (Exception $ex) {
            $db_sult = false;
         }
      } else {
         $tr = $dbObj->beginTransaction();
         $sql_sult = array();
         try {
            foreach ($sql as $key => $a_sql) {
               $sql_sult[$key] = $dbObj->createCommand($a_sql)->execute();
            }
            $tr->commit();
            $db_sult = true;
         } catch (\Exception $e) {
            $tr->rollBack();
            $db_sult = false;
         }
      }
      return $db_sult;
   }

   //*******************************************几个和配置类的方法S*********************************************************************
   /**
    * 获取当前操作人的Kid
    * @return string
    */
   static function getCurrentUserKid() {
      self::initMaxMem();
      $u_kids = strval(Yii::$app->user->getId());
      if (!$u_kids) {
         return '00000000-0000-0000-0000-000000000001';
      }
      return $u_kids;
   }

   /**
    * 获取全部的特训营运行配置
    */
   static function getRunConfig($create_mode = 0) {
      self::initMaxMem();
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $sql = "SELECT * from eln_boe_txy_run_config";
         $connection = Yii::$app->db;
         $db_sult = $connection->createCommand($sql)->queryAll();
         if (is_array($db_sult)) {
            self::$_env[$cache_name] = array();
            foreach ($db_sult as $a_info) {
               if ($a_info['key']) {
                  self::$_env[$cache_name][$a_info['key']] = $a_info['value'];
               }
            }
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name]);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 当前日期是否在特训营配置的允许日期范围内
    * @param type $config
    * @param type $key
    * @return type
    */
   static function checkCurrentInAllowDaysConfig($config, $key, $current_day) {
      $days = BoeBase::array_key_is_nulls($config, $key, NULL);

      $days = trim(str_replace(array('\\', '/', ':', ',', '：', '；', '，'), ';', $days), ';');
      if (!$days) {
         return true;
      }
      $days = explode(';', $days);
//      BoeBase::debug(__METHOD__);
//      BoeBase::debug(in_array($current_day, $days));
//      BoeBase::debug($days, 1);
      return in_array($current_day, $days);
   }

   /**
    * 将内存调整到最大1024M
    */
   private static function initMaxMem() {
      if (!isset(self::$_env['maxMem'])) {
         @ini_set('max_execution_time', '600');
         @ini_set('memory_limit', '1024M');
         self::$_env['maxMem'] = true;
      }
   }

   /**
    * 获取全部的特训营的学员,非常重要的方法
    * @param type $create_mode
    * @return type
    */
   static function getAllStudent($create_mode = 0) {
      self::initMaxMem();
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }
      if (empty(self::$_env[$cache_name])) {
         $run_config = self::getRunConfig();
         $domain_id = BoeBase::array_key_is_nulls($run_config, 'domain_id', '');
         if (!$domain_id) {
            BoeBase::debug("没有指定要的域名", 1);
         }

         $sql = "select u.kid,u.real_name,u.user_name,u.orgnization_id,u.domain_id,u.company_id from eln_fw_user u where domain_id ='{$domain_id}' and is_deleted = '0';";
         $connection = Yii::$app->db;
         $db_sult = $connection->createCommand($sql)->queryAll();

         if (is_array($db_sult)) {
//            BoeBase::debug(__METHOD__);
//            BoeBase::debug($sql);
//            BoeBase::debug($db_sult);
            $t_arr = array();
            foreach ($db_sult as $a_info) {
               $o_id = $a_info['orgnization_id'];
               $t_path_info = BoeBaseService::getTreeParentId('FwOrgnization', $o_id);
//               BoeBase::debug($o_id);
//               BoeBase::debug($t_path_info);
               $a_info['orgnization_name'] = BoeBase::array_key_is_nulls($t_path_info, array(0 => 'orgnization_name'), '未知'); //班级名称
               $a_info['battalion_id'] = BoeBase::array_key_is_nulls($t_path_info, array(1 => 'kid'), $o_id); //营级ID
               $a_info['battalion_name'] = BoeBase::array_key_is_nulls($t_path_info, array(1 => 'orgnization_name'), '未知'); //营级名称
               $a_info['area_id'] = BoeBase::array_key_is_nulls($t_path_info, array(2 => 'kid'), $a_info['battalion_id']); //大区ID    
               $a_info['area_name'] = BoeBase::array_key_is_nulls($t_path_info, array(2 => 'orgnization_name'), '未知'); //大区名称
               $t_arr[$a_info['kid']] = $a_info;
            }
         } else {
            $t_arr = 'null';
         }
         self::$_env[$cache_name] = $t_arr;
         self::setCache($cache_name, self::$_env[$cache_name], self::$commonInfoCacheTime);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取某个学员的某个字段或是某个学员的信息
    * @param type $user_id
    * @param type $fields
    * @return type
    */
   static function getStudentInfo($user_id = '', $fields = '*') {
      $all_student = self::getAllStudent();
      if ($fields != '*' && $fields != 'all' && $fields) {
         return BoeBase::array_key_is_nulls($all_student, array($user_id => $fields), null);
      } else {
         return BoeBase::array_key_is_nulls($all_student, $user_id, null);
      }
   }

   /**
    * 获取全部的特训营班级
    * @param type $create_mode
    * @return type array()
    */
   static function getAllClass($create_mode = 0) {
      self::initMaxMem();
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }
      if (empty(self::$_env[$cache_name])) {
         $all_user = self::getAllStudent($create_mode == 2 ? 0 : $create_mode); //从全部的学员信息中读取
         if (!$all_user) {
            $t_arr = 'null';
         } else {
            $t_arr = array();
            foreach ($all_user as $a_user) {
               $key = trim($a_user['orgnization_id']);
               if (!isset($t_arr[$key])) {
                  $t_arr[$key] = array(
                    'orgnization_id' => $key,
                    'orgnization_name' => $a_user['orgnization_name'],
                    'o_id' => $key,
                    'o_name' => $a_user['orgnization_name'],
                    'battalion_id' => $a_user['battalion_id'],
                    'battalion_name' => $a_user['battalion_name'],
                    'area_id' => $a_user['area_id'],
                    'area_name' => $a_user['area_name'],
                    'user_kids' => array(),
                  );
               }
               $t_arr[$key]['user_kids'][$a_user['kid']] = $a_user['kid'];
            }
         }
         self::$_env[$cache_name] = $t_arr;
         self::setCache($cache_name, self::$_env[$cache_name], self::$commonInfoCacheTime);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取某个班级的某个字段或是某个班级的信息
    * @param type $class_id
    * @param type $fields
    * @return type
    */
   static function getClassInfo($class_id = '', $fields = '*') {
      $all_info = self::getAllClass();
      if ($fields != '*' && $fields != 'all' && $fields) {
         return BoeBase::array_key_is_nulls($all_info, array($class_id => $fields), null);
      } else {
         return BoeBase::array_key_is_nulls($all_info, $class_id, null);
      }
   }

   /**
    * 获取全部的特训营营队信息
    * @param type $create_mode
    * @return type array()
    */
   static function getAllBattalion($create_mode = 0) {
      self::initMaxMem();
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }
      if (empty(self::$_env[$cache_name])) {
         $all_user = self::getAllStudent($create_mode == 2 ? 0 : $create_mode); //从全部的学员信息中读取
         if (!$all_user) {
            $t_arr = 'null';
         } else {
            $t_arr = array();
            foreach ($all_user as $a_user) {
               $key = trim($a_user['battalion_id']);
               if (!isset($t_arr[$key])) {
                  $t_arr[$key] = array(
                    'o_id' => $key,
                    'o_name' => $a_user['battalion_name'],
                    'battalion_id' => $key,
                    'battalion_name' => $a_user['battalion_name'],
                    'area_id' => $a_user['area_id'],
                    'area_name' => $a_user['area_name'],
                    'user_kids' => array(),
                    'class_info' => array(),
                  );
               }
               $t_arr[$key]['user_kids'][$a_user['kid']] = $a_user['kid'];

               if (!isset($t_arr[$key]['class_info'][$a_user['orgnization_id']])) {
                  $t_arr[$key]['class_info'][$a_user['orgnization_id']] = array(
                    'o_id' => $a_user['orgnization_id'],
                    'o_name' => $a_user['orgnization_name'],
                  );
               }
            }
         }
         self::$_env[$cache_name] = $t_arr;
         self::setCache($cache_name, self::$_env[$cache_name], self::$commonInfoCacheTime);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取某个营队的某个字段或是全部信息
    * @param type $info_id
    * @param type $fields
    * @return type
    */
   static function getBattalionInfo($info_id = '', $fields = '*') {
      $all_info = self::getAllBattalion();
      if ($fields != '*' && $fields != 'all' && $fields) {
         return BoeBase::array_key_is_nulls($all_info, array($info_id => $fields), null);
      } else {
         return BoeBase::array_key_is_nulls($all_info, $info_id, null);
      }
   }

   /**
    * 获取全部的特训营大区信息
    * @param type $create_mode
    * @return type array()
    */
   static function getAllArea($create_mode = 0) {
      self::initMaxMem();
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }
      if (empty(self::$_env[$cache_name])) {
         $all_user = self::getAllStudent($create_mode == 2 ? 0 : $create_mode); //从全部的学员信息中读取
         if (!$all_user) {
            $t_arr = 'null';
         } else {
            $t_arr = array();
            foreach ($all_user as $a_user) {
               $key = trim($a_user['area_id']);
               if (!isset($t_arr[$key])) {
                  $t_arr[$key] = array(
                    'o_id' => $key,
                    'o_name' => $a_user['area_name'],
                    'area_id' => $key,
                    'area_name' => $a_user['area_name'],
                    'battalion_info' => array(),
                    'class_info' => array(),
                    'user_kids' => array(),
                  );
               }
               if (!isset($t_arr[$key]['class_info'][$a_user['orgnization_id']])) {
                  $t_arr[$key]['class_info'][$a_user['orgnization_id']] = array(
                    'o_id' => $a_user['orgnization_id'],
                    'o_name' => $a_user['orgnization_name'],
                    'p_id' => $a_user['battalion_id'],
                    'p_name' => $a_user['battalion_name'],
                  );
               }

               if (!isset($t_arr[$key]['battalion_info'][$a_user['battalion_id']])) {
                  $t_arr[$key]['battalion_info'][$a_user['battalion_id']] = array(
                    'o_id' => $a_user['battalion_id'],
                    'o_name' => $a_user['battalion_name'],
                  );
               }
               $t_arr[$key]['user_kids'][$a_user['kid']] = $a_user['kid'];
            }
         }
         self::$_env[$cache_name] = $t_arr;
         self::setCache($cache_name, self::$_env[$cache_name], self::$commonInfoCacheTime);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取某个大区的某个字段或是全部信息
    * @param type $info_id
    * @param type $fields
    * @return type
    */
   static function getAreaInfo($info_id = '', $fields = '*') {
      $all_info = self::getAllArea();
      if ($fields != '*' && $fields != 'all' && $fields) {
         return BoeBase::array_key_is_nulls($all_info, array($info_id => $fields), null);
      } else {
         return BoeBase::array_key_is_nulls($all_info, $info_id, null);
      }
   }

   /**
    * 检测用户的权限是否在某个level_value中
    * @param type $user_level_info
    * @param type $level_value
    * @return boolean
    */
   static function checkRoleFromUserLevel($user_level_info, $level_value = 0, $return_deatil = false) {
//      BoeBase::debug($user_level_info,1);
      foreach ($user_level_info as $a_info) {
         if (is_array($level_value)) {
            if (in_array($a_info['level'], $level_value)) {
               return $return_deatil ? $a_info : true;
            }
         } else {
            if ($a_info['level'] == $level_value) {
               return $return_deatil ? $a_info : true;
            }
         }
      }
      return false;
   }

   /**
    * 获取用户的最高等级的组织信息
    * @param type $user_level_info
    * @param type $level_value
    * @return boolean
    */
   static function getMaxLevelFromUserLevelInfo($user_level_info) {
      $level = array();
      foreach ($user_level_info as $key => $a_info) {
         $level[$a_info['level']] = $a_info;
      }
      krsort($level);
      $level = current($level);
      return $level['info'];
   }

   /**
    * 根据组织的ID，获取其对应的路径ID
    * @param type $oid 
    * @return string
    */
   static function getOrgnizationIdArray($oid) {
      self::initMaxMem();
      $log_key_name = __METHOD__ . '_' . $oid;
      if (!isset(self::$_env[$log_key_name])) {//当前进程中没有相关的数据时  
         self::$_env[$log_key_name] = BoeBaseService::getTreeParentId('FwOrgnization', $oid);
      }
      return self::$_env[$log_key_name];
   }

   /**
    * 同步积分表,积分汇总表, 班级积分表,班级积分汇总表、学员徽章、班级徽章表的组织ID
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function syncOrg($date = '', $create_mode = 0) {
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -100,
              'message' => '指定的日期格式不正确',
            );
         }
      }
      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $writing_cache_name = __METHOD__ . '_date_' . $string_day . '_is_writing';
      $write_status = self::getCache($writing_cache_name); //读取数据统计的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -99,
           'message' => '其它进程正在进行操作',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作

      $all_class = self::getAllClass(1);
      if (!$all_class || !is_array($all_class)) {
         self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
         return array(
           'result' => -98,
           'message' => '没有可用的班级信息',
           'list' => NULL,
         );
      }
      $table_name1 = BoeTxyStudentIntegral::tableName();
      $table_name2 = BoeTxyStudentIntegralSummary::tableName();
      $table_name3 = BoeTxyClassIntegral::tableName();
      $table_name4 = BoeTxyClassIntegralSummary::tableName();

      $table_name5 = BoeTxyStudentBadge::tableName();
      $table_name6 = BoeTxyClassBadge::tableName();
      $sql = array();
      $sql[] = "update {$table_name1} set orgnization_id='',battalion_id='',area_id='' where `date`='{$string_day}'";
      $sql[] = "update {$table_name2} set orgnization_id='',battalion_id='',area_id='' where `summary_date`='{$string_day}'";
      $sql[] = "update {$table_name3} set battalion_id='',area_id='' where `date`='{$string_day}'";
      $sql[] = "update {$table_name4} set battalion_id='',area_id='' where `summary_date`='{$string_day}'";
      $sql[] = "update {$table_name5} set orgnization_id='',battalion_id='',area_id='' where `date`='{$string_day}'";
      $sql[] = "update {$table_name6} set battalion_id='',area_id='' where `date`='{$string_day}'";
      foreach ($all_class as $a_class) {
         $user_ids = "'" . implode("','", $a_class['user_kids']) . "'";
         //同步学员积分表的组织信息
         $sql[] = "update {$table_name1} set orgnization_id='{$a_class['orgnization_id']}',battalion_id='{$a_class['battalion_id']}',area_id='{$a_class['area_id']}' where  `date`='{$string_day}' and `user_id` in({$user_ids}) ";
         //同步学员积分汇总表的组织信息
         $sql[] = "update {$table_name2} set orgnization_id='{$a_class['orgnization_id']}',battalion_id='{$a_class['battalion_id']}',area_id='{$a_class['area_id']}' where  `summary_date`='{$string_day}' and `user_id` in({$user_ids}) ";
         //同步班级积分表的组织信息
         $sql[] = "update {$table_name3} set battalion_id='{$a_class['battalion_id']}',area_id='{$a_class['area_id']}' where  `date`='{$string_day}' and `orgnization_id` ='{$a_class['orgnization_id']}' ";
         //同步班级积分汇总表的组织信息
         $sql[] = "update {$table_name4} set battalion_id='{$a_class['battalion_id']}',area_id='{$a_class['area_id']}' where  `summary_date`='{$string_day}' and `orgnization_id` ='{$a_class['orgnization_id']}'";
         //同步学员徽章表的组织信息
         $sql[] = "update {$table_name5} set orgnization_id='{$a_class['orgnization_id']}',battalion_id='{$a_class['battalion_id']}',area_id='{$a_class['area_id']}' where  `date`='{$string_day}' and `user_id` in({$user_ids}) ";
         //同步班级徽章的组织信息
         $sql[] = "update {$table_name6} set battalion_id='{$a_class['battalion_id']}',area_id='{$a_class['area_id']}' where  `date`='{$string_day}' and `orgnization_id` ='{$a_class['orgnization_id']}'";
      }
      $db_sult = self::transactionSql($sql);

      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug("Error Sql:");
            BoeBase::debug(str_replace(array('{{%', '}}'), array('eln_', ''), implode(";\n", $sql)), 1);
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }

      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   //*******************************************几个和配置类的方法E*********************************************************************
//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>牛的不像地球人一样的与徽章有关的几个接口从这里开始<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
   private static $badgeConfigParams = array(//有关徽章事件的参数  
     'course' => 'course', //课程徽章
     'examine' => 'examine', //结营考试
     'bd' => 'bd', //拓展徽章
     'train' => 'train', //训练标兵
     'service' => 'service', //内务标兵
     'campaign' => 'campaign', //活动标兵
     'basketball' => 'basketball', //篮球队队员
     'speech' => 'speech', //演讲比赛
     'party' => 'party', //晚会成就
     'meeting' => 'meeting', //班会成就
     'all_student' => 'all_student', //一个都不能少
     'bd_achievement' => 'bd_achievement', //拓展成就
     'mi_achievement' => 'mi_achievement', //军事训练评比成就
     'basketball_achievement' => 'basketball_achievement', //篮球赛决赛成就
     'knowledge_achievement' => 'knowledge_achievement', //知识竞赛
   );

   /**
    * 获取全部的徽章配置
    */
   static function getAllBadgeConfig($create_mode = 0) {
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $sql = "SELECT kid, `name`, `key`, `type`, `integral_config`,`integral_is_rank`,`img_url` from eln_boe_txy_badge WHERE is_deleted =0";
         $connection = Yii::$app->db;
         $db_sult = $connection->createCommand($sql)->queryAll();
         if (is_array($db_sult)) {
            self::$_env[$cache_name] = array();
            foreach ($db_sult as $a_info) {
               if ($a_info['key']) {
                  $a_info['integral_config'] = !empty($a_info['integral_config']) ? str_replace(array('：', ';', ',', ':', '，', ':'), ';', $a_info['integral_config']) : array();
                  self::$_env[$cache_name][$a_info['key']] = $a_info;
               }
            }
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name]);
      }
      if ($create_mode) {
         self::getAllBadgeConfigKid(1);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取全部的徽章配置用KID做索引
    */
   static function getAllBadgeConfigKid($create_mode = 0) {
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $sql = "SELECT kid, `name`, `key`, `type`, `integral_config`,`integral_is_rank`,`img_url` from eln_boe_txy_badge WHERE is_deleted =0";
         $connection = Yii::$app->db;
         $db_sult = $connection->createCommand($sql)->queryAll();
         if (is_array($db_sult)) {
            self::$_env[$cache_name] = array();
            foreach ($db_sult as $a_info) {
               if ($a_info['kid']) {
                  $a_info['integral_config'] = !empty($a_info['integral_config']) ? str_replace(array('：', ';', ',', ':', '，', ':'), ';', $a_info['integral_config']) : array();
                  self::$_env[$cache_name][$a_info['kid']] = $a_info;
               }
            }
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name]);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取徽章的配置参数
    * @param type $type_key
    * @return type
    */
   static function getBadgeConfig($type_key) {
      $cache_name = __METHOD__ . 'type_key_' . $type_key;
      if (isset(self::$_env[$cache_name])) {
         return self::$_env[$cache_name];
      }
      $config_key = BoeBase::array_key_is_nulls(self::$badgeConfigParams, $type_key);

      $all_config = self::getAllBadgeConfig();
      self::$_env[$cache_name] = BoeBase::array_key_is_nulls($all_config, $config_key);
      return self::$_env[$cache_name];
   }

   /**
    * 检测徽章信息是否正确
    * @param type $date
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function checkBadgeConfigFromDate($date, $event_key, $event_name) {
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -100,
              'message' => '指定的日期格式不正确',
            );
         }
      }
      $b_config = self::getBadgeConfig($event_key);
      if (!is_array($b_config) || !$b_config) {
         return array(
           'result' => -99,
           'message' => '徽章配置信息中还没有[' . $event_name . ']相关的配置',
         );
      }

      $b_config['result'] = 1;
      $b_config['string_day'] = date('Y-m-d', strtotime($date)); //日期字符串型
      return $b_config;
   }

//++++++++++++++++++++++++++++++++和班级徽章有关的方法S++++++++++++++++++++++++++++++++++++++++++
   /**
    * 将某一个班级，某一天的徽章的状态切换
    * @param type $badge_type
    * @param type $class_id
    * @param type $date
    * @return type
    */
   static function switchClassBadge($badge_type, $class_id, $date = '') {
      if (!$badge_type) {
         return array('result' => -100, 'message' => '未指定徽章配置');
      }
      if (!$class_id) {
         return array('result' => -99, 'message' => '未指定连队信息');
      }
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -98,
              'message' => '指定的日期格式不正确',
            );
         }
      }

      $b_config = self::getBadgeConfig($badge_type);
      if (!$b_config) {
         return array('result' => -97, 'message' => '指定的徽章类型不正确!');
      }
      $condition = array(
        'orgnization_id' => $class_id,
        'badge_id' => $b_config['kid'],
        'date' => $date,
      );

      $dbobj = new BoeTxyClassBadge();
      $list_params = array(
        'condition' => array(
          $condition
        ),
        'return_total_count' => 1,
        'show_deleted' => 1,
        'field' => 'date,badge_id,is_deleted'
      );

      $db_sult = $dbobj->getList($list_params);
//      BoeBase::debug(__METHOD__.'$badge_type:'.$badge_type);
//      BoeBase::debug($b_config,1);
      $c_info = is_array($db_sult['list'][0]) ? $db_sult['list'][0] : NULL;
      if ($c_info) {//对于已经存在的数据
//         BoeBase::debug('对于已经存在的数据' . ($c_info['is_deleted'] == 1 ? '点亮' : '熄灭'));
         $sult = self::updateClassBadge($class_id, $date, ($c_info['is_deleted'] == 1 ? 0 : 1), $badge_type);
      } else {
//         BoeBase::debug('对于还没有添加的数据');
         $sult = self::addClassBadge($class_id, $date, '', $badge_type);
      }
      if ($sult['result'] == 1) {
         if (isset($c_info['is_deleted'])) {
            if ($c_info['is_deleted'] == 0) {//已经点亮的，关闭它
               return array('result' => 1, 'message' => 'success', 'status' => 0);
            } else {//对于已经关闭的，再次点亮它 
               return array('result' => 1, 'message' => 'success', 'status' => 1);
            }
         } else {//还没有添加,点亮它
            return array('result' => 1, 'message' => 'success', 'status' => 1);
         }
      }
      return $sult;
   }

   /**
    * 根据不同的班级KID，获取不同的班级徽章列表
    * @param type $org_id
    * @param type $badge_id
    * @param type $date
    * @param type $get_rank 
    * @return type
    */
   static function getClassBadgeList($org_id = '', $badge_id = '', $date = '', $get_rank = 0) {
      if (!$org_id) {
         return array(
           'result' => -100,
           'message' => '未指定徽章查询的组织ID和类型',
           'list' => NULL,
         );
      }
      $condition = array(
        'orgnization_id' => $org_id
      );
      if ($badge_id) {
         $condition['badge_id'] = $badge_id;
      }
      if ($date) {
         $condition['date'] = $date;
      }
      $dbobj = new BoeTxyClassBadge();
      $list_params = array(
        'condition' => array(
          $condition
        ),
        'return_total_count' => 1,
        'field' => 'date,badge_id,is_deleted'
      );

      $db_sult = $dbobj->getList($list_params);
      $badge_all_config = self::getAllBadgeConfigKid();

      $sult = array();
      if (is_array($db_sult['list'])) {
         $class_integral_list = array();
         foreach ($db_sult['list'] as $a_info) {
            if (!isset($sult[$a_info['date']])) {
               $sult[$a_info['date']] = array();
            }
            $tmp_key = BoeBase::array_key_is_nulls($badge_all_config, array($a_info['badge_id'] => 'key'));
            if ($tmp_key) {
               if ($get_rank) {
                  $tmp_rank = self::getClassBadgeRank($org_id, $tmp_key,$a_info['date']);
                  $sult[$a_info['date']][$tmp_key] = array(
                    'name' => BoeBase::array_key_is_nulls($badge_all_config, array($a_info['badge_id'] => 'name')),
                    'rank' => $tmp_rank,
                  );
               } else {
                  $sult[$a_info['date']][$tmp_key] = $tmp_key;
               }
//               $sult[$a_info['date']][$tmp_key] = $get_rank ? self::getClassBadgeRank($org_id, $a_info['date'], $tmp_key) : $tmp_key;
            }
         }
      }

      return array(
        'result' => 1,
        'message' => 'success',
        'list' => $sult
      );
   }

   /**
    * 获取某一个班级某一种徽章，某一天的排名
    * @param type $org_id
    * @param type $date
    * @param type $badge_key
    * @param type $debug
    * @return int
    */
   static function getClassBadgeRank($org_id, $badge_key = '', $date = '', $debug = 0) {
//      BoeBase::debug(__METHOD__);
      $badge_config = self::getBadgeConfig($badge_key);
      $integral_key_config = BoeBase::array_key_is_nulls($badge_config, 'integral_config');
      if ($debug) {
         BoeBase::debug(__METHOD__ . $date);
         BoeBase::debug($integral_key_config);
      }
      if (!$integral_key_config) {
         return 0;
      }
      $integral_key_config_arr = explode(';', $integral_key_config);
      if (count($integral_key_config_arr) == 1) {//只有一个时，属于不需要排名的徽章
         return 0;
      }
      $integral_id = array();
      $integral_key = array();
      $rank = 1;
      foreach ($integral_key_config_arr as $a_key) {
         $integral_info = self::getIntegralConfig($a_key);
         if ($integral_info) {
            $integral_id[$integral_info['kid']] = $rank;
            $integral_key[$a_key] = $integral_info['kid'];
            $rank++;
         }
      }
      if ($debug) {
         BoeBase::debug('---------------------------------------');
         BoeBase::debug($integral_id);
         BoeBase::debug($integral_key);
      }
      if (!$integral_id) {
         return 0;
      }
      $integral_list = self::getClassIntegralList($org_id, 'orgnization_id', '班级', $date, 0, 1);
      if ($debug) {
         BoeBase::debug('-------------积分列表--------------------------');
         BoeBase::debug($integral_list);
      }
      if (empty($integral_list['list'])) {
         return 0;
      }

      foreach ($integral_list['list'] as $a_info) {
         if (isset($integral_id[$a_info['integral_id']])) {
            return $integral_id[$a_info['integral_id']];
         }
      }
      return 0;
   }

   /**
    * 根据不同的字段，字段值，不同的日期，获取不同的班级徽章列表
    * @param type $tag
    * @param type $field
    * @param type $org_id
    * @param type $date
    * @return type
    */
   static function getClassBadgeFromOrg($tag = '', $field = '', $org_id = '', $date = '') {
      if (!$tag || !$org_id || !$field) {
         return array(
           'result' => -100,
           'message' => '未指定徽章类型',
           'list' => NULL,
         );
      }
      if (!$org_id || !$field) {
         return array(
           'result' => -99,
           'message' => '未指定徽章查询的组织ID和类型',
           'list' => NULL,
         );
      }

      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -98,
              'message' => '指定的日期格式不正确',
              'list' => NULL,
            );
         }
      }

      $b_config = self::getBadgeConfig($tag);
      if (!is_array($b_config) || !$b_config) {
         return array(
           'result' => -97,
           'message' => '徽章配置信息中还没有[' . $tag . ']相关的配置',
         );
      }


      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $dbobj = new BoeTxyClassBadge();
      $list_params = array(
        'condition' => array(
          array($field => $org_id, 'date' => $string_day, 'badge_id' => $b_config['kid']),
        ),
        'return_total_count' => 1,
        'indexBy' => 'orgnization_id',
      );
      $db_sult = $dbobj->getList($list_params);
//      BoeBase::debug(__METHOD__);
//      BoeBase::debug($db_sult,1);
      return array(
        'result' => 1,
        'message' => 'success',
        'list' => (!empty($db_sult['list']) && is_array($db_sult['list'])) ? $db_sult['list'] : array()
      );
   }

   /**
    * 根据不同的字段，字段值， 获取不同的班级徽章数量统计列表
    * @param type $tag
    * @param type $field
    * @param type $org_id
    * @param type $date
    * @return type
    */
   static function getClassBadgeCountList($tag = '', $field = '', $org_id = '') {
      if (!$tag || !$org_id || !$field) {
         return array(
           'result' => -100,
           'message' => '未指定徽章类型',
           'list' => NULL,
         );
      }
      if (!$org_id || !$field) {
         return array(
           'result' => -99,
           'message' => '未指定徽章查询的组织ID和类型',
           'list' => NULL,
         );
      }


      $b_config = self::getBadgeConfig($tag);
      if (!is_array($b_config) || !$b_config) {
         return array(
           'result' => -98,
           'message' => '徽章配置信息中还没有[' . $tag . ']相关的配置',
         );
      }

      $table_name = BoeTxyClassBadge::tableName();
      $sql = "SELECT count(*) as `count`, orgnization_id from {$table_name} where  {$field}='{$org_id}' and badge_id='{$b_config['kid']}' and is_deleted='0' group by orgnization_id ";
      $connection = Yii::$app->db;
      $db_sult = $connection->createCommand($sql)->queryAll();
      $sult = array();
      if (is_array($db_sult)) {
         foreach ($db_sult as $a_info) {
            $sult[$a_info['orgnization_id']] = $a_info['count'];
         }
      }

      return array(
        'result' => 1,
        'message' => 'success',
        'list' => $sult
      );
   }

   /**
    * 根据不同的字段，字段值， 获取不同的班级徽章数量日期汇总
    * @param type $tag
    * @param type $field
    * @param type $org_id
    * @param type $date
    * @return type
    */
   static function getClassBadgeDateGroup($tag = '', $field = '', $org_id = '', $create_mode = 0) {
      if (!$tag || !$org_id || !$field) {
         return array(
           'result' => -100,
           'message' => '未指定徽章类型',
           'list' => NULL,
         );
      }
      if (!$org_id || !$field) {
         return array(
           'result' => -99,
           'message' => '未指定徽章查询的组织ID和类型',
           'list' => NULL,
         );
      }

      $b_config = self::getBadgeConfig($tag);
      if (!is_array($b_config) || !$b_config) {
         return array(
           'result' => -98,
           'message' => '徽章配置信息中还没有[' . $tag . ']相关的配置',
         );
      }
      self::initMaxMem();
      $cache_name = __METHOD__ . "{$field}_{$org_id}_{$tag}";
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $table_name = BoeTxyClassBadge::tableName();
         $sql = "SELECT `date`  from {$table_name} where  {$field}='{$org_id}' and badge_id='{$b_config['kid']}' and is_deleted='0' group by `date` order by `date` desc ";
         $connection = Yii::$app->db;
         $db_sult = $connection->createCommand($sql)->queryAll();
//         BoeBase::debug(__METHOD__);
//         BoeBase::debug($sql, 1);
         if (is_array($db_sult)) {
            self::$_env[$cache_name] = array();
            foreach ($db_sult as $a_info) {
               self::$_env[$cache_name][] = $a_info['date'];
            }
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name], self::$treatedCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
        'list' => (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name],
      );
   }

   /**
    * 更新班级某一天获得某一种徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function updateClassBadge($class_id, $date = '', $status = 0, $event_key = '', $event_name = '', $mark = '') {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }
      if (!$class_id) {
         return array(
           'result' => -98,
           'message' => '未指定班级ID',
         );
      }

      if (!$b_config['type']) {
         return array(
           'result' => -97,
           'message' => '[' . $event_name . ']不是使用在班级上的',
         );
      }
      $db_where = array(
        'orgnization_id' => $class_id,
        'badge_id' => $b_config['kid'],
        'date' => $b_config['string_day'],
      );
      $current_user_kid = self::getCurrentUserKid();
      $current_time = time();
      $current_from = 'PC';
      $current_ip = TNetworkHelper::getClientRealIP();
      $update_arr1 = array(
        'is_deleted' => $status,
        'updated_by' => $current_user_kid,
        'updated_at' => $current_time,
        'updated_from' => $current_from,
        'updated_ip' => $current_ip,
        'version' => new Expression("`version`+1"),
      );
      if ($mark) {
         $update_arr1['mark'] = $mark;
      }
      $table_name1 = BoeTxyClassBadge::tableName();
      $table_name2 = BoeTxyClassIntegral::tableName();
      $dbObj = Yii::$app->db;
      $sql['badge_sql'] = $dbObj->createCommand()->update($table_name1, $update_arr1, $db_where)->getRawSql();
      if ($b_config['integral_config']) {
         $i_config = self::checkIntegralConfigFromDate($b_config['string_day'], $b_config['integral_config'], $event_name);
         if ($i_config['result'] === 1) {
            $db_where = array(
              'orgnization_id' => $class_id,
              'integral_id' => $i_config['integral_id'],
              'date' => $b_config['string_day'],
            );
            $sql['integral_sql'] = $dbObj->createCommand()->update($table_name2, $update_arr1, $db_where)->getRawSql();
         }
      }
//      BoeBase::debug(__METHOD__);
//      BoeBase::debug($sql);
      $db_sult = self::transactionSql($sql);
      if (!$db_sult) {//数据库出错 
         return array(
           'result' => -96,
           'message' => "数据库操作失败,错误如下:\n" . implode(";\n", $sql) . ';',
         );
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 班级某一天获得某一种徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function addClassBadge($class_id, $date = '', $mark = '', $event_key = '', $event_name = '') {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }
      if (!$class_id) {
         return array(
           'result' => -98,
           'message' => '未指定班级ID',
         );
      }

      if (!$b_config['type']) {
         return array(
           'result' => -97,
           'message' => '[' . $event_name . ']不是使用在班级上的',
         );
      }
      $cache_name = __METHOD__ . '_' . $event_key . '_class_id_' . $class_id . '_date_' . $b_config['string_day'];
      $sult = self::getCache($cache_name);
      if (!$sult) {//缓存中没有的时候
         $db_where = array(
           'orgnization_id' => $class_id,
           'badge_id' => $b_config['kid'],
           'date' => $b_config['string_day'],
         );
         $add_count = BoeTxyClassBadge::find()->where($db_where)->count();  // 返回数量
         if (!$add_count) {//还没有添加相应的记录
            if (!$mark) {
               $mark = BoeBase::array_key_is_nulls($b_config, 'name', $event_name);
            }
            $class_info = self::getClassInfo($class_id);
            $current_user_kid = self::getCurrentUserKid();
            $current_time = time();
            $current_from = 'PC';
            $current_ip = TNetworkHelper::getClientRealIP();
            $i_value = NULL;

            $table_name1 = BoeTxyClassBadge::tableName();
            $table_name2 = BoeTxyClassIntegral::tableName();
            $dbObj = Yii::$app->db;
            if ($b_config['integral_config']) {
               $i_config = self::checkIntegralConfigFromDate($b_config['string_day'], $b_config['integral_config'], $event_name);
               if ($i_config['result'] === 1) {
                  $i_value = array(
                    'kid' => new Expression('upper(UUID())'),
                    'orgnization_id' => $class_id,
                    'battalion_id' => BoeBase::array_key_is_nulls($class_info, 'battalion_id', ''),
                    'area_id' => BoeBase::array_key_is_nulls($class_info, 'area_id', ''),
                    'integral_id' => $i_config['integral_id'],
                    'score' => $i_config['score'],
                    'date' => $i_config['string_day'],
                    'mark' => $i_config['name'],
                    'type' => 0,
                    'version' => 1,
                    'created_by' => $current_user_kid,
                    'created_at' => $current_time,
                    'created_from' => $current_from,
                    'created_ip' => $current_ip,
                    'updated_by' => $current_user_kid,
                    'updated_at' => $current_time,
                    'updated_from' => $current_from,
                    'updated_ip' => $current_ip,
                    'is_deleted' => 0,
                  );
//                        BoeBase::debug(__METHOD__);
//                        BoeBase::debug($i_config,1);
               }
            }

            $b_value = array(
              'kid' => new Expression('upper(UUID())'),
              'orgnization_id' => $class_id,
              'battalion_id' => BoeBase::array_key_is_nulls($class_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($class_info, 'area_id', ''),
              'badge_id' => $b_config['kid'],
              'date' => $b_config['string_day'],
              'mark' => $mark,
              'version' => 1,
              'created_by' => $current_user_kid,
              'created_at' => $current_time,
              'created_from' => $current_from,
              'created_ip' => $current_ip,
              'updated_by' => $current_user_kid,
              'updated_at' => $current_time,
              'updated_from' => $current_from,
              'updated_ip' => $current_ip,
              'is_deleted' => 0,
            );
            $sql = array();
            $sql['badge_sql'] = $dbObj->createCommand()->insert($table_name1, $b_value)->getRawSql();
            if ($i_value) {
               $sql['integral_sql'] = $dbObj->createCommand()->insert($table_name2, $i_value)->getRawSql();
            }

            $db_sult = self::transactionSql($sql);
            if (!$db_sult) {//数据库出错 
               return array(
                 'result' => -96,
                 'message' => "数据库操作失败,错误如下:\n" . implode(";\n", $sql) . ';',
               );
            }
         }
         self::setCache($cache_name, '1', self::$treatedCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 班级得到班会成就徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addClassMeetingBadge($class_id = '', $date = '', $mark = '') {
      return self::addClassBadge($class_id, $date, $mark, 'meeting', '班会成就');
   }

   /**
    * 班级得到晚会成就徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addClassPartyBadge($class_id = '', $date = '', $mark = '') {
      return self::addClassBadge($class_id, $date, $mark, 'party', '晚会成就');
   }

   /**
    * 添加某一天的全部班级的某一样徽章
    * @param type $date
    * @param string $mark
    * @param type $event_key
    * @param type $event_name
    * @param type $create_mode
    * @return type
    */
   private static function addAllClassBadge($date = '', $mark = '', $event_key = '', $event_name = '', $create_mode = 0) {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }

      if (!$b_config['type']) {
         return array(
           'result' => -98,
           'message' => '[' . $event_name . ']不是使用在班级上的',
         );
      }
      if (!$mark) {
         $mark = '每日得到' . $event_name;
      }


      $writing_cache_name = __METHOD__ . '_date_' . $b_config['string_day'] . '_event_key_' . $event_key . '_is_writing';
      $write_status = $create_mode ? 0 : self::getCache($writing_cache_name); //读取正在写数据的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -97,
           'message' => '其它进程正在进行更新',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作
      $all_class_kids = self::getAllClass();

      if (!$all_class_kids) {
         self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
         return array(
           'result' => -95,
           'message' => '没有班级数据',
         );
      } else {
         $all_class_kids = array_keys($all_class_kids);
      }

      $exists_params = array(
        'condition' => array(
          ['badge_id' => $b_config['kid']],
          ['orgnization_id' => $all_class_kids],
          ['date' => $b_config['string_day']],
        ),
        'return_total_count' => 1,
        'indexBy' => 'orgnization_id',
        'field' => 'orgnization_id',
        'showDeleted' => true
      );
      $dbObj = new BoeTxyClassBadge();
      $exist_log = $dbObj->getList($exists_params);


      if (!empty($exist_log['list'])) {//对特定日期已经有存在班级徽章记录时,要进行忽略
         $exist_class_kids = array_keys($exist_log['list']);
//         BoeBase::debug(__METHOD__);
//         BoeBase::debug($exist_log);
//         BoeBase::debug($all_class_kids, 1);
         $all_class_kids = array_diff($all_class_kids, $exist_class_kids); //去除已经添加过的 
      }

      $exist_log = null;
      $db_sult = true;
      $sql = array();
      $i_config = NULL;
      if ($all_class_kids) {//有需要插入数据的状态时S
         if ($b_config['integral_config']) {
            $i_config = self::checkIntegralConfigFromDate($b_config['string_day'], $b_config['integral_config'], $event_name);
            if ($i_config['result'] !== 1) {
               $i_config = NULL;
            }
         }
         $current_user_kid = self::getCurrentUserKid();
         $current_time = time();
         $current_from = 'PC';
         $current_ip = TNetworkHelper::getClientRealIP();
         $table_name1 = BoeTxyClassBadge::tableName();
         $table_name2 = BoeTxyClassIntegral::tableName();
         $b_value_arr = $i_value_arr = array();

         foreach ($all_class_kids as $a_kid) {
            $class_info = self::getClassInfo($a_kid);
            $b_value_arr[] = array(
              'kid' => new Expression('upper(UUID())'),
              'orgnization_id' => $a_kid,
              'battalion_id' => BoeBase::array_key_is_nulls($class_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($class_info, 'area_id', ''),
              'badge_id' => $b_config['kid'],
              'mark' => $mark,
              'date' => $b_config['string_day'],
              'version' => 1,
              'created_by' => $current_user_kid,
              'created_at' => $current_time,
              'created_from' => $current_from,
              'created_ip' => $current_ip,
              'updated_by' => $current_user_kid,
              'updated_at' => $current_time,
              'updated_from' => $current_from,
              'updated_ip' => $current_ip,
              'is_deleted' => 0,
            );
            if ($i_config) {
               $i_value_arr[] = array(
                 'kid' => new Expression('upper(UUID())'),
                 'orgnization_id' => $a_kid,
                 'battalion_id' => BoeBase::array_key_is_nulls($class_info, 'battalion_id', ''),
                 'area_id' => BoeBase::array_key_is_nulls($class_info, 'area_id', ''),
                 'integral_id' => $i_config['integral_id'],
                 'score' => $i_config['score'],
                 'type' => 0,
                 'mark' => $i_config['name'],
                 'date' => $i_config['string_day'],
                 'version' => 1,
                 'created_by' => $current_user_kid,
                 'created_at' => $current_time,
                 'created_from' => $current_from,
                 'created_ip' => $current_ip,
                 'updated_by' => $current_user_kid,
                 'updated_at' => $current_time,
                 'updated_from' => $current_from,
                 'updated_ip' => $current_ip,
                 'is_deleted' => 0,
               );
            }
         }
         $dbObj = Yii::$app->db;
         $b_field_arr = array_keys(current($b_value_arr));
         $sql['badge_sql'] = $dbObj->createCommand()->batchInsert($table_name1, $b_field_arr, $b_value_arr)->getRawSql();
         if ($i_config) {
            $i_field_arr = array_keys(current($i_value_arr));
            $sql['integral_sql'] = $dbObj->createCommand()->batchInsert($table_name2, $i_field_arr, $i_value_arr)->getRawSql();
         }
         $db_sult = self::transactionSql($sql);
      }//有需要插入数据的状态时E
      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode()) {//数据库出错
            BoeBase::debug(__METHOD__);
            BoeBase::debug("Error Sql:");
            BoeBase::debug($badge_sql . ";\n" . implode(";\n", $sql), 1);
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 添加某一天的全部班级的一个都不能少徽章
    * @param type $date
    * @param string $mark 
    * @param type $create_mode
    * @return type
    */
   static function addAllClassAllInBadge($date = '', $mark = '', $create_mode = 0) {
      $event_key = 'all_student';
      $event_name = '一个都不能少';
      return self::addAllClassBadge($date, $mark, $event_key, $event_name, $create_mode);
   }

   /**
    * 某些个班级得到根据排名得到某一种徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function addClassRankBadge($class_id, $date = '', $mark = '', $event_key = '', $event_name = '') {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }
      if (!$class_id) {
         return array(
           'result' => -98,
           'message' => '未指定班级ID',
         );
      }

      if (!$b_config['type']) {
         return array(
           'result' => -97,
           'message' => '[' . $event_name . ']不是使用在班级上的',
         );
      }

      if (!$mark) {
         $mark = BoeBase::array_key_is_nulls($b_config, 'name', $event_name);
      }

      $current_user_kid = self::getCurrentUserKid();
      $current_time = time();
      $current_from = 'PC';
      $current_ip = TNetworkHelper::getClientRealIP();
      $table_name1 = BoeTxyClassBadge::tableName();
      $table_name2 = BoeTxyClassIntegral::tableName();

      $delete_badge_where = array(
        'badge_id' => $b_config['kid'],
        'date' => $b_config['string_day'],
      );
      $delete_integral_where = NULL;

      if (!is_array($class_id)) {
         $class_id = trim(str_replace(array('：', ';', ',', ':', '，', ':'), ';', $class_id), ';');
         $class_id_arr = explode(';', $class_id);
      } else {
         $class_id_arr = $class_id;
      }
      $class_id_arr = array_unique($class_id_arr); //去重复
      $b_value_arr = $i_value_arr = $i_config = array();
      if ($b_config['integral_config']) {//指定的徽章在获取时能得到积分
         $integral_key_arr = explode(';', $b_config['integral_config']);
         $integral_kid = array();
         $loop_i = 0;
         foreach ($integral_key_arr as $a_key) {
            if (trim($a_key)) {
               $i_config[$loop_i] = self::getIntegralConfig($a_key);
               if ($i_config[$loop_i]) {
                  $integral_kid[] = $i_config[$loop_i]['integral_id'];
               }
               $loop_i++;
            }
         }
         $delete_integral_where = array(//需要删除的之前的班级积分信息
           'integral_id' => $integral_kid,
           'date' => $b_config['string_day'],
         );
      }
      $loop_i = 0;

      foreach ($class_id_arr as $a_class) {
         $tmp_class_info = self::getClassInfo($a_class); //获取班级的详细信息

         if ($tmp_class_info) {//如果班级信息是正确的,并且相应的排名是能够得到徽章的状态S
            if (isset($i_config[$loop_i])) {
               $i_value_arr[$loop_i] = array(//班级积分表的记录
                 'kid' => new Expression('upper(UUID())'),
                 'orgnization_id' => $a_class,
                 'battalion_id' => BoeBase::array_key_is_nulls($tmp_class_info, 'battalion_id', ''),
                 'area_id' => BoeBase::array_key_is_nulls($tmp_class_info, 'area_id', ''),
                 'integral_id' => $i_config[$loop_i]['integral_id'],
                 'score' => $i_config[$loop_i]['score'],
                 'date' => $b_config['string_day'],
                 'mark' => $i_config[$loop_i]['name'],
                 'type' => 0,
                 'version' => 1,
                 'created_by' => $current_user_kid,
                 'created_at' => $current_time,
                 'created_from' => $current_from,
                 'created_ip' => $current_ip,
                 'updated_by' => $current_user_kid,
                 'updated_at' => $current_time,
                 'updated_from' => $current_from,
                 'updated_ip' => $current_ip,
                 'is_deleted' => 0,
               );
            }

            $b_value_arr[$loop_i] = array(//班级徽章表的记录
              'kid' => new Expression('upper(UUID())'),
              'orgnization_id' => $a_class,
              'battalion_id' => BoeBase::array_key_is_nulls($tmp_class_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($tmp_class_info, 'area_id', ''),
              'badge_id' => $b_config['kid'],
              'date' => $b_config['string_day'],
              'mark' => $mark,
              'version' => 1,
              'created_by' => $current_user_kid,
              'created_at' => $current_time,
              'created_from' => $current_from,
              'created_ip' => $current_ip,
              'updated_by' => $current_user_kid,
              'updated_at' => $current_time,
              'updated_from' => $current_from,
              'updated_ip' => $current_ip,
              'is_deleted' => 0,
            );

            $loop_i++;
         }//如果班级信息是正确的,并且相应的排名是能够得到徽章的状态E
      }
      $dbObj = Yii::$app->db;
      $db_sult = true;
      $sql = array();
      //步骤1：删除之前的班级徽章记录
      $sql['delete_badge'] = $dbObj->createCommand()->delete($table_name1, $delete_badge_where)->getRawSql();
      if ($delete_integral_where) {
         //步骤2：删除之前的班级积分记录
         $sql['delete_integral'] = $dbObj->createCommand()->delete($table_name2, $delete_integral_where)->getRawSql();
      }
      if ($b_value_arr) {
         //步骤3：添加数据到班级徽章记录
         $b_field_arr = array_keys(current($b_value_arr));
         $sql['insert_badge'] = $dbObj->createCommand()->batchInsert($table_name1, $b_field_arr, $b_value_arr)->getRawSql();
      }
      if ($i_value_arr) {
         //步骤4：添加数据到班级积分记录
         $i_field_arr = array_keys(current($i_value_arr));
         $sql['insert_integral'] = $dbObj->createCommand()->batchInsert($table_name2, $i_field_arr, $i_value_arr)->getRawSql();
      }

//      BoeBase::debug(implode(";\n", $sql), 1);
      $db_sult = self::transactionSql($sql);
      if (!$db_sult) {//数据库出错 
         return array(
           'result' => -96,
           'message' => "数据库操作失败,错误如下:\n" . implode(";\n", $sql) . ';',
         );
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 特定班级得到拓展成就徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $create_mode
    * @return type
    */
   static function addClassBdAchievementRanKBadge($class_id = '', $date = '', $mark = '', $create_mode = 0) {
      $event_key = 'bd_achievement';
      $event_name = '拓展成就';
      return self::addClassRankBadge($class_id, $date, $mark, $event_key, $event_name, $create_mode);
   }

   /**
    * 特定班级得到军事训练评比成就徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $create_mode
    * @return type
    */
   static function addClassMiAchievementRanKBadge($class_id = '', $date = '', $mark = '', $create_mode = 0) {
      $event_key = 'mi_achievement';
      $event_name = '军事训练评比成就';
      return self::addClassRankBadge($class_id, $date, $mark, $event_key, $event_name, $create_mode);
   }

   /**
    * 特定班级得到篮球赛决赛成就徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $create_mode
    * @return type
    */
   static function addClassBasketballAchievementRanKBadge($class_id = '', $date = '', $mark = '', $create_mode = 0) {
      $event_key = 'basketball_achievement';
      $event_name = '篮球赛决赛成就';
      return self::addClassRankBadge($class_id, $date, $mark, $event_key, $event_name, $create_mode);
   }

   /**
    * 特定班级得到知识竞赛徽章
    * @param type $class_id
    * @param type $date
    * @param type $mark
    * @param type $create_mode
    * @return type
    */
   static function addClassKnowledgeAchievementRanKBadge($class_id = '', $date = '', $mark = '', $create_mode = 0) {
      $event_key = 'knowledge_achievement';
      $event_name = '知识竞赛';
      return self::addClassRankBadge($class_id, $date, $mark, $event_key, $event_name, $create_mode);
   }

//++++++++++++++++++++++++++++++++和班级徽章有关的方法E++++++++++++++++++++++++++++++++++++++++++
//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@和学员得到徽章有关的方法开始@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

   /**
    * 根据不同的学员KID，获取不同的学员徽章列表
    * @param type $student_id
    * @param type $badge_id
    * @param type $date
    * @return type
    */
   static function getStudentBadgeList($student_id = '', $badge_id = '', $date = '') {

      if (!$student_id) {
         return array(
           'result' => -100,
           'message' => '未指定徽章查询的学员ID',
           'list' => NULL,
         );
      }
      $condition = array(
        'user_id' => $student_id
      );
      if ($badge_id) {
         $condition['badge_id'] = $badge_id;
      }
      if ($date) {
         $condition['date'] = $date;
      }
      $dbobj = new BoeTxyStudentBadge();
      $list_params = array(
        'condition' => array(
          $condition
        ),
        'return_total_count' => 1,
        'field' => 'date,badge_id,is_deleted'
      );

      $db_sult = $dbobj->getList($list_params);
      $all_config = self::getAllBadgeConfigKid();
//      BoeBase::debug($all_config);
//      BoeBase::debug($db_sult, 1);
      $sult = array();
      if (is_array($db_sult['list'])) {
         foreach ($db_sult['list'] as $a_info) {
            if (!isset($sult[$a_info['date']])) {
               $sult[$a_info['date']] = array();
            }
            $tmp_key = BoeBase::array_key_is_nulls($all_config, array($a_info['badge_id'] => 'key'));
            if ($tmp_key) {
               $sult[$a_info['date']][$tmp_key] = $tmp_key;
            }
         }
      }

      return array(
        'result' => 1,
        'message' => 'success',
        'list' => $sult
      );
   }

   /**
    * 将某一个学员，某一天的徽章的状态切换
    * @param type $badge_type
    * @param type $student_id
    * @param type $date
    * @return type
    */
   static function switchStudentBadge($badge_type, $student_id, $date = '') {
      if (!$badge_type) {
         return array('result' => -100, 'message' => '未指定徽章配置');
      }
      if (!$student_id) {
         return array('result' => -99, 'message' => '未指定学员信息');
      }
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -98,
              'message' => '指定的日期格式不正确',
            );
         }
      }

      $b_config = self::getBadgeConfig($badge_type);
      if (!$b_config) {
         return array('result' => -97, 'message' => '指定的徽章类型不正确!');
      }
      $condition = array(
        'user_id' => $student_id,
        'badge_id' => $b_config['kid'],
        'date' => $date,
      );

      $dbobj = new BoeTxyStudentBadge();
      $list_params = array(
        'condition' => array(
          $condition
        ),
        'return_total_count' => 1,
        'show_deleted' => 1,
        'field' => 'date,badge_id,is_deleted'
      );

      $db_sult = $dbobj->getList($list_params);
//      BoeBase::debug(__METHOD__.'$badge_type:'.$badge_type);
//      BoeBase::debug($b_config,1);
      $c_info = is_array($db_sult['list'][0]) ? $db_sult['list'][0] : NULL;
      if ($c_info) {//对于已经存在的数据
//         BoeBase::debug('对于已经存在的数据' . ($c_info['is_deleted'] == 1 ? '点亮' : '熄灭'));
         $sult = self::updateStudentBadge($student_id, $date, ($c_info['is_deleted'] == 1 ? 0 : 1), $badge_type);
      } else {
//         BoeBase::debug('对于还没有添加的数据');
         $sult = self::addStudentBadge($student_id, $date, '', $badge_type);
      }
      if ($sult['result'] == 1) {
         if (isset($c_info['is_deleted'])) {
            if ($c_info['is_deleted'] == 0) {//已经点亮的，关闭它
               return array('result' => 1, 'message' => 'success', 'status' => 0);
            } else {//对于已经关闭的，再次点亮它 
               return array('result' => 1, 'message' => 'success', 'status' => 1);
            }
         } else {//还没有添加,点亮它
            return array('result' => 1, 'message' => 'success', 'status' => 1);
         }
      }
      return $sult;
   }

   /**
    * 更新学员某一天获得某一种徽章
    * @param type $student_id
    * @param type $date
    * @param type $mark
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function updateStudentBadge($student_id, $date = '', $status = 0, $event_key = '', $event_name = '', $mark = '') {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }
      if (!$student_id) {
         return array(
           'result' => -98,
           'message' => '未指定学员ID',
         );
      }

      if ($b_config['type']) {
         return array(
           'result' => -97,
           'message' => '[' . $event_name . ']不是使用在学员上的',
         );
      }
      $db_where = array(
        'user_id' => $student_id,
        'badge_id' => $b_config['kid'],
        'date' => $b_config['string_day'],
      );
      $current_user_kid = self::getCurrentUserKid();
      $current_time = time();
      $current_from = 'PC';
      $current_ip = TNetworkHelper::getClientRealIP();
      $update_arr1 = array(
        'is_deleted' => $status,
        'updated_by' => $current_user_kid,
        'updated_at' => $current_time,
        'updated_from' => $current_from,
        'updated_ip' => $current_ip,
        'version' => new Expression("`version`+1"),
      );
      if ($mark) {
         $update_arr1['mark'] = $mark;
      }
      $table_name1 = BoeTxyStudentBadge::tableName();
      $table_name2 = BoeTxyStudentIntegral::tableName();
      $dbObj = Yii::$app->db;
      $sql['badge_sql'] = $dbObj->createCommand()->update($table_name1, $update_arr1, $db_where)->getRawSql();
      if ($b_config['integral_config']) {
         $i_config = self::checkIntegralConfigFromDate($b_config['string_day'], $b_config['integral_config'], $event_name);
         if ($i_config['result'] === 1) {//有积分的相关选项时S
            $db_where = array(
              'user_id' => $student_id,
              'integral_id' => $i_config['integral_id'],
              'date' => $b_config['string_day'],
            );
            $sql['integral_sql'] = $dbObj->createCommand()->update($table_name2, $update_arr1, $db_where)->getRawSql();
         }//有积分的相关选项时E
      }
//      BoeBase::debug(__METHOD__);
//      BoeBase::debug($sql);
      $db_sult = self::transactionSql($sql);
      if (!$db_sult) {//数据库出错 
         return array(
           'result' => -96,
           'message' => "数据库操作失败,错误如下:\n" . implode(";\n", $sql) . ';',
         );
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 学员某一天获得某一种徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function addStudentBadge($user_id, $date = '', $mark = '', $event_key = '', $event_name = '') {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }
      if (!$user_id) {
         return array(
           'result' => -98,
           'message' => '未指定学员ID',
         );
      }

      if ($b_config['type']) {
         return array(
           'result' => -97,
           'message' => '[' . $event_name . ']不是使用在学员上的',
         );
      }
      $cache_name = __METHOD__ . '_' . $event_key . '_user_id_' . $user_id . '_date_' . $b_config['string_day'];
      $sult = self::getCache($cache_name);
      if (!$sult) {//缓存中没有的时候
         $db_where = array(
           'user_id' => $user_id,
           'badge_id' => $b_config['kid'],
           'date' => $b_config['string_day'],
         );
         $add_count = BoeTxyStudentBadge::find()->where($db_where)->count();  // 返回数量
         if (!$add_count) {//还没有添加相应的记录
            if (!$mark) {
               $mark = BoeBase::array_key_is_nulls($b_config, 'name', $event_name);
            }
            $student_info = self::getStudentInfo($user_id);
            $current_user_kid = self::getCurrentUserKid();
            $current_time = time();
            $current_from = 'PC';
            $current_ip = TNetworkHelper::getClientRealIP();
            $i_value = NULL;

            $table_name1 = BoeTxyStudentBadge::tableName();
            $table_name2 = BoeTxyStudentIntegral::tableName();
            $dbObj = Yii::$app->db;
            if ($b_config['integral_config']) {
               $i_config = self::checkIntegralConfigFromDate($b_config['string_day'], $b_config['integral_config'], $event_name);
               if ($i_config['result'] === 1) {
                  $i_value = array(
                    'kid' => new Expression('upper(UUID())'),
                    'user_id' => $user_id,
                    'orgnization_id' => BoeBase::array_key_is_nulls($student_info, 'orgnization_id', ''),
                    'battalion_id' => BoeBase::array_key_is_nulls($student_info, 'battalion_id', ''),
                    'area_id' => BoeBase::array_key_is_nulls($student_info, 'area_id', ''),
                    'integral_id' => $i_config['integral_id'],
                    'score' => $i_config['score'],
                    'date' => $i_config['string_day'],
                    'mark' => $b_config['name'],
                    'version' => 1,
                    'created_by' => $current_user_kid,
                    'created_at' => $current_time,
                    'created_from' => $current_from,
                    'created_ip' => $current_ip,
                    'updated_by' => $current_user_kid,
                    'updated_at' => $current_time,
                    'updated_from' => $current_from,
                    'updated_ip' => $current_ip,
                    'is_deleted' => 0,
                  );
               }
            }
            $b_value = array(
              'kid' => new Expression('upper(UUID())'),
              'user_id' => $user_id,
              'orgnization_id' => BoeBase::array_key_is_nulls($student_info, 'orgnization_id', ''),
              'battalion_id' => BoeBase::array_key_is_nulls($student_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($student_info, 'area_id', ''),
              'badge_id' => $b_config['kid'],
              'date' => $b_config['string_day'],
              'mark' => $mark,
              'version' => 1,
              'created_by' => $current_user_kid,
              'created_at' => $current_time,
              'created_from' => $current_from,
              'created_ip' => $current_ip,
              'updated_by' => $current_user_kid,
              'updated_at' => $current_time,
              'updated_from' => $current_from,
              'updated_ip' => $current_ip,
              'is_deleted' => 0,
            );
            $sql = array();
            $sql['badge_sql'] = $dbObj->createCommand()->insert($table_name1, $b_value)->getRawSql();
            if ($i_value) {
               $sql['integral_sql'] = $dbObj->createCommand()->insert($table_name2, $i_value)->getRawSql();
            }


            $db_sult = self::transactionSql($sql);
            if (!$db_sult) {//数据库出错 
               return array(
                 'result' => -96,
                 'message' => "数据库操作失败,错误如下:\n" . implode(";\n", $sql) . ';',
               );
            }
         }
         self::setCache($cache_name, '1', self::$treatedCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 学员得到结营考试徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentExamineBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'course', '得到结营考试徽章');
   }

   /**
    * 学员得到训练标兵徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentTrainBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'train', '得到训练标兵徽章');
   }

   /**
    * 学员得到内务标兵徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentServiceBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'service', '得到内务标兵徽章');
   }

   /**
    * 学员得到活动标兵徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentCampaignBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'campaign', '得到活动标兵徽章');
   }

   /**
    * 学员参加篮球队队员得到徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentBasketballBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'basketball', '参加篮球队队员得到徽章');
   }

   /**
    * 学员参加演讲比赛得到徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentSpeechBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'speech', '参加演讲比赛徽章');
   }

   /**
    * 学员参加晚会表演徽章
    * @param type $user_id
    * @param type $date
    * @param type $mark
    * @return type
    */
   static function addStudentShowBadge($user_id = '', $date = '', $mark = '') {
      return self::addStudentBadge($user_id, $date, $mark, 'show', '参加晚会表演徽章');
   }

   /**
    * 添加某一天的全部学员的某一样徽章
    * @param type $date
    * @param string $mark
    * @param type $event_key
    * @param type $event_name
    * @param type $create_mode
    * @return type
    */
   private static function addAllStudentBadge($date = '', $mark = '', $event_key = '', $event_name = '', $create_mode = 0) {
      $b_config = self::checkBadgeConfigFromDate($date, $event_key, $event_name);
      if ($b_config['result'] !== 1) {
         return $b_config;
      }

      if ($b_config['type']) {
         return array(
           'result' => -98,
           'message' => '[' . $event_name . ']不是使用在学员上的',
         );
      }
      if (!$mark) {
         $mark = '每日得到' . $event_name;
      }

      $writing_cache_name = __METHOD__ . '_date_' . $b_config['string_day'] . '_event_key_' . $event_key . '_is_writing';
      $write_status = $create_mode ? 0 : self::getCache($writing_cache_name); //读取正在写数据的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -97,
           'message' => '其它进程正在进行更新',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作
      $all_student_kids = self::getAllStudent();
      if (!$all_student_kids) {
         self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
         return array(
           'result' => -95,
           'message' => '没有学员数据',
         );
      } else {
         $all_student_kids = array_keys($all_student_kids);
      }

      $exists_params = array(
        'condition' => array(
          ['badge_id' => $b_config['kid']],
          ['user_id' => $all_student_kids],
          ['date' => $b_config['string_day']],
        ),
        'return_total_count' => 1,
        'indexBy' => 'user_id',
        'field' => 'user_id',
        'showDeleted' => true
      );
      $dbObj = new BoeTxyStudentBadge();
      $exist_log = $dbObj->getList($exists_params);
      if (!empty($exist_log['list'])) {//对特定日期已经有存在学员徽章记录时,要进行忽略
         $exist_student_kids = array_keys($exist_log['list']);
         $all_student_kids = array_diff($all_student_kids, $exist_student_kids); //去除已经添加过的 
      }
      $dbObj = $exist_log = null;
      $db_sult = true;
      if ($all_student_kids) {//有需要插入数据的状态时S
         $i_config = NULL;
         if ($b_config['integral_config']) {
            $i_config = self::checkIntegralConfigFromDate($b_config['string_day'], $b_config['integral_config'], $event_name);
            if ($i_config['result'] !== 1) {
               $i_config = NULL;
            }
         }
         $current_user_kid = self::getCurrentUserKid();
         $current_time = time();
         $current_from = 'PC';
         $current_ip = TNetworkHelper::getClientRealIP();
         $table_name1 = BoeTxyStudentBadge::tableName();
         $table_name2 = BoeTxyStudentIntegral::tableName();
         $b_value_arr = $i_value_arr = array();

         foreach ($all_student_kids as $a_user_id) {
            $student_info = self::getStudentInfo($a_user_id);
            $b_value_arr[] = array(
              'kid' => new Expression('upper(UUID())'),
              'user_id' => $a_user_id,
              'badge_id' => $b_config['kid'],
              'mark' => $mark,
              'date' => $b_config['string_day'],
              'orgnization_id' => BoeBase::array_key_is_nulls($student_info, 'orgnization_id', ''),
              'battalion_id' => BoeBase::array_key_is_nulls($student_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($student_info, 'area_id', ''),
              'created_by' => $current_user_kid,
              'created_at' => $current_time,
              'created_from' => $current_from,
              'created_ip' => $current_ip,
              'updated_by' => $current_user_kid,
              'updated_at' => $current_time,
              'updated_from' => $current_from,
              'updated_ip' => $current_ip,
              'version' => 1,
              'is_deleted' => 0
            );
            if ($i_config) {
               $i_value_arr[] = array(
                 'kid' => new Expression('upper(UUID())'),
                 'user_id' => $a_user_id,
                 'orgnization_id' => BoeBase::array_key_is_nulls($student_info, 'orgnization_id', ''),
                 'battalion_id' => BoeBase::array_key_is_nulls($student_info, 'battalion_id', ''),
                 'area_id' => BoeBase::array_key_is_nulls($student_info, 'area_id', ''),
                 'integral_id' => $i_config['integral_id'],
                 'score' => $i_config['score'],
                 'mark' => $i_config['name'],
                 'date' => $b_config['string_day'],
                 'created_by' => $current_user_kid,
                 'created_at' => $current_time,
                 'created_from' => $current_from,
                 'created_ip' => $current_ip,
                 'updated_by' => $current_user_kid,
                 'updated_at' => $current_time,
                 'updated_from' => $current_from,
                 'updated_ip' => $current_ip,
                 'version' => 1,
                 'is_deleted' => 0
               );
            }
         }

         $dbObj = Yii::$app->db;
         $sql = array();
         if ($b_value_arr) {
            $b_field_arr = array_keys(current($b_value_arr));
            $sql['badge_sql'] = $dbObj->createCommand()->batchInsert($table_name1, $b_field_arr, $b_value_arr)->getRawSql();
         }
         if ($i_config) {
            $i_field_arr = array_keys(current($i_value_arr));
            $sql['integral_sql'] = $dbObj->createCommand()->batchInsert($table_name2, $i_field_arr, $i_value_arr)->getRawSql();
         }

         $db_sult = self::transactionSql($sql);
         if (!$db_sult) {//数据库出错 
            return array(
              'result' => -96,
              'message' => "数据库操作失败,错误如下:\n" . implode(";\n", $sql) . ';',
            );
         }
      }//有需要插入数据的状态时E
      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug($i_config);
            BoeBase::debug(var_dump($badge_sult, true));
            BoeBase::debug(var_dump($i_sult, true));
            BoeBase::debug("Error Sql:");
            BoeBase::debug(implode(";\n", $sql), 1);
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 添加某一天的全部学员的课程徽章
    * @param type $date
    * @param string $mark 
    * @param type $create_mode
    * @return type
    */
   static function addAllStudentCourseBadge($date = '', $mark = '', $create_mode = 0) {
      $event_key = 'course';
      $event_name = '课程';
      return self::addAllStudentBadge($date, $mark, $event_key, $event_name, $create_mode);
   }

   /**
    * 添加某一天的全部学员的扩展徽章
    * @param type $date
    * @param string $mark 
    * @param type $create_mode
    * @return type
    */
   static function addAllStudentBdBadge($date = '', $mark = '', $create_mode = 0) {
      $event_key = 'bd';
      $event_name = '拓展';
      return self::addAllStudentBadge($date, $mark, $event_key, $event_name, $create_mode);
   }

//@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@和学员得到徽章有关的方法结束@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
//>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>牛的不像地球人一样的与徽章有关的几个接口到这里结束<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
//--------------------------------------------介于牛A与牛C之间的与积分有关的几个接口从这里开始--------------------------------------------------------------------
   private static $integralConfigParams = array(//有关积分事件的参数 
     'weilog' => 'weilog',
     'plan' => 'plan',
     'knowledge' => 'knowledge',
     'king' => 'king',
     'tomorrow' => 'tomorrow',
     'master' => 'master',
     'course' => 'course',
     //------------------------------以下这些个都是得到徽章后加的积分选项--------------------------
     'badge_course' => 'badge_course', //得到课程徽章奖励
     'badge_examine' => 'badge_examine', //得到结营考试徽章奖励
     'badge_bd' => 'badge_bd', //得到拓展徽章奖励
     'badge_train' => 'badge_train', //得到训练标兵徽章奖励
     'badge_service' => 'badge_service', //得到内务标兵徽章奖励
     'badge_campaign' => 'badge_campaign', //得到活动标兵徽章奖励
     'badge_basketball' => 'badge_basketball', //得到篮球队队员徽章奖励
     'badge_speech' => 'badge_speech', //得到演讲比赛徽章奖励
     'badge_show' => 'badge_show', //得到晚会表演徽章奖励
     //------------------------------以下这些个班级有关的积分选项--------------------------
     'class_avg' => 'class_avg', //班级成员积分当日平均值
     'badge_party' => 'badge_party', //得到班会成就徽章奖励
     'badge_meeting' => 'badge_meeting', //得到班级成就徽章奖励
     'badge_all_student' => 'badge_all_student', //得到班级一个都不能少徽章奖励
     'bd_achievement1' => 'bd_achievement1', //班级拓展成就第1名 
     'bd_achievement2' => 'bd_achievement2', //班级拓展成就第1名 
     'bd_achievement3' => 'bd_achievement3', //班级拓展成就第1名 
     'mi_achievement1' => 'mi_achievement1', //班级军事训练评比成就第1名 
     'mi_achievement2' => 'mi_achievement2', //班级军事训练评比成就第2名 
     'mi_achievement3' => 'mi_achievement3', //班级军事训练评比成就第3名 
     'basketball_achievement1' => 'basketball_achievement1', //班级篮球赛决赛成就第1名 
     'basketball_achievement2' => 'basketball_achievement2', //班级篮球赛决赛成就第2名 
     'basketball_achievement3' => 'basketball_achievement3', //班级篮球赛决赛成就第3名 
     'knowledge_achievement1' => 'knowledge_achievement1', //班级知识竞赛第1名 
     'knowledge_achievement2' => 'knowledge_achievement2', //班级知识竞赛第2名 
     'knowledge_achievement3' => 'knowledge_achievement3', //班级知识竞赛第3名  
   );

   /**
    * 获取全部的积分配置
    */
   static function getAllIntegralConfig($create_mode = 0) {
      $cache_name = __METHOD__;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $sql = "SELECT * from eln_boe_txy_integral_config WHERE is_deleted =0";
         $connection = Yii::$app->db;
         $db_sult = $connection->createCommand($sql)->queryAll();
         if (is_array($db_sult)) {
            self::$_env[$cache_name] = array();
            foreach ($db_sult as $a_info) {
               if ($a_info['key']) {
                  self::$_env[$cache_name][$a_info['key']] = array(
                    'integral_id' => $a_info['kid'],
                    'kid' => $a_info['kid'],
                    'name' => $a_info['name'],
                    'score' => BoeBase::array_key_is_numbers($a_info, 'score'),
                    'start_day' => !empty($a_info['start_day']) && $a_info['start_day'] != '0000-00-00' ? strtotime($a_info['start_day']) : 0,
                    'end_day' => !empty($a_info['end_day']) && $a_info['end_day'] != '0000-00-00' ? strtotime($a_info['end_day']) : 0,
                    'allow_days' => !empty($a_info['allow_days']) ? str_replace(array('：', ';', ',', ':', '，', ':'), ';', $a_info['allow_days']) : '',
                  );
               }
            }
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name]);
      }
      return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
   }

   /**
    * 获取积分的配置参数
    * @param type $params
    * @return type
    */
   static function getIntegralConfig($type_key) {
      $cache_name = __METHOD__ . 'type_key_' . $type_key;
      if (isset(self::$_env[$cache_name])) {
         return self::$_env[$cache_name];
      }
      $config_key = BoeBase::array_key_is_nulls(self::$integralConfigParams, $type_key);
      $all_config = self::getAllIntegralConfig();
      self::$_env[$cache_name] = BoeBase::array_key_is_nulls($all_config, $config_key);
      return self::$_env[$cache_name];
   }

   /**
    * 检测某个时间在积分配置是否正确
    * @param type $date
    * @param type $event_key
    * @param type $event_name
    * @return type
    */
   private static function checkIntegralConfigFromDate($date = '', $event_key = '', $event_name = '') {
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -99,
              'message' => '指定的日期格式不正确',
            );
         }
      }
      $i_config = self::getIntegralConfig($event_key);
      if (!is_array($i_config) || !$i_config) {
         return array(
           'result' => -98,
           'message' => '积分配置中还没有[' . $event_name . ']相关的配置',
         );
      }
      $score = BoeBase::array_key_is_numbers($i_config, 'score');
      if ($score < 1) {
         return array(
           'result' => -97,
           'message' => '[' . $event_name . ']积分事件配置的积分值(' . $score . ')小于0,',
         );
      }

      $i_config['string_day'] = $date_day = date('Y-m-d', strtotime($date)); //日期字符串型
      $i_config['int_day'] = $int_day = strtotime($date_day); //数字型日期
      $i_config['int_end_time'] = $int_end_time = $int_end_time = $int_day + 86399;
      $config_start_day = BoeBase::array_key_is_numbers($i_config, 'start_day');
      $config_end_day = BoeBase::array_key_is_numbers($i_config, 'end_day');
      $config_allow_days = BoeBase::array_key_is_nulls($i_config, 'allow_days');

      if ($config_allow_days) {//只允许在特定的日期内操作时,例如:2017-06-30,2017-07-10,2017-07-15这三天内
         if (!is_array($config_allow_days)) {
            $config_allow_days = explode(';', $config_allow_days);
         }
         $date_match = false;
         foreach ($config_allow_days as $key => $a_info) {
            $a_info = strtotime($date);
            $a_info = date('Y-m-d', $a_info);
            if ($a_info == $date_day) {
               $date_match = true;
               break;
            }
         }
         if (!$date_match) {
            return array(
              'result' => -95,
              'message' => '[' . $event_name . ']获得积分事件不在特定的时间内,允许时间是:' . implode('、', $config_allow_days),
            );
         }
      } else {//根据范围段的查询开始S
         if ($config_start_day && $int_day < $config_start_day) {//指定了开始时间时,指定的时候很不幸的处于还没有开始
            return array(
              'result' => -94,
              'message' => '[' . $event_name . ']获得积分事件还没有开始,开始时间是:' . date("Y-m-d H:i:s", $config_start_day)
            );
         }

         if ($config_end_day && $int_day >= $config_end_day) {//指定了结束时间时
            return array(
              'result' => -93,
              'message' => '[' . $event_name . ']获得积分已经结束,结束时间是:' . date("Y-m-d H:i:s", $config_end_day)
            );
         }
      }//根据范围段的查询开始E
      $i_config['result'] = 1;
      return $i_config;
   }

   /**
    * 学员完成特定操作后获得积分的执行方法
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @param type $event_key
    * @param type $event_name
    * @return int
    */
   private static function addStudentIntegral($user_id, $date = '', $mark = '', $event_key = '', $event_name = '') {
      if (!$user_id) {
         return array(
           'result' => -100,
           'message' => '未指定学员ID',
         );
      }

      $i_config = self::checkIntegralConfigFromDate($date, $event_key, $event_name);
      if ($i_config['result'] !== 1) {
         return $i_config;
      }

      $cache_name = __METHOD__ . '_' . $event_key . '_user_id_' . $user_id . '_date_' . $i_config['string_day'];
      $sult = self::getCache($cache_name);
      if (!$sult) {//缓存中没有的时候
         $db_where = array(
           'user_id' => $user_id,
           'integral_id' => $i_config['integral_id'],
           'date' => $i_config['string_day'],
         );
         $add_count = BoeTxyStudentIntegral::find()->where($db_where)->count();  // 返回数量
         if (!$add_count) {//还没有添加相应的记录
            $dbObj = new BoeTxyStudentIntegral();
            if (!$mark) {
               $mark = BoeBase::array_key_is_nulls($i_config, 'name', $event_name);
            }
            $student_info = self::getStudentInfo($user_id);
            $data = array(
              'user_id' => $user_id,
              'orgnization_id' => BoeBase::array_key_is_nulls($student_info, 'orgnization_id', ''),
              'battalion_id' => BoeBase::array_key_is_nulls($student_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($student_info, 'area_id', ''),
              'integral_id' => $i_config['integral_id'],
              'score' => $i_config['score'],
              'date' => $i_config['string_day'],
              'mark' => $mark,
            );
            $db_sult = $dbObj->CommonSaveInfo($data);
            if (is_array($db_sult)) {//数据库出错
               return array(
                 'result' => -96,
                 'message' => "数据库操作失败,错误如下:\n" . var_export($db_sult, true),
               );
            }
         } else {
//                BoeBase::debug(__METHOD__ . '$event_name:' . $event_name . '_count:' . $add_count);
         }
         self::setCache($cache_name, '1', self::$treatedCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 添加某一天的全部学员的课程积分
    * @param type $date
    * @param string $mark 
    * @param type $create_mode
    * @return type
    */
   static function addAllStudentCourseIntegral($date = '', $mark = '', $create_mode = 0) {
      if (!$mark) {
         $mark = '完成每日课程';
      }

      $i_config = self::checkIntegralConfigFromDate($date, 'course', '完成每日课程');
      if ($i_config['result'] !== 1) {
         return $i_config;
      }

      $writing_cache_name = __METHOD__ . '_date_' . $i_config['string_day'] . '_is_writing';
      $write_status = $create_mode ? 0 : self::getCache($writing_cache_name); //读取正在写数据的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -92,
           'message' => '其它进程正在进行更新',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作
      $all_student_kids = self::getAllStudent();
      if (!$all_student_kids) {
         self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
         return array(
           'result' => -91,
           'message' => '没有学员数据',
         );
      } else {
         $all_student_kids = array_keys($all_student_kids);
      }

      $exists_params = array(
        'condition' => array(
          ['integral_id' => $i_config['integral_id']],
          ['user_id' => $all_student_kids],
          ['date' => $i_config['string_day']],
        ),
        'return_total_count' => 1,
        'indexBy' => 'user_id',
        'field' => 'user_id',
        'showDeleted' => true
      );
      $dbObj = new BoeTxyStudentIntegral();
      $exist_log = $dbObj->getList($exists_params);
      if (!empty($exist_log['list'])) {//对特定日期已经有存在用户课程积分记录时,要进行忽略
         $exist_student_kids = array_keys($exist_log['list']);
         $all_student_kids = array_diff($all_student_kids, $exist_student_kids); //去除已经添加过的 
      }
      $dbObj = $exist_log = null;
      $db_sult = true;
      $sql = array();
      if ($all_student_kids) {//有需要插入数据的状态时S
         $current_user_kid = self::getCurrentUserKid();
         $current_time = time();
         $current_from = 'PC';
         $current_ip = TNetworkHelper::getClientRealIP();
         $value_arr = array();
         foreach ($all_student_kids as $a_user_id) {
            $student_info = self::getStudentInfo($a_user_id);
            $value_arr[] = array(
              'kid' => new Expression('upper(UUID())'),
              'user_id' => $a_user_id,
              'integral_id' => $i_config['integral_id'],
              'score' => $i_config['score'],
              'mark' => $mark,
              'date' => $i_config['string_day'],
              'orgnization_id' => BoeBase::array_key_is_nulls($student_info, 'orgnization_id', ''),
              'battalion_id' => BoeBase::array_key_is_nulls($student_info, 'battalion_id', ''),
              'area_id' => BoeBase::array_key_is_nulls($student_info, 'area_id', ''),
              'version' => 1,
              'created_by' => $current_user_kid,
              'created_at' => $current_time,
              'created_from' => $current_from,
              'created_ip' => $current_ip,
              'updated_by' => $current_user_kid,
              'updated_at' => $current_time,
              'updated_from' => $current_from,
              'updated_ip' => $current_ip,
              'is_deleted' => 0,
            );
         }
         $dbObj = Yii::$app->db;
         $field_arr = array_keys(current($value_arr));
         $sql[] = $dbObj->createCommand()->batchInsert(BoeTxyStudentIntegral::tableName(), $field_arr, $value_arr)->getRawSql();
         $db_sult = self::transactionSql($sql);
      }//有需要插入数据的状态时E
      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug("Error Sql:");
            BoeBase::debug(str_replace(array('{{%', '}}'), array('eln_', ''), implode(";\n", $sql)), 1);
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }
      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 学员在添加完微日志后,添加积分的操作
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @return int
    */
   static function addWeiLogIntegral($user_id = '', $date = '', $mark = '') {
      return self::addStudentIntegral($user_id, $date, $mark, 'weilog', '发布微日志');
   }

   /**
    * 学员完成行动计划获得积分,添加积分的操作
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @return int
    */
   static function addPlanIntegral($user_id = '', $date = '', $mark = '') {
      return self::addStudentIntegral($user_id, $date, $mark, 'plan', '完成行动计划');
   }

   /**
    * 学员完成知识答题获得积分,添加积分的操作
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @return int
    */
   static function addKnowledgeIntegral($user_id = '', $date = '', $mark = '') {
      return self::addStudentIntegral($user_id, $date, $mark, 'knowledge', '完成知识答题');
   }

   /**
    * 学员完成致国王活动获得积分,添加积分的操作
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @return int
    */
   static function addKingIntegral($user_id = '', $date = '', $mark = '') {
      return self::addStudentIntegral($user_id, $date, $mark, 'king', '完成致国王活动');
   }

   /**
    * 学员完成个人致未来的自己获得积分,添加积分的操作
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @return int
    */
   static function addTomorrowIntegral($user_id = '', $date = '', $mark = '') {
      return self::addStudentIntegral($user_id, $date, $mark, 'tomorrow', '完成个人致未来的自己');
   }

   /**
    * 学员完成致教官获得积分,添加积分的操作
    * @param type $user_id
    * @param type $date
    * @param string $mark
    * @return int
    */
   static function addMasterIntegral($user_id = '', $date = '', $mark = '') {
      return self::addStudentIntegral($user_id, $date, $mark, 'master', '完成致教官');
   }

   /**
    * 统计某一天全部学员的积分信息
    * @这又是一个伟大的算法体现
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function statsStudentIntegral($date = '', $create_mode = 0) {
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -100,
              'message' => '指定的日期格式不正确',
            );
         }
      }
      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $writing_cache_name = __METHOD__ . '_date_' . $string_day . '_is_writing';
      $write_status = $create_mode ? 0 : self::getCache($writing_cache_name); //读取数据统计的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -99,
           'message' => '其它进程正在进行统计',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作
      $field_arr = array(
        'kid', 'user_id', 'summary_score', 'summary_date',
        'created_by', 'created_at', 'created_from', 'created_ip',
        'updated_by', 'updated_at', 'updated_from', 'updated_ip',
        'version', 'is_deleted',
      );


      $field_arr_str = '`' . implode('`,`', $field_arr) . '`';
      $current_user_kid = self::getCurrentUserKid();
      $current_time = time();
      $current_from = 'PC';
      $current_ip = TNetworkHelper::getClientRealIP();
      $table_name1 = BoeTxyStudentIntegralSummary::tableName();
      $table_name2 = BoeTxyStudentIntegral::tableName();
      $sql = array('delete_sql' => '');
      $insert_sql = "INSERT INTO {$table_name1} ($field_arr_str) ";
      $insert_sql .= " select *,'{$string_day}', '{$current_user_kid}','{$current_time}','{$current_from}','{$current_ip}'";
      $insert_sql .= ",  '{$current_user_kid}','{$current_time}','{$current_from}','{$current_ip}',1,0";
      $insert_sql .= " from ( 
                            select max(`kid`) as `kid`,user_id,sum(`score`) as `score`  from {$table_name2}  where `date`='{$string_day}'  group by `user_id` order by `score` 
                           ) as cg789";

      $sql['insert_sql'] = $insert_sql;
      $sql['orgnization_id_sql'] = "update {$table_name1} set orgnization_id=(select orgnization_id from {$table_name2} where kid={$table_name1}.kid limit 1)";
      $sql['battalion_id_sql'] = "update {$table_name1} set battalion_id=(select battalion_id from {$table_name2} where kid={$table_name1}.kid limit 1)";
      $sql['area_id_sql'] = "update {$table_name1} set area_id=(select area_id from {$table_name2} where kid={$table_name1}.kid limit 1)";

      $dbObj = Yii::$app->db;
      $sql['delete_sql'] = $dbObj->createCommand()->delete($table_name1, array('summary_date' => $string_day))->getRawSql();

      $db_sult = self::transactionSql($sql);
      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug("Error Sql:");
            BoeBase::debug(str_replace(array('{{%', '}}'), array('eln_', ''), implode(";\n", $sql)), 1);
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }

      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 获取某种组织构架的某一天的全部成员的积分统计记录
    * @param type $object_id
    * @param type $object_field
    * @param type $object_name
    * @param type $date
    * @param type $limit
    * @param type $create_mode
    * @return type
    */
   private static function getStudentIntegralRank($object_id = '', $object_field = '', $object_name = '', $date = '', $limit = 0, $create_mode = 0) {
      if (!$object_id) {
         return array(
           'result' => -100,
           'message' => '未指定' . $object_name,
           'list' => NULL,
         );
      }

      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -99,
              'message' => '指定的日期格式不正确',
              'list' => NULL,
            );
         }
      }
      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $cache_name = __METHOD__ . $object_field . '_' . $object_id . '_date_' . $string_day . '_limit_' . $limit;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $dbobj = new BoeTxyStudentIntegralSummary();
         $list_params = array(
           'condition' => array(
             array($object_field => $object_id, 'summary_date' => $string_day),
           ),
           'return_total_count' => 1,
           'limit' => $limit,
           'orderBy' => 'summary_score desc',
           'indexBy' => 'user_id',
           'field' => 'user_id,summary_score,summary_date',
         );

         $db_sult = $dbobj->getList($list_params);
         if (!empty($db_sult['list'])) {//找到数据时
            self::$_env[$cache_name] = $db_sult['list'];
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name], self::$statsInfoCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
        'list' => (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name]
      );
   }

   /**
    * 获取某个班级某一天的学员们的积分统计
    * @param type $org_id
    * @param type $date
    * @param type $limit
    * @param type $create_mode
    * @return type
    */
   static function getOrgnizationStudentIntegralRank($org_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getStudentIntegralRank($org_id, 'orgnization_id', '班级', $date, $limit, $create_mode);
   }

   /**
    * 获取某个营区某一天的全部成员的积分统计记录
    * @param type $battalion_id
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function getBattalionStudentIntegralRank($battalion_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getStudentIntegralRank($battalion_id, 'battalion_id', '营区', $date, $limit, $create_mode);
   }

   /**
    * 获取某个大区某一天的全部成员的积分统计记录
    * @param type $area_id
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function getAreaStudentIntegralRank($area_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getStudentIntegralRank($area_id, 'area_id', '大区', $date, $limit, $create_mode);
   }

   /**
    * 统计某一天全部班级的成员积分的平均值并保存到班级积分表里
    * @这更是一个伟大的算法体现
    * @param type $date
    * @param string $mark 
    * @param type $create_mode
    * @return type
    */
   static function statsClassAvgIntegral($date = '', $create_mode = 0) {
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -100,
              'message' => '指定的日期格式不正确',
            );
         }
      }
      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $writing_cache_name = __METHOD__ . '_date_' . $string_day . '_is_writing';
      $write_status = $create_mode ? 0 : self::getCache($writing_cache_name); //读取数据统计的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -99,
           'message' => '其它进程正在进行统计',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作
      $field_arr = array(//千万不要修改这里数组的每个元素的顺序啊!!
        'orgnization_id', 'score', 'type', 'date', 'kid', 'mark', 'integral_id',
        'created_by', 'created_at', 'created_from', 'created_ip',
        'updated_by', 'updated_at', 'updated_from', 'updated_ip',
        'version', 'is_deleted',
      );


      $field_arr_str = '`' . implode('`,`', $field_arr) . '`';
      $current_user_kid = self::getCurrentUserKid();
      $current_time = time();
      $current_date_time = date('Y-m-d H:i:s', time());
      $current_from = 'PC';
      $current_ip = TNetworkHelper::getClientRealIP();
      $table_name1 = BoeTxyStudentIntegral::tableName();
      $table_name2 = BoeTxyClassIntegral::tableName();
      $i_config = self::getIntegralConfig('class_avg');
      $integral_id = BoeBase::array_key_is_nulls($i_config, 'kid', '99999999-9999-9999-9999-999999999999');
      $dbObj = Yii::$app->db;
      $sql = array();
      $sql['delete_sql'] = $dbObj->createCommand()->delete($table_name2, array('date' => $string_day, 'type' => 1))->getRawSql();
      //************************************坤哥入行以来写过最复杂的SQL查询语句,自我膜拜下S******************************************/
      $insert_sql = "INSERT INTO {$table_name2}($field_arr_str)\n";
      $insert_sql .= " select cg789_sum.orgnization_id,(sum_score/user_count) as avg_value";
      $insert_sql .= ",1,'{$string_day}',upper(UUID()),'当前时间[{$current_date_time}]班级全部成员的积分值平均值','{$integral_id}'";
      $insert_sql .= ",'{$current_user_kid}','{$current_time}','{$current_from}','{$current_ip}'";
      $insert_sql .= ",'{$current_user_kid}','{$current_time}','{$current_from}','{$current_ip}'";
      $insert_sql .= ",1,0\n";
      $insert_sql .= " from\n";
      $insert_sql .= "(
                            SELECT orgnization_id,sum(score) as sum_score  FROM  {$table_name1} where `date`='{$string_day}' and `is_deleted`='0' group by orgnization_id
                        ) as cg789_sum\n";
      $insert_sql .= " INNER JOIN \n";
      $insert_sql .= "(";
      $insert_sql .= "      select orgnization_id,count(*) as user_count from \n";
      $insert_sql .= "        (";
      $insert_sql .= "          SELECT orgnization_id,user_id   FROM  {$table_name1}  where `date`='{$string_day}'  and `is_deleted`='0' group by orgnization_id,user_id";
      $insert_sql .= "       ) as cg_789_base group by orgnization_id\n";
      $insert_sql .= " )  as cg789_count\n";
      $insert_sql .= "  on cg789_sum.orgnization_id=cg789_count.orgnization_id  order by avg_value asc";
      //************************************坤哥入行以来写过最复杂的SQL查询语句,自我膜拜下E******************************************/
      $sql['insert_sql'] = $insert_sql;
      $sql['battalion_id_sql'] = "update {$table_name2} set battalion_id=(select battalion_id from {$table_name1} where orgnization_id={$table_name2}.orgnization_id limit 1)";
      $sql['area_id_sql'] = "update {$table_name2} set area_id=(select area_id from {$table_name1} where orgnization_id={$table_name2}.orgnization_id limit 1)";


      $db_sult = self::transactionSql($sql);
      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode() || 1) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug($sql_sult);
            BoeBase::debug("Error Sql:");
            BoeBase::debug(str_replace(array('{{%', '}}'), array('eln_', ''), implode(";\n", $sql)));
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }

      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 统计某一天全部班级的积分信息
    * @这是一个伟大的算法体现
    * @param type $date
    * @param string $mark 
    * @param type $create_mode
    * @return type
    */
   static function statsClassIntegral($date = '', $create_mode = 0) {
      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -100,
              'message' => '指定的日期格式不正确',
            );
         }
      }
      if ($create_mode) {//同步组织信息
         self::syncOrg($date, 1);
      }
      self::statsClassAvgIntegral($date, $create_mode); //先统计出相应的平均分

      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $writing_cache_name = __METHOD__ . '_date_' . $string_day . '_is_writing';
      $write_status = $create_mode ? 0 : self::getCache($writing_cache_name); //读取数据统计的标志,避免其它进程中重复操作了
      if ($write_status == 1) {
         return array(
           'result' => -99,
           'message' => '其它进程正在进行统计',
         );
      }
      self::setCache($writing_cache_name, '1', self::$writingStatusCacheTime); //写入一个标志,告诉其它进程正在操作
      $field_arr = array(
        'kid', 'orgnization_id', 'summary_score', 'summary_date',
        'created_by', 'created_at', 'created_from', 'created_ip',
        'updated_by', 'updated_at', 'updated_from', 'updated_ip',
        'version', 'is_deleted',
      );

      $field_arr_str = '`' . implode('`,`', $field_arr) . '`';
      $current_user_kid = self::getCurrentUserKid();
      $current_time = time();
      $current_from = 'PC';
      $current_ip = TNetworkHelper::getClientRealIP();
      $table_name1 = BoeTxyClassIntegralSummary::tableName();
      $table_name2 = BoeTxyClassIntegral::tableName();
      $sql = array('delete_sql' => '');
      $insert_sql = "INSERT INTO {$table_name1} ($field_arr_str) ";
      $insert_sql .= " select *,'{$string_day}', '{$current_user_kid}','{$current_time}','{$current_from}','{$current_ip}'";
      $insert_sql .= ",  '{$current_user_kid}','{$current_time}','{$current_from}','{$current_ip}',1,0";
      $insert_sql .= " from ( 
                            select max(`kid`) as `kid`,`orgnization_id`,sum(`score`) as `score`  from {$table_name2}  where `date`='{$string_day}'  and `is_deleted`='0'  group by `orgnization_id` order by `score` 
                           ) as cg789";

      $sql['insert_sql'] = $insert_sql;
      $sql['battalion_id_sql'] = "update {$table_name1} set battalion_id=(select battalion_id from {$table_name2} where kid={$table_name1}.kid limit 1)";
      $sql['area_id_sql'] = "update {$table_name1} set area_id=(select area_id from {$table_name2} where kid={$table_name1}.kid limit 1)";

      $dbObj = Yii::$app->db;
      $sql['delete_sql'] = $dbObj->createCommand()->delete($table_name1, array('summary_date' => $string_day))->getRawSql();

      $db_sult = self::transactionSql($sql);
      self::setCache($writing_cache_name, '0', self::$writingStatusCacheTime); //写操作完成,其它进程可以来了
      if (!$db_sult) {//数据库出错
         if (self::isDebugMode() || 1) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug("Error Sql:");
            BoeBase::debug(str_replace(array('{{%', '}}'), array('eln_', ''), implode(";\n", $sql)), 1);
         }
         return array(
           'result' => -96,
           'message' => "数据库操作失败",
         );
      }

      return array(
        'result' => 1,
        'message' => 'success',
      );
   }

   /**
    * 获取某种组织构架的某一天的班级的积分统计记录
    * @param type $object_id
    * @param type $object_field
    * @param type $object_name
    * @param type $date
    * @param type $limit
    * @param type $create_mode
    * @return type
    */
   private static function getClassIntegralRank($object_id = '', $object_field = '', $object_name = '', $date = '', $limit = 0, $create_mode = 0) {
      if (!$object_id) {
         return array(
           'result' => -100,
           'message' => '未指定' . $object_name,
           'list' => NULL,
         );
      }

      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -99,
              'message' => '指定的日期格式不正确',
              'list' => NULL,
            );
         }
      }
      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $cache_name = __METHOD__ . $object_field . '_' . $object_id . '_date_' . $string_day . '_limit_' . $limit;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $dbobj = new BoeTxyClassIntegralSummary();
         $list_params = array(
           'condition' => array(
             array($object_field => $object_id, 'summary_date' => $string_day),
           ),
           'return_total_count' => 1,
           'limit' => $limit,
           'orderBy' => 'summary_score desc',
           'indexBy' => 'orgnization_id',
           'field' => 'orgnization_id, summary_score, summary_date',
         );

         $db_sult = $dbobj->getList($list_params);
         if (!empty($db_sult['list'])) {//找到数据时
            self::$_env[$cache_name] = $db_sult['list'];
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name], self::$statsInfoCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
        'list' => (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name]
      );
   }

   /**
    * 获取某个营区某一天的班级的积分统计记录
    * @param type $battalion_id
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function getBattalionClassIntegralRank($battalion_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getClassIntegralRank($battalion_id, 'battalion_id', '营区', $date, $limit, $create_mode);
   }

   /**
    * 获取某个大区某一天的班级的积分统计记录
    * @param type $area_id
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function getAreaClassIntegralRank($area_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getClassIntegralRank($area_id, 'area_id', '大区', $date, $limit, $create_mode);
   }

   /**
    * 获取某种组织构架的某一天的班级积分列表
    * @param type $integral_id 积分类型
    * @param type $object_id 字段值
    * @param type $object_field 组织字段名   
    * @param type $object_name 组织字段说明名称
    * @param type $date
    * @param type $limit
    * @param type $create_mode
    * @return type
    */
   private static function getClassIntegralList($object_id = '', $object_field = '', $object_name = '', $date = '', $limit = 0, $create_mode = 0) {

      if (!$object_id) {
         return array(
           'result' => -99,
           'message' => '未指定' . $object_name,
           'list' => NULL,
         );
      }

      if (!$date) {
         $date = date('Y-m-d');
      } else {
         if (strtotime($date) === false) {
            return array(
              'result' => -98,
              'message' => '指定的日期格式不正确',
              'list' => NULL,
            );
         }
      }
      $string_day = date('Y-m-d', strtotime($date)); //日期字符串型 
      $cache_name = __METHOD__ . $object_field . '_' . $object_id . '_date_' . $string_day . '_limit_' . $limit;
      if ($create_mode) {
         self::$_env[$cache_name] = array();
      } else {
         if (isset(self::$_env[$cache_name])) {
            return (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name];
         }
         self::$_env[$cache_name] = self::getCache($cache_name);
      }

      if (empty(self::$_env[$cache_name])) {
         $dbobj = new BoeTxyClassIntegral();
         $list_params = array(
           'condition' => array(
             array(
               $object_field => $object_id,
               'date' => $string_day
             ),
           ),
           'return_total_count' => 1,
           'limit' => $limit,
           'orderBy' => 'score desc',
           'field' => 'orgnization_id, score, date,integral_id',
         );

         $db_sult = $dbobj->getList($list_params);
         if (!empty($db_sult['list'])) {//找到数据时
            self::$_env[$cache_name] = $db_sult['list'];
         } else {
            self::$_env[$cache_name] = 'null';
         }
         self::setCache($cache_name, self::$_env[$cache_name], self::$statsInfoCacheTime);
      }
      return array(
        'result' => 1,
        'message' => 'success',
        'list' => (!is_array(self::$_env[$cache_name])) ? array() : self::$_env[$cache_name]
      );
   }

   /**
    * 获取某个营区某一天的班级所有的积分列表记录
    * @param type $battalion_id
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function getBattalionClassIntegralList($battalion_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getClassIntegralList($battalion_id, 'battalion_id', '营区', $date, $limit, $create_mode);
   }

   /**
    * 获取某个大区某一天的班级所有的积分列表记录
    * @param type $area_id
    * @param type $date
    * @param type $create_mode
    * @return type
    */
   static function getAreaClassIntegralList($area_id = '', $date = '', $limit = 0, $create_mode = 0) {
      return self::getClassIntegralList($area_id, 'area_id', '大区', $date, $limit, $create_mode);
   }

//--------------------------------------------介于牛A与牛C之间的与积分有关的几个接口从这里结束--------------------------------------------------------------------
   //************************************和前台操作特训营徽章有关的方法S*********************************************************
   //************************************和前台操作特训营徽章有关的方法E*********************************************************
}

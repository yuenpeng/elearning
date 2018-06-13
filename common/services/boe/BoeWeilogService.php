<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\base\BoeBase;
use common\models\boe\BoeSubject;
use common\models\boe\BoeSubjectNews;
use common\models\boe\BoeSubjectConfig;
use common\models\boe\BoeSubjectWeilog;
use common\models\boe\BoeBadword;
use common\services\boe\BoeBaseService;
use yii\db\Query;
use Yii;

/**
 * 专题微日志相关
 * @author xinpeng
 */
class BoeWeilogService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_weilog_';

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
     * 前台删除日志信息
     */

    public static function deleteLog($log_id, $user_id) {
        $deleteValue = 0;
        $sult = array(
            'deleteValue' => 0,
            'errorCode' => '',
        );
        if ($log_id) {
            $logObj = new BoeSubjectWeilog();
            $where = array(
                'kid' => $log_id,
                'created_by' => $user_id
            );
            $find = $logObj->find(false)->where($where)->asArray()->one();
            if ($find) {
                if ($find['recommend_status'] == 1 || $find['publish_status'] == 1) {
                    $deleteValue = -5;
                } else {
                    $current_time 		= time();//当前时间
					$today_time 		= strtotime(date("Y-m-d"));//今天
					$today_time_line 	= $today_time+43200;//判断是否今天日志的临界点
					if($current_time < $today_time_line)//中午12点以前
					{
						$today_time_begin 	= $today_time-43200;
					}
					else
					{
						$today_time_begin 	= $today_time_line;
					}
                    if ($find['created_at'] >= $today_time_begin) {
                        $deleteValue = $logObj->deleteInfo($log_id);
                    } else {

                        $deleteValue = -6;
                    }
                }
            } else {
                $deleteValue = -4;
            }
        }
        $sult['deleteValue'] = $deleteValue;
        if ($deleteValue < 1) {
            switch ($deleteValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -1:
                    $sult['errorCode'] = Yii::t('boe', 'info_loss');
                    break;
                case -2: case -3:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $deleteValue;
                    break;
                case -4:
                    $sult['errorCode'] = Yii::t('boe', 'no_power');
                    break;
                case -5:
                    $sult['errorCode'] = Yii::t('boe', 'boe_subject_send_error');
                    break;
                case -6:
                    $sult['errorCode'] = Yii::t('boe', 'boe_subject_weilog_time_error');
                    break;
            }
        }
        return $sult;
    }

    /*
     * 日志点赞
     * Input:$uid String 
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID和log_id判断有没有当前对应的用户对于指定的log_id有没有点过赞,如果有返回1,判断依据是缓存中有无记录
      2、更新数据库;
      3、添加缓存标记;
     */

    public static function likeLog($log_id, $user_id) {
        $likeValue = 0;
        $log_data = array();
        $sult = array(
            'likeValue' => 0,
            'errorCode' => '',
            'likeNum' => 0,
        );
        $cache_name = __METHOD__ . md5('_weilog_like_' . serialize($log_id));
        $log_data = Yii::$app->cache->get($cache_name);
        if (isset($log_data[$user_id]) && $log_data[$user_id] == 1) {
            $likeValue = -5;
        } else {
            if ($log_id) {
                $where = array('kid' => $log_id);
                $logObj = new BoeSubjectWeilog();
                $find = $logObj->find(false)->where($where)->asArray()->one();
                if ($find) {
                    $data = array(
                        'kid' => $log_id,
                        'like_num' => $find['like_num'] + 1
                    );
                    $likeValue = $logObj->saveInfo($data);
                    if ($likeValue) {
                        $sult['likeNum'] = $data['like_num'];
                        $likeValue = 1;
                    } else {
                        $likeValue = -6;
                    }
                }
            }
        }
        $sult['likeValue'] = $likeValue;
        if ($likeValue < 1) {
            switch ($likeValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -5:
                    $sult['errorCode'] = Yii::t('boe', 'like_end');
                    break;
                case -6:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $deleteValue;
                    break;
            }
        } else {
            $log_data[$user_id] = 1;
            Yii::$app->cache->set($cache_name, $log_data); // 设置缓存
        }
        return $sult;
    }

    /*
     * 辅导员设置推荐状态
     * updateLogRecommendStatus($log_id,$uid)
      Input:$uid String  Or Array Not NULL
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID获取出对应的用户等级，只有等级是1的辅导员才可以，反之 返回-99;
      2、根据UID获取出对应的用户的组织ID,orgnization_id，如果获取不到 ，返回 -98
      3、读取当前orgnization_id对应的微日志今天推荐的数量，如果大于大爷指定的数量，返回-97;
     */

    public static function updateLogRecommendStatus($log_id, $user_id, $status) {
        $recommendValue = 0;
        $log_data = array();
        $sult = array(
            'recommendValue' => 0,
            'errorCode' => '',
            'recommendText' => '',
            'recommendClass' => '',
        );
        if (!$user_id) {
            $recommendValue = -9; //账号为空	
        } else {
            $level = self::getUserLevel($user_id);
            if ($level['level'] != 1) {
                $recommendValue = -10; //无推荐权限
            } else {
                $manager_area = self::getManagerArea($user_id, 0);
                if ($log_id) {
                    $logObj = new BoeSubjectWeilog();
                    $where = array('kid' => $log_id);
                    $find = $logObj->find(false)->where($where)->asArray()->one();
                    if ($find) {
                        $data = array(
                            'kid' => $log_id,
                            'recommend_status' => $find['recommend_status'] == 1 ? 0 : 1,
                        );
                        if ($find['recommend_status'] == 0) {
                            $params = array(
                                'orgnization' => $manager_area['kid'],
                                'recommend_status' => 1,
                                'time' => 'today'
                            );
                            $find_num = self::getLogList($params);
                            $find_num = $find_num['totalCount'];
                        }
                        if (isset($find_num) && $find_num >= 2) {
                            $recommendValue = -11;
                        } else {
                            $recommendValue = $logObj->saveInfo($data);
                            if ($recommendValue) {
                                $sult['recommendText'] = $data['recommend_status'] == 1 ? Yii::t('boe', 'boe_subject_weilog_recommend_yes') : Yii::t('boe', 'boe_subject_weilog_recommend_no');
                                $sult['recommendClass'] = $data['recommend_status'] == 1 ? "red_star" : "no_star";
                                $recommendValue = 1;
                            } else {
                                $recommendValue = -7;
                            }
                        }
                    }
                }
            }
        }
        $sult['recommendValue'] = $recommendValue;
        if ($recommendValue < 1) {
            switch ($recommendValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -7:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $recommendValue;
                    break;
                case -9:
                    $sult['errorCode'] = Yii::t('boe', 'user_null');
                    break;
                case -10:
                    $sult['errorCode'] = Yii::t('boe', 'no_power');
                    break;
                case -11:
                    $sult['errorCode'] = Yii::t('boe', 'recommend_num_limit');
                    break;
            }
        }
        return $sult;
    }

    /*
     * 管理员设置发布到首页状态
     * updateLogPublishStatus($log_id,$uid,$status)
      Input:$uid String  Or Array Not NULL
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID获取出对应的用户等级，只有等级是2的辅导员才可以，反之 返回-99;
      2、根据UID获取出对应的用户的组织ID,orgnization_id，如果获取不到 ，返回 -98;
     */

    public static function updateLogPublishStatus($log_id, $user_id, $status) {
        $publishValue = 0;
        $log_data = array();
        $sult = array(
            'publishValue' => 0,
            'errorCode' => '',
            'publishText' => '',
            'publishText2' => '',
            'publishClass' => '',
        );
		if (!$user_id) {
            $publishValue = -12; //账号为空	
        } else {
            $level = self::getUserLevel($user_id);
            if ($level['level'] != 3) {
                $publishValue = -13; //无发布权限
            } else {
                $manager_area = self::getManagerArea($user_id, 1);
				$area_keys	  = array_keys($manager_area['area_array']);
				if ($log_id) {
					$where 	= array('kid' => $log_id);
					$logObj = new BoeSubjectWeilog();
					$find 	= $logObj->find(false)->where($where)->asArray()->one();
					if ($find) {
						$data = array(
							'kid' => $log_id,
							'publish_status' => $find['publish_status'] == 1 ? 0 : 1,
						);
						if ($find['publish_status'] == 0) {
                            $params = array(
                                'orgnization' => $area_keys,
                                'publish_status' => 1,
                                'time' => 'today'
                            );
                            $find_num = self::getLogList($params);
                            $find_num = $find_num['totalCount'];
                        }
                        if (isset($find_num) && $find_num > 2) {
                            $publishValue = -14;
                        } else {
							$publishValue = $logObj->saveInfo($data);
							if ($publishValue) {
								$sult['publishText'] = $data['publish_status'] == 1 ? Yii::t('boe', 'boe_subject_weilog_publish_yes') : Yii::t('boe', 'boe_subject_weilog_publish_no');
								$sult['publishClass'] = $data['publish_status'] == 1 ? "btn-default" : "btn-info";
								$sult['publishText2'] = $data['publish_status'] == 1 ? Yii::t('boe', 'boe_subject_weilog_publish_yes2') : Yii::t('boe', 'boe_subject_weilog_publish_no2');
								$sult['publishClass2'] = $data['publish_status'] == 1 ? "red_star" : "no_star";
								$publishValue = 1;
							} else {
								$publishValue = -8;
							}	
						}	
					}
				}	
			}
		}
        $sult['publishValue'] = $publishValue;
        if ($publishValue < 1) {
            switch ($publishValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -8:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $publishValue;
                    break;
                case -12:
                    $sult['errorCode'] = Yii::t('boe', 'user_null');
                    break;
                case -13:
                    $sult['errorCode'] = Yii::t('boe', 'no_power');
                    break;
                case -14:
                    $sult['errorCode'] = Yii::t('boe', 'publish_num_limit') ;
                    break;
            }
        }
        return $sult;
    }

//----------------------------------------------------------和特训营专区的用户有关的方法开始--------------------------------------------------------------
    /**
     * 获取全部的微日志管理员信息，包括大区管理员，辅导员
     */
    private static function getAllBarrackManager($create_mode = 0) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $query = new Query();
            $query->from('eln_boe_barrack_manager');
            $command = $query->createCommand();
            $sult = array(
                'sql' => $command->getRawSql(),
                'list' => $query->all(),
            );
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /**
     * 获取单个用户的基本信息
     * @param type $uid
     * @param type $create_mode
     */
    public static function getOneUserInfo($uid, $create_mode = 0) {
        if (!$uid) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_uid_' . $uid;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if ($sult === NULL || $sult === false) {//从数据库读取
            $tmp_sult = BoeBaseService::getMoreUserInfo($uid, 1);
            $sult = BoeBase::array_key_is_nulls($tmp_sult, $uid, array());
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }
        return $sult;
    }

    /*
     * 获取特训营信息管理树形信息
     */

    public static function getALLOrgnizationTree() {
        $orgnization_id = Yii::t('boe', 'boe_subject_weilog_Orgnization_Kid');
        $sult = BoeBaseService::getTreeScatterArray('FwOrgnization', $orgnization_id);
        return $sult;
    }

    /*
     * 获取管理员的辖区
     * getManagerArea($uid,$detail=0)
      Input:$uid String  Not NULL
      $detail Int
      如果$detail=0,那么只会返回当前管理员辖区的子辖区
      反之返回当前管理员辖区的其辖区的子子孙孙
      Output: Array  or NULL
      array(
      'kid'=>array(//orgnization表的单个信息
      ),
      )
     */

    public static function getManagerArea($uid, $detail = 0, $create_mode = 0) {
        if (!$uid) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_uid_' . $uid . '_detail_' . $detail;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        $sult = NULL;
        if (!$sult) {//从数据库读取 
            $user_info = self::getOneUserInfo($uid);
            if ($user_info) {
                $all_manger = self::getAllBarrackManager($create_mode); //获取全部的管理员信息
                $all_manger = BoeBase::array_key_is_nulls($all_manger, 'list', NULL);
                if ($all_manger) {//读取到了全部的特训营管理员信息时S
                    foreach ($all_manger as $a_info) {//循环判断ForStart
                        if ($a_info['user_id'] == $uid) {
                            $orgnization_id = $a_info['orgnization_id'];
                            break;
                        }
                    }//循环判断ForEnd
                }//读取到了全部的特训营管理员信息时E
                $sult = BoeBaseService::getTableOneInfo('FwOrgnization', $orgnization_id);
                $sult_all = BoeBaseService::getTreeSubId('FwOrgnization', $orgnization_id);
                if ($detail) {//获取其子子孙孙S
                    $sult['area_array'] = $sult_all;
                } else {
                    //$sult[$orgnization_id] = BoeBaseService::getTableOneInfo('FwOrgnization', $orgnization_id);
                    foreach ($sult_all as $s_key => $s_value) {
                        if ($s_value['parent_orgnization_id'] == $sult['kid']) {
                            $sult['area_array'][$s_value['kid']] = $s_value;
                        }
                    }
                }
                //  BoeBase::debug(__METHOD__ . var_export($user_info, true) ."\norgnization_id:".$orgnization_id ."\n". var_export($sult, true),1);
                self::setCache($cache_name, $sult); // 设置缓存 
            } else {
                $sult = NULL;
            }
        }
        return $sult;
    }

    /*
     * 获取管理员的学员(辅导员/管理员)
     * Input:$uid String  Not NULL
      $get_orgnization_path Int
      如果$get_orgnization_path=1,那么在返回结果中会包括用户的组织路径信息

      Output: Array  or NULL
      array(
      'kid'=>array(//fw_user表的单个信息
      'orgnization_path'=>'',//根据get_orgnization_path决定
      ),
      )
     */

    public static function getManagerStudent($uid, $get_orgnization_path = 0, $create_mode = 0) {
        if (!$uid) {
            return NULL;
        }
        if (!$uid) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_uid_' . $uid . '_get_orgnization_path_' . $get_orgnization_path;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        //   $sult='';
        if (!$sult) {//从数据库读取 
            $area_info = self::getManagerArea($uid, 1); //获取当前用户的区域范围
            //  BoeBase::debug(__METHOD__.var_export($area_info,true),1);
            if (!$area_info) {
                $sult = array();
            } else {
                $orgnization_ids = array_keys($area_info['area_array']);
                $where = array('and');
                $where[] = array('is_deleted' => 0);
                $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
                $where[] = array('in', 'orgnization_id', $orgnization_ids);
                $field = 'real_name,nick_name,user_name,kid,email,user_no,orgnization_id,domain_id,company_id';

                $user_model = FwUser::find(false)->select($field);
                $user_model->where($where)->indexby('kid');
                $sult = array(
                    'sql' => $user_model->createCommand()->getRawSql(),
                    'list' => $user_model->asArray()->all(),
                );
                if ($get_orgnization_path) {
                    $sult['list'] = BoeBaseService::parseUserListInfo($sult['list']);
                }
//                BoeBase::debug(__METHOD__);
//                BoeBase::debug($sult,1);
            }
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }
        return $sult;
    }

    /**
     * 判断用户是否为特训营管理员
     * @input $user_obj=object||array()
     * @output boolean
     */
    public static function checkUserIsParticipator($user_obj = NULL) {
//        BoeBase::debug(__METHOD__);
//        BoeBase::debug(var_dump($user_obj));

        if (!$user_obj) {
            return false;
        }
        if (!is_object($user_obj) && !is_array($user_obj)) {
            return false;
        }
        $config_d_id = Yii::t('boe', 'boe_subject_weilog_Domain_Kid');

        $uid = (is_object($user_obj)) ? $user_obj->kid : $user_obj['kid'];
        $d_id = (is_object($user_obj)) ? $user_obj->domain_id : $user_obj['domain_id'];
//        BoeBase::debug('$config_d_id:' . $config_d_id);
//        BoeBase::debug('$d_id:' . $d_id);
//        BoeBase::debug('$uid:' . $uid);
        if ($d_id == $config_d_id) {
            return true;
        }
        $level_arr = self::getUserLevel($uid);
        if ($level_arr['level']==0) {
            return true;
        }
        return false;
    }

    /*
     * 获取用户的等级
     * getUserLevel($uid,$orgnization_id=NULL)
      Input:$uid String  Not NULL
      Output:5个值
      0或NULL表示只是学员
      -1:非特训营成员
      1：辅导员(连长)，
      2：营长
      3：大区管理员
     */

    public static function getUserLevel($uid, $create_mode = 0) {
        $level_data = array(
            -1 => array('level' => -1, 'role' => 'guest'),
            0 => array('level' => 0, 'role' => 'student'),
            1 => array('level' => 1, 'role' => 'teacher'),
            2 => array('level' => 2, 'role' => 'battalion'),
            3 => array('level' => 3, 'role' => 'manager')
        );
        $level_key = 0;
        if (!$uid) {
            return $level_data[$level_key];
        }
//指定了用户信息时
        $cache_name = __METHOD__ . '_uid_' . $uid;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据中整理S
            $all_manger = self::getAllBarrackManager($create_mode); //获取全部的管理员信息
            $all_manger = BoeBase::array_key_is_nulls($all_manger, 'list', NULL);
            if ($all_manger) {//读取到了全部的特训营管理员信息时S
                foreach ($all_manger as $a_info) {//循环判断ForStart
                    if ($a_info['user_id'] == $uid) {
                        $level_key = $a_info['level'];
                        break;
                    }
                }//循环判断ForEnd
            }//读取到了全部的特训营管理员信息时E
            if (!$level_key) {
                $user_info = self::getOneUserInfo($uid);
                $domain_id = Yii::t('boe', 'boe_subject_weilog_Domain_Kid');
                if ($user_info['domain_id'] != $domain_id) {
                    $level_key = -1;
                }
            }
            $sult = $level_data[$level_key];
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }//从数据中整理E
        return $sult;
        //指定了用户信息E
    }

    /*
     * 获取某个辅导员其下今日已发布日志的学员列表
     * Output:  array(//已经发布日志的人员信息  
      'kid'=>array(//fw_user表的单个信息
      ),
      ),
     */

    public static function getTeacherTodayLogGroup($uid) {
        if (!$uid) {
            return NULL;
        }
        $orgnization = self::getManagerArea($uid); //获取当前辅导员的辖区ID
        $sult = array();
        if ($orgnization) {
            $orgnization_id = $orgnization['kid'];
            $field = array(
                'eln_boe_subject_weilog.created_by as kid',
                'eln_fw_user.real_name as real_name',
                new \yii\db\Expression('count(*) as num'),
            );
			$current_time 		= time();//当前时间
			$today_time 		= strtotime(date("Y-m-d"));//今天
			$today_time_line 	= $today_time+43200;//判断是否今天日志的临界点
			if($current_time < $today_time_line)//中午12点以前
			{
				$today_time_begin 	= $today_time-43200;
				$where_time 		= array('between', 'eln_boe_subject_weilog.created_at', $today_time_begin, $today_time_line - 1);
			}
			else
			{
				$where_time			= array('>=', 'eln_boe_subject_weilog.created_at', $today_time_line);
			}
            $where = array(
                'and',
                array('=', 'eln_boe_subject_weilog.orgnization_id', $orgnization_id),
                array('=', 'eln_boe_subject_weilog.is_deleted', 0),
                array('=', 'eln_fw_user.is_deleted', 0),
                array('<>', 'eln_fw_user.status', FwUser::STATUS_FLAG_STOP),
                $where_time,	
            );
            $query = new Query();
            $query->from('eln_boe_subject_weilog')->select($field)->join('INNER JOIN', 'eln_fw_user', "eln_fw_user.kid=eln_boe_subject_weilog.created_by");
            $query->where($where)->groupBy('eln_boe_subject_weilog.created_by')->orderBy('num desc')->indexBy('kid');
            $tmp_sult = array(
                'sql' => $query->createCommand()->getRawSql(),
                'list' => $query->all(),
            );
            //  BoeBase::debug($tmp_sult, 1);
            if ($tmp_sult['list'] && is_array($tmp_sult['list'])) {
                $sult = $tmp_sult['list'];
            }
        }
        return $sult;
    }

    /*
     * 筛选日志列表
     * 	$params['time']取值：不指定表示全部
      today 今天发布的
      yesterday 昨天发布的
      other_day 两天前发布的
      $params['orgnization']取值：不指定表示全部 组织ID String Or Array
      $params['user_id']取值：不指定表示全部 用户ID String Or Array
      $params['recommend_status'] 取值：
      0表示不推荐
      1表示推荐
      其它值表示全部
      $params['publish_status'] 取值：
      0表示未发布
      1表示已发布
      其它值表示全部
      $params['offset'] 取值：int
      $params['limit'] 取值：int
      $params['get_orgnization_path'] 取值：int
      如果$params['get_orgnization_path']=1,那么在返回结果中会包括用户的组织路径信息
     */

    public static function getLogList($params = array()) {
        $sult = array();
        $where = array(
            'and',
            array('in', 'eln_boe_subject_weilog.orgnization_id', $orgnization_id),
            array('=', 'eln_boe_subject_weilog.is_deleted', 0),
        );

        if (isset($params['time']) && $params['time']) {
            //时间
			$current_time 		= time();//当前时间
			$today_time 		= strtotime(date("Y-m-d"));//今天
			$yesterday_time 	= strtotime(date("Y-m-d", strtotime("-1 day")));//昨天
            switch ($params['time']) {
                case 'today'://今天发布的
					$today_time_line 	= $today_time+43200;//判断是否今天日志的临界点
					if($current_time < $today_time_line)//中午12点以前
					{
						$today_time_begin 	= $today_time-43200;
						$where[] = array('between', 'eln_boe_subject_weilog.created_at', $today_time_begin, $today_time_line - 1);
					}
					else
					{
						$where[] = array('>=', 'eln_boe_subject_weilog.created_at', $today_time_line);
					}
                    break;
                case 'yesterday'://昨天发布的
                    $where[] = array('between', 'eln_boe_subject_weilog.created_at', $yesterday_time, $today_time - 1);
                    break;
                case 'other_day'://两天前发布的
                    $where[] = array('<', 'eln_boe_subject_weilog.created_at', $yesterday_time - 1);
                    break;
            }
        }
        //组织
        if (isset($params['orgnization']) && $params['orgnization']) {
            $where[] = array(is_array($params['orgnization']) ? 'in' : '=', 'eln_boe_subject_weilog.orgnization_id', $params['orgnization']);
        }
        //用户
        if (isset($params['user_id']) && $params['user_id']) {
            $where[] = array(is_array($params['user_id']) ? 'in' : '=', 'eln_boe_subject_weilog.created_by', $params['user_id']);
        }
        //推荐
        if (isset($params['recommend_status']) && $params['recommend_status'] && ($params['recommend_status'] == '0' || $params['recommend_status'] == '1')) {
            $where[] = array('=', 'eln_boe_subject_weilog.recommend_status', $params['recommend_status']);
        }
        //发布
        if (isset($params['publish_status']) && $params['publish_status'] && ($params['publish_status'] == '0' || $params['publish_status'] == '1')) {
            $where[] = array('=', 'eln_boe_subject_weilog.publish_status', $params['publish_status']);
        }
        $db_obj = new BoeSubjectWeilog();
        $p = array(
            'condition' => array($where),
            'offset' => isset($params['offset']) && $params['offset'] ? $params['offset'] : 0,
            'limit' => isset($params['limit']) && $params['limit'] ? $params['limit'] : 0,
            'returnTotalCount' => 1,
            'orderBy' => 'created_at desc',
            'indexby' => 'kid',
        );
        $sult = $db_obj->getList($p);
        //组织路径
        if (isset($params['get_orgnization_path']) && $params['get_orgnization_path'] == 1 && isset($sult['list']) && is_array($sult['list'])) {

            $user_data = $user_array = array();
            foreach ($sult['list'] as $s_key => $s_value) {
                $user_array[$s_value['created_by']] = $s_value['created_by'];
                $sult['list'][$s_key]['create_time'] = date("Y-m-d H:i:s", $s_value['created_at']);
                //$sult['list'][$s_key]['content'] = self::boeTrim($s_value['content']);
                $sult['list'][$s_key]['recommend_text'] = $s_value['recommend_status'] == 1 ? Yii::t('boe', 'boe_subject_weilog_recommend_yes') : Yii::t('boe', 'boe_subject_weilog_recommend_no');
                $sult['list'][$s_key]['recommend_class'] = $s_value['recommend_status'] == 1 ? "red_star" : "no_star";
                $sult['list'][$s_key]['publish_text'] = $s_value['publish_status'] == 1 ? Yii::t('boe', 'boe_subject_weilog_publish_yes') : Yii::t('boe', 'boe_subject_weilog_publish_no');
                $sult['list'][$s_key]['publish_class'] = $s_value['publish_status'] == 1 ? "btn-default" : "btn-info";

                $sult['list'][$s_key]['publish_text2'] = $s_value['publish_status'] == 1 ? Yii::t('boe', 'boe_subject_weilog_publish_yes2') : Yii::t('boe', 'boe_subject_weilog_publish_no2');
                $sult['list'][$s_key]['publish_class2'] = $s_value['publish_status'] == 1 ? "red_star" : "no_star";
            }
            $sult['user_data'] = BoeBaseService::getMoreUserInfo($user_array, 1);
            foreach ($sult['user_data'] as $s_key => $_value) {
                $orgnization_path = self::boeTrim($_value['orgnization_path'], "2016特训营\\");
                $sult['user_data'][$s_key]['orgnization_path'] = $orgnization_path;
            }
        }
        return $sult;
    }

    /* 信息过滤 */

    public static function boeTrim($str, $remove = "") {
        $search = array(" ", "　", "\n", "\r", "\t");
        $replace = array("", "", "", "", "");
        $str = str_replace($search, $replace, $str);
        if ($remove) {
            $str = str_replace($remove, "", $str);
        }
        return $str;
    }

    /*     * ************************************敏感词汇的过滤****************************************************** */
    /*
     * 获取所有敏感词汇数组信息
     */

    public static function get_all_badword() {
        $sult = array();
        $cache_name = __METHOD__ . md5('_badword_all');
        $all_badword = Yii::$app->cache->get($cache_name);
        if ($all_badword) {
            $sult = $all_badword;
        } else {
            $badObj = new BoeBadword();
            $sult = $badObj->getList();
            Yii::$app->cache->set($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /*
     * 获取所有敏感词汇的索引数组信息
     */

    public static function get_all_badword_key() {
        $sult = array();
        $cache_name = __METHOD__ . md5('_badword_all_key');
        $all_badword_key = Yii::$app->cache->get($cache_name);
        if ($all_badword_key) {
            $sult = $all_badword_key;
        } else {
            $sult_all = self::get_all_badword();
            foreach ($sult_all as $s_key => $s_value) {
                $sult[$s_value['first']] = $s_value['first'];
            }
            $sult = array_keys($sult);
            Yii::$app->cache->set($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /*
     * 敏感词汇检测
     * 返回数组包含敏感词汇和原关键词
     */

    public static function check_bad_word($keyword = '') {
        if (!$keyword) {
            return -99;
        }
        $all_key = self::get_all_badword_key();
        $all_key = implode("|", $all_key);
        if ($all_key) {
            $all_key = "/{$all_key}/is";
            preg_match_all($all_key, $keyword, $bad_key);
            if (isset($bad_key[0]) && $bad_key[0] && is_array($bad_key[0])) {
                $check_p = array(
                    'index' => $bad_key[0],
                    'search_key' => $keyword,
                );
                $sult = self::check_badword_key($check_p);
                return $sult;
            }
        }
        return '';
    }

    /*
     * 敏感词汇检测 检测索引和对应的关键词
     */

    public static function check_badword_key($p = array()) {
        $sult = '';
        if (!isset($p) || !$p) {
            return -98;
        }
        if (!isset($p['index']) || !$p['index'] || !is_array($p['index'])) {
            return -97;
        }
        if (!isset($p['search_key']) || !$p['search_key'] || !is_string($p['search_key'])) {
            return -96;
        }
        $sult_badword = self::get_badword_from_key($p['index']); //获取的是数组
        //return $sult_badword;
        foreach ($sult_badword as $b_key => $b_value) {
            if (strpos($p['search_key'], $b_value) !== false) {
                $sult.=$b_value . ";";
            }
        }
        $sult = trim($sult, ";");
        if (is_int($sult) && $sult < 0) {
            $sult = '';
        }
        return $sult;
    }

    /*
     * 根据索引获得相关敏感词汇的相关数组
     */

    public static function get_badword_from_key($p = array()) {
        if (!isset($p) || !$p) {
            return -95;
        }
        $sult = array();
        foreach ($p as $key => $value) {
            if ($value) {
                $badword_array = self::get_badword_from_a_key($value);
                $sult = array_merge($sult, $badword_array);
            }
        }
        return $sult;
    }

    /*
     * 根据单个索引获取相关的敏感词汇的数组	
     */

    public static function get_badword_from_a_key($key = NULL) {
        if (!isset($key) || !$key) {
            return -94;
        }
        $sult = array();
        $cache_name = __METHOD__ . md5('_badword_one_key_' . serialize($key));
        $badword = Yii::$app->cache->get($cache_name);
        if ($badword) {
            $sult = $badword;
        } else {
            $sult_all = self::get_all_badword();
            foreach ($sult_all as $s_key => $s_value) {
                if ($s_value['first'] == $key) {
                    $sult[] = $s_value['keyword'];
                }
            }
            Yii::$app->cache->set($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

}

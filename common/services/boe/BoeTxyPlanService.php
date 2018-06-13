<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\services\boe\BoeTxyService;
use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\boe\BoeTxyPlan;
use common\models\boe\BoeBadword;
use common\services\boe\BoeBaseService;
use yii\db\Query;
use Yii;

/**
 * 特训营行动计划相关
 * @author xinpeng
 */
class BoeTxyPlanService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
	private static $timeInterval = 43200;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_txy_plan_';

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
     * 前台删除行动计划信息
     */
    public static function deletePlan($plan_id, $user_id) {
        $deleteValue = 0;
        $sult = array(
            'deleteValue' => 0,
            'errorCode' => '',
        );
        if ($plan_id) {
            $planObj = new BoeTxyPlan();
            $where = array(
                'kid' => $plan_id,
                'created_by' => $user_id
            );
            $find = $planObj->find(false)->where($where)->asArray()->one();
            if ($find) {
                if ($find['recommend_status'] == 1 || $find['publish_status'] == 1) {
                    $deleteValue = -5;
                } else {
                    $current_time 		= time();//当前时间
					$today_time 		= strtotime(date("Y-m-d"));//今天
                    if ($find['created_at'] >= $today_time) {
                        $deleteValue = $planObj->deleteInfo($plan_id);
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
                    $sult['errorCode'] = Yii::t('boe', 'boe_txy_plan_send_error');
                    break;
                case -6:
                    $sult['errorCode'] = Yii::t('boe', 'boe_txy_plan_time_error');
                    break;
            }
        }
        return $sult;
    }

    /*
     * 行动计划点赞
     * Input:$uid String 
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID和plan_id判断有没有当前对应的用户对于指定的plan_id有没有点过赞,如果有返回1,判断依据是缓存中有无记录
      2、更新数据库;
      3、添加缓存标记;
     */

    public static function likePlan($plan_id, $user_id) {
        $likeValue = 0;
        $plan_data = array();
        $sult = array(
            'likeValue' => 0,
            'errorCode' => '',
            'likeNum' => 0,
        );
        $cache_name = __METHOD__ . md5('_plan_like_' . serialize($plan_id));
        $plan_data = Yii::$app->cache->get($cache_name);
        if (isset($plan_data[$user_id]) && $plan_data[$user_id] == 1) {
            $likeValue = -5;
        } else {
            if ($plan_id) {
                $where = array('kid' => $plan_id);
                $planObj = new BoeTxyPlan();
                $find = $planObj->find(false)->where($where)->asArray()->one();
                if ($find) {
                    $data = array(
                        'kid' => $plan_id,
                        'like_num' => $find['like_num'] + 1
                    );
                    $likeValue = $planObj->saveInfo($data);
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
            $plan_data[$user_id] = 1;
            Yii::$app->cache->set($cache_name, $plan_data); // 设置缓存
        }
        return $sult;
    }

    /*
     * 辅导员设置推荐状态
     * updatePlanRecommendStatus($plan_id,$uid)
      Input:$uid String  Or Array Not NULL
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID获取出对应的用户等级，只有等级是1的辅导员才可以，反之 返回-99;
      2、根据UID获取出对应的用户的组织ID,orgnization_id，如果获取不到 ，返回 -98
      3、读取当前orgnization_id对应的行动计划今天推荐的数量，如果大于大爷指定的数量，返回-97;
     */

    public static function updatePlanRecommendStatus($plan_id, $user_id, $c_key) {
        $recommendValue = 0;
        $plan_data = array();
        $sult = array(
            'recommendValue' => 0,
            'errorCode' => '',
            'recommendText' => '',
            'recommendClass' => '',
        );
        if (!$user_id) {
            $recommendValue = -9; //账号为空	
        } else {
            $level = BoeTxyService::getUserLevel2($user_id);
			//return $level;
            if ($level[$c_key]['level'] != 1) {
                $recommendValue = -10; //无推荐权限
            } else {
                $orgnization_id	= $level[$c_key]['info']['orgnization_id'];
				$manager_area = BoeTxyService::getManagerArea($orgnization_id, 0);
				//return $manager_area;
                if ($plan_id) {
                    $planObj = new BoeTxyPlan();
                    $where = array('kid' => $plan_id);
                    $find = $planObj->find(false)->where($where)->asArray()->one();
                    if ($find) {
                        $data = array(
                            'kid' => $plan_id,
                            'recommend_status' => $find['recommend_status'] == 1 ? 0 : 1,
                        );
                        if ($find['recommend_status'] == 0) {
                            $params = array(
                                'orgnization' => $manager_area['kid'],
                                'recommend_status' => 1,
                                'time' => 'today'
                            );
                            $find_num = self::getPlanList($params);
                            $find_num = $find_num['totalCount'];
                        }
						//return $find_num;
                        if (isset($find_num) && $find_num >= 1) {
                            $recommendValue = -11;
                        } else {
                            $recommendValue = $planObj->saveInfo($data);
                            if ($recommendValue) {
                                $sult['recommendText'] = $data['recommend_status'] == 1 ? Yii::t('boe', 'boe_txy_plan_recommend_yes') : Yii::t('boe', 'boe_txy_plan_recommend_no');
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
     * updatePlanPublishStatus($plan_id,$uid,$status)
      Input:$uid String  Or Array Not NULL
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID获取出对应的用户等级，只有等级是2的辅导员才可以，反之 返回-99;
      2、根据UID获取出对应的用户的组织ID,orgnization_id，如果获取不到 ，返回 -98;
     */

    public static function updatePlanPublishStatus($plan_id, $user_id, $c_key) {
        $publishValue = 0;
        $plan_data = array();
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
            $level = BoeTxyService::getUserLevel2($user_id);
			//return $level[$c_key]['level'];
            if ($level[$c_key]['level'] != 2) {
                $publishValue = -13; //无发布权限
            } else {
                $orgnization_id	= $level[$c_key]['info']['orgnization_id'];
				$manager_area = BoeTxyService::getManagerArea($orgnization_id, 0);
				$area_keys	  = array_keys($manager_area['area_array']);
				if ($plan_id) {
					$where 	= array('kid' => $plan_id);
					$planObj = new BoeTxyPlan();
					$find 	= $planObj->find(false)->where($where)->asArray()->one();
					if ($find) {
						$data = array(
							'kid' => $plan_id,
							'publish_status' => $find['publish_status'] == 1 ? 0 : 1,
						);
						if ($find['publish_status'] == 0) {
                            $params = array(
                                'orgnization' => $area_keys,
                                'publish_status' => 1,
                                'time' => 'today'
                            );
                            $find_num = self::getPlanList($params);
                            $find_num = $find_num['totalCount'];
                        }
                        if (isset($find_num) && $find_num > 2) {
                            $publishValue = -14;
                        } else {
							$publishValue = $planObj->saveInfo($data);
							if ($publishValue) {
								$sult['publishText'] = $data['publish_status'] == 1 ? Yii::t('boe', 'boe_txy_plan_publish_yes') : Yii::t('boe', 'boe_txy_plan_publish_no');
								$sult['publishClass'] = $data['publish_status'] == 1 ? "btn-default" : "btn-info";
								$sult['publishText2'] = $data['publish_status'] == 1 ? Yii::t('boe', 'boe_txy_plan_publish_yes2') : Yii::t('boe', 'boe_txy_plan_publish_no2');
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

    /*
     * 获取某个辅导员其下今日已发布行动计划的学员列表
     * Output:  array(//已经发布行动计划的人员信息  
      'kid'=>array(//fw_user表的单个信息
      ),
      ),
     */
    public static function getTeacherTodayPlanGroup($orgnization_id) {
        if (!$orgnization_id) {
            return NULL;
        }
        $orgnization = BoeTxyService::getManagerArea($orgnization_id); //获取当前辅导员的辖区ID
		$sult = array();
        if ($orgnization) {
            $orgnization_id = $orgnization['kid'];
            $field = array(
                'eln_boe_txy_plan.created_by as kid',
                'eln_fw_user.real_name as real_name',
                new \yii\db\Expression('count(*) as num'),
            );
			$current_time 		= time();//当前时间
			$today_time 		= strtotime(date("Y-m-d"));//今天
			$where_time			= array('>=', 'eln_boe_txy_plan.created_at', $today_time);
            $where = array(
                'and',
                array('=', 'eln_boe_txy_plan.orgnization_id', $orgnization_id),
                array('=', 'eln_boe_txy_plan.is_deleted', 0),
                array('=', 'eln_fw_user.is_deleted', 0),
                array('<>', 'eln_fw_user.status', FwUser::STATUS_FLAG_STOP),
                $where_time,	
            );
            $query = new Query();
            $query->from('eln_boe_txy_plan')->select($field)->join('INNER JOIN', 'eln_fw_user', "eln_fw_user.kid=eln_boe_txy_plan.created_by");
            $query->where($where)->groupBy('eln_boe_txy_plan.created_by')->orderBy('num desc')->indexBy('kid');
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
     * 筛选行动计划列表
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

    public static function getPlanList($params = array()) {
        $sult = array();
        $where = array(
            'and',
            //array('in', 'eln_boe_txy_plan.orgnization_id', $params['orgnization_id']),
            //array('=', 'eln_boe_txy_plan.is_deleted', 0),
        );
        if (isset($params['time']) && $params['time']) {
            //时间
			$current_time 		= time();//当前时间
			$today_time 		= strtotime(date("Y-m-d"));//今天
			$yesterday_time 	= strtotime(date("Y-m-d", strtotime("-1 day")));//昨天
            switch ($params['time']) {
                case 'today'://今天发布的
					$where[] = array('>=', 'eln_boe_txy_plan.created_at', $today_time);
                    break;
                case 'yesterday'://昨天发布的
                    $where[] = array('between', 'eln_boe_txy_plan.created_at', $yesterday_time, $today_time - 1);
                    break;
                case 'other_day'://两天前发布的
                    $where[] = array('<', 'eln_boe_txy_plan.created_at', $yesterday_time - 1);
                    break;
            }
        }
        //组织
        if (isset($params['orgnization']) && $params['orgnization']) {
            $where[] = array(is_array($params['orgnization']) ? 'in' : '=', 'eln_boe_txy_plan.orgnization_id', $params['orgnization']);
        }
        //用户
        if (isset($params['user_id']) && $params['user_id']) {
            $where[] = array(is_array($params['user_id']) ? 'in' : '=', 'eln_boe_txy_plan.created_by', $params['user_id']);
        }
        //推荐
        if (isset($params['recommend_status']) && $params['recommend_status'] && ($params['recommend_status'] == '0' || $params['recommend_status'] == '1')) {
            $where[] = array('=', 'eln_boe_txy_plan.recommend_status', $params['recommend_status']);
        }
        //发布
        if (isset($params['publish_status']) && $params['publish_status'] && ($params['publish_status'] == '0' || $params['publish_status'] == '1')) {
            $where[] = array('=', 'eln_boe_txy_plan.publish_status', $params['publish_status']);
        }
        $db_obj = new BoeTxyPlan();
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
                $sult['list'][$s_key]['recommend_text'] = $s_value['recommend_status'] == 1 ? Yii::t('boe', 'boe_txy_plan_recommend_yes') : Yii::t('boe', 'boe_txy_plan_recommend_no');
                $sult['list'][$s_key]['recommend_class'] = $s_value['recommend_status'] == 1 ? "red_star" : "no_star";
                $sult['list'][$s_key]['publish_text'] = $s_value['publish_status'] == 1 ? Yii::t('boe', 'boe_txy_plan_publish_yes') : Yii::t('boe', 'boe_txy_plan_publish_no');
                $sult['list'][$s_key]['publish_class'] = $s_value['publish_status'] == 1 ? "btn-default" : "btn-info";

                $sult['list'][$s_key]['publish_text2'] = $s_value['publish_status'] == 1 ? Yii::t('boe', 'boe_txy_plan_publish_yes2') : Yii::t('boe', 'boe_txy_plan_publish_no2');
                $sult['list'][$s_key]['publish_class2'] = $s_value['publish_status'] == 1 ? "red_star" : "no_star";
            }
            $sult['user_data'] = BoeBaseService::getMoreUserInfo($user_array, 1);
            foreach ($sult['user_data'] as $s_key => $_value) {
                $orgnization_path = self::boeTrim($_value['orgnization_path'], "2017特训营\\");
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
	
	/*
	 * 学员提交行动计划信息
	*/
	public static function submitPlan($params = array()) {
		
		$sult = array('error' => 0,'message' => '');
		$data = array();
		if(!isset($params['user_id']) || !$params['user_id'])
		{
			$sult = array(
			   'error' => -7,
			   'message' => Yii::t('boe', 'boe_txy_plan_user_id_must')
			);
			return $sult;	
		}
		//缓存名称
		$cache_name = __METHOD__ . '_user_id_' . $params['user_id'] . '_current_date_' .date("Y-m-d");
		//return $cache_name;
		if(!isset($params['content']) || !$params['content'])
		{
			$sult = array(
			   'error' => -6,
			   'message' => Yii::t('boe', 'boe_txy_plan_content_must')
			);
			return $sult;	
		}
		/*if(strlen($params['content'])<240)
		{
			$sult = array(
			   'error' => -5,
			   'message' => Yii::t('boe', 'boe_txy_plan_content_len')
			);
			return $sult;
		}*/
		$data['content'] = preg_replace_callback('/[\xf0-\xf7].{3}/', function($r) {
                return '';
            }, $params['content']);
        $data['content'] = htmlspecialchars($data['content']);
		$badword_check = BoeTxyService::check_bad_word($data['content']);
		if ($badword_check && is_string($badword_check)) {
			$sult = array(
				'error' => -4,
				'message' => Yii::t('boe', 'boe_badword_contain') . $badword_check
			);
		}
		if(isset($params['plan_id'])&&$params['plan_id'])
		{
			$data['kid'] =  $params['plan_id'];
		}else
		{
			$sult_cache = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
			if($sult_cache == 1)
			{
				$sult = array(
					'error' => -3,
					'message' => Yii::t('boe', 'boe_txy_plan_today_added')
				);
				return $sult;
			}
			//判断今天是都已发表过日志信息
			$params_log = array(
				'user_id' 	=> $params['user_id'],
				'time' 		=> 'today'
			);
			$check_sult = self::getPlanList($params_log);
			if($check_sult['totalCount'] > 0 )
			{
				$sult = array(
					'error' => -3,
					'message' => Yii::t('boe', 'boe_txy_plan_today_added')
				);
				//写入缓存
				self::setCache($cache_name, 1);
				return $sult;
			}	
		}
		$user_info = BoeTxyService::getOneUserInfo($params['user_id']);
        $data['orgnization_id'] = $user_info['orgnization_id'];
		$data['user_id'] = $params['user_id'];
		$mesObj 	= new BoeTxyPlan();
		$db_sult 	= $mesObj->saveInfo($data);
		if (is_array($db_sult) || $db_sult === false) {
			if (is_array($db_sult)) {
				$sult['error'] = -1;
				$sult['message'] = BoeBase::implodeAdv('<br/ >', $db_sult);
			} else {
				$sult['error'] = -2;
				$sult['message'] = Yii::t('boe', 'boe_txy_plan_save_error');
			}
		}else
		{
			if(!isset($params['plan_id'])||!$params['plan_id'])
			{
				//写入缓存
				self::setCache($cache_name, 1);
			}
			//积分
			//BoeTxyApiService::addWeiLogIntegral($this->current_user_id);
		}
		$dbObj = NULL;
		return $sult;	
	}
	
	/*
	 * 学员提交行动计划信息(补交)
	*/
	public static function submitPlan2($params = array()) {
		
		$sult = array('error' => 0,'message' => '');
		$data = array();
		if(!isset($params['user_id']) || !$params['user_id'])
		{
			$sult = array(
			   'error' => -7,
			   'message' => Yii::t('boe', 'boe_txy_plan_user_id_must')
			);
			return $sult;	
		}
		if(!isset($params['created_at']) || !$params['created_at'])
		{
			$sult = array(
			   'error' => -8,
			   'message' => Yii::t('boe', 'boe_txy_created_at_must')
			);
			return $sult;	
		}
		//缓存名称
		//$cache_name = __METHOD__ . '_user_id_' . $params['user_id'] . '_current_date_' .date("Y-m-d");
		//return $cache_name;
		if(!isset($params['content']) || !$params['content'])
		{
			$sult = array(
			   'error' => -6,
			   'message' => Yii::t('boe', 'boe_txy_plan_content_must')
			);
			return $sult;	
		}
		$data['content'] = preg_replace_callback('/[\xf0-\xf7].{3}/', function($r) {
                return '';
            }, $params['content']);
        $data['content'] = htmlspecialchars($data['content']);
		$badword_check = BoeTxyService::check_bad_word($data['content']);
		if ($badword_check && is_string($badword_check)) {
			$sult = array(
				'error' => -4,
				'message' => Yii::t('boe', 'boe_badword_contain') . $badword_check
			);
		}
		if(isset($params['plan_id'])&&$params['plan_id'])
		{
			$data['kid'] =  $params['plan_id'];
		}else
		{
			/*$sult_cache = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
			if($sult_cache == 1)
			{
				$sult = array(
					'error' => -3,
					'message' => Yii::t('boe', 'boe_txy_plan_today_added')
				);
				return $sult;
			}
			//判断今天是都已发表过日志信息
			$params_log = array(
				'user_id' 	=> $params['user_id'],
				'time' 		=> 'today'
			);
			$check_sult = self::getPlanList($params_log);
			if($check_sult['totalCount'] > 0 )
			{
				$sult = array(
					'error' => -3,
					'message' => Yii::t('boe', 'boe_txy_plan_today_added')
				);
				//写入缓存
				self::setCache($cache_name, 1);
				return $sult;
			}*/	
		}
		$user_info = BoeTxyService::getOneUserInfo($params['user_id']);
        $data['orgnization_id'] = $user_info['orgnization_id'];
		$data['user_id'] = $params['user_id'];
		$data['created_at'] = strtotime($params['created_at']." ".date("H:i:s"));
		$mesObj 	= new BoeTxyPlan();
		$db_sult 	= $mesObj->saveInfo($data);
		if (is_array($db_sult) || $db_sult === false) {
			if (is_array($db_sult)) {
				$sult['error'] = -1;
				$sult['message'] = BoeBase::implodeAdv('<br/ >', $db_sult);
			} else {
				$sult['error'] = -2;
				$sult['message'] = Yii::t('boe', 'boe_txy_plan_save_error');
			}
		}else
		{
			/*if(!isset($params['plan_id'])||!$params['plan_id'])
			{
				//写入缓存
				self::setCache($cache_name, 1);
			}*/
			//积分
			//BoeTxyApiService::addWeiLogIntegral($this->current_user_id);
		}
		$dbObj = NULL;
		return $sult;	
	}

}

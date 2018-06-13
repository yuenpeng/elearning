<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\services\boe\BoeTxyService;
use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\boe\BoeTxyMessages;
use common\models\boe\BoeBadword;
use common\services\boe\BoeBaseService;
use yii\db\Query;
use Yii;

/**
 * 特训营留言相关
 * @author xinpeng
 */
class BoeTxyMessagesService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
	private static $timeInterval = 43200;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_txy_messages_';

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
     * 前台删除留言信息
     */
    public static function deleteMessages($messages_id, $user_id) {
        $deleteValue = 0;
        $sult = array(
            'deleteValue' => 0,
            'errorCode' => '',
        );
        if ($messages_id) {
            $messagesObj = new BoeTxyMessages();
            $where = array(
                'kid' => $messages_id,
                'created_by' => $user_id
            );
            $find = $messagesObj->find(false)->where($where)->asArray()->one();
            if ($find) {
                if ($find['recommend_status'] == 1 || $find['publish_status'] == 1) {
                    $deleteValue = -5;
                } else {
                    $current_time 		= time();//当前时间
					$today_time 		= strtotime(date("Y-m-d"));//今天
                    if ($find['created_at'] >= $today_time) {
                        $deleteValue = $messagesObj->deleteInfo($messages_id);
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
                    $sult['errorCode'] = Yii::t('boe', 'boe_txy_messages_send_error');
                    break;
                case -6:
                    $sult['errorCode'] = Yii::t('boe', 'boe_txy_messages_time_error');
                    break;
            }
        }
        return $sult;
    }
	

    /*
     * 筛选留言列表
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
    public static function getMessagesList($params = array()) {
        $sult = array();
        $where = array(
            'and',
            array('=', 'eln_boe_txy_messages.is_deleted', 0),
        );
        if (isset($params['time']) && $params['time']) {
            //时间
			$current_time 		= time();//当前时间
			$today_time 		= strtotime(date("Y-m-d"));//今天
			$yesterday_time 	= strtotime(date("Y-m-d", strtotime("-1 day")));//昨天
            switch ($params['time']) {
                case 'today'://今天发布的
					$where[] = array('>=', 'eln_boe_txy_messages.created_at', $today_time);
                    break;
                case 'yesterday'://昨天发布的
                    $where[] = array('between', 'eln_boe_txy_messages.created_at', $yesterday_time, $today_time - 1);
                    break;
                case 'other_day'://两天前发布的
                    $where[] = array('<', 'eln_boe_txy_messages.created_at', $yesterday_time - 1);
                    break;
            }
        }
        //组织
        if (isset($params['orgnization']) && $params['orgnization']) {
            $where[] = array(is_array($params['orgnization']) ? 'in' : '=', 'eln_boe_txy_messages.orgnization_id', $params['orgnization']);
        }
        //用户
        if (isset($params['user_id']) && $params['user_id']) {
            $where[] = array(is_array($params['user_id']) ? 'in' : '=', 'eln_boe_txy_messages.created_by', $params['user_id']);
        }
        $db_obj = new BoeTxyMessages();
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
				$sult['list'][$s_key]['update_time'] = date("Y-m-d H:i:s", $s_value['updated_at']);
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

}

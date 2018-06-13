<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\services\boe\BoeBaseService;
use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\boe\BoeMakeNews;
use common\models\boe\BoeMakeNewsCategory;
use common\models\boe\BoeMakeStudent;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 专职制造工程师特训营相关
 * @author xinpeng
 */
class BoeMakeService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
	private static $tongjiCacheTime = 1800;//统计的缓存时间是0.5小时
	private static $timeInterval = 43200;
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
     * 判别用户的是否有专职制造的权限 
     */
    public static function getUserPower($uid, $create_mode = 0) {
        $cache_name = __METHOD__ . '_uid_' . $uid;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据中整理S
            $all_user = self::getAllMakeUser($create_mode); //获取全部的用户信息
			if(isset($all_user[$uid])&&$all_user[$uid])
			{
				$sult = 1;
			}else
			{
				$sult = 0;
			}
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }//从数据中整理E
        return $sult;
        //指定了用户信息E
    }
	
	/**
     * 获取全部的专职制造信息
     */
    static function getAllMakeUser($create_mode = 0) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
			$sult = BoeMakeStudent::find(false)->where(array('is_deleted'=>'0'))->indexBy('user_id')->asArray()->all();
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }
	
	
	
	
	
	//----------------------------------------------------------和特训营的资讯有关的方法开始
	/**
     * 根据TAG获取对应的分类信息（包含子孙信息）
     */
    static function getNewsCategoryListFromTag($news_category_tag = NULL, $hastagid = 0) {
        if (!$news_category_tag) {
            return NULL;
        }
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit . '_news_category_tag_' . $news_category_tag;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S 
            $sult 		= array();
			$news_db	= new BoeMakeNewsCategory();
            $sult 		= $news_db->getBaseTreeFromTag($news_category_tag);
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }
	
	/**
     * 读取在首页推荐的分类和信息列表
     * getIndexMakeNewsList
     * $hastagid 是否包含当前tagid,默认为0,不包含，1为包含
     */
    static function getIndexMakeNewsListFromTag($limit = 4, $new_category_tag = NULL, $hastagid = 0,$is_cache =1) {
        if (!$new_category_tag) {
            return NULL;
        }
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit . '_new_category_tag_' . $new_category_tag;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult) || $is_cache) {//缓存中没有或是强制生成缓存模式时S 
            $sult = array();
			$news_category_db	= new BoeMakeNewsCategory();
            $cate_info = $news_category_db->getSubTag($new_category_tag, 0, $hastagid); //找出分类对应子子孙孙的ID
            $tmp_kid = array();
            if ($cate_info && is_array($cate_info)) {
                foreach ($cate_info as $key => $a_cate) {
                    $sult['cate_' . $key] = array(
                        'cate_id' => $a_cate['kid'],
                        'cate_name' => $a_cate['name'],
                        'info_list' => self::getRecommendMakeNewsInfo($a_cate['kid'], $limit, 1),
                    );
                    $tmp_kid = array_merge($tmp_kid, $sult['cate_' . $key]['info_list']);
                }
            }
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }

    /**
     * 获取某个分类在首页的推荐信息
     * @param type $cate_id
     * @param type $limit
     */
    private static function getRecommendMakeNewsInfo($cate_id = '', $limit = 9, $fill = 0, $debug = 0) {
        $params = array(
            'condition' => array(
                'base' => array('>', 'recommend_sort1', 0)
            ),
            'orderBy' => 'recommend_sort1 asc,created_at desc',
            'indexby' => 'kid',
            'returnTotalCount' => 1,
            'limit' => $limit,
            'debug' => $debug,
        );
        if ($cate_id) {//分类搜索
            $news_category_db	= new BoeMakeNewsCategory();
			$tmp_arr = $news_category_db->getSubId($cate_id, 1); //找出分类对应子子孙孙的ID
            if ($tmp_arr) {
                if (count($tmp_arr) > 1) {
                    $params['condition']['cate'] = array('in', 'category_id', $tmp_arr);
                } else {
                    $params['condition']['cate'] = array('category_id' => $tmp_arr[0]);
                }
            }
            $tmp_arr = NULL;
        }
        if ($debug) {
            BoeBase::debug($params);
        }
		$news_db	= new BoeMakeNews();
        $dbData 	= $news_db->getList($params);
        $sult = isset($dbData['list']) && is_array($dbData['list']) ? $dbData['list'] : array();
        if ($fill && isset($dbData['totalCount']) && $dbData['totalCount'] < $limit) {
            $params['condition']['base'] = array('not in', 'kid', array_keys($sult));
            $params['limit'] = $limit - $dbData['totalCount'];
            $dbData = $news_db->getList($params);
            $tmp_sult = isset($dbData['list']) && is_array($dbData['list']) ? $dbData['list'] : array();
            $sult = array_merge($sult, $tmp_sult);
            $tmp_sult = NULL;
        }
        $dbData = NULL;
        return self::parseMakeNewsList($sult);
    }

    private static function parseMakeNewsList($news_list) {
        $delete_key = array(
            'recommend_sort2',
            'recommend_sort3',
            'recommend_sort4',
            'recommend_sort5',
            'recommend_sort6',
            'recommend_sort7',
            'recommend_sort8',
            'recommend_sort9',
            'recommend_sort10',
            'keyword1',
            'keyword2',
            'keyword3',
            'keyword4',
            'keyword5',
            'keyword6',
            'keyword7',
            'keyword8',
            'keyword9',
            'keyword10',
        );
		$news_category_db	= new BoeMakeNewsCategory();
        foreach ($news_list as $key => $a_info) {
            $a_info['front_url'] = Yii::$app->urlManager->createUrl(array('boe/make/news-detail', 'id' => $a_info['kid']));
            $a_info['cate_name'] 		= $news_category_db->getInfo($a_info['category_id'], 'name');
            $a_info['update_time'] 		= date("Y-m-d H:i:s", $a_info['updated_at']);
            $a_info['update_day'] 		= date("Y-m-d", $a_info['updated_at']);
            $a_info['update_base_day'] 	= date("m-d", $a_info['updated_at']);
            $a_info['create_time'] 		= date("Y-m-d H:i:s", $a_info['created_at']);
            $a_info['create_day'] 		= date("Y-m-d", $a_info['created_at']);
            $a_info['create_base_day'] 	= date("m-d", $a_info['created_at']);

            if (!$a_info['cate_name']) {
                $a_info['cate_name'] = Yii::t('boe', 'txy_error');
            }
            foreach ($delete_key as $a_key) {
                if (isset($a_info[$a_key])) {
                    unset($a_info[$a_key]);
                }
            }
            $news_list[$key] = $a_info;
        }
        return $news_list;
    }
	
	public static function getMakeNewsDetail($id = '', $show_ad = 0) {
        $cache_mode = self::isNoCacheMode() ? 1 : 0;
        if (DIRECTORY_SEPARATOR == "\\") {
            $cache_mode = 1;
        }
		$db_obj		= new BoeMakeNews();
        $info 		= $db_obj->getInfo($id, '*', $cache_mode);
        if ($info) {
            if ($info['image_url']) {
                $info['image_url'] = BoeBase::getFileUrl($info['image_url'], 'makeNews');
            }
            $parse_info = self::parseMakeNewsList(array($info));
            return current($parse_info);
        }
        return NULL;
    }
	
	//----------------------------------------------------------和特训营的资讯有关的方法结束
	
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

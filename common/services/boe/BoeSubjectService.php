<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\base\BoeBase;
use common\models\boe\BoeSubject;
use common\models\boe\BoeSubjectNews;
use common\models\boe\BoeSubjectConfig;
use common\models\boe\BoeSubjectStudent;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 专题相关
 * @author xinpeng
 */
class BoeSubjectService {

    static $loadedObject = array();
    static $initedLog = array();
    static $_env = array();
    static $cache_time = 300;
    private static $checkExpirsTime = false;

    static function initDb($key = '') {
        self::boeExpirsTimeCheck();
        $db_key = 'db_' . $key;
        if (!isset(self::$loadedObject[$db_key])) {
            $class_name = "common\\models\\boe\\{$key}";
            self::$loadedObject[$db_key] = new $class_name();
        }
        return self::$loadedObject[$db_key];
    }

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isNoCacheMode() {
        return Yii::$app->request->get('no_cache') == 1 ? true : false;
    }

    private static function boeExpirsTimeCheck() {
        
    }

    /**
     * isNoCacheMode当前是否处于BUG的状态
     * @return type
     */
    private static function isDebugMode() {
        return Yii::$app->request->get('debug_mode') == 1 ? true : false;
    }

    /*
     * 以tag为键获取所有的专题分类信息
     */

    public static function getBoeSubjectAllTag() {
        $data = $sult = array();
        $sult = self::initDb('BoeSubject')->getAll();
        $news = Yii::t('boe', 'boe_subject_tag_news');
        $pics = Yii::t('boe', 'boe_subject_tag_pics');
        foreach ($sult as $s_key => $s_value) {
            if ($s_value['tag']) {
                $data[$s_value['tag']] = $s_value;
                $url = in_array($s_value['tag'], $pics['tag']) ? $pics['url'] : $news['url'];
                $data[$s_value['tag']]['list_url'] = Yii::$app->urlManager->createUrl(array($url, 'typekid' => $s_value['kid']));
            }
        }
        return $data;
    }

    public static function getSubjectNewsDetail($id = '', $show_ad = 0) {
        $cache_mode = self::isNoCacheMode() ? 1 : 0;
        if (DIRECTORY_SEPARATOR == "\\") {
            $cache_mode = 1;
        }
        $info = self::initDb('BoeSubjectNews')->getInfo($id, '*', $cache_mode);
        if ($info) {
            if ($info['image_url']) {
                $info['image_url'] = BoeBase::getFileUrl($info['image_url'], 'subjectNews');
            } else {
                $info['image_url'] = $show_ad ? self::getBoeSubjectNewsDetailAd() : '';
            }
            $parse_info = self::parseSubjectNewsList(array($info));
            return current($parse_info);
        }
        return NULL;
    }

    /**
     * 获取资讯detail的AD
     * @return type
     */
    static function getBoeSubjectNewsDetailAd() {
        self::boeSubjectConfigInit();
        $tmp_logo = self::initDb('BoeSubectConfig')->getInfo('subjectnews_detail_ad', 'content');
        return BoeBase::getFileUrl($tmp_logo, 'subjectConfig');
    }

    /**
     * boeSubjectConfigInit 读取BoeSystemConfig的配置信息时进行初始化
     */
    private static function boeSubjectConfigInit() {
        self::boeExpirsTimeCheck();
        if (self::isNoCacheMode()) {//清理缓存
            if (!isset(self::$initedLog['boeSubjectConfig'])) {
                self::initDb('BoeSubjectConfig')->getAll(1);
                self::$initedLog['boeSubjectConfig'] = 1;
            }
        }
    }

    /**
     * 获取专题基本信息 
     * @return type
     */
    static function getBoeSubjectBasicInfo() {
        self::boeSubjectConfigInit();
        $basic_info = array();
        $basic_info['subject_short_name'] = self::initDb('BoeSubjectConfig')->getInfo('subject_short_name', 'content');
        $basic_info['subject_full_name'] = self::initDb('BoeSubjectConfig')->getInfo('subject_full_name', 'content');
        $basic_info['subject_begin_date'] = self::initDb('BoeSubjectConfig')->getInfo('subject_begin_date', 'content');
        $basic_info['subject_end_date'] = self::initDb('BoeSubjectConfig')->getInfo('subject_end_date', 'content');
        return $basic_info;
    }
	
	/**
     * 获取闯关专区专题基本信息 
     * @return type
     */
    static function getBoeSubjectHabitInfo() {
        self::boeSubjectConfigInit();
        $basic_info = array();
        $basic_info['habit_short_name'] = self::initDb('BoeSubjectConfig')->getInfo('habit_short_name', 'content');
        $basic_info['habit_full_name'] = self::initDb('BoeSubjectConfig')->getInfo('habit_full_name', 'content');
        $basic_info['habit_begin_date'] = self::initDb('BoeSubjectConfig')->getInfo('habit_begin_date', 'content');
        $basic_info['habit_end_date'] = self::initDb('BoeSubjectConfig')->getInfo('habit_end_date', 'content');
        return $basic_info;
    }

    /**
     * 获取每日课程安排信息 
     * @return type
     */
    static function getBoeSubjectCourseInfo($area_id = "") {
        self::boeSubjectConfigInit();
        $sult = array();
        $config = self::initDb('BoeSubjectConfig')->getInfo('course_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = $a_info['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
                $config[$key]['course_link_text'] = $a_info['course_link_text'] = BoeBase::left($a_info['course_link_text'], 20, '');
                if ($area_id == $a_info['area_id']) {
                    $sult[] = $a_info;
                }
            }
        }
        $config = $area_id ? $sult : $config;
        return $config;
    }

    /**
     * 获取最佳学员倒计时信息 
     * @return type
     */
    static function getBoeSubjectCountDownInfo() {
        self::boeSubjectConfigInit();
        $config = self::initDb('BoeSubjectConfig')->getInfo('countdown_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
            }
        }
        return $config;
    }

    static function getBoeSubjectNiceStudent($num = 10) {
        $cache_mode = self::isNoCacheMode() ? 1 : 0;
        if (DIRECTORY_SEPARATOR == "\\") {
            $cache_mode = 1;
        }
        $sult = self::initDb('BoeSubjectStudent')->getFrontendStudentList($num, $cache_mode, self::$cache_time);
        if ($sult && is_array($sult)) {
            foreach ($sult as $key => $a_info) {
                $sult[$key]['image_url'] = $a_info['image_url'] ? BoeBase::getFileUrl($a_info['image_url'], 'subjectConfig') : '';
                $orgnization_path = explode('\\', $a_info['orgnization_path']);
                if(count($orgnization_path)>2){
                    $orgnization_path=  array_slice($orgnization_path, -2);
                }
                $sult[$key]['short_orgnization_path'] = $orgnization_path;
            }
        }
        return $sult;
    }
	
	 static function getBoeSubjectDateStudent($params = array(),$create_mode = 0) {
        $cache_mode = self::isNoCacheMode() ? 1 : 0;
        if (DIRECTORY_SEPARATOR == "\\") {
            $cache_mode = 1;
        }
        $sult = self::initDb('BoeSubjectStudent')->getFrontendDateStudentList($params, $cache_mode, self::$cache_time);
		return $sult;
        if ($sult && is_array($sult)) {
            foreach ($sult as $key => $a_info) {
                $sult[$key]['image_url'] = $a_info['image_url'] ? BoeBase::getFileUrl($a_info['image_url'], 'subjectConfig') : '';
                $orgnization_path = explode('\\', $a_info['orgnization_path']);
                if(count($orgnization_path)>2){
                    $orgnization_path=  array_slice($orgnization_path, -2);
                }
                $sult[$key]['short_orgnization_path'] = $orgnization_path;
            }
        }
        return $sult;
    }

    /**
     * 获取线上认亲会信息 
     * @return type
     */
    static function getBoeSubjectAttachedInfo() {
        self::boeSubjectConfigInit();
        $config = self::initDb('BoeSubjectConfig')->getInfo('attached_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取电子明信片信息 
     * @return type
     */
    static function getBoeSubjectPostCardInfo() {
        self::boeSubjectConfigInit();
        $config = self::initDb('BoeSubjectConfig')->getInfo('postcard_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取线上视频信息 
     * @return type
     */
    static function getBoeSubjectLineVideoInfo() {
        self::boeSubjectConfigInit();
        $config = self::initDb('BoeSubjectConfig')->getInfo('linevideo_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取总结视频信息 
     * @return type
     */
    static function getBoeSubjectSummaryVideoInfo() {
        self::boeSubjectConfigInit();
        $config = self::initDb('BoeSubjectConfig')->getInfo('summaryvideo_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取通栏信息 
     * @return type
     */
    static function getBoeSubjectBannerInfo() {
        self::boeSubjectConfigInit();
        $config = self::initDb('BoeSubjectConfig')->getInfo('banner_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'subjectConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取专题头部导航信息
     * @return type
     */
    static function getBoeSubjectHeaderNav($active_key = '') {
        $tmp_info = Yii::$app->params['boeSubjectHeaderNav'];
        $all_tag = self::getBoeSubjectAllTag();
        $sult = array();
        if ($tmp_info && is_array($tmp_info)) {
            $current_actions = Yii::$app->controller->id . '/' . Yii::$app->controller->action->id;
            $has_actived_tag = 0;
            foreach ($tmp_info as $key => $a_info) {
                $sult[$key] = array();
                $sult[$key]['text'] = Yii::t('boe', $a_info['lang_key']);
                $sult[$key]['url'] = "javascript:;";
                $sult[$key]['tips_text'] = "";
                $sult[$key]['is_active'] = 0;
                $sult[$key]['target'] = 0;
                $sult[$key]['class'] = isset($a_info['class']) ? $a_info['class'] : "";
                $sult[$key]['is_mobile'] = isset($a_info['is_mobile']) ? $a_info['is_mobile'] : 0;
                if (!empty($a_info['url'])) {
                    if (is_array($a_info['url'])) {//是个数组时
                        $sult[$key]['url'] = Yii::$app->urlManager->createUrl($a_info['url']);
                    } else {
                        if (preg_match('/(http|https):\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $a_info['url'])) {//如果是个直接的URL地址
                            $sult[$key]['url'] = $a_info['url'];
                        }
                        if (isset($all_tag[$a_info['url']])) {
                            $sult[$key]['url'] = $all_tag[$a_info['url']]['list_url'];
                        }
                    }
                } else {
                    $sult[$key]['tips_text'] = Yii::t('boe', $a_info['tips_key']);
                }

                if (!$has_actived_tag) {
                    if ($active_key) {
                        if (!empty($a_info['active_key'])) {
                            $sult[$key]['is_active'] = $a_info['active_key'] == $active_key ? 1 : 0;
                        }
                        if ($sult[$key]['is_active']) {
                            $has_actived_tag = 1;
                        }
                    } else {
                        if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == $sult[$key]['url']) {
                            $sult[$key]['is_active'] = 1;
                            $has_actived_tag = 1;
                        }
                    }
                }
                $sult[$key]['actions'] = !empty($a_info['actions']) && is_array($a_info['actions']) ? $a_info['actions'] : array();
                $sult[$key]['target'] = !empty($a_info['target']) ? $a_info['target'] : '_self';
            }
            if (!$has_actived_tag) {//没有找到特定的active时,看看当前的Action是否在配置指定的Action中S
                foreach ($sult as $key => $a_info) {
                    $has_actived_tag = $sult[$key]['is_active'] = in_array($current_actions, $a_info['actions']) ? 1 : 0;
                    if ($has_actived_tag) {
                        break;
                    }
                }
            }//没有找到特定的active时,看看当前的Action是否在配置指定的Action中E
        }
        return $sult;
    }

    /**
     * 根据TAG获取对应的分类信息（包含子孙信息）
     */
    static function getSubjectTypeListFromTag($subject_tag = NULL, $hastagid = 0) {
        if (!$subject_tag) {
            return NULL;
        }
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit . '_subject_tag_' . $subject_tag;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S 
            $sult = array();
            $sult = self::initDb('BoeSubject')->getBaseTreeFromTag($subject_tag);
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }

    /**
     * 读取在首页推荐的分类和信息列表
     * getIndexSubjectNewsList
     * $hastagid 是否包含当前tagid,默认为0,不包含，1为包含
     */
    static function getIndexSubjectNewsListFromTag($limit = 4, $subject_tag = NULL, $hastagid = 0) {
        if (!$subject_tag) {
            return NULL;
        }
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit . '_subject_tag_' . $subject_tag;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S 
            $sult = array();
            $cate_info = self::initDb('BoeSubject')->getSubTag($subject_tag, 0, $hastagid); //找出分类对应子子孙孙的ID
            $tmp_kid = array();
            if ($cate_info && is_array($cate_info)) {
                foreach ($cate_info as $key => $a_cate) {
                    $sult['cate_' . $key] = array(
                        'cate_id' => $a_cate['kid'],
                        'cate_name' => $a_cate['name'],
                        'info_list' => self::getRecommendSubjectNewsInfo($a_cate['kid'], $limit, 1),
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
    private static function getRecommendSubjectNewsInfo($cate_id = '', $limit = 9, $fill = 0, $debug = 0) {
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
            $tmp_arr = self::initDb('BoeSubject')->getSubId($cate_id, 1); //找出分类对应子子孙孙的ID
            if ($tmp_arr) {
                if (count($tmp_arr) > 1) {
                    $params['condition']['cate'] = array('in', 'subject_id', $tmp_arr);
                } else {
                    $params['condition']['cate'] = array('subject_id' => $tmp_arr[0]);
                }
            }
            $tmp_arr = NULL;
        }
        if ($debug) {
            BoeBase::debug($params);
        }
        $dbData = self::initDb('BoeSubjectNews')->getList($params);
        $sult = isset($dbData['list']) && is_array($dbData['list']) ? $dbData['list'] : array();
        if ($fill && isset($dbData['totalCount']) && $dbData['totalCount'] < $limit) {
            $params['condition']['base'] = array('not in', 'kid', array_keys($sult));
            $params['limit'] = $limit - $dbData['totalCount'];
            $dbData = self::initDb('BoeSubjectNews')->getList($params);
            $tmp_sult = isset($dbData['list']) && is_array($dbData['list']) ? $dbData['list'] : array();
            $sult = array_merge($sult, $tmp_sult);
            $tmp_sult = NULL;
        }
        $dbData = NULL;
        return self::parseSubjectNewsList($sult);
    }

    private static function parseSubjectNewsList($news_list) {
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
        foreach ($news_list as $key => $a_info) {
            $a_info['front_url'] = Yii::$app->urlManager->createUrl(['boe/subject/detail', 'id' => $a_info['kid']]);
            $a_info['cate_name'] = self::initDb('BoeSubject')->getInfo($a_info['subject_id'], 'name');
            $a_info['update_time'] = date("Y-m-d H:i:s", $a_info['updated_at']);
            $a_info['update_day'] = date("Y-m-d", $a_info['updated_at']);
            $a_info['update_base_day'] = date("m-d", $a_info['updated_at']);
            $a_info['create_time'] = date("Y-m-d H:i:s", $a_info['created_at']);
            $a_info['create_day'] = date("Y-m-d", $a_info['created_at']);
            $a_info['create_base_day'] = date("m-d", $a_info['created_at']);

            if (!$a_info['cate_name']) {
                $a_info['cate_name'] = Yii::t('boe', 'subject_error');
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

}

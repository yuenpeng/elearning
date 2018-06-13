<?php

namespace common\services\boe;

use common\base\BoeBase;
use common\models\boe\BoeNewsCategory;
use common\models\boe\BoeNews;
use common\models\boe\BoeDocCategory;
use common\models\boe\BoeDoc;
use common\models\boe\BoeSystemConfig;
use common\models\learning\LnCourse;
use common\models\learning\LnResourceDomain;
use common\models\learning\LnCourseComplete;
use common\models\framework\FwUser;
use common\services\framework\DictionaryService;
use common\models\learning\LnCourseTeacher;
use common\models\learning\LnTeacher;
use common\models\social\SoQuestion;
use common\models\social\SoAnswer;
use common\services\boe\BoeDocService;
use common\services\boe\BoeBaseService;
use common\services\boe\BoeWeilogService;
use yii\db\Expression;
use yii\db\Query;
use Yii;

/**

 * User: Zheng lk
 * Date: 2016/2/26
 * Time: 14:10
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class FrontService {

    static $loadedObject = array();
    static $initedLog = array();
    static $_env = array();
    private static $checkExpirsTime = false;

    static function initDb($key = '') {
        self::boeExpirsTimeCheck();
        $db_key = 'db_' . $key;
        if (!isset(self::$loadedObject[$db_key])) {
            // exit($key=='BoeSystemConfig'?"ddd":"eeeee");
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

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isDebugMode() {
        return Yii::$app->request->get('debug_mode') == 1 ? true : false;
    }

    /**
     * 读取在首页的排行版信息
     */
    static function getIndexRankingInfo($limit = 9) {
        return array();
    }

    /**
     * 读取在首页推荐的热门文库
     */
    static function getIndexHotDocInfo($limit = 9) {
        return array();
    }

    /**
     * 读取在首页的热门回答
     */
    static function getIndexAnswersInfo($company_id = NULL, $limit = 9, $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5('_company_info_' . serialize($company_id) . '_limit_' . $limit);
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 

        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $sult = array();
            $question_table_name = SoQuestion::realTableName();
            $user_table_name = FwUser::realTableName();
            $select_field = array(
                'title', 'kid', 'user_id'
            );
            $select_field_str = array();
            foreach ($select_field as $a_info) {
                $select_field_str[] = "{$question_table_name}.{$a_info} as {$a_info}";
            }
            $c_time = time();
            $select_field_str = implode(',', $select_field_str);

            $query = (new Query())->from($question_table_name)
                    ->select($select_field_str)
                    ->orderBy($question_table_name . '.answer_num desc')
                    ->limit($limit)
                    ->indexby('kid')
                    //   ->where($where_p)
                    ->andFilterWhere(array('=', $question_table_name . '.is_deleted', 0));
            if ($company_id) {//指定了公司信息时 
                $query->distinct();
                $query->join('INNER JOIN', $user_table_name, "{$user_table_name}.kid={$question_table_name}.user_id");
                $query->andFilterWhere(array('=', $user_table_name . '.is_deleted', 0));
                if (is_array($company_id)) {
                    $query->andFilterWhere(array('in', $question_table_name . '.company_id', $company_id));
                } else {
                    $query->andFilterWhere(array('=', $question_table_name . '.company_id', $company_id));
                }
            }

            $command = $query->createCommand();
            if ($debug) {
                BoeBase::debug(__METHOD__ . "\nSql:\n");
                BoeBase::debug($command->getRawSql());
            }
            $db_info = $query->all();
            if ($db_info) {
                foreach ($db_info as $key => $a_info) {
                    $a_info['url'] = Yii::$app->urlManager->createUrl(['question/detail', 'id' => $a_info['kid']]);
                    $sult[$key] = $a_info;
                }
            }
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }
	
	/**
     * 读取在首页的开班信息
     */
    static function getIndexLessonInfo2($domain_id = 0, $work_place_id = '', $limit = 6, $teacher_num = 3, $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5('_domain_id_' . serialize($domain_id) . '_work_place_' . $work_place_id . '_limit_' . $limit . '_teacher_num' . $teacher_num);
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E  
        $debug_mode = $debug || self::isDebugMode();
        if ($debug_mode || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $params = array(
                'domain_id' => $domain_id,
                'teacher_num' => $teacher_num, //老师的显示数量
                'limit_num' => 0,
                'orderby' => 'open_start_time asc',
                'no_cache' => 1,
                'course_type' => 1, //0=在线课程，1表示面授，-1表示全部
            );
            $db_info = self::getLessonInfo($params, $debug_mode);
            $sult = array();
            $group_info = Yii::$app->params['indexLessonGroup'];
            //获取大学的组织ID信息S
            $p_special_orgnization_id = Yii::$app->params['indexLessonSpecialOrgnizationId'];
            $special_orgnization_id = array();
            if ($p_special_orgnization_id) {
                if (is_array($p_special_orgnization_id)) {
                    foreach ($p_special_orgnization_id as $a_orgnization_id) {
                        $tmp_info = BoeBaseService::getTreeSubId('FwOrgnization', $a_orgnization_id, 1);
                        if (is_array($tmp_info) && $tmp_info) {
                            $special_orgnization_id = array_merge($special_orgnization_id, $tmp_info);
                        }
                    }
                } else {
                    $special_orgnization_id = BoeBaseService::getTreeSubId('FwOrgnization', $p_special_orgnization_id, 1);
                }
            }
            //获取大学的组织ID信息E
            $tmp_group_info = array();
            $tmp_work_place = array();
            $tmp_special_info = array();

            if ($db_info && is_array($db_info)) {//整理出分组S
                //读取用户信息S
                $tmp_user_id = array();
                foreach ($db_info as $key => $a_info) {
                    if (!isset($tmp_user_id[$a_info['created_by']])) {
                        $tmp_user_id[$a_info['created_by']] = $a_info['created_by'];
                    }
                }
                $query = (new Query())->from(FwUser::realTableName());
                $query->select('kid,work_place,company_id,orgnization_id');
                $query->where(array(
                    'and',
                    array('in', 'kid', $tmp_user_id),
                    array('=', 'is_deleted', 0),
                ));
                $query->indexBy('kid');
                $user_info = $query->all();
                $user_sql = $query->createCommand()->getRawSql();
                //读取用户信息E
                //合并出相应的分组信息中去

                foreach ($db_info as $key => $a_info) {
                    $u_id = $tmp_user_id[$a_info['created_by']];
                    if (!isset($user_info[$u_id])) {
                        unset($db_info[$key]);
                    } else {//开始整理S
                        $a_info['work_place'] = $db_info[$key]['work_place'] = $work_place = $user_info[$u_id]['work_place'];
                        $a_info['company_id'] = $db_info[$key]['company_id'] = $company_id = $user_info[$u_id]['company_id'];
                        $a_info['orgnization_id'] = $db_info[$key]['orgnization_id'] = $orgnization_id = $user_info[$u_id]['orgnization_id'];
                        if (!isset($tmp_group_info[$work_place])) {
                            $tmp_group_info[$work_place] = array();
                        }
                        $a_info['work_place_text'] = $db_info[$key]['work_place_text'] = self::getWorkPlaceName($work_place['work_place'], $company_id);
                        $tmp_group_info[$work_place][$key] = $a_info;

                        if ($work_place == $work_place_id) {//当前特定的所在地
                            $tmp_work_place[$key] = $a_info;
                        }

                        if (in_array($orgnization_id, $special_orgnization_id)) {//特定的组织
                            $tmp_special_info[$key] = $a_info;
                        }
                    }//开始整理E
                }
                unset($user_info);
            }//整理出分组E
            if ($debug_mode) {
//               BoeBase::debug($sult);
//                BoeBase::debug($special_orgnization_id);
//                BoeBase::debug($tmp_work_place);
//                BoeBase::debug($tmp_special_info);
//                BoeBase::debug($tmp_group_info, 1);
            } else {
                if ($cache_time) {
                    Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
                }
            }
        } else {
//            BoeBase::debug(__METHOD__);
//            BoeBase::debug("From Cache!");
        }
        foreach ($group_info as $gkey => $a_group) {
            $sult[$gkey] = array();
            $is_all = !empty($a_group['is_all']) ? 1 : 0;
            $sult[$gkey]['more_level'] = $a_group['more_level'];
            $sult[$gkey]['lesson'] = array();
            if ($is_all) {
                $sult[$gkey]['lesson'] = $tmp_special_info;

                $tmp_count = count($sult[$gkey]['lesson']);
                $diff = $limit - $tmp_count;
                if ($diff > 0) {//不足补全,以当前区域的信息补全S
                    $tmp_keys = array_keys($sult[$gkey]['lesson']);
                    $i = 0;
                    foreach ($tmp_work_place as $w_key => $a_tmp_info) {
                        if ($i < $diff && !in_array($w_key, $tmp_keys)) {
                            $sult[$gkey]['lesson'][$w_key] = $a_tmp_info;
                            $i++;
                        }
                    }
                }//不足补全,以当前区域的信息补全E
				return $sult;

                $tmp_count = count($sult[$gkey]['lesson']);
                $diff = $limit - $tmp_count;
                if ($diff > 0) {//不足补全,以当前区域和特定区域之外的的信息补全S
                    $tmp_keys = array_keys($sult[$gkey]['lesson']);
                    $i = 0;
                    foreach ($db_info as $w_key => $a_tmp_info) {
                        if ($i < $diff && !in_array($w_key, $tmp_keys)) {
                            $sult[$gkey]['lesson'][$w_key] = $a_tmp_info;
                            $i++;
                        }
                    }
                }//不足补全,以当前区域和特定区域之外的的信息补全E
                $tmp_count = count($sult[$gkey]['lesson']);
                if ($tmp_count > $limit) {
                    $sult[$gkey]['lesson'] = array_slice($sult[$gkey]['lesson'], 0, $limit);
                }
            } else {
                if ($a_group['more_level']) {
                    foreach ($a_group['work_place'] as $nkey => $w_p_id) {
                        $sult[$gkey]['lesson'][$nkey] = self::indexLessArrayMerge($tmp_group_info, $w_p_id, $limit);
                        //BoeBase::debug(__METHOD__.$gkey.'lesson--'.$nkey.'ArrayLength:'.count($sult[$gkey]['lesson'][$nkey]));
                    }
                } else {
                    $sult[$gkey]['lesson'] = self::indexLessArrayMerge($tmp_group_info, $a_group['work_place'], $limit);
                    // BoeBase::debug(__METHOD__.$gkey.'lesson--ArrayLength:'.count($sult[$gkey]['lesson'][$nkey]));
                }
            }
        }
//          BoeBase::debug($sult,1);
        return $sult;
    }
	
	

    /**
     * 读取在首页的开班信息
     */
    static function getIndexLessonInfo($domain_id = 0, $work_place_id = '', $limit = 6, $teacher_num = 3, $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5('_domain_id_' . serialize($domain_id) . '_work_place_' . $work_place_id . '_limit_' . $limit . '_teacher_num' . $teacher_num);
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E  
        $debug_mode = $debug || self::isDebugMode();
        if ($debug_mode || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $params = array(
                'domain_id' => $domain_id,
                'teacher_num' => $teacher_num, //老师的显示数量
                'limit_num' => 0,
                'orderby' => 'open_start_time asc',
                'no_cache' => 1,
                'course_type' => 1, //0=在线课程，1表示面授，-1表示全部
            );
            $db_info = self::getLessonInfo($params, $debug_mode);
            $sult = array();
            $group_info = Yii::$app->params['indexLessonGroup'];
            //获取大学的组织ID信息S
            $p_special_orgnization_id = Yii::$app->params['indexLessonSpecialOrgnizationId'];
            $special_orgnization_id = array();
            if ($p_special_orgnization_id) {
                if (is_array($p_special_orgnization_id)) {
                    foreach ($p_special_orgnization_id as $a_orgnization_id) {
                        $tmp_info = BoeBaseService::getTreeSubId('FwOrgnization', $a_orgnization_id, 1);
                        if (is_array($tmp_info) && $tmp_info) {
                            $special_orgnization_id = array_merge($special_orgnization_id, $tmp_info);
                        }
                    }
                } else {
                    $special_orgnization_id = BoeBaseService::getTreeSubId('FwOrgnization', $p_special_orgnization_id, 1);
                }
            }
            //获取大学的组织ID信息E
            $tmp_group_info = array();
            $tmp_work_place = array();
            $tmp_special_info = array();
			$tmp_special_creator = array('BBA65A4A-1378-D614-73D5-C8C0BBA7CC89');//特定创建者

            if ($db_info && is_array($db_info)) {//整理出分组S
                //读取用户信息S
                $tmp_user_id = array();
                foreach ($db_info as $key => $a_info) {
                    if (!isset($tmp_user_id[$a_info['created_by']])) {
                        $tmp_user_id[$a_info['created_by']] = $a_info['created_by'];
                    }
                }
                $query = (new Query())->from(FwUser::realTableName());
                $query->select('kid,work_place,company_id,orgnization_id');
                $query->where(array(
                    'and',
                    array('in', 'kid', $tmp_user_id),
                    array('=', 'is_deleted', 0),
                ));
                $query->indexBy('kid');
                $user_info = $query->all();
                $user_sql = $query->createCommand()->getRawSql();
                //读取用户信息E
                //合并出相应的分组信息中去

                foreach ($db_info as $key => $a_info) {
                    $u_id = $tmp_user_id[$a_info['created_by']];
                    if (!isset($user_info[$u_id])) {
                        unset($db_info[$key]);
                    } else {//开始整理S
                        $a_info['work_place'] = $db_info[$key]['work_place'] = $work_place = $user_info[$u_id]['work_place'];
                        $a_info['company_id'] = $db_info[$key]['company_id'] = $company_id = $user_info[$u_id]['company_id'];
                        $a_info['orgnization_id'] = $db_info[$key]['orgnization_id'] = $orgnization_id = $user_info[$u_id]['orgnization_id'];
                        if (!isset($tmp_group_info[$work_place])) {
                            $tmp_group_info[$work_place] = array();
                        }
                        $a_info['work_place_text'] = $db_info[$key]['work_place_text'] = self::getWorkPlaceName($work_place['work_place'], $company_id);
                        $tmp_group_info[$work_place][$key] = $a_info;

                        if ($work_place == $work_place_id) {//当前特定的所在地
                            $tmp_work_place[$key] = $a_info;
                        }

                        if (in_array($orgnization_id, $special_orgnization_id) || in_array($u_id, $tmp_special_creator)) {//特定的组织
                            $tmp_special_info[$key] = $a_info;
                        }
                    }//开始整理E
                }
                unset($user_info);
            }//整理出分组E
            if ($debug_mode) {
//               BoeBase::debug($sult);
//                BoeBase::debug($special_orgnization_id);
//                BoeBase::debug($tmp_work_place);
//                BoeBase::debug($tmp_special_info);
//                BoeBase::debug($tmp_group_info, 1);
            } else {
                if ($cache_time) {
                    Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
                }
            }
        } else {
//            BoeBase::debug(__METHOD__);
//            BoeBase::debug("From Cache!");
        }
        foreach ($group_info as $gkey => $a_group) {
            $sult[$gkey] = array();
            $is_all = !empty($a_group['is_all']) ? 1 : 0;
            $sult[$gkey]['more_level'] = $a_group['more_level'];
            $sult[$gkey]['lesson'] = array();
            if ($is_all) {
                $sult[$gkey]['lesson'] = $tmp_special_info;

                $tmp_count = count($sult[$gkey]['lesson']);
                $diff = $limit - $tmp_count;
                if ($diff > 0) {//不足补全,以当前区域的信息补全S
                    $tmp_keys = array_keys($sult[$gkey]['lesson']);
                    $i = 0;
                    foreach ($tmp_work_place as $w_key => $a_tmp_info) {
                        if ($i < $diff && !in_array($w_key, $tmp_keys)) {
                            $sult[$gkey]['lesson'][$w_key] = $a_tmp_info;
                            $i++;
                        }
                    }
                }//不足补全,以当前区域的信息补全E

                $tmp_count = count($sult[$gkey]['lesson']);
                $diff = $limit - $tmp_count;
                if ($diff > 0) {//不足补全,以当前区域和特定区域之外的的信息补全S
                    $tmp_keys = array_keys($sult[$gkey]['lesson']);
                    $i = 0;
                    foreach ($db_info as $w_key => $a_tmp_info) {
                        if ($i < $diff && !in_array($w_key, $tmp_keys)) {
                            $sult[$gkey]['lesson'][$w_key] = $a_tmp_info;
                            $i++;
                        }
                    }
                }//不足补全,以当前区域和特定区域之外的的信息补全E
                $tmp_count = count($sult[$gkey]['lesson']);
                if ($tmp_count > $limit) {
                    $sult[$gkey]['lesson'] = array_slice($sult[$gkey]['lesson'], 0, $limit);
                }
            } else {
                if ($a_group['more_level']) {
                    foreach ($a_group['work_place'] as $nkey => $w_p_id) {
                        $sult[$gkey]['lesson'][$nkey] = self::indexLessArrayMerge($tmp_group_info, $w_p_id, $limit);
                        //BoeBase::debug(__METHOD__.$gkey.'lesson--'.$nkey.'ArrayLength:'.count($sult[$gkey]['lesson'][$nkey]));
                    }
                } else {
                    $sult[$gkey]['lesson'] = self::indexLessArrayMerge($tmp_group_info, $a_group['work_place'], $limit);
                    // BoeBase::debug(__METHOD__.$gkey.'lesson--ArrayLength:'.count($sult[$gkey]['lesson'][$nkey]));
                }
            }
        }
//          BoeBase::debug($sult,1);
        return $sult;
    }

    private static function indexLessArrayMerge($group_info, $id_array, $limit = 0) {
        $sult = array();
        foreach ($group_info as $key => $a_info) {
            if (in_array($key, $id_array)) {
                $sult = array_merge($sult, $a_info);
            }
        }
        if ($limit) {
            $sult = array_slice($sult, 0, $limit);
        }
        return $sult;
    }

    /**
     * 读取最新课程
     */
    static function get_new_lessinfo($domain_id = 0, $limit = 8, $teacher_num = 0, $course_type = -1, $debug = 0) {
        $get_new_lessinfo_params = array(
            'domain_id' => $domain_id,
            'teacher_num' => $teacher_num, //老师的显示数量,为0就不显示任何老师的信息
            'limit_num' => $limit, //数据数量
            'course_type' => $course_type, //0=在线课程，1表示面授，-1表示全部
            'order_by' => 'created_at desc',
            'ignore_open_start_time' => 1,
        );
        $debug_mode = $debug || self::isDebugMode();
        return self::getLessonInfo($get_new_lessinfo_params, $debug_mode);
    }

    /**
     * 读取最热课程
     */
    static function get_hot_lessinfo($domain_id = 0, $limit = 8, $teacher_num = 0, $course_type = -1, $debug = 0) {
        $get_hot_lessinfo_params = array(
            'domain_id' => $domain_id,
            'teacher_num' => $teacher_num, //老师的显示数量,为0就不显示任何老师的信息
            'limit_num' => $limit, //数据数量
            'course_type' => $course_type, //0=在线课程，1表示面授，-1表示全部
            'order_by' => 'register_number desc', //根据注册数量注册量，倒序，也可用这些字段以下字段
            'ignore_open_start_time' => 1,
                //(学习量:learned_number desc)
                //(评价量:rated_number desc)
                //(报名成功量:enroll_number desc)
                //(访问量:visit_number desc)
        );
        $debug_mode = $debug || self::isDebugMode();
        return self::getLessonInfo($get_hot_lessinfo_params, $debug_mode);
    }

    /**
     * 读取在首页推荐的分类和新闻列表
     * getIndexNewsListFromHotCategory
     * @param type $limit
     * @param type $all_news_no_cate_info //同一新闻是否在分类中已经显示了，是否还要在全部新闻中显示=1表示不显示，0表示显示
     * @return type
     */
    static function getIndexNewsListFromHotCategory($limit = 9, $all_news_no_cate_info = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit . '_all_news_no_cate_info_' . $all_news_no_cate_info;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S 
            $sult = array();
            $sult['all'] = array(
                'cate_id' => 0,
                'cate_name' => Yii::t('boe', 'boe_all_news_tag'),
                'info_list' => NULL,
            );

            $get_recommon_cate_p = array(
                'condition' => array(
                    'index_sort' => '>0',
                ),
                'orderby' => array(
                    'index_sort' => 'asc',
                ),
                'limit' => 0
            );
            $cate_info = self::initDb('BoeNewsCategory')->getList($get_recommon_cate_p);
            $tmp_kid = array();
            if ($cate_info && is_array($cate_info)) {
                // $tmp_kid = array_keys($sult['all']['info_list']); 
                foreach ($cate_info as $key => $a_cate) {
                    // BoeBase::debug($a_cate);
                    $sult['cate_' . $key] = array(
                        'cate_id' => $a_cate['kid'],
                        'cate_name' => $a_cate['name'],
                        'info_list' => self::getRecommendNewsInfo($a_cate['kid'], $limit, 1, $tmp_kid),
                    );
                    $tmp_kid = array_merge($tmp_kid, $sult['cate_' . $key]['info_list']);
                }
            }
            if ($all_news_no_cate_info) {//所有的新闻中不包括分类中已经显示的新闻
                $sult['all']['info_list'] = self::getRecommendNewsInfo(0, $limit, 1, $tmp_kid);
            } else {
                $sult['all']['info_list'] = self::getRecommendNewsInfo(0, $limit, 1);
            }
            //BoeBase::debug($sult, 1);
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
//      BoeBase::debug($sult,1);
        return $sult;
    }

    /**
     * 获取某个分类在首页的推荐信息
     * @param type $cate_id
     * @param type $limit
     */
    private static function getRecommendNewsInfo($cate_id = '', $limit = 9, $fill = 0, $not_kid = NULL, $debug = 0) {
        $params = array(
            'condition' => array(
                'base' => array('>', 'recommend_sort1', 0)
            ),
            'orderBy' => 'recommend_sort1 asc,updated_at desc',
            'indexby' => 'kid',
            'returnTotalCount' => 1,
            'limit' => $limit,
            'debug' => $debug,
        );

        if ($not_kid && is_array($not_kid)) {
            $params['condition']['not_kid'] = array('not in', 'kid', $not_kid);
        }
        if ($cate_id) {//分类搜索
            $tmp_arr = self::initDb('BoeNewsCategory')->getSubId($cate_id, 1); //找出分类对应子子孙孙的ID
            if ($tmp_arr) {
                $params['condition']['cate'] = array('in', 'category_id', $tmp_arr);
            }
            $tmp_arr = NULL;
        }
        if ($debug) {
            BoeBase::debug($params);
        }
        $dbData = self::initDb('BoeNews')->getList($params);
        $sult = isset($dbData['list']) && is_array($dbData['list']) ? $dbData['list'] : array();
        if ($fill && isset($dbData['totalCount']) && $dbData['totalCount'] < $limit) {
            $params['condition']['base'] = array('not in', 'kid', array_keys($sult));
            $params['limit'] = $limit - $dbData['totalCount'];
            $dbData = self::initDb('BoeNews')->getList($params);
            $tmp_sult = isset($dbData['list']) && is_array($dbData['list']) ? $dbData['list'] : array();
            $sult = array_merge($sult, $tmp_sult);
            $tmp_sult = NULL;
        }
        $dbData = NULL;
        return self::parseNewsList($sult);
    }

    /**
     * 获取某个分类在首页的推荐信息
     * @param type $cate_id
     * @param type $limit
     */
    static function getLoginNewsInfo($limit = 9) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时
            $params = array(
                'orderBy' => 'recommend_sort1 asc,updated_at desc',
                'indexby' => 'kid',
                'returnTotalCount' => 1,
                'limit' => $limit,
                    //'debug'=>1,
            );
            $sult = array();
            $dbData = self::initDb('BoeNews')->getList($params);
            if ($dbData['list']) {
                $sult = self::parseNewsList($dbData['list']);
                $dbData['list'] = NULL;
            }

            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }

        return $sult;
    }

    private static function parseNewsList($news_list) {
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
            $a_info['front_url'] = Yii::$app->urlManager->createUrl(['boe/news/detail', 'id' => $a_info['kid']]);
            $a_info['cate_name'] = self::initDb('BoeNewsCategory')->getInfo($a_info['category_id'], 'name');
            $a_info['update_time'] = date("Y-m-d H:i:s", $a_info['updated_at']);
            $a_info['update_day'] = date("Y-m-d", $a_info['updated_at']);
            $a_info['update_base_day'] = date("m-d", $a_info['updated_at']);
            $a_info['create_time'] = date("Y-m-d H:i:s", $a_info['created_at']);
            $a_info['create_day'] = date("Y-m-d", $a_info['created_at']);
            $a_info['create_base_day'] = date("m-d", $a_info['created_at']);

            if (!$a_info['cate_name']) {
                $a_info['cate_name'] = Yii::t('boe', 'news_category_error');
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

    public static function getNewsDetail($id = '', $show_ad = 0) {
        $cache_mode = self::isNoCacheMode() ? 1 : 0;
        if (DIRECTORY_SEPARATOR == "\\") {
            $cache_mode = 1;
        }
        $info = self::initDb('BoeNews')->getInfo($id, '*', $cache_mode);
        if ($info) {
            if ($info['image_url']) {
                $info['image_url'] = BoeBase::getFileUrl($info['image_url'], 'news');
            } else {
                $info['image_url'] = $show_ad ? self::getBoeNewsDetailAd() : '';
            }
            $parse_info = self::parseNewsList(array($info));
            return current($parse_info);
        }
        return NULL;
    }

    private static function boeExpirsTimeCheck() {
        
    }

    /**
     * boeConfigInit 读取BoeSystemConfig的配置信息时进行初始化
     */
    private static function boeConfigInit() {
        self::boeExpirsTimeCheck();
        if (self::isNoCacheMode()) {//清理缓存
            if (!isset(self::$initedLog['boeConfig'])) {
                self::initDb('BoeSystemConfig')->getAll(1);
                self::$initedLog['boeConfig'] = 1;
            }
        }
    }

    /**
     * 获取头部的通知公告
     * @return array();
     */
    static function getTopNotice($limit_num = 10, $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $notice_kid = Yii::$app->params['boeTopNoticeKid'];
        $cache_name = __METHOD__ . '_limit_num_' . $limit_num;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        $sult = NULL;
        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时
            $params = array(
                'condition' => array(
                    array('category_id' => $notice_kid)
                ),
                'orderBy' => 'recommend_sort1 asc,updated_at desc',
                'indexby' => 'kid',
                'returnTotalCount' => 1,
                'limit' => $limit_num,
                    //'debug'=>1,
            );
            $sult = array();
            //return $params;
            $dbData = self::initDb('BoeNews')->getList($params);
            if ($dbData['list']) {
                $sult = self::parseNewsList($dbData['list']);
                $dbData['list'] = NULL;
            }
            if ($debug) {
                BoeBase::debug($sult, 1);
            }
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }
        return $sult;
    }

    /**
     * 获取在轮播图片信息 
     * @return type
     */
    static function getBoeSliderInfo() {
        self::boeConfigInit();
        $config = self::initDb('BoeSystemConfig')->getInfo('slider_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'systemConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取头部导航信息
     * @return type
     */
    static function getBoeHeaderNav($active_key = '') {
        $tmp_info = Yii::$app->params['boeFrontHeaderNav'];
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
                    }
                } else {
                    $sult[$key]['tips_text'] = Yii::t('boe', $a_info['tips_key']);
                }

                if (!$has_actived_tag) {
                    if ($active_key) {
                        if (!empty($a_info['active_key'])) {
                            $sult[$key]['is_active'] = $a_info['active_key'] == $active_key ? 1 : 0;
                        }
                        //   BoeBase::debug(__METHOD__ . '$active_key:' . $sult[$key]['is_active'], 1);
                        if ($sult[$key]['is_active']) {
                            $has_actived_tag = 1;
                        }
                    } else {
                        if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == $sult[$key]['url']) {
                            $sult[$key]['is_active'] = 1;
                            $has_actived_tag = 1;
                        }
                        //  $has_actived_tag = $sult[$key]['is_active'];
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
        // BoeBase::debug(__METHOD__ . var_export($sult, true),1);
        return $sult;
    }

    /**
     * 获取站点的Logo
     * @return type
     */
    static function getBoeLogo() {
        self::boeConfigInit();
        $tmp_logo = self::initDb('BoeSystemConfig')->getInfo('site_logo', 'content');
        return BoeBase::getFileUrl($tmp_logo, 'systemConfig');
    }

    /**
     * 获取资讯detail的AD
     * @return type
     */
    static function getBoeNewsDetailAd() {
        self::boeConfigInit();
        $tmp_logo = self::initDb('BoeSystemConfig')->getInfo('news_detail_ad', 'content');
        return BoeBase::getFileUrl($tmp_logo, 'systemConfig');
    }

    /**
     * 获取站点的底部内容
     * @return type
     */
    static function getBoeFootContent() {
        self::boeConfigInit();
        return self::initDb('BoeSystemConfig')->getInfo('foot_content', 'content');
    }

    /**
     * 获取站点的课程推荐信息
     * @return type
     */
    static function getBoeRecommendLessonInfo($domain_id = 0) {
        $cache_time = intval(Yii::$app->params['boeIndexCacheTime']);
        $sult = NULL;
        self::boeConfigInit();
        $dbInfo = self::initDb('BoeSystemConfig')->getInfo('lesson_recommend_info', 'content');

        $dbInfoMd5 = md5(serialize($dbInfo));
        $cache_mode = $cache_time && !self::isNoCacheMode() ? true : false;
        $cache_name = __METHOD__ . md5(serialize($domain_id));
        $cache_name2 = "lesson_recommend_info_md5";

        if ($cache_mode) {//有缓存的模式时
            $sult = Yii::$app->cache->get($cache_name);
            $cacheInfoMd5 = Yii::$app->cache->get($cache_name2);
            if ($cacheInfoMd5 !== $dbInfoMd5) {//配置的信息发生了变更，缓存无效
                $sult = NULL;
            }
        }
        if (!$sult || !is_array($sult)) {
            $sult = array();
            if (is_array($dbInfo)) {
                $kid_info = array();
                foreach ($dbInfo as $key => $a_info) {//拼接出基本信息S
                    $a_info['kid'] = trim($a_info['kid']);
                    if ($a_info['kid'] && !isset($kid_info[$a_info['kid']])) {
                        $kid_info[$a_info['kid']] = "{$a_info['kid']}";
                        $sult[$a_info['kid']] = array(
                            'lesson_description' => $a_info['lesson_description'],
                            'url' => Yii::$app->urlManager->createUrl(['resource/course/view', 'id' => $a_info['kid']]),
                            'image_url' => '',
                            'type_text' => '',
                        );
                    }
                }//拼接出基本信息E

                if ($kid_info) {
                    $ln_table_name = LnCourse::realTableName();
                    $domain_table_name = LnResourceDomain::realTableName();
                    $select_field = array(
                        'course_name', 'category_id', 'theme_url', 'kid', 'course_type'
                    );
                    $select_field_str = array();
                    foreach ($select_field as $a_info) {
                        $select_field_str[] = "{$ln_table_name}.{$a_info} as {$a_info}";
                    }
                    $select_field_str = implode(',', $select_field_str);

                    $query = (new Query())->from($ln_table_name)
                            ->select($select_field_str)
                            ->orderBy($ln_table_name . '.start_time desc')
                            ->limit($limit)->indexBy('kid')
                            ->andFilterWhere(array('in', $ln_table_name . '.kid', $kid_info))
                            ->andFilterWhere(array('=', $ln_table_name . '.is_deleted', 0));
                    if ($domain_id) {//指定了域信息时,先找出对应域信息S                    
                        $query->distinct();
                        $query->join('INNER JOIN', $domain_table_name, "{$domain_table_name}.resource_id={$ln_table_name}.kid");
                        $query->andFilterWhere(array('=', $domain_table_name . '.is_deleted', 0));
                        $query->andFilterWhere(array('=', $domain_table_name . '.resource_type', 1));
                        if (is_array($domain_id)) {
                            $query->andFilterWhere(array('in', $domain_table_name . '.domain_id', $domain_id));
                        } else {
                            $query->andFilterWhere(array('=', $domain_table_name . '.domain_id', $domain_id));
                        }
                    }
                    $ln_db_info = $query->all();
                    $command = $query->createCommand();
                    foreach ($sult as $key => $a_info) {
                        if (isset($ln_db_info[$key])) {
                            $type_lang_key = $ln_db_info[$key]['course_type'] == 1 ? 'face-to-face' : 'online';
                            $sult[$key]['image_url'] = $ln_db_info[$key]['theme_url'];
                            $sult[$key]['type_text'] = Yii::t('common', $type_lang_key);
                            $sult[$key]['course_name'] = $ln_db_info[$key]['course_name'];
                        } else {
                            unset($sult[$key]);
                        }
                    }
                }
            }
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
                Yii::$app->cache->set($cache_name2, $dbInfoMd5, $cache_time); // 设置关键变更信息的缓存
            }
        }
        return $sult;
    }

    /**
     * 获取首页的微专区
     * @return type
     */
    static function getBoeMicroArea() {
        self::boeConfigInit();
        $config = self::initDb('BoeSystemConfig')->getInfo('micro_area_info', 'content');
        if (is_array($config)) {
            foreach ($config as $key => $a_info) {
                if (isset($a_info['image_info'])) {
                    $config[$key]['image_url'] = BoeBase::getFileUrl($a_info['image_info'], 'systemConfig');
                }
            }
        }
        return $config;
    }

    /**
     * 获取站点的Logo
     * @return type
     */
    static function getBoeLoginBackgroundImage() {
        self::boeConfigInit();
        $tmp_logo = self::initDb('BoeSystemConfig')->getInfo('login_background_image', 'content');
        return BoeBase::getFileUrl($tmp_logo, 'systemConfig');
    }

    /**
     * 根据用户ID获取其对应的工作地
     * @param type $user_id $string or array 
     */
    static function getUserWorkPlaceName($user_id) {
        if (!isset(self::$loadedObject['UserModelObject'])) {
            self::$loadedObject['UserModelObject'] = new FwUser();
        }
        $model = self::$loadedObject['UserModelObject']->find(false);
        $model->select('kid,company_id,work_place');
        $model->andFilterWhere(array('in', 'kid', $user_id));
        $model->indexBy('kid');
        $user_info = $model->asArray()->all();
        if ($user_info && is_array($user_info)) {
            foreach ($user_info as $key => $a_info) {
                $user_info[$key]['work_place_text'] = self::getWorkPlaceName($a_info['work_place'], $a_info['company_id']);
            }
        }
        return !is_array($user_info) ? array() : $user_info;
    }

    /**
     * 根据用户的企业ID和work_place获取对应的名称
     * @return type
     */
    static function getWorkPlaceName($work_place = 0, $company_id = 0) {
        $key = "work_plac_{$work_place}_company_id_{$company_id}";
        if (!isset(self::$initedLog[$key])) {
            if (!isset(self::$loadedObject['DictionaryService'])) {
                self::$loadedObject['DictionaryService'] = new DictionaryService();
            }
            self::$initedLog[$key] = self::$loadedObject['DictionaryService']->getDictionaryNameByValue("work_place", $work_place, $company_id, false);
            if (empty(self::$initedLog[$key])) {
                self::$initedLog[$key] = $work_place;
            }
        }
        return self::$initedLog[$key];
    }

    /**
     * 学习之星排行版
      /*
     * 参与语句
      SELECT
      count(DISTINCT course_id) AS num,  eln_ln_course_complete.user_id
      ,eln_fw_user.work_place,  eln_fw_user.real_name,  eln_fw_user.nick_name, eln_fw_user.user_name,  eln_fw_user.company_id
      FROM  eln_ln_course_complete   INNER JOIN eln_fw_user ON eln_fw_user.kid = eln_ln_course_complete.user_id
      WHERE
      eln_ln_course_complete.is_deleted = '0'
      AND eln_ln_course_complete.complete_type = '1'
      AND (
      eln_ln_course_complete.complete_status = '2'
      OR eln_ln_course_complete.is_retake = '1'
      )
      GROUP BY  user_id   ORDER BY  num DESC   limit 100
     * @param type $limit
     * @return type
     */
    static function getStarOfStudyRanking($limit = 10, $domain_info = '', $company_id = '', $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        if (!$limit) {
            $limit = 100;
        }
        $cache_name = md5(__METHOD__ . 'domain_info_' . serialize($domain_info) . '_company_id_' . serialize($company_id) . '_limit_' . $limit);
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 

        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $sult = array();
            $u_table_name = FwUser::realTableName();
            $c_table_name = LnCourseComplete::realTableName();
            $query = new Query();
            $query->from($c_table_name);
            $query->select("count(distinct {$c_table_name}.course_id) as study_num,"
                    . "{$c_table_name}.user_id as user_id,"
                    . " {$u_table_name}.real_name as real_name,"
                    . " {$u_table_name}.nick_name as nick_name,"
                    . " {$u_table_name}.user_name as user_name,"
                    . " {$u_table_name}.work_place as work_place,"
                    . " {$u_table_name}.company_id as company_id"
            );
            $query->orderBy('study_num desc');
            if ($limit) {
                $query->limit($limit);
            }
            $query->join('INNER JOIN', $u_table_name, "{$u_table_name}.kid={$c_table_name}.user_id");
            $complete_type_where = array('or');
            $complete_type_where[] = array('=', $c_table_name . '.complete_status', '2');
            $complete_type_where[] = array('=', $c_table_name . '.is_retake', '1');

            $query->andFilterWhere(
                    array('and',
                        array('=', $c_table_name . '.is_deleted', '0'),
                        array('=', $c_table_name . '.complete_type', '1')
                    )
            );
            $query->andFilterWhere($complete_type_where);
            if ($domain_info) {//指定了域信息时S  
                $query->andFilterWhere(array(is_array($domain_info) ? 'in' : '=', $u_table_name . '.domain_id', $domain_info));
            }//指定了域信息时E
            if ($company_id) {//指定了公司信息时S  
                $query->andFilterWhere(array(is_array($company_id) ? 'in' : '=', $u_table_name . '.company_id', $company_id));
            }//指定了公司信息时S
            $query->groupBy(array('user_id'));
            $query->having(array('>', 'study_num', 0));
            $sult = $query->all();
            $command = $query->createCommand();

            if ($sult && is_array($sult)) {
                foreach ($sult as $key => $a_info) {
                    $sult[$key]['work_place_text'] = self::getWorkPlaceName($a_info['work_place'], $a_info['company_id']);
                }
            }
            if ($debug) {
                BoeBase::debug(__METHOD__ . "\nSql:\n" . $command->getRawSql() . "\nSult:\n" . var_export($sult, true), 1);
            }
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }

    /**
     * 问答之星排行版
      问答之星语句
      SELECT
      count(DISTINCT eln_so_answer.question_id) AS answer_num,
      `eln_so_answer`.`user_id` AS `user_id`,
      `eln_fw_user`.`real_name` AS `real_name`,
      `eln_fw_user`.`nick_name` AS `nick_name`,
      `eln_fw_user`.`user_name` AS `user_name`,
      `eln_fw_user`.`work_place` AS `work_place`,
      `eln_fw_user`.`company_id` AS `company_id`
      FROM
      eln_so_answer
      INNER JOIN eln_fw_user ON eln_fw_user.kid = eln_so_answer.user_id
      GROUP BY
      user_id
      ORDER BY
      answer_num DESC;
     * @param type $limit
     * @return type
     */
    static function getStarOfAnswerRanking($limit = 10, $domain_info = '', $company_id = '', $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        if (!$limit) {
            $limit = 100;
        }
        $cache_name = md5(__METHOD__ . 'domain_info_' . serialize($domain_info) . '_company_id_' . serialize($company_id) . '_limit_' . $limit);
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S 
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 

        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $sult = array();
            $u_table_name = FwUser::realTableName();
            $c_table_name = SoAnswer::realTableName();
            $query = new Query();
            $query->from($c_table_name);
            $query->select("count(distinct {$c_table_name}.question_id) as answer_num,"
                    . "{$c_table_name}.user_id as user_id,"
                    . " {$u_table_name}.real_name as real_name,"
                    . " {$u_table_name}.nick_name as nick_name,"
                    . " {$u_table_name}.user_name as user_name,"
                    . " {$u_table_name}.work_place as work_place,"
                    . " {$u_table_name}.company_id as company_id"
            );
            $query->orderBy('answer_num desc');
            if ($limit) {
                $query->limit($limit);
            }
            $query->join('INNER JOIN', $u_table_name, "{$u_table_name}.kid={$c_table_name}.user_id");
            $query->andFilterWhere(array('=', $c_table_name . '.is_deleted', '0'));
            if ($domain_info) {//指定了域信息时S  
                $query->andFilterWhere(array(is_array($domain_info) ? 'in' : '=', $u_table_name . '.domain_id', $domain_info));
            }//指定了域信息时E
            if ($company_id) {//指定了公司信息时S  
                $query->andFilterWhere(array(is_array($company_id) ? 'in' : '=', $u_table_name . '.company_id', $company_id));
            }//指定了公司信息时S
            $query->groupBy(array('user_id'));
            $query->having(array('>', 'answer_num', 0));
            $sult = $query->all();
            $command = $query->createCommand();

            if ($sult && is_array($sult)) {
                foreach ($sult as $key => $a_info) {
                    $sult[$key]['work_place_text'] = self::getWorkPlaceName($a_info['work_place'], $a_info['company_id']);
                }
            }
            if ($debug) {
                BoeBase::debug(__METHOD__ . "\nSql:\n" . $command->getRawSql() . "\nSult:\n" . var_export($sult, true), 1);
            }
            if ($cache_time) {
                $cache_sult = Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
//                BoeBase::debug(__METHOD__ . '$cache_time:' . $cache_time);
//                BoeBase::debug(__METHOD__ . '$cache_name:' . $cache_name);
//                BoeBase::debug(__METHOD__ . '$cache_sult:' . $cache_sult);
//                BoeBase::debug($sult, 1);
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }

    /**
     * 分享之星排行版
      分享之星语句
      SELECT
      sum( eln_boe_doc.share_num
      ) AS share_num,
      `eln_boe_doc`.`user_id` AS `user_id`,
      `eln_fw_user`.`real_name` AS `real_name`,
      `eln_fw_user`.`nick_name` AS `nick_name`,
      `eln_fw_user`.`user_name` AS `user_name`,
      `eln_fw_user`.`work_place` AS `work_place`,
      `eln_fw_user`.`company_id` AS `company_id`
      FROM
      eln_boe_doc
      INNER JOIN eln_fw_user ON eln_fw_user.kid = eln_boe_doc.user_id
      GROUP BY
      user_id
      ORDER BY
      share_num DESC;
     * @param type $limit
     * @return type
     */
    static function getStarOfShareRanking($limit = 10, $domain_info = '', $company_id = '', $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        if (!$limit) {
            $limit = 100;
        }
        $cache_name = md5(__METHOD__ . 'domain_info_' . serialize($domain_info) . '_company_id_' . serialize($company_id) . '_limit_' . $limit);

        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 

        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $sult = array();
            $u_table_name = FwUser::realTableName();
            $c_table_name = BoeDoc::realTableName();
            $query = new Query();
            $query->from($c_table_name);
            //    $query->select("sum({$c_table_name}.share_num) as share_num,"
            $query->select("count(*) as share_num,"
                    . "{$c_table_name}.user_id as user_id,"
                    . " {$u_table_name}.real_name as real_name,"
                    . " {$u_table_name}.nick_name as nick_name,"
                    . " {$u_table_name}.user_name as user_name,"
                    . " {$u_table_name}.work_place as work_place,"
                    . " {$u_table_name}.company_id as company_id"
            );
            $query->orderBy('share_num desc');
            if ($limit) {
                $query->limit($limit);
            }
            $query->join('INNER JOIN', $u_table_name, "{$u_table_name}.kid={$c_table_name}.user_id");
            $query->andFilterWhere(array('=', $c_table_name . '.is_deleted', '0'));
            if ($domain_info) {//指定了域信息时S  
                $query->andFilterWhere(array(is_array($domain_info) ? 'in' : '=', $u_table_name . '.domain_id', $domain_info));
            }//指定了域信息时E
            if ($company_id) {//指定了公司信息时S  
                $query->andFilterWhere(array(is_array($company_id) ? 'in' : '=', $u_table_name . '.company_id', $company_id));
            }//指定了公司信息时S
            $query->groupBy(array('user_id'));
            $query->having(array('>', 'share_num', 0));
            $sult = $query->all();
            $command = $query->createCommand();

            if ($sult && is_array($sult)) {
                foreach ($sult as $key => $a_info) {
                    $sult[$key]['work_place_text'] = self::getWorkPlaceName($a_info['work_place'], $a_info['company_id']);
                }
            }
            if ($debug) {
                BoeBase::debug(__METHOD__ . "\nSql:\n" . $command->getRawSql() . "\nSult:\n" . var_export($sult, true), 1);
            }
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }

    /**
     * 获取首页用Tag加列表的形式展示的文档信息
     * @param type $limit 每个 Tag列表文档信息的数量
     * @param type $no_repeated 同一个文档信息是否在不同的Tag列表框显示
     * @param type $order_info 排序信息
     * @param type $debug 是否debug
     * @return array(
      'kid1'=>array('cate_info'=>array(),'doc_list'=>array()),
      'kid2'=>array('cate_info'=>array(),'doc_list'=>array()),
      )
     */
    static function getIndexDocInfo($limit = 6, $no_repeated = 0, $order_info = 'visit_num desc,created_at desc', $debug = 0) {
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = md5(__METHOD__ . '_order_info:' . $order_info . '_limit_' . $limit . '_exclude_id_' . $no_repeated);
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        //$sult = NULL;
        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $sult = array();
            if (empty(self::$loadedObject['doc_category'])) {
                self::$loadedObject['doc_category'] = new BoeDocCategory();
            }
            $indexCate = self::$loadedObject['doc_category']->getIndexTagNav();

            if ($indexCate) {//有分类信息时S
                $doc_params = array(
                    'orderBy' => $order_info ? $order_info : 'visit_num desc,created_at desc',
                    'indexby' => 'kid',
                    'returnTotalCount' => 0,
                    'return_list' => 0,
                    'limit' => $limit,
                );
                $readedKid = array();
                // BoeBase::debug($indexCate, 1);
                foreach ($indexCate as $k => $a_info) {
                    $tmp_array = array('cate_info' => $a_info);
                    $doc_params['category_id'] = $a_info['kid'];
                    if ($no_repeated) {//不显示重复的数据S
                        $doc_params['exclude_id'] = $readedKid;
                    } else {
                        $doc_params['exclude_id'] = NULL;
                    }
                    $db_info = BoeDocService::getDocList($doc_params);
                    $tmp_array['doc_list'] = &$db_info['list'];
                    if ($tmp_array['doc_list']) {
                        if ($no_repeated) {
                            $readedKid = array_merge($readedKid, array_keys($tmp_array['doc_list']));
                        }
                        $tmp_array['doc_list'] = self::parseDocList($tmp_array['doc_list']);
                    }
                    $indexCate[$k]['query_params'] = $doc_params;
                    $indexCate[$k]['sql'] = $db_info['sql'];
                    $sult[$a_info['kid']] = $tmp_array;
                    $indexCate[$k]['list'] = &$db_info['list'];
                    $tmp_array = array();
                }
            }//有分类信息时E
            if ($debug) {
                BoeBase::debug(__METHOD__ . "\nSult:\n" . var_export($sult, true), 1);
            }
            //  BoeBase::debug($indexCate, 1);
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时S
        return $sult;
    }

    /**
     * 整理下得到的多维数组形式的文档列表
     * @param type $doc_list
     * @return type
     */
    private static function parseDocList($doc_list) {
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
        foreach ($doc_list as $key => $a_info) {
            $a_info['front_url'] = Yii::$app->urlManager->createUrl(['boe/doc/detail', 'id' => $a_info['kid']]);
            $a_info['cate_name'] = self::initDb('BoeDocCategory')->getInfo($a_info['category_id'], 'name');
            $a_info['update_time'] = date("Y-m-d H:i:s", $a_info['updated_at']);
            $a_info['update_day'] = date("Y-m-d", $a_info['updated_at']);
            $a_info['update_base_day'] = date("m-d", $a_info['updated_at']);
            $a_info['create_time'] = date("Y-m-d H:i:s", $a_info['created_at']);
            $a_info['create_day'] = date("Y-m-d", $a_info['created_at']);
            $a_info['create_base_day'] = date("m-d", $a_info['created_at']);

            if (!$a_info['cate_name']) {
                $a_info['cate_name'] = Yii::t('boe', 'doc_category_error');
            }
            foreach ($delete_key as $a_key) {
                if (isset($a_info[$a_key])) {
                    unset($a_info[$a_key]);
                }
            }
            $doc_list[$key] = $a_info;
        }
        return BoeDocService::parseDocList($doc_list, 0, 1);
    }

    /**
     * 读取课程信息
     */
    static function getLessonInfo($params = array(), $debug = 0) {
        $cache_arr = array();
        $cache_arr['domain_id'] = $domain_id = BoeBase::array_key_is_nulls($params, array('domain_id', 'domain_info', 'domainInfo', 'domainId'), NULL);
        $cache_arr['limit_num'] = $limit = BoeBase::array_key_is_numbers($params, array('limit', 'limit_num', 'limitNum'), 0);
        $cache_arr['teacher_num'] = $teacher_num = BoeBase::array_key_is_numbers($params, array('teacher_num', 'teacherNum'), 0);
        $cache_arr['course_type'] = $course_type = BoeBase::array_key_is_numbers($params, array('course_type', 'course_type'), -1);
        $cache_arr['course_period_text'] = $course_period_text = BoeBase::array_key_is_numbers($params, array('coursePeriodText', 'course_period_text'), 1);

        $cache_arr['ignore_open_start_time'] = $ignore_open_start_time = BoeBase::array_key_is_numbers($params, array('ignore_open_start_time', 'ignoreOpenStartTime', 'no_open_start_time', 'noOpenStartTime'));
        $cache_arr['orderby'] = $orderby = BoeBase::array_key_is_nulls($params, array('orderBy', 'order_by', 'orderby'), 'open_start_time desc');
        $no_cache = BoeBase::array_key_is_numbers($params, array('no_cache', 'noCache'));

        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $sult = NULL;
        if (!$no_cache && $cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
//            BoeBase::debug("Cache Sult:".var_export($sult,true),1);
        }//需要读取缓存信息时E 
        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $debug_text = array();
            $debug_text[] = __METHOD__;
            $ln_table_name = LnCourse::realTableName();
            /*$select_field = array(
                'course_name', 'category_id',
                'theme_url', 'kid', 'course_type',
                'start_time', 'end_time',
                'open_start_time', 'open_end_time',
                'course_period', 'course_period_unit', 'created_by'
            );*/
			$select_field = array(
                'course_name', 'course_code','category_id','course_desc',
                'theme_url', 'kid', 'course_type','course_level',
                'start_time', 'end_time','release_at','training_address',
                'open_start_time', 'open_end_time','default_credit',
                'course_period', 'course_period_unit', 'created_by','visit_number'
            );
            $select_field_str = array();
            foreach ($select_field as $a_info) {
                $select_field_str[] = "{$ln_table_name}.{$a_info} as {$a_info}";
            }
            $c_time = time();
            $select_field_str = implode(',', $select_field_str);
            $sult = array();
			$course_array	=array(
			  0=>'8901CC9F-0603-A323-93E4-27B3D62E3DA3',1=>'5FBDD0A6-08C6-CAFA-AAFD-B335EDB93627',
			  2=>'5FD51FF8-5ED3-FC65-87C4-36CC4B1F1F75',3=>'474DA91C-91E2-DFE7-F4A7-C3DA72C407C2',
			  4=>'1490CD18-52BE-39EA-8BE1-A837C15972E9',5=>'6FACE0A9-B299-2864-AA7A-B4A0425474C2',
			  6=>'63620CD0-9E2A-DE39-34EC-8FCD3A2212E8',7=>'79321D31-C8D8-3E72-0487-44CB5E232252',
			  8=>'BA00C537-9AEE-FB9C-B057-E212DAA3E62A',9=>'FCF239E1-4B2C-33D7-62B1-A4B55563FCFC',
			  10=>'728B56B5-01C6-1B78-4FD8-175E9D1551E3',11=>'049F3DCE-060B-3E39-2C75-821A0FF0BF32',
			  12=>'0A09997E-CF58-971D-978F-231D90E4A216',13=>'0319509E-2A4F-AF8E-D8C9-379E934AB261',
			  14=>'D26BA25F-2CF5-3510-FE2D-B6A96D451303',15=>'F6853685-CBE6-A48B-C7D5-B910AF7DA950',
			  16=>'A0F19DC7-4F11-A373-9CC9-50FA2CAB0C00',17=>'46961AE4-5F42-9977-BC9C-2AC31972205C',
			  18=>'AF41490C-3AE7-B9A3-8A9D-9362837EEAEC',19=>'5CDAFC7F-D641-FAE8-EC46-51FC11C12BD5',
			  20=>'8287C325-39B7-E471-C795-E5CE616C1D33',21=>'BDD87491-5DE0-2945-8514-A0FD2B1A2DF3',
			  22=>'1487FD8A-25EC-CED0-C988-1321D1696734',23=>'F6EAA4BF-4960-BDF8-6965-C3E3E3AB6FFD',			
			  );
            $base_where = array('and',
                array('=', $ln_table_name . '.is_deleted', 0),
                array('=', $ln_table_name . '.is_display_pc', LnCourse::DISPLAY_PC_YES),
                array('=', $ln_table_name . '.status', LnCourse::STATUS_FLAG_NORMAL),
                array('<=', $ln_table_name . '.start_time', $c_time),
				array('not in', $ln_table_name . '.kid', $course_array),
            );

            if (!$ignore_open_start_time) {
                $base_where[] = array('>', $ln_table_name . '.open_start_time', $c_time);
            }

            if ($course_type != -1) {
                $base_where[] = array('=', $ln_table_name . '.course_type', $course_type);
            }

            $time_where_p = array(
                'or',
                array('=', $ln_table_name . '.end_time', 0),
//                array('is', $ln_table_name . '.end_time',NULL), 
                 new Expression("{$ln_table_name}.end_time is null"),
                array('>=', $ln_table_name . '.end_time', $c_time),
            );
            $query = (new Query())->from($ln_table_name);
            if ($domain_id) {//指定了域信息时S  
                $domain_table_name = LnResourceDomain::realTableName();
                $query->distinct();
                $query->join('INNER JOIN', $domain_table_name, "{$domain_table_name}.resource_id={$ln_table_name}.kid");
                $base_where[] = array('=', $domain_table_name . '.is_deleted', 0);
                $base_where[] = array('=', $domain_table_name . '.resource_type', 1);
                $base_where[] = array(is_array($domain_id) ? 'in' : '=', $domain_table_name . '.domain_id', $domain_id);
            }//指定了域信息时E
            $query->select($select_field_str);
            $query->orderBy($ln_table_name . ".{$orderby}");
            $query->indexBy('kid');
            if ($limit) {
                $query->limit($limit);
            }
            $query->andFilterWhere($base_where);
             $query->andFilterWhere($time_where_p);
            //$where_p = "({$ln_table_name}.end_time=0 or  {$ln_table_name}.end_time is null  or {$c_time}<={$ln_table_name}.end_time)";
           // $query->where($where_p);
            $ln_db_info = $query->all();
            $command = $query->createCommand();
            if ($debug) {
                $debug_text[] = "$params:";
                $debug_text[] = var_export($params, true);
                $debug_text[] = "base_where:";
                $debug_text[] = var_export($base_where, true);
                $debug_text[] = "time_where_p:";
                $debug_text[] = var_export($time_where_p, true);
                $debug_text[] = "Get LessonInfo Sql:";
                $debug_text[] = $command->getRawSql();
                $debug_text[] = 'CacheArray:';
                $debug_text[] = var_export($cache_arr, true);
                $debug_text[] = "LessonInfo Sult:";
                $debug_text[] = var_export($ln_db_info, true);
            }
            $query = NULL;

            if ($ln_db_info && is_array($ln_db_info)) {//读取到了相关的课程信息S
                $teacher_info = $teacher_list = NULL;
                if ($teacher_num) {//需要读取出老师的信息时间S
                    $teacher_info = self::getCourseListTeacherInfo($ln_db_info, 1);
                    $teacher_list = &$teacher_info['teacher_list'];
                    if ($debug) {
                        $debug_text[] = "需要读取出老师的信息:";
                        $debug_text[] = "\tGet Teacher Sql:";
                        $debug_text[] = $teacher_info['teacher_sql'];
                        $debug_text[] = "\tTeacher Num:{$teacher_num}";
                        $debug_text[] = "\tTeacher list:";
                        $debug_text[] = var_export($teacher_list, true);
                    }
                }//需要读取出老师的信息时间E
                else {
                    $debug_text[] = "需要读取出老师的信息。";
                }

                foreach ($ln_db_info as $key => $a_info) {
                    $sult[$key] = self::parseOneLessonInfo($a_info, $teacher_num, $teacher_list, $course_period_text);
                }
            }//读取到了相关的课程信息E 
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        if ($debug) {
            $debug_text[] = "最终结果:";
            $debug_text[] = var_export($sult, true);
            BoeBase::debug(implode("\n", $debug_text), 1);
        }
        return $sult;
    }

    /**
     * 拼接相关的课程
     * @param type $a_info
     * @param type $teacher_num
     * @param type $teacher_list
     * @param type $course_period_text
     * @return string
     */
    private function parseOneLessonInfo($a_info, $teacher_num = 0, $teacher_list = NULL, $course_period_text = 0) {
        //拼接结果S
        if ($course_period_text) {
            if (!isset(self::$loadedObject['CourseModelObject'])) {
                self::$loadedObject['CourseModelObject'] = new LnCourse();
            }
        }
        if ($teacher_num) {
            $a_info['teacher_name'] = self::getCourseMoreTeacherName($a_info['kid'], $teacher_list, $teacher_num);
        }
        $a_info['url'] = Yii::$app->urlManager->createUrl(['resource/course/view', 'id' => $a_info['kid']]);
        $a_info['start_time_full'] = date("Y-m-d H:i:s", $a_info['start_time']);
        $a_info['start_time_day'] = date("Y-m-d", $a_info['start_time']);
        $a_info['start_time_base_day'] = date("m-d", $a_info['start_time']);
        if ($a_info['end_time']) {
            $a_info['end_time_full'] = date("Y-m-d H:i:s", $a_info['end_time']);
            $a_info['end_time_day'] = date("Y-m-d", $a_info['end_time']);
            $a_info['end_time_base_day'] = date("m-d", $a_info['end_time']);
        } else {
            $a_info['end_time_full'] = "";
            $a_info['end_time_day'] = "";
            $a_info['end_time_base_day'] = "";
        }
        $a_info['open_start_time_full'] = date("Y-m-d H:i:s", $a_info['open_start_time']);
        $a_info['open_start_time_day'] = date("Y-m-d", $a_info['open_start_time']);
        $a_info['open_start_time_base_day'] = date("m-d", $a_info['open_start_time']);
        if ($a_info['open_end_time']) {
            $a_info['open_end_time_full'] = date("Y-m-d H:i:s", $a_info['open_end_time']);
            $a_info['open_end_time_day'] = date("Y-m-d", $a_info['open_end_time']);
            $a_info['open_end_time_base_day'] = date("m-d", $a_info['open_end_time']);
        } else {
            $a_info['open_end_time_full'] = "";
            $a_info['open_end_time_day'] = "";
            $a_info['open_end_time_base_day'] = "";
        }
        if ($course_period_text) {
            $a_info['course_period_text'] = $a_info['course_period'] . self::$loadedObject['CourseModelObject']->getCoursePeriodUnits($a_info['course_period_unit']);
        }
        return $a_info;
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
        $base_where[] = array('in', $course_teacher_table_name . '.course_id', $kid_info);
        $base_where[] = array('=', $course_teacher_table_name . '.status', '1');
        $base_where[] = array('=', $course_teacher_table_name . '.is_deleted', '0');

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
    private static function getCourseTeacherList($c_id, $teach_link_info) {
        $sult = array();
        $i = 1;
        foreach ($teach_link_info as $a_teach_link_info) {
            if ($a_teach_link_info['course_id'] == $c_id) {
                $sult[] = $a_teach_link_info['teacher_name'];
            }
        }
        return $sult;
    }

    /**
     * 根据列表信息和课程ID得到相应的老师名称
     * @param type $c_id
     * @param type $teach_link_info
     * @param type $max_num 超过几个后，用等数字表达
     * @return type
     */
    private static function getCourseMoreTeacherName($c_id, $teach_link_info, $max_num = 3) {
        $sult = self::getCourseTeacherList($c_id, $teach_link_info);
        $count = count($sult);
        $other_info = '';
        if ($count > $max_num) {
            array_splice($sult, $max_num);
            $sult[] = str_replace('{count}', $count, Yii::t('boe', 'index_study_info_teacher_more'));
        }
        return $sult;
    }

    /**
     * 登录后跳转到Boe首页
     */
    static function loginGotoBoeIndexPage() {
        $referer = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
//        BoeBase::debug(__METHOD__);
//        BoeBase::debug($_SERVER);
        $a = $b = $c = $d = false;
        $b = Yii::$app->controller->id == 'site';
        $match_action = array('index', 'error', 'no-authority');
        $c = in_array(Yii::$app->controller->action->id, $match_action);
        $d = true;
        $userId = Yii::$app->user->getId();
        $boe_index_url = Yii::$app->urlManager->createUrl(array('/boe/news/index'));
        if ($referer) {
            $loginUrl = Yii::$app->urlManager->createUrl(array('/site/login'));
            $a = stripos($referer, $loginUrl) !== false ? true : false;
        }
        $isParticipator = ($userId && BoeWeilogService::checkUserIsParticipator(Yii::$app->user->getIdentity()));
//        $isParticipator = false;

        $log_dir = BoeWebRootDir . '/boe_doc_log/login';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir);
        }
        $host = BoeBase::array_key_is_nulls($_SERVER, 'HTTP_HOST', '');
        if ($host != 'u.boe.com') {
            $log_file = $log_dir . '/' . date("YmdH") . '.log';
            $log_content = "\n==========================" . date("Y-m-d H:i:s") . "==================================\n";
            $log_content.='$a=' . var_export($a, true) . "\n";
            $log_content.='$server=' . var_export($_SERVER, true) . "\n";
            $log_content.='$b=' . var_export($b, true) . "\n";
            $log_content.='$c=' . var_export($c, true) . "\n";
            $log_content.='$d=' . var_export($d, true) . "\n";
            $log_content.='$controller=' . Yii::$app->controller->id . "\n";
            $log_content.='$action=' . Yii::$app->controller->action->id . "\n";
            $log_content.='$isParticipator=' . var_export($isParticipator, true) . "\n";
            $log_content.='$current_user=' . var_export(Yii::$app->user->getIdentity(), true) . "\n";
            $log_content.="===========================================================================================================================\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
        }

        if ($a) {//是从login页面进来的 S
            if ($b && $c && $d) {
                $go_url = $boe_index_url;
                //特训营用户跳转到特训营首页S
                if ($isParticipator) {//当前是属于特训营用户
                    $go_url = Yii::$app->urlManager->createUrl(array('/boe/subject/index'));
                    //特训营用户跳转到特训营首页E 
                }
                header("location:{$go_url}");
                exit();
            }
        }//是从login页面进来的 E
        else {//已经登录后，从其它渠道进入大学其它页面时，针对特训营用户，不能提示权限不足的问题S
            if ($b && $c && $isParticipator) {
                header("location:{$boe_index_url}");
                exit();
            }
        } //已经登录后，从其它渠道进入大学其它页面时，针对特训营用户，不能提示权限不足的问题S
    }

}

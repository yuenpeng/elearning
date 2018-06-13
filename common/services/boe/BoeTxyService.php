<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\services\boe\BoeBaseService;
use common\services\boe\BoeTxyApiService;
use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\boe\BoeTxyNews;
use common\models\boe\BoeTxyNewsCategory;
use common\models\boe\BoeTxyStudentEvent;
use common\models\boe\BoeTxyStudentStatus;
use common\models\boe\BoeTxyStudentQuit;
use common\models\boe\BoeTxyEventReg;
use common\models\boe\BoeTxyTask;
use common\models\boe\BoeTxyBadge;
use common\models\boe\BoeTxyWeilog;
use common\models\boe\BoeTxyPlan;
use common\models\boe\BoeTxyConfig;
use common\models\boe\BoeBadword;
use Yii;

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

/**
 * 特训营相关
 * @author xinpeng
 */
class BoeTxyService {

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
	 * 根据日期和组织信息获取参加该日期参加知识答题的学员数量
	*/
	static function txyJoinTaskStudentNum($orgnization_id = NULL,$date = NULL){
		if(!$orgnization_id)
		{
			return 0;	
		}
		$manage_area			= self::getManagerArea($orgnization_id,1);
		$orgnization_array		= array_keys($manage_area['area_array']);
		$orgnization_id			= "'".implode("','",$orgnization_array)."'";
		$org_where 				= " and u.orgnization_id in ({$orgnization_id}) ";
		$data					= 0;
		$date					= $date?$date:date("Y-m-d");
		$domain_id 				= Yii::t('boe', 'boe_txy_domain_kid');
		//根据任务配置获取知识答题的kid
		$db_obj		= new BoeTxyTask();
		$db_where	= array('is_deleted'=>'0','type'=>'1','is_begin'=>1,'begin_date'=>$date);
		//$db_where	= array('is_deleted'=>'0','type'=>'1','is_begin'=>1,'begin_date'=>'2017-07-15');
		$task_info	= $db_obj->find(false)->where($db_where)->asArray()->one();
		$exam_id	= isset($task_info['type_id'])?$task_info['type_id']:"";
		if($exam_id)
		{
			$exam_where = "and r.examination_id = '{$exam_id}' ";
			$connection	= Yii::$app->db;
			$sql		= "
	select 
	count(distinct(r.user_id)) as student_num
	from eln_ln_examination_result_user r 
	INNER JOIN eln_fw_user AS u ON u.kid = r.user_id and u.domain_id ='{$domain_id}' {$org_where}
	where r.is_deleted ='0' and r.examination_status = '2' {$exam_where} ;
			";
			$data		= $connection->createCommand($sql)->queryOne();
			return $data['student_num'];	
		}
		return $data;
	}
	
	/*
	 * 获取学员结营考试结果信息并点亮徽章
	*/
	static function txyExamResult()
	{
		$domain_id 	= Yii::t('boe', 'boe_txy_domain_kid');
		//根据任务配置获取结营考试的kid
		$db_obj		= new BoeTxyTask();
		$db_where	= array('is_deleted'=>'0','type'=>'3','is_begin'=>1);
		$task_info	= $db_obj->find(false)->where($db_where)->asArray()->one();
		$exam_id	= isset($task_info['type_id'])?$task_info['type_id']:"";
		if($exam_id)
		{
			$exam_where = "and r.examination_id = '{$exam_id}' ";	
		}
		//及格线设置
		$jg_score	= 80;
		$jg_where	= " and r.examination_score >= {$jg_score} ";
		$connection	= Yii::$app->db;
		$sql		= "
select 
r.examination_score,	
r.user_id,
u.user_name,
u.real_name
from eln_ln_examination_result_user r 
INNER JOIN eln_fw_user AS u ON u.kid = r.user_id and u.domain_id ='{$domain_id}'
where r.is_deleted ='0' and r.examination_status = '2' {$exam_where} {$jg_where};
		";
		$data		= $connection->createCommand($sql)->queryAll();
		if($data)
		{
			//点亮学员结营考试徽章
			foreach($data as $d_key=>$d_value )
			{
				BoeTxyApiService::addStudentExamineBadge($d_value['user_id']);		
			}
		}
		return $data;
	}
	/*
	 * 获取所有的徽章配置信息
	*/
	static function txyBadgeInfo($type = 0)
	{
		if(!in_array($type,array(0,1)))
		{
			return NULL;	
		}
		$db_obj		= new BoeTxyBadge();
		$db_where	= array('is_deleted'=>'0','type'=>$type);
		$badge_info	= $db_obj->find(false)->where($db_where)->asArray()->all();
		return $badge_info;
		
	}
		
	/*
	 * 获取课程任务配置信息
	*/
	static function txyTaskInfo($is_current = 0)
	{
		$db_where	= array('is_deleted'=>'0');
		if($is_current ==1)
		{
			$db_where['begin_date']	=	date("Y-m-d");
		}
		$db_obj			= new BoeTxyTask();
		$task_info		= $db_obj->find(false)->where($db_where)->asArray()->all();
		$new_task		= array();
		foreach($task_info as $t_key=>$t_value)
		{
			$new_task[$t_value['type']][] = $t_value;
		}
		return $new_task;
	}
	
	/*
	 * 统计信息
	*/
	static function txyTongji($orgnization_id = NULL,$create_mode = 0,$current_date = NULL)
	{
		if(!$orgnization_id)
		{
			return NULL;	
		}
        $cache_time = self::$tongjiCacheTime;
		//return $cache_time;
        $cache_name = __METHOD__ . '_limit_' . $limit . '_orgnization_id_' . $orgnization_id;
		if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
		if (!$sult || !is_array($sult) || $create_mode==1) {//缓存中没有或是强制生成缓存模式时S
			$sult					= array();
			//当前日期
			$current_date			= $current_date?$current_date:date("Y-m-d");
			$current_at				= strtotime($current_date);
			$current_at_end			= $current_at+24*3600;
			$sult['current_date']	= $current_date;
			//根据组织ID获取管辖区域的所有组织ID
			$manage_area			= self::getManagerArea($orgnization_id,1);
			$orgnization_array		= array_keys($manage_area['area_array']);
			//获取组织内的学员总数量
			$manager_student 		= self::getManagerStudent($orgnization_id);
			$sult['student_num']	= count($manager_student['list']);
			/*//获取该组织的学员离营数量
			$quit_where				= array(
				'and',
				array('in','orgnization_id',$orgnization_array),
				array('=','is_deleted','0'),
			);
			$quit_num				= BoeTxyStudentQuit::find(false)->where($quit_where)->count();
			$sult['student_num']	= $sult['student_num']-$quit_num;*/
			//获取飞虎队的学员数量（以当天的数量为最终结果）
			$fhd_where				= array(
				'and',
				array('=','is_fhd',1),
				array('=','investigator_date',$current_date),
				array('in','orgnization_id',$orgnization_array),
				array('=','is_deleted','0'),
			);
			$sult['fhd_num'] = BoeTxyStudentEvent::find(false)->where($fhd_where)->count();
			//获取每日标兵的学员数量
			$mrbb_where = array(
				'and',
				array('=','investigator_date',$current_date),
				array('in','orgnization_id',$orgnization_array),
				array('=','is_deleted','0'),
				array(
					'or',
					array('=','is_nwbb',1),
					array('=','is_xlbb',1),
					array('=','is_hdbb',1),
				)	
			);
			$sult['mrbb_num'] = BoeTxyStudentEvent::find(false)->where($mrbb_where)->count();
			$sult['status_avg']		  = self::txyStudentStatusTongji($orgnization_id,2,0);
			//身体状况平均值（汇总）
			$sult['body_status']	  = round(($sult['status_avg'][0]['body_status_avg']+$sult['status_avg'][1]['body_status_avg'])/2,1);
			//情绪状况平均值（汇总）
			$sult['mood_status']	  = round(($sult['status_avg'][0]['mood_status_avg']+$sult['status_avg'][1]['mood_status_avg'])/2,1);
			//学员的微日志完成情况
			$weilog_where	= array(
				'and',
				//array('>=','created_at',$current_at),
				array('between', 'created_at', $current_at, $current_at_end),
				array('in','orgnization_id',$orgnization_array),
				array('=','is_deleted','0'),
			);
			//boeBase::dump($current_date);
			//boeBase::dump($weilog_where);
			$sult['weilog_num']	= BoeTxyWeilog::find(false)->select("user_id")->distinct()->where($weilog_where)->count();
			$sult['weilog_percent'] = round($sult['weilog_num']/$sult['student_num'],2)*100;
			//学院的行动计划完成情况
			$plan_where	= array(
				'and',
				//array('>=','created_at',$current_at),
				array('between', 'created_at', $current_at, $current_at_end),
				array('in','orgnization_id',$orgnization_array),
				array('=','is_deleted','0'),
			);
			$sult['plan_num']	= BoeTxyPlan::find(false)->select("user_id")->distinct()->where($plan_where)->count();
			$sult['plan_percent'] = round($sult['plan_num']/$sult['student_num'],2)*100;
			
			if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }	
		}
		
		return $sult;
	}
	
	/*
	 * 你的国王（评估国王，评估自己）数据信息统计
	*/
	static function txyStudentStatusTongji($orgnization_id = NULL,$object_type = 1,$is_date = 1)
	{
		if(!$orgnization_id)
		{
			return NULL;
		}
		$type_where				=$group_where	= $select_field = "";
		$manage_area			= self::getManagerArea($orgnization_id,1);
		$orgnization_array		= array_keys($manage_area['area_array']);
		$orgnization_id			= "'".implode("','",$orgnization_array)."'";
		$org_where 				= " orgnization_id in ({$orgnization_id}) ";
		if($is_date == 1)
		{
			//数据图表，按照日期和评估种类的统计
			if(in_array($object_type,array(0,1)))
			{
				$type_where = " and object_type = {$object_type} ";
			}
			$select_field		= " investigator_date,";
			$group_where		= " GROUP BY investigator_date ASC ";
		}else
		{
			//总体统计，按照评估种类的统计
			$select_field		= " object_type,";
			$group_where		= " GROUP BY object_type ASC ";
		}
		$connection					= Yii::$app->db;
		$sql						= "
SELECT
{$select_field}
SUM(body_status)/COUNT(student_id) body_status_avg,
SUM(mood_status)/COUNT(student_id) mood_status_avg
FROM
eln_boe_txy_student_status
WHERE investigator_date <= '2017-07-26' and {$org_where} {$type_where} {$group_where};
		";
		$data						= $connection->createCommand($sql)->queryAll();
		if($is_date != 1)
		{
			return $data;
		}
		$sult						= array();
		foreach($data as $d_key=>$d_value)
		{
			$sult['date'][]			= date("m-d",strtotime($d_value['investigator_date']));
			$sult['body_data'][]	= round($d_value['body_status_avg'],1);
			$sult['mood_data'][]	= round($d_value['mood_status_avg'],1);	
		}	
		return $sult;
	}
	
	
	/*
	 * 每日任务的信息统计
	*/
	 public static function txyDailyTasks($orgnization_id = NULL,$current_date = NULL) {
		if(!$orgnization_id)
		{
		   return NULL;
		}
		$sult	= array();
		$current_date					= $current_date?$current_date:date("Y-m-d");
		$current_at_begin				= strtotime($current_date);
		$current_at_end					= strtotime($current_date)+24*3600;
		$sult['current_date']			= $current_date;
		//获取该组织的所有今日的微日志信息
		$weilog_where	= array(
			'and',
			array('between', 'created_at', $current_at_begin, $current_at_end),
			//array('>=','created_at',$current_at),
			array('=','orgnization_id',$orgnization_id),
			array('=','is_deleted','0'),
		);
		$sult['weilog_info']		= BoeTxyWeilog::find(false)->select('user_id,kid')->where($weilog_where)->indexBy('user_id')->asArray()->all();
		//获取该组织的所有今日的行动计划信息
		$plan_where	= array(
			'and',
			array('between', 'created_at', $current_at_begin, $current_at_end),
			array('=','orgnization_id',$orgnization_id),
			array('=','is_deleted','0'),
		);
		$sult['plan_info']			= BoeTxyPlan::find(false)->select('user_id,kid')->where($plan_where)->indexBy('user_id')->asArray()->all();
		//获取评价国王和评估自己的信息
		$self_where	= array(
			'and',
			array('=', 'investigator_date', $current_date),
			array('=','orgnization_id',$orgnization_id),
			array('=','object_type',0),
			array('=','is_deleted','0'),
		);
		$sult['self_info']			= BoeTxyStudentStatus::find(false)->select('investigator_id,kid')->where($self_where)->indexBy('investigator_id')->asArray()->all();
		$king_where	= array(
			'and',
			array('=', 'investigator_date', $current_date),
			array('=','orgnization_id',$orgnization_id),
			array('=','object_type',1),
			array('=','is_deleted','0'),
		);
		$sult['king_info']			= BoeTxyStudentStatus::find(false)->select('investigator_id,kid')->where($king_where)->indexBy('investigator_id')->asArray()->all();
		//获取活动报名情况信息
		$event_where	= array(
			'and',
			array('=','orgnization_id',$orgnization_id),
			array('=','is_deleted','0'),
		);
		$sult['event_info']			= BoeTxyEventReg::find(false)->select('user_id,kid')->where($event_where)->indexBy('user_id')->asArray()->all();
		//获取该组织的所有学员信息
		$sult['student_info']		= self::getManagerStudent($orgnization_id,1,1);
		$sult['student_info']		= $sult['student_info']['list'];
		return $sult;  
	 }
	
	/*
	 * 获取每日标兵信息
	*/
	static function getMrbbInfo($limit = 0,$img_must = 0)
	{
		$connection					= Yii::$app->db;
		//$current_date				= date("Y-m-d");
		$limit						= $limit>0?"limit 0,{$limit}":"";
		$img_where					= $img_must == 1?" and e.image_url <> ''":"";
		$orgnization				= BoeTxyService::getAreaOrgnization();
		//boeBase::dump($orgnization['list']);
		//exit();
		$sult						=array();
		foreach($orgnization['list'] as $o_key=>$o_value)
		{
			$data						=array();
			$manage_area				= BoeTxyService::getManagerArea($o_value['kid'],1);
			$orgnization_id				= array_keys($manage_area['area_array']);
			$orgnization_id 			= "'".implode("','",$orgnization_id)."'";
			$org_where 					= " and e.orgnization_id in ({$orgnization_id})";
			$sql						= "
				SELECT
	e.kid,e.user_id,e.orgnization_id,o.orgnization_code,e.investigator_id,e.investigator_date,e.is_fhd,e.is_not_course,
	e.is_not_extend,e.is_nwbb,e.is_xlbb,e.is_hdbb,e.image_url,u.user_name,u.real_name,
	SUBSTR(CONCAT(n.node_code_path,n.tree_node_code),2) orgnization_code_path,
	SUBSTR(CONCAT(n.node_name_path,n.tree_node_name),2) orgnization_path
	FROM
	eln_boe_txy_student_event e
	INNER JOIN eln_fw_user u ON u.kid = e.user_id
	INNER JOIN eln_fw_orgnization o ON u.orgnization_id = o.kid
	INNER JOIN eln_fw_tree_node n ON o.tree_node_id = n.kid
	WHERE e.is_deleted ='0' {$img_where} {$org_where}
	AND (e.is_hdbb = 1 OR e.is_nwbb = 1 OR e.is_xlbb = 1 )
	ORDER BY e.investigator_date desc {$limit};
			";
			$data						= $connection->createCommand($sql)->queryAll();
			$sult						= array_merge($sult,$data);
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
			$news_db	= new BoeTxyNewsCategory();
            $sult 		= $news_db->getBaseTreeFromTag($news_category_tag);
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $sult, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $sult;
    }
	
	/**
     * 读取在首页推荐的分类和信息列表
     * getIndexTxyNewsList
     * $hastagid 是否包含当前tagid,默认为0,不包含，1为包含
     */
    static function getIndexTxyNewsListFromTag($limit = 4, $new_category_tag = NULL, $hastagid = 0) {
        if (!$new_category_tag) {
            return NULL;
        }
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . '_limit_' . $limit . '_new_category_tag_' . $new_category_tag;
        $sult = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E 
        if (!$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S 
            $sult = array();
			$news_category_db	= new BoeTxyNewsCategory();
            $cate_info = $news_category_db->getSubTag($new_category_tag, 0, $hastagid); //找出分类对应子子孙孙的ID
            $tmp_kid = array();
            if ($cate_info && is_array($cate_info)) {
                foreach ($cate_info as $key => $a_cate) {
                    $sult['cate_' . $key] = array(
                        'cate_id' => $a_cate['kid'],
                        'cate_name' => $a_cate['name'],
                        'info_list' => self::getRecommendTxyNewsInfo($a_cate['kid'], $limit, 1),
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
    private static function getRecommendTxyNewsInfo($cate_id = '', $limit = 9, $fill = 0, $debug = 0) {
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
            $news_category_db	= new BoeTxyNewsCategory();
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
		$news_db	= new BoeTxyNews();
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
        return self::parseTxyNewsList($sult);
    }

    private static function parseTxyNewsList($news_list) {
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
		$news_category_db	= new BoeTxyNewsCategory();
        foreach ($news_list as $key => $a_info) {
            $a_info['front_url'] = Yii::$app->urlManager->createUrl(array('boe/txy/news-detail', 'id' => $a_info['kid']));
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
	
	public static function getTxyNewsDetail($id = '', $show_ad = 0) {
        $cache_mode = self::isNoCacheMode() ? 1 : 0;
        if (DIRECTORY_SEPARATOR == "\\") {
            $cache_mode = 1;
        }
		$db_obj		= new BoeTxyNews();
        $info 		= $db_obj->getInfo($id, '*', $cache_mode);
        if ($info) {
            if ($info['image_url']) {
                $info['image_url'] = BoeBase::getFileUrl($info['image_url'], 'txyNews');
            }
            $parse_info = self::parseTxyNewsList(array($info));
            return current($parse_info);
        }
        return NULL;
    }
	
	//----------------------------------------------------------和特训营的资讯有关的方法结束
	
	//----------------------------------------------------------和特训营的用户有关的方法开始
	
	/**
     * 获取全部的管理员信息（副总指挥,区副，营副，连副）
     */
    static function getAllTxyManager($create_mode = 0) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
			
			$connection			= Yii::$app->db;
			$sql				= "
SELECT
u.user_no,
u.real_name,
m.orgnization_id,
m.user_id,
m.`level`,
m.mark,
SUBSTR(CONCAT(n.node_code_path,n.tree_node_code),2) orgnization_code,
SUBSTR(CONCAT(n.node_name_path,n.tree_node_name),2) orgnization_path,
m.is_deleted
FROM
eln_boe_txy_manager m
INNER JOIN eln_fw_orgnization o ON o.kid = m.orgnization_id and o.is_deleted = '0'
INNER JOIN eln_fw_tree_node n ON o.tree_node_id = n.kid and n.is_deleted = '0'
INNER JOIN eln_fw_user u ON u.kid = m.user_id and u.is_deleted ='0'
WHERE m.is_deleted = '0';	
			";
			$command			= $connection->createCommand($sql)->queryAll();
            $sult = array(
                'sql' 	=> $sql,
                'list' 	=> $command,
            );
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }
	
	/**
     * 以组织为索引获取所有管理员信息
     */
	static function getTxyOrgManager($create_mode = 0) {
		$cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
			$all_manger = self::getAllTxyManager(1); //获取全部的管理员信息
			foreach($all_manger['list'] as $a_key=>$a_value)
			{
				$sult[$a_value['orgnization_id']][] =$a_value;
			}
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;	
	}
	
	/*
     * 获取特训营信息管理树形信息
     */
    public static function getALLOrgnizationTree() {
        $orgnization_id = Yii::t('boe', 'boe_txy_orgnization_kid');
		//return $orgnization_id;
        $sult = BoeBaseService::getTreeScatterArray('FwOrgnization', $orgnization_id);
        return $sult;
    }
	/*
     * 获取特训营大区分类信息
     */
	 public static function getAreaOrgnization() {
	 	$orgnization_id 		= Yii::t('boe', 'boe_txy_orgnization_kid');
		$all_orgnization		= self::getALLOrgnizationTree();
		$area_data				= array();
		$area_data['root_id']	= $orgnization_id;
		foreach($all_orgnization as $a_key=>$a_value )
		{
			if($a_value['parent_orgnization_id'] == $orgnization_id )
			$area_data['list'][$a_key]	= $a_value;
		}
		return $area_data;
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
    public static function getManagerArea($orgnization_id, $detail = 0, $create_mode = 0) {
		if (!$orgnization_id) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_orgnization_id_' . $orgnization_id . '_detail_' . $detail;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取 
				$sult = BoeBaseService::getTableOneInfo('FwOrgnization', $orgnization_id);
                $sult_all = BoeBaseService::getTreeSubId('FwOrgnization', $orgnization_id);
				 
                if ($detail) {//获取其子子孙孙S
                    $sult['area_array'] = $sult_all;
                } else {
                    foreach ($sult_all as $s_key => $s_value) {
                        if ($s_value['parent_orgnization_id'] == $sult['kid']) {
                            $sult['area_array'][$s_value['kid']] = $s_value;
                        }
                    }
                }
                //  BoeBase::debug(__METHOD__ . var_export($user_info, true) ."\norgnization_id:".$orgnization_id ."\n". var_export($sult, true),1);
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
    public static function getManagerStudent($orgnization_id, $get_orgnization_path = 0, $create_mode = 0) {
        if (!$orgnization_id) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_orgnization_id_' . $orgnization_id . '_get_orgnization_path_' . $get_orgnization_path;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取 
            $area_info = self::getManagerArea($orgnization_id, 1); //获取当前用户的区域范围
            //return $area_info;
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
	
	/*
     * 获取用户的等级
     * getUserLevel($uid,$orgnization_id=NULL)
      Input:$uid String  Not NULL
      Output:5个值
      0或NULL表示只是学员
      -1:非特训营成员
      1：连副
      2：营副
      3：区副
	  4、副总指挥或是子项PM
     */
    public static function getUserLevel($uid, $create_mode = 0) {
        $level_data = Yii::t('boe', 'boe_txy_manager_level');
        $level_key = -1;
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
            $all_manger = self::getAllTxyManager($create_mode); //获取全部的管理员信息
            $all_manger = BoeBase::array_key_is_nulls($all_manger, 'list', NULL);
            if ($all_manger) {//读取到了全部的特训营管理员信息时S
                foreach ($all_manger as $a_info) {//循环判断ForStart
                    if ($a_info['user_id'] == $uid) {
                        $level_key = $a_info['level'];
						$level_info	= $a_info;
                        break;
                    }
                }//循环判断ForEnd
            }//读取到了全部的特训营管理员信息时E
            if (!$level_key) {
                $user_info = self::getOneUserInfo($uid);
                $domain_id = Yii::t('boe', 'boe_txy_domain_kid');
                if ($user_info['domain_id'] != $domain_id) {
                    $level_key = -1;
                }else{
					$level_key = 0;
				}
            }
			$level_data[$level_key]['info']=$level_info;
            $sult = $level_data[$level_key];
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }//从数据中整理E
        return $sult;
        //指定了用户信息E
    }
	
	/*
     * 获取用户的等级
     * getUserLevel($uid,$orgnization_id=NULL)
      Input:$uid String  Not NULL
      Output:5个值
      0或NULL表示只是学员
      -1:非特训营成员
      1：连副
      2：营副
      3：区副
	  4、副总指挥或是子项PM
     */
    public static function getUserLevel2($uid, $create_mode = 0) {
        $level_data = Yii::t('boe', 'boe_txy_manager_level');
        $level_key = -1;
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
            $all_manger = self::getAllTxyManager($create_mode); //获取全部的管理员信息
            $all_manger = BoeBase::array_key_is_nulls($all_manger, 'list', NULL);
			$level_array	= array();
            if ($all_manger) {//读取到了全部的特训营管理员信息时S
                foreach ($all_manger as $a_key=>$a_info ) {//循环判断ForStart
                    if ($a_info['user_id'] == $uid) {
						$a_info['level_name']	= $a_info['mark']?$a_info['mark']:$level_data[$a_info['level']]['name'];
						$level_array[]			= array(
							'level'	=> $a_info['level'],
							'role'	=> $level_data[$a_info['level']]['role'],
							'name'	=> $a_info['mark']?$a_info['mark']:$level_data[$a_info['level']]['name'],
							'info'	=> $a_info
						);
                    }
                }//循环判断ForEnd
            }//读取到了全部的特训营管理员信息时E
			//return $level_array;
            if (!$level_array) {
                $user_info = self::getOneUserInfo($uid);
                $domain_id = Yii::t('boe', 'boe_txy_domain_kid');
                if ($user_info['domain_id'] != $domain_id) {
                    $level_key = -1;
                }else{
					$level_key = 0;
				}
				$level_array[]			= array(
					'level'	=> $level_key,
					'role'	=> $level_data[$level_key]['role'],
					'info'	=> NULL,
					'name'	=>$level_data[$level_key]['name']
				);	
            }
            $sult = $level_array;
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }//从数据中整理E
        return $sult;
        //指定了用户信息E
    }
	//----------------------------------------------------------和特训营的用户有关的方法结束
	/*
	 * 特训营标兵种类识别
	*/
	public static function getMrbbType($bb_array = array()) {
		$current_bb		= array();
		$mrbb_type 		= Yii::t('boe', 'boe_txy_mrbb_type');
		foreach($mrbb_type as $bb_key=>$bb_value)
		{
			if(isset($bb_array[$bb_key])&& $bb_array[$bb_key]==1)
			{
				$current_bb = array(
				  'bb_key'	=>$bb_key,
				  'bb_name'	=>$bb_value
				);
			}
		}
		return $current_bb;	
	}
	//----------------------------------------------------------和特训营的活动报名的的方法开始
	 public static function getEventInfo($orgnization_id = NULL,$recommend_status =0,$publish_status =0,$reg_type =0) {
		if(!$orgnization_id)
		{
			return NULL;
		}
		if(is_array($orgnization_id))
		{
			$orgnization_id = "'".implode("','",$orgnization_id)."'";
			$org_where 		= " o.kid in ({$orgnization_id})";
		}else{
			$org_where 		= " o.kid = '{$orgnization_id}'";
		}
		if($recommend_status==1)
		{
			$re_where 		= " and r.recommend_status = 1 ";
		}
		if($publish_status==1)
		{
			$pu_where 		= " and r.publish_status = 1 ";
		}
		if($reg_type>0)
		{
			$ty_where 		= " and r.reg_type = {$reg_type} ";
		}
		$sult				= array();
		$sql 				= "
SELECT
r.kid,
r.user_id,
u.user_name,
u.real_name,
u.orgnization_id,
r.reg_type,
r.recommend_status,
r.publish_status,
o.orgnization_code,
SUBSTR(CONCAT(n.node_code_path,n.tree_node_code),2) orgnization_code_path,
SUBSTR(CONCAT(n.node_name_path,n.tree_node_name),2) orgnization_path
FROM
eln_fw_orgnization o
INNER JOIN eln_fw_tree_node n ON o.tree_node_id = n.kid and n.is_deleted = '0'
INNER JOIN eln_fw_user u ON u.orgnization_id = o.kid and u.is_deleted ='0'
INNER JOIN eln_boe_txy_event_reg r ON r.user_id = u.kid and r.is_deleted ='0'
WHERE {$org_where} {$re_where} {$pu_where} {$ty_where}
order by n.node_code_path asc, n.sequence_number asc;";
		$connection			= Yii::$app->db;
		$event_info			= $connection->createCommand($sql)->queryAll();
		$event_reg_type		= Yii::t('boe', 'boe_txy_event_reg');
		$event_array		= $event_org_array= array();
		foreach($event_info as $e_key=>$e_value )
		{
			$e_value['event_name']	=$event_reg_type[$e_value['reg_type']];
			$e_value['orgnization_path']=self::boeTrim($e_value['orgnization_path'], "2017特训营/");
			$event_array[$e_value['reg_type']][$e_value['user_id']]= $e_value;
			$event_org_array[$e_value['orgnization_id']]= $e_value['orgnization_path'] ;		
		}
		$sult['event_type']		= $event_reg_type;
		$sult['event_array']	= $event_array;
		$sult['event_org_array']= $event_org_array;	
		return $sult;				 
	 }
	//----------------------------------------------------------和特训营的活动报名的的方法结束
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

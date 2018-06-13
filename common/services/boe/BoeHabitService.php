<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\base\BoeBase;
use common\models\boe\BoeSubject;
use common\models\boe\BoeSubjectHabit;
use common\models\boe\BoeSubjectHabitMod;
use common\models\boe\BoeSubjectHabitUser;
use common\models\boe\BoeSubjectHabitPer;
use common\models\boe\BoeSubjectHabitOrg;
use common\models\boe\BoeSubjectHabitExam;
use common\models\boe\BoeSubjectConfig;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseMods;
use common\models\learning\LnCourseComplete;
use common\models\learning\LnComponent;
use common\models\learning\LnModRes;
use common\models\learning\LnResComplete;
use common\models\learning\LnCourseware;
use common\models\learning\LnCourseactivity;
use common\models\framework\FwUser;
use common\models\framework\FwOrgnization;
use common\services\boe\BoeCourseService;
use common\services\boe\BoeBaseService;
use yii\db\Query;
use Yii;

/**
 * 专题闯关相关
 * @author xinpeng
 */
class BoeHabitService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_habit_';

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
	/************************************前台页面需要读取的相关信息S*********************************************/
	/*
	 * 判断当前账号是否为原特训营学员以作权限划分
	 * 返回true或是false
	*/
	public static function IsStudentCheck($uid = NULL) {
		if(!$uid)
		{
			return false;	
		}
		$uObj	= new BoeSubjectHabitUser();
		$where	= array(
			'and',
			"`is_deleted` = '0'",
			array(
				'or',
				"`user_kid` = '{$uid}'",
				"`t_kid` =  '{$uid}'"
			)
		);
		$find	= $uObj	->find(false)->where($where)->asArray()->one();	
		if($find)
		{
			return $find;
		}else{
			return false;
		}
	}
	
	/*
	 * 获取团队和个人的排行榜信息
	 * 返回数组
	*/
	public static function getHabitUserInfo($uid) {
		$u_info			= self::IsStudentCheck($uid);
		if($u_info)
		{
			//团队排名信息
			$org_top	= BoeHabitService::getHabitOrgTop();
			//团队个数
			$u_info['org_total']		= count($org_top);
			//获取该团队在团队排名的索引key
			$org_key	= self::arrayValueToKey($org_top,'t_orgnization_id',$u_info['t_orgnization_id']);
			//团队排名
			$org_top	= !is_array($org_key)?$org_key+1:0;
			$u_info['org_top']			= $org_top;
			/**********************************************************/
			//获取该学员的课程通过情况
			$u_info['course_status']	= self::getHabitUserCourseStatus($u_info['user_kid']);
			//return $u_info['course_status'];
			/***********************************************************/
			//团队学员数据信息
			$org_array		= self::getHabitPerTop(0,$u_info['t_orgnization_id']);
			//获取该学员在团队中的索引key
			$per_org_key	= self::arrayValueToKey($org_array,'hu_kid',$u_info['kid']);
			//团队排名
			$per_org_top	= !is_array($per_org_key)?$per_org_key+1:0;
			//金币总数量
			$u_info['gold_num']			= $org_array[$per_org_key]['gold_num'];
			//学习总时间
			$u_info['study_time']		= $org_array[$per_org_key]['study_time'];
			//团队排名
			$u_info['per_top']			= $per_org_top;
			//团队个数
			$u_info['per_total']		= count($org_array);
			
			//闯关总关数和该学员总共闯关的关数
			//=======获取个人的考试成绩详细信息（个人考试成绩信息获取版）
			$exam_info					= self::getHabitUserExam($u_info['kid']);
			$u_info['exam_info']			= $exam_info['exam_info'];
			$u_info['finsh_course_num']		= $exam_info['finish_num'];
			//=======获取个人的考试成绩详细信息（即时版）
			$exam_info2					= self::getHabitUserExam2($u_info['user_kid']);
			//return $exam_info2;
			$u_info['exam_info2']			= $exam_info2['exam_info'];
			$u_info['finsh_course_num2']	= $exam_info2['finish_num'];
			$u_info['total_gold_num']		= $exam_info2['total_gold_num'];
			$u_info['work_gold_num']		= self::countHabitUserWork($u_info['kid']);
			$u_info['total_gold_num']		= $u_info['total_gold_num']+$u_info['work_gold_num'];
			
			//=======闯关总关数
			//$all_course					= self::getAllHabitCourse();
			//$u_info['count_course_num']		= count($all_course);	
		}
		return $u_info;
	}
	
	/*
	 * 获取个人的考试成绩完成状态信息
	*/
	public static function getHabitUserCourseStatus($uid = NULL) {
		if(!$uid)
		{
			return NULL;
		}
		$all_course	= self::getAllHabitCourse();
		$course_array = array_keys($all_course);
		
		$where_c = array(
			'and',
			array('=', 'user_id', $uid),
			array('in', 'course_id', $course_array),
			array('=','complete_status','2'),
			array('=','is_passed','1'),
			array('=','complete_type','1'),
			array('=', 'is_deleted', '0')
		);
		$cObj 		= new LnCourseComplete();
		$sult 	= $cObj->find(false)->where($where_c)->select('course_id,complete_status,is_passed,complete_type')->indexBy('course_id')->asArray()->all();
		return $sult;	
	}
	
	/*
	 * 获取个人的考试成绩详细信息(即时更新数据)
	 * 返回数组
	*/
	public static function getHabitUserExam2($uid = NULL) {
		if(!$uid)
		{
			return NULL;
		}
		$all_course		= self::getAllHabitCourse();
		$course_array 	= array_keys($all_course);
		$exam_array		= BoeCourseService::getCourseExam($course_array,$uid);
		//return $exam_array;
		$finish_num	= $total_gold_num = 0;
		$sult		= $exam_data = array();
		foreach($exam_array as $e_key =>$e_value )
		{
			$examination_score  = self::checkUserExamScore($e_value['hu_kid'],$e_value['course_id'],$e_value['examination_score']);
			$bi					=	self::scoreToBi($examination_score);
			//$bi		=	self::scoreToBi($e_value['examination_score']);
			if($bi >=1)
			{
				$finish_num++;
			}
			$exam_data[$e_value['course_id']]				= $e_value;
			$exam_data[$e_value['course_id']]['gold_num']	= $bi;
			$total_gold_num					= $total_gold_num+$bi;
			unset($bi);
		}
		$sult	= array(
			'finish_num'	=> $finish_num,
			'exam_info'		=> $exam_data,
			'total_gold_num'=> $total_gold_num,
		);
		//return $examination_score;
		return $sult;	
	}
	
	/*
	 * 检查并更新个人考试成绩临时表
	*/
	public static function checkUserExamScore($hu_kid = NULL , $course_id = NULL,$examination_score = NULL)
	{
		if(!$hu_kid || !$course_id)
		{
			return 0;
		}
		//检查当前考试成绩与之前考试成绩（可以多次考试）做比较，
		//=====若是比之前高，更新
		//=====若是相同，不做改变，不更新
		//=====若是比之前低,取之前成绩,不更新
		$examObj		= new BoeSubjectHabitExam();
		$e_where		= array(
			'hu_kid'	=>$hu_kid,
			'course_id'	=>$course_id,
			'is_deleted'=>'0'
		);
		$find_exam		= $examObj->find(false)->where($e_where)->asArray()->one();
		//return $find_exam;
		$data_exam		= array();
		if(isset($find_exam['kid'])&&$find_exam['kid'])
		{
			if($find_exam['exam_score'] > 0)
			{
				if($examination_score > $find_exam['exam_score'])
				{
					//更新成绩数据
					$data_exam	= array(
						'kid'			=>$find_exam['kid'],
						'exam_score'	=> $examination_score
					);	
				}
				if($examination_score < $find_exam['exam_score'])
				{
					//取之前成绩
					$examination_score	= $find_exam['exam_score'];
				}
				
			}else{
				//之前数据为空情况下，当前数据大于0时更新
				if($examination_score > 0)
				{
					//更新成绩数据
					$data_exam	= array(
						'kid'			=>$find_exam['kid'],
						'exam_score'	=> $examination_score
					);
				}	
			}		
		}else
		{
			//新增成绩数据
			$data_exam	= array(
				'hu_kid'		=>$hu_kid,
				'course_id'		=>$course_id,
				'exam_score'	=>$examination_score
			);
		}
		//存在需要更新的数据
		if($data_exam)
		{
			//return $data_exam;
			$examObj		= new BoeSubjectHabitExam();
			$sult			= $examObj->saveInfo($data_exam);
			if(is_array($sult))
			{
				return false;
			}
		}
		return $examination_score;
	}
	
	
	/*
	 * 获取个人的考试成绩详细信息
	 * 返回数组
	*/
	public static function getHabitUserExam($hu_kid = NULL) {
		if(!$hu_kid)
		{
			return NULL;
		}
		$examObj	= new BoeSubjectHabitExam();
		$e_where	= array('hu_kid'=>$hu_kid,'is_deleted'=>'0');
		$exam_array	= $examObj->find(false)->select('course_id,exam_score')->where($e_where)->indexBy('course_id')->asArray()->all();
		$finish_num	= 0;  
		$sult		= $exam_data = array();
		foreach($exam_array as $e_key =>$e_value )
		{
			$bi		=	self::scoreToBi($e_value['exam_score']);
			if($bi >=1)
			{
				$finish_num++;
			}
			$exam_data[$e_key]				= $e_value;
			$exam_data[$e_key]['gold_num']	= $bi;
			unset($bi);
		}
		$sult	= array(
			'finish_num'	=> $finish_num,
			'exam_info'		=> $exam_data
		);
		return $sult;	
	}
	
	/*
	 * 获取团队的排行榜信息
	 * 返回数组
	*/
	public static function getHabitOrgTop($limit) {
		$o				= BoeSubjectHabitOrg::realTableName();
		$org_obj		= new BoeSubjectHabitOrg();
		$find			= $org_obj->find(false)
		->where(array('is_publish'=>'0','is_deleted'=>'0'))->asArray()->one();
		if(isset($find['kid'])&&$find['kid'])
		{
			$field		= " t_orgnization_id,is_publish,gold_num2 as gold_num,gold_average_num2 as gold_average_num,study_time2 as study_time,updated_at";
			$sql 		= "select {$field} from {$o} order by gold_average_num2 desc,study_time2 asc ";
		}else{
			$field		= " t_orgnization_id,is_publish,gold_num,gold_average_num,study_time,updated_at";
			$sql 		= "select {$field} from {$o} order by gold_average_num desc,study_time asc ";
		}
		if($limit)
		{
			$limit		= intval($limit);
			$sql		= $sql." limit 0,{$limit} ";
		}
		$connection		= Yii::$app->db;
		$sult			= $connection->createCommand($sql)->queryAll();
		return $sult ;
	}
	
	/*
	 * 获取个人的排行榜信息
	 * 返回数组
	*/
	public static function getHabitPerTop($limit = 0,$org_id = NULL) {
		$u				= BoeSubjectHabitUser::realTableName();
		$p				= BoeSubjectHabitPer::realTableName();
		$per_obj		= new BoeSubjectHabitPer();
		$find			= $per_obj->find(false)
		->where(array('is_publish'=>'0','is_deleted'=>'0'))->asArray()->one();
		if(isset($find['kid'])&&$find['kid'])
		{
			$field			= " p.hu_kid,p.hu_org,u.user_kid,u.user_real_name,u.t_orgnization_id,p.gold_num2 as gold_num,p.study_time2 as study_time ";
			$order			=" gold_num2 desc,study_time2 asc ";
		}else
		{
			$field			= " p.hu_kid,p.hu_org,u.user_kid,u.user_real_name,u.t_orgnization_id,p.gold_num,p.study_time ";
			$order			=" gold_num desc,study_time asc ";
		}
		$org_where			= " where u.is_test='0' ";
		if($org_id)
		{
			$org_where		= $org_where ." and u.t_orgnization_id='{$org_id}' ";
		}
		$sql 				= "
select {$field} from {$p} p left join  $u u on p.hu_kid=u.kid  {$org_where} order by {$order}";
		if($limit)
		{
			$limit		= intval($limit);
			$sql		= $sql." limit 0,{$limit} ";
		}
		$connection		= Yii::$app->db;
		$sult			= $connection->createCommand($sql)->queryAll();
		return $sult ;
	}
	
	/*
	 * 二位数组根据值中的元素返回键
	*/
	public static function arrayValueToKey($data = array(),$field = NULL,$value = NULL) {
		$key	= array(1);
		if(is_array($data)&&$data)
		{
			foreach($data as $d_key=>$d_value ){
				if(isset($d_value[$field])&&$d_value[$field] == $value )
				{
					return $d_key;
				}
			}
		}
		return $key;
	}
	
	/************************************前台页面需要读取的相关信息E*********************************************/
	/***********************************************************************************************/
	/*
	 * 判断所有大关的所有闯关课程信息并有序排列（基础数据：课程标题，课程KID）
	 * 按照先大关排序，后大关关联课程排序的原则
	 * 返回排序后的数据（数组）信息
	*/
	public static function getAllHabitInfo($create_mode = 0) {
		$hObj		= new BoeSubjectHabit;
		$data		= array();
		$all_type	= Yii::t('boe', 'habit_type');
		foreach($all_type as $a_key=>$a_value)
		{
			$h_where		= array('course_type'=>$a_key,'is_deleted'=>0);
			$data[$a_key]['type_name']		= $all_type[$a_key]['name'];
			$data[$a_key]['course_list']	= $hObj->find(false)->where($h_where)->orderBy('course_order asc')->asArray()->all();
		}
		return $data;	
	}
   /*
	* 获取一门闯关课程的详细信息
	* 返回该课程的详细信息(包含模块信息等)
   */
	public static function getOneHabitInfo($habit_id = NULL) {
		if(!$habit_id)
		{
			return NULL;
		}
		$hObj		= new BoeSubjectHabit;
		$h_where	= array('kid'=>$habit_id,'is_deleted'=>0);
		$sult		= $hObj	->find(false)->where($h_where)->asArray()->one();
		if(!isset($sult['course_id'])&&$sult['course_id'])
		{
			return NULL;
		}
		$c_where			= array('kid'=>$sult['course_id'],'is_deleted'=>0);
		$sult['course_info']	= LnCourse::find(false)->select('release_at,status')->where($c_where)->asArray()->all();
		$sult['mod_res_info']	= self::getModResInfo($sult['course_id']);
		return $sult;
	}
	
	/*
	 * 获取所有组件类型信息
	*/
	public static function getAllComponentInfo() {
		$coObj			= new LnComponent();
		$all_component	= $coObj->find(false)->select('kid,component_code,title,file_type')->where($c_where)->indexBy('kid')->asArray()->all();
		
		return $all_component;
	}
	
	/*
	 * 获取课程模块的类型相关信息
	*/
	public static function getModResInfo($course_id = NULL,$res_key = 0) {
		if(!$course_id)
		{
			return NULL;	
		}
		$rObj	= new LnModRes();//模块资源表
		$cObj	= new LnCourseware();//课件资源表
		$aObj	= new LnCourseactivity();//活动资源表---针对考试
		//
		$r_where = array('course_id'=>$course_id,'is_deleted'=>'0');
		$r_field =" kid,course_id,mod_id,component_id,res_type,sequence_number,courseware_id,courseactivity_id ";
		$all_res = $rObj->find(false)->where($r_where)->select($r_field)->orderBy('sequence_number asc')->asArray()->all();
		
		$sult			= array();
		foreach($all_res as $a_key=>$a_value )
		{
			//类型
			$res_name	= "";
			if($a_value['res_type']==1) //考试等
			{
				$a_where	= array('kid'=>$a_value['courseactivity_id'],'is_deleted'=>'0');
				$a_info		= $aObj->find(false)->where($a_where)->select('activity_name')->asArray()->one();
				$res_name	= $a_info['activity_name'];
			}else{
				$c_where	= array('kid'=>$a_value['courseware_id'],'is_deleted'=>'0');
				$c_info		= $cObj->find(false)->where($c_where)->select('courseware_name')->asArray()->one();
				$res_name	= $c_info['courseware_name'];
			}
			if($res_key == 1)
			{
				$sult[$a_value['mod_id']][$a_value['kid']] = $a_value;
				$sult[$a_value['mod_id']][$a_value['kid']]['res_name'] = $res_name;
			}else{
				$sult[$a_value['mod_id']][$a_key] = $a_value;
				$sult[$a_value['mod_id']][$a_key]['res_name'] = $res_name;
			}
		}
		return $sult;
	}
	
	/*
	 * 获取所有的连队级别信息
	 * 组织树形顶端为 eln_fw_orgnization 
	 * 	orgnization_code ='barrack' 以此为准
	 *  orgnization_name ='2016特训营'
	 * 返回数组
	*/
	public static function getAllTxyLian($has_user = 0,$create_mode = 0) {
		$cache_arr = array(
			'all_lian'	=>1,
			'has_user'	=>$has_user
		);
        $cache_time = 3600;//缓存时间1小时
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$create_mode) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
		if ($create_mode || !$data || !is_array($data)) 
		{//缓存中没有或是强制生成缓存模式时S
			$orgnization_id = Yii::t('boe', 'boe_subject_weilog_Orgnization_Kid');
			$qu = BoeBaseService::getTreeDetail('FwOrgnization', $orgnization_id);
			$data	= array();
			//大区级别
			foreach($qu as $q_key=>$q_value )
			{
				//营一级别
				foreach($q_value['sub_cate'] as $y_key=>$y_value)
				{
					//连一级别
					foreach($y_value['sub_cate'] as $l_key=>$l_value)
					{
						if($has_user)
						{
							$u_where 	= array(
								'orgnization_id'	=>$l_value['kid'],
								'is_deleted' 		=> 0
							);
							$u_field 	='real_name,user_name,kid,orgnization_id,domain_id,company_id';
							$user		= FwUser::find(false)->select($u_field)->where($u_where)->indexby('kid')->asArray()->all();	
							$user_id	= array_keys($user);
							$h_where  	= array(
								'and',
								array('in', 't_kid', $user_id),
								array('=', 'is_deleted', '0')
							);
							$h_field 	='user_kid,user_real_name,user_no,user_id_no,t_kid';
							$user_data  = BoeSubjectHabitUser::find(false)->select($h_field)->where($h_where)->indexby('user_kid')->asArray()->all();
							$data[$l_key]	= array(
								'kid'		=>$l_value['kid'],
								'qu_kid'	=>$q_value['kid'],
								'qu_name'	=>$q_value['orgnization_name'],
								'ying_name'	=>$y_value['orgnization_name'],
								'lian_name'	=>$l_value['orgnization_name'],
								'all_name'	=>$y_value['orgnization_name'].$l_value['orgnization_name'],
								'user_data'	=>$user_data
							);
							unset($where,$user_data);	
						}else
						{
							$data[$l_key]	= array(
								'kid'		=>$l_value['kid'],
								'qu_kid'	=>$q_value['kid'],
								'qu_name'	=>$q_value['orgnization_name'],
								'ying_name'	=>$y_value['orgnization_name'],
								'lian_name'	=>$l_value['orgnization_name'],
								'all_name'	=>$y_value['orgnization_name'].$l_value['orgnization_name'],
							);
						}
						
					}	
				}	
			}
			if ($cache_time) {
				Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
			}
		}//缓存中没有或是强制生成缓存模式时E
		return $data;	
	}
	
	
	/*********************************成绩统计 个人、团队数据信息更新 定时任务等S**********************/
	/*
	 * 获取所有已开放的所有闯关课程的考试成绩信息
	 * 按照课程返回成绩信息数组
	*/
	public static function getAllHabitCourse($is_begin = 0 ) {
		$hObj		= new BoeSubjectHabit;
		$data		= array();
		if($is_begin)
		{
			$h_where	= array('is_begin'=>'1','is_deleted'=>0);
		}else{
			$h_where	= array('is_deleted'=>0);
		}
		$all_course		= $hObj->find(false)->select('course_id,course_name')->where($h_where)->orderBy('course_type asc,course_order asc')->indexBy('course_id')->asArray()->all();
		//下面是测试数据
		/*$all_course	= array(
			'3DE8662A-1399-E1F6-3717-BF96593693D9'	=> '3DE8662A-1399-E1F6-3717-BF96593693D9',
			'6B5BD8B9-C7D7-982D-C635-563E78E16A89'	=> '6B5BD8B9-C7D7-982D-C635-563E78E16A89',
			'4E249462-086D-DD10-E114-F80444651C2D'	=> '4E249462-086D-DD10-E114-F80444651C2D'
		);*/
		return $all_course;
	}
	/*
	* 根据分数判断所获金币数量
	* 返回金币数量
   */
   public static function scoreToBi($score = 0) {
	    if(!$score)
		{
			return 0;
		}
		if($score >= 60&&$score <= 80)
		{
			return 1;
		}
		if($score >= 81&&$score <= 99)
		{
			return 2;
		}
		if($score = 100)
		{
			return 3;
		}
		return 0;
   }
   
	/*
	 * 个人数据信息更新（定时任务调用方法）
	*/
	public static function habitPerUpdate() {
		ini_set('max_execution_time', '0');
		set_time_limit(0);
		//1：判断并录入初始数据信息
		$sult	= self::habitPerExport();
		if($sult == 1)
		{
			//2：获取所有闯关课程下所有学员的成绩信息---坚持有则更新的原则，减少没必要的更新循环
			//2.1:获取所有已开放的闯关课程信息
			$all_begin_course	= self::getAllHabitCourse(1);
			//2.2:获取所有闯关课程下所有学员的成绩信息
			$all_begin_course	= array_keys($all_begin_course);
			$all_per	 		= BoeCourseService::getCourseExam($all_begin_course);
			$all_user			= $all_user2	= array();
			if($all_per&&is_array($all_per))
			{
				//3：整理并换算金币，学习时间
				foreach($all_per as $a_key=>$a_value )
				{
					//3.1:检查当前考试成绩与之前考试成绩（可以多次考试）做比较，
					//=====若是比之前高，更新
					//=====若是相同，不做改变，不更新
					//=====若是比之前低,取之前成绩,不更新
					$examination_score	= $a_value['examination_score'];
					$examObj		= new BoeSubjectHabitExam();
					$e_where		= array(
						'hu_kid'	=>$a_value['hu_kid'],
						'course_id'	=>$a_value['course_id'],
						'is_deleted'=>'0'
					);
					$find_exam		= $examObj->find(false)->where($e_where)->asArray()->one();
					$data_exam		= array();
					if(isset($find_exam['kid'])&&$find_exam['kid'])
					{
						if($find_exam['exam_score'] > 0)
						{
							if($examination_score > $find_exam['exam_score'])
							{
								//更新成绩数据
								$data_exam	= array(
									'kid'			=>$find_exam['kid'],
									'exam_score'	=> $examination_score
								);	
							}
							if($examination_score < $find_exam['exam_score'])
							{
								//取之前成绩
								$examination_score	= $find_exam['exam_score'];
							}
						}else{
							//之前数据为空情况下，当前数据大于0时更新
							if($examination_score > 0)
							{
								//更新成绩数据
								$data_exam	= array(
									'kid'			=>$find_exam['kid'],
									'exam_score'	=> $examination_score
								);
							}	
						}		
					}else
					{
						//新增成绩数据
						$data_exam	= array(
							'hu_kid'		=>$a_value['hu_kid'],
							'course_id'		=>$a_value['course_id'],
							'exam_score'	=>$examination_score
						);
					}
					//存在需要更新的数据
					if($data_exam)
					{
						//return $data_exam;
						$examObj		= new BoeSubjectHabitExam();
						$sult			= $examObj->saveInfo($data_exam);
						if(is_array($sult))
						{
							return false;
						}
					}
					//3.2:整理
					$bi	= self::scoreToBi($examination_score);
					if(isset($a_value['release_at'])&&$a_value['release_at']&&isset($a_value['end_at'])&&$a_value['end_at'])
					{
						$cha_time	= $a_value['end_at'] - $a_value['release_at'] ;
						$cha_time	= round($cha_time/(60*60),4);
						$all_user[$a_value['hu_kid']][$a_value['course_id']]=array(
							'gold_num'		=>$bi,
							'study_time'	=>$cha_time
						);
					}	
				}
				//4:按照学员统计金币之和，学习时间之和并且录入到个人数据信息表
				if($all_user)
				{
					foreach($all_user as $u_key=>$u_value )
					{
						$sum_gold_num	= $sum_study_time = 0;
						foreach($u_value as $u_key2=>$u_value2)
						{
							$sum_gold_num = $sum_gold_num + $u_value2['gold_num'];
							$sum_study_time = $sum_study_time + $u_value2['study_time'];
						}
						//4.2 检查并更新个人数据信息
						$perObj		= new BoeSubjectHabitPer();
						$p_where	= array('hu_kid'=>$u_key,'is_deleted'=>'0');
						$find		= $perObj->find(false)->where($p_where)->asArray()->one();
						//$count_time	= time();
						
						/***************************************************/
						//4.2.2 写行动计划（作业）的额外加一币
						//=====统计写行动计划的课程个数，1个算一个金币
						$work_gold_num	= self::countHabitUserWork($u_key);
						/**************************************************/
						
						$data		= array(
							'hu_kid'		=>$u_key,
							'is_publish'	=>0,
							'gold_num'		=>$sum_gold_num + $work_gold_num,
							'study_time'	=>round($sum_study_time,2)
						);
						if(isset($find['kid'])&&$find['kid'])
						{
							$data['kid']		= $find['kid'];
							//$data['gold_num2']	= $find['gold_num'];
							//$data['study_time2']  = $find['study_time'];
							unset($data['hu_kid']);
						}
						//boeBase::dump($data);
						$perObj		= new BoeSubjectHabitPer();
						$sult		= $perObj->saveInfo($data);
						if(is_array($sult))
						{
							return false;
						}
						//boeBase::dump($sult);
						/*$all_user2[$u_key]	= array(
							'gold_num'		=>$sum_gold_num,
							'study_time'	=>round($sum_study_time,2)
						);*/
					}
				}	
			}
			return true;	
		}
		return false;
	}
	
	/*
	 * 根据员工ID来获取对应的完成课程行动计划的数量
	*/
	public static function countHabitUserWork($hu_kid = NULL){
		if(!$hu_kid)
		{
			return 0;
		}
		$find	= BoeSubjectHabitUser::find(false)->where(array('kid'=>$hu_kid,'is_deleted'=>'0'))->asArray()->one();
		if(!isset($find['kid']))
		{
			return 0;
		}
		$course_id = self::getAllHabitCourse(1);
		$course_id = array_keys($course_id);
		$course_id	= "'".implode("','",$course_id)."'";
		$r	= LnModRes::realTableName();
		$c	= LnResComplete::realTableName();
		$h	= BoeSubjectHabitUser::realTableName();
		$component_id	= "00000000-0000-0000-0000-000000000013";
		$sql			= "
		select h.kid,c.user_id,c.course_id,h.user_no,h.user_real_name from {$r} r
left join {$c} c on r.kid = c.mod_res_id
left join {$h} h on c.user_id = h.user_kid
where r.component_id = '{$component_id}' and r.course_id in ({$course_id})
and c.user_id ='{$find['user_kid']}'
and c.is_passed ='1' group by c.course_id;
		";
		//return $sql;
		$connection		= Yii::$app->db;
		$data			= array();
		$sult			= $connection->createCommand($sql)->queryAll();
		return count($sult);
	}
	
	
	/*
	 * 团队数据信息发布（后台调用）
	*/
	public static function habitOrgPublish() {
		$o				= BoeSubjectHabitOrg::realTableName();
		$sql 			= "update {$o} set is_publish = 1,gold_num2 = gold_num,gold_average_num2 = gold_average_num,study_time2 = study_time;";
		$connection		= Yii::$app->db;
		$sult			= $connection->createCommand($sql)->execute();
		if(is_array($sult))
		{
			return $sult;
		}
		return 1;
	}
	
	/*
	 * 个人数据信息发布（后台调用）
	*/
	public static function habitPerPublish() {
		$p				= BoeSubjectHabitPer::realTableName();
		$sql 			= "update {$p} set is_publish = 1,gold_num2 = gold_num,study_time2 = study_time ;";
		$connection		= Yii::$app->db;
		$sult			= $connection->createCommand($sql)->execute();
		if(is_array($sult))
		{
			return $sult;
		}
		return 1;
	}
	
	/*
	 * 个人数据信息更改（后台调用）
	*/
	public static function habitPerEdit($data = array()) {
		if(isset($data['kid'])&&$data['kid'])
		{
			//return $data;
			$perObj	= new BoeSubjectHabitPer();
			$sult	= $perObj->saveInfo($data);
			if (is_array($sult) || $sult === false) {
				return $sult;
			}
		}
		return 1;
	}
	
	
	/*
	 * 团队数据信息更新（定时任务调用方法）
	*/
	public static function habitOrgUpdate() {
		$sult	= self::habitOrgExport();
		if($sult == 1)
		{
			set_time_limit(0);
			$p	= BoeSubjectHabitPer::realTableName();
			$u	= BoeSubjectHabitUser::realTableName();
			$sql 			= "
select u.t_orgnization_id,sum(p.gold_num) as gold_num,
sum(p.study_time) as study_time,(sum(p.gold_num)/count(u.kid)) as gold_average_num
from {$p} p left join {$u} u on p.hu_kid = u.kid 
where p.is_deleted = '0' and u.is_deleted='0' and u.is_test = '0'
group by u.t_orgnization_id;";
			$connection		= Yii::$app->db;
			$data			= array();
			$sult			= $connection->createCommand($sql)->queryAll();
			foreach($sult as $d_key=>$d_value)
			{
				//if($d_value['gold_num'] > 0)
				//{
					$orgObj		= new BoeSubjectHabitOrg();
					$o_where	= array(
						't_orgnization_id'		=>$d_value['t_orgnization_id'],
						'is_deleted'			=>'0'
						);
					$find 		= $orgObj->find(false)->where($o_where)->asArray()->one();
					$data		= array(
						't_orgnization_id'	=>$d_value['t_orgnization_id'],
						'gold_num'			=>$d_value['gold_num'],
						'study_time'		=>$d_value['study_time'],
						'gold_average_num'	=>round($d_value['gold_average_num'],2),
						'is_publish'		=>0
					);
					if(isset($find['kid'])&&$find['kid'])
					{
						$data['kid']					= $find['kid'];
						//$data['gold_num2']				= $find['gold_num'];
						//$data['study_time2']				= $find['study_time'];
						//$data['gold_average_num2']		= $find['gold_average_num'];
						unset($data['t_orgnization_id']);
					}
					$orgObj		= new BoeSubjectHabitOrg();
					$sult		= $orgObj->saveInfo($data);
					if(is_array($sult))
					{
						return false;
					}	
				//}	
			}
			return true;	
		}
		return false;	
	}
	/*********************************成绩统计 个人、团队数据信息更新 定时任务等E**********************/
	
	
	/*********************************一些基础信息的导入S***********************************/
	/*
	 * 特训营学员身份证号与员工编号逻辑关系初始数据信息批量导入
	*/
	public static function habitUserExport() {
		set_time_limit(0);
		//初始导入数据信息的获取与组合
		$sql 			= "select * from a_habit_user u where NOT EXISTS ( select 1 from eln_boe_subject_habit_user h where h.user_no = u.user_no );";
		$connection		= Yii::$app->db;
		$data			= array();
		$sult			= $connection->createCommand($sql)->queryAll();
		foreach($sult as $d_key=>$d_value)
		{
			$uObj		= new BoeSubjectHabitUser();
			$f_where	= array(
				'user_no'		=>$d_value['user_no'],
				'user_id_no'	=>$d_value['user_id_no'],
				'is_deleted'	=>'0'
				);
			$find 		= $uObj->find(false)->where($f_where)->asArray()->one();
			if(!$find)
			{
				$data		= self::getUserRelation($d_value['user_no'],$d_value['user_id_no']);
				if(is_array($data))
				{
					$uObj->saveInfo($data);	
					unset($uObj);
				}
			}		
		}
		return 1;
	}
	/*
	 * 获取所有特训营学员逻辑关系数据信息
	*/
	public static function getAllHabitUser() {
		$u 				= BoeSubjectHabitUser::realTableName();
		$u_field		= "kid,user_kid,user_real_name,user_no,user_id_no,t_kid,t_orgnization_id";
		$sql 			= "select {$u_field} from {$u} order by created_at asc;";
		$connection		= Yii::$app->db;
		$data			= array();
		$sult			= $connection->createCommand($sql)->queryAll();
		return $sult;
	}
	/*
    * 个人数据信息表初始信息录入
   */
   public static function habitPerExport() {
	   	set_time_limit(0);
		$u 				= BoeSubjectHabitUser::realTableName();
		$p 				= BoeSubjectHabitPer::realTableName();
		$u_field		= "kid,user_kid,user_real_name,user_no,user_id_no,t_kid,t_orgnization_id";
		$sql 			= "select {$u_field} from {$u} u where NOT EXISTS ( select 1 from {$p} p where p.hu_kid = u.kid );";
		$connection		= Yii::$app->db;
		$data			= array();
		$all_user		= $connection->createCommand($sql)->queryAll();
		//return $all_user;
		foreach($all_user as $a_key=>$a_value)
		{
			$per_obj  = new  BoeSubjectHabitPer();
			$data		=array(
				'hu_kid'			=>$a_value['kid'],
			);
			$sult	= $per_obj->saveinfo($data);
			//return $sult;
			unset($per_obj);
		} 
	   return 1;   
   }
   /*
    * 团队数据信息表初始信息录入
   */
   public static function habitOrgExport() {
	   $org_obj	= new  BoeSubjectHabitOrg();
	   $sult	= $org_obj->find()->asArray()->one();
	   if(!$sult)
	   {//无数据的情况下建立初始数据S
	   		$all_lian = self::getAllTxyLian();
			foreach($all_lian as $a_key=>$a_value)
			{
				$org_obj  = new  BoeSubjectHabitOrg();
				$data		=array(
					't_orgnization_id'	=>$a_key
				);
				$org_obj->saveinfo($data);
			}
	   }//无数据的情况下建立初始数据E
	   return 1;   
   }
	/*********************************一些基础信息的导入E************************************/
	
	
/****************************************************************************************************************/	
	/*
	 * 获取用户头像信息
	 * 如果存在返回路径地址，不存在返回默认头像地址
	*/ 
	public static function userImg($uid = NULL) {
		if(!$uid)
		{
			return NULL;
		}
		$uObj		= new fwUser;
		$u_where	= array('kid'=>$uid,'is_deleted'=>0);
		$sult		= $uObj ->find(false)->select('kid,thumb')->where($u_where)->asArray()->one();
		return $sult;
	}
	/*
	 * 根据员工编号和员工身份证号来获取员工现在的身份信息和原来的特训营身份信息
	 * 返回如果信息有误返回NULL如果信息真实返回数组
	*/
	public static function getUserRelation($user_no = NULL,$user_id_no = NULL) {
		if(!$user_no || !$user_id_no)
		{
			return -89;
		}
		$fwObj		= new FwUser();
		//根据员工编号获取对应员工信息
		$u_where	= array('user_name'=>$user_no,'is_deleted'=>'0');
		$u_info		= $fwObj->find('kid,user_name,real_name')->where($u_where)->asArray()->one();
		//根据员工身份证号获取对应的特训营学员
		$t_where	= array('user_name'=>$user_id_no,'is_deleted'=>'0');
		$t_info		= $fwObj->find('kid,user_name')->where($t_where)->asArray()->one();
		if(isset($u_info['kid'])&&$u_info['kid']&&isset($t_info['kid'])&&$t_info['kid'])
		{
			//BoeBase::dump($data);
			if($t_info['domain_id']=='3C94C49F-2E4B-36BE-9464-BF5192A3BB9F')
			{
				$data	= array(
					'user_kid'			=>$u_info['kid'],
					'user_real_name'	=>$u_info['real_name'],
					'user_no'			=>$user_no,
					'user_id_no'		=>$user_id_no,
					't_kid'				=>$t_info['kid'],
					't_orgnization_id'	=>$t_info['orgnization_id']
				);
				return $data;
			}else
			{
				return -87;
			}
			
		}else
		{
			return -88;
		}
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
	  * 添加学员逻辑关系信息
	 */
	 public static function addHabitUser($params = array())
	 {
		 if(!isset($params['user_no']) || !$params['user_no'])
		 {
			 return -95;	 
		 }
		 if(!isset($params['user_id_no']) || !$params['user_id_no'])
		 {
			 return -94;	 
		 }
		 $where_u 	= array('user_no'=>$params['user_no'],'is_deleted'=>0);
		 $uObj 		= new BoeSubjectHabitUser();
		 $find_u 	= $uObj->find(false)->where($where_u)->asArray()->one();
		 if(isset($find_u['kid'])&&$find_u['kid'])
		 {
			 return -93;
		 }
		 $data		= self::getUserRelation($params['user_no'],$params['user_id_no']);
		 $data['is_test']	= $params['is_test'];
		 if(!is_array($data))
		 {
			 return -92;//该员工信息有误
		 }
		  //return $data;
		 $uObj 		= new BoeSubjectHabitUser();
		 $db_sult 		= $uObj->saveInfo($data);
		 if (is_array($db_sult) || $db_sult === false) {
				return -91;
		 }
		 return $db_sult;
	 }
	 /*
	  * 编辑学员逻辑关系信息
	 */
	public static function updateHabitUser($params = array())
	{ 
	 	if(!isset($params['user_no']) || !$params['user_no'])
		 {
			 return -95;	 
		 }
		 if(!isset($params['user_id_no']) || !$params['user_id_no'])
		 {
			 return -94;	 
		 }
		if(!isset($params['kid']) || !$params['kid'])
		{
			 return -90;
		}
		$where_u = array(
			'and',
			array('=', 'user_no', $params['user_no']),
			array('<>', 'kid', $params['kid']),
			array('=', 'is_deleted', '0')
		);
		$uObj 		= new BoeSubjectHabitUser();
		$find_u 	= $uObj->find(false)->where($where_u)->asArray()->one();
		if(isset($find_u['kid'])&&$find_u['kid'])
		{
			return -93;
		}
		$data		= self::getUserRelation($params['user_no'],$params['user_id_no']);
		$data['kid']= $params['kid'];
		$data['is_test']	= $params['is_test'];
		if(!is_array($data))
		{
			return -92;//该员工信息有误
		}
		$uObj 		= new BoeSubjectHabitUser();
		$db_sult 		= $uObj->saveInfo($data);
		if (is_array($db_sult) || $db_sult === false) {
				return -91;
		}
		return $db_sult;		
	}
	
	/*
	 * 根据课程ID获取课程模块的点赞信息
	 * 返回以模块ID为索引的数组
	*/
	public static function getCourseLikeInfo($course_id = NULL)
	{
		if(!$course_id)
		{
			return NULL;
		}
		$modObj		= new BoeSubjectHabitMod();
		$m_where	= array('course_id'=>$course_id ,'is_deleted'=>'0');
		$all_mod	= $modObj->find(false)->select('course_id,mod_id,res_id,like_num')->where($m_where)->indexBy('res_id')->asArray()->all();
		return $all_mod;
	}
	
	 /*
     * 课程模块点赞
     * Input:$uid String 
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID和mod_id判断有没有当前对应的用户对于指定的mod_id有没有点过赞,如果有返回1,判断依据是缓存中有无记录
      2、更新数据库;
      3、添加缓存标记;
     */
    public static function likeHabit($course_id = NULL ,$mod_id = NULL,$res_id = NULL , $user_id = NULL ) {
        $likeValue = 0;
        $habit_data = array();
        $sult = array(
            'likeValue' => 0,
            'errorCode' => '',
            'likeNum' => 0,
        );
		$md5_id = $course_id.$mod_id.$res_id;
        $cache_name = __METHOD__ . md5('_habit_mod_like_' . serialize($md5_id));
        $habit_data = Yii::$app->cache->get($cache_name);
        if (isset($habit_data[$user_id]) && $habit_data[$user_id] == 1) {
            $likeValue = -99;
        } else {
            if ($course_id&&$mod_id&&$user_id) {
                $where = array('mod_id' => $mod_id,'res_id'=>$res_id);
                $modObj = new BoeSubjectHabitMod();
                $find = $modObj->find(false)->where($where)->asArray()->one();
                if ($find) {
                    $data = array(
                        'kid' 		=> $find['kid'],
                        'like_num' 	=> $find['like_num'] + 1
                    );
                    $likeValue = $modObj->saveInfo($data);
                    if ($likeValue) {
                        $sult['likeNum'] = $data['like_num'];
                        $likeValue = 1;
                    } else {
                        $likeValue = -98;
                    }
                }
				else
				{
					$data = array(
                        'course_id' =>$course_id,
						'mod_id'	=>$mod_id,
						'res_id'	=>$res_id,
						'like_num' 	=> 1
                    );
					$likeValue = $modObj->saveInfo($data);
                    if ($likeValue) {
                        $sult['likeNum'] = $data['like_num'];
                        $likeValue = 1;
                    } else {
                        $likeValue = -97;
                    }
				}
            }else{
				$likeValue = -96;
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
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $likeValue;
                    break;
            }
        } else {
			$habit_data[$user_id] = 1;
            Yii::$app->cache->set($cache_name, $habit_data); // 设置缓存
        }
        return $sult;
    }
	/*
	 * 新增特训营学员信息
	*/
	public static function addTxyUser() {
		//set_time_limit(0);
		//初始导入数据信息的获取与组合
		$sql 			= "select * from a_habit_user_new";
		$connection		= Yii::$app->db;
		$data			= $user_all_no = array();
		$sult			= $connection->createCommand($sql)->queryAll();
		$org_obj		= new FwOrgnization();
		$user_obj		= new FwUser();
		foreach($sult as $d_key=>$d_value)
		{
			$new_value		= explode("\\",$d_value['user_txy_area']);
			$new_key		= md5($new_value[2].$new_value[3]);
			$o_where		= array('orgnization_name'=>$new_value[2],'is_deleted'	=>'0');
			$org_info		= $org_obj->find(false)->select('kid')->where($o_where)->asArray()->one();
			if(isset($org_info['kid'])&&$org_info['kid'])
			{
				$o_where2		= array(
					'orgnization_name'=>$new_value[3],
					'parent_orgnization_id'=>$org_info['kid'],
					'is_deleted'	=>'0');
				$org_info2		= $org_obj->find(false)->select('kid')->where($o_where2)->asArray()->one();
				$data[$new_key]	= array(
					'org_area'					=>$d_value['user_txy_area'],
					'orgnization_id'			=>$org_info2['kid'],
					'parent_orgnization_id'		=>$org_info['kid']
				);
			}
			$u_where	= array('user_no'=>$d_value['user_no'],'is_deleted'	=>'0');	
			$user_info[]	= $user_obj->find(false)->select('kid,gender,email')->where($u_where)->asArray()->one();
			//$user_all_no[] = $d_value['user_no'];		
		}
		//获取所有新增学员对应的员工信息
		return $user_info;
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
	 * 个人数据信息更新---查询bug专用
	*/
	public static function habitPerUpdate2() {
		ini_set('max_execution_time', '0');
		set_time_limit(0);
		//1：判断并录入初始数据信息
		$sult	= self::habitPerExport();
		if($sult == 1)
		{
			//2：获取所有闯关课程下所有学员的成绩信息---坚持有则更新的原则，减少没必要的更新循环
			//2.1:获取所有已开放的闯关课程信息
			$all_begin_course	= self::getAllHabitCourse(1);
			//2.2:获取所有闯关课程下所有学员的成绩信息
			$all_begin_course	= array_keys($all_begin_course);
			$all_per	 		= BoeCourseService::getCourseExam($all_begin_course);
			//return count($all_per);
			$all_user			= $all_user2	= array();
			if($all_per&&is_array($all_per))
			{
				//3：整理并换算金币，学习时间
				foreach($all_per as $a_key=>$a_value )
				{
					//3.1:检查当前考试成绩与之前考试成绩（可以多次考试）做比较，
					//=====若是比之前高，更新
					//=====若是相同，不做改变，不更新
					//=====若是比之前低,取之前成绩,不更新
					$examination_score	= $a_value['examination_score'];
					$examObj		= new BoeSubjectHabitExam();
					$e_where		= array(
						'hu_kid'	=>$a_value['hu_kid'],
						'course_id'	=>$a_value['course_id'],
						'is_deleted'=>'0'
					);
					$find_exam		= $examObj->find(false)->where($e_where)->asArray()->one();
					$data_exam		= array();
					if(isset($find_exam['kid'])&&$find_exam['kid'])
					{
						if($find_exam['exam_score'] > 0)
						{
							if($examination_score > $find_exam['exam_score'])
							{
								//更新成绩数据
								$data_exam	= array(
									'kid'			=>$find_exam['kid'],
									'exam_score'	=> $examination_score
								);	
							}
							if($examination_score < $find_exam['exam_score'])
							{
								//取之前成绩
								$examination_score	= $find_exam['exam_score'];
							}
						}else{
							//之前数据为空情况下，当前数据大于0时更新
							if($examination_score > 0)
							{
								//更新成绩数据
								$data_exam	= array(
									'kid'			=>$find_exam['kid'],
									'exam_score'	=> $examination_score
								);
							}	
						}		
					}else
					{
						//新增成绩数据
						$data_exam	= array(
							'hu_kid'		=>$a_value['hu_kid'],
							'course_id'		=>$a_value['course_id'],
							'exam_score'	=>$examination_score
						);
					}
					//存在需要更新的数据
					if($data_exam)
					{
						//return $data_exam;
						$examObj		= new BoeSubjectHabitExam();
						$sult			= $examObj->saveInfo($data_exam);
						if(is_array($sult))
						{
							return false;
						}
					}
					//3.2:整理
					$bi	= self::scoreToBi($examination_score);
					if(isset($a_value['release_at'])&&$a_value['release_at']&&isset($a_value['end_at'])&&$a_value['end_at'])
					{
						$cha_time	= $a_value['end_at'] - $a_value['release_at'] ;
						$cha_time	= round($cha_time/(60*60),4);
						$all_user[$a_value['hu_kid']][$a_value['course_id']]=array(
							'gold_num'		=>$bi,
							'study_time'	=>$cha_time
						);
					}	
				}
				//4:按照学员统计金币之和，学习时间之和并且录入到个人数据信息表
				if($all_user)
				{
					foreach($all_user as $u_key=>$u_value )
					{
						$sum_gold_num	= $sum_study_time = 0;
						foreach($u_value as $u_key2=>$u_value2)
						{
							$sum_gold_num = $sum_gold_num + $u_value2['gold_num'];
							$sum_study_time = $sum_study_time + $u_value2['study_time'];
						}
						//4.2 检查并更新个人数据信息
						$perObj		= new BoeSubjectHabitPer();
						$p_where	= array('hu_kid'=>$u_key,'is_deleted'=>'0');
						$find		= $perObj->find(false)->where($p_where)->asArray()->one();
						//$count_time	= time();
						
						/***************************************************/
						//4.2.2 写行动计划（作业）的额外加一币
						//=====统计写行动计划的课程个数，1个算一个金币
						$work_gold_num	= self::countHabitUserWork($u_key);
						/**************************************************/
						
						$data		= array(
							'hu_kid'		=>$u_key,
							'is_publish'	=>0,
							'gold_num'		=>$sum_gold_num + $work_gold_num,
							'work_gold_num'	=>$work_gold_num,
							'study_time'	=>round($sum_study_time,2)
						);
						if(isset($find['kid'])&&$find['kid'])
						{
							$data['kid']		= $find['kid'];
							//$data['gold_num2']	= $find['gold_num'];
							//$data['study_time2']  = $find['study_time'];
							//unset($data['hu_kid']);
						}
						//return $data;
						boeBase::dump($data);
						//$perObj		= new BoeSubjectHabitPer();
						//$sult		= $perObj->saveInfo($data);
						//if(is_array($sult))
						//{
						//	return false;
						//}
						/*$all_user2[$u_key]	= array(
							'gold_num'		=>$sum_gold_num,
							'study_time'	=>round($sum_study_time,2)
						);*/
					}
				}	
			}
			//return true;	
		}
		//return false;
	}

}

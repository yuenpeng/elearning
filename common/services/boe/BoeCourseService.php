<?php

namespace common\services\boe;

use common\base\BoeBase;
use common\models\framework\FwUser;
use common\models\boe\BoeSubjectHabitUser;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseMods;
use common\models\learning\LnModRes;
use common\models\learning\LnCourseactivity;
use common\models\learning\LnExamination;
use common\models\learning\LnExaminationResultUser;
use common\models\learning\LnCourseEnroll;
use common\models\learning\LnCourseReg;
use common\models\learning\LnInvestigationResult;
use common\models\learning\LnInvestigationQuestion;
use common\models\learning\LnInvestigation;
use common\models\learning\LnCourseTeacher;
use common\models\learning\LnCertification;
use common\models\learning\LnCourseCertification;
use common\models\learning\LnTeacher;
use common\models\learning\LnCourseMarkSummary;
use common\models\learning\LnResourceDomain;
use common\models\framework\FwUserPosition;
use common\models\framework\FwRolePermission;
use common\models\framework\FwPermission;
use common\models\framework\FwTag;
use common\models\framework\FwTagReference;
use common\services\boe\FrontService;
use common\services\framework\DictionaryService;
use yii\db\Query;
use yii\db\Expression;
use Yii;
use yii\helpers\Url;
use common\models\framework\FwOrgnization;

/**

 * User: xinpeng
 * Date: 2016/9/23
 * Time: 14:10
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeCourseService  {

    static $loadedObject    = array();
    static $initedLog       = array();
    static $currentLog      = array();
    static $host            = "http://u.boe.com";
    static $rurl            = "http://u.boe.com/api/b1/course/rurl?";
    static $course_period_unit      = array(1=>'分钟',2=>'小时',3=>'天');
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
    );
    
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
     * 课程时长处理
    */
    public static function boeTime($time_num = 0, $time_unit = 0) {
        //1=>'分钟',2=>'小时',3=>'天'
        //时：分 00:00
        $hour = $min = 0;
        switch($time_unit)
        {
            case 1://时长是分钟
                if($time_num>=60)
                {
                    $hour   =  floor($time_num / 60) ;
                    $min    =  $time_num % 60;
                }else{
                    $min    =  $time_num;
                }
                break;
            case 2://时长是小时
                $hour   = $time_num;
                break;
            case 3://时长是天
                $hour   = $time_num * 24;
                break;  
        }
        $min    = $min==0?"00":($min>0&&$min<10?"0".$min:$min);
        $time   = $hour>0?($hour.":".$min):"0:".$min;
        return $time;   
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
    /**************************************************面授课程信息的更改开始*******************************/
    /*
     * 根据课程ID获取该课程讲师的信息
    */
    public static function getCourseTeacher($course_id = NULL)
    {
        if(!$course_id)
        {
            return NULL;
        }
        $u  = FwUser::realTableName();
        $t  = LnTeacher::realTableName();
        $ct = LnCourseTeacher::realTableName();
        $sql    = "
        SELECT t.kid,t.teacher_type,t.teacher_name,t.company_id,t.user_id,u.real_name,u.email FROM {$u} u
INNER JOIN {$t} t ON u.kid = t.user_id and t.is_deleted ='0'
INNER JOIN {$ct} ct ON ct.teacher_id = t.kid and ct.is_deleted ='0' and ct.`status`='1'
where ct.course_id ='{$course_id}'
        ";
        $connection     = Yii::$app->db;
        $data           = $connection->createCommand($sql)->queryAll();
        return $data;
    }
    /*
     * 根据课程ID获取该课程标签的信息
    */
    public static function getCourseTag($course_id = NULL,$status = NULL)
    {
        if(!$course_id)
        {
            return NULL;
        }
        $r          = FwTagReference::realTableName();
        $t          = FwTag::realTableName();
        $where      = " and r.subject_id ='{$course_id}' "; 
        if(in_array($status,array('1','2')))
        {
            $where.= " and r.`status` = '{$status}' ";
        }
        $sql    = "
            SELECT r.kid,r.tag_id,r.tag_category_id,r.tag_value,r.subject_id,r.`status`,r.start_at,r.end_at 
FROM {$r} r INNER JOIN {$t} t ON r.tag_id = t.kid
where t.is_deleted = '0' {$where};
        ";
        $connection     = Yii::$app->db;
        $data           = $connection->createCommand($sql)->queryAll();
        return $data;
    }
    
    /*
     * 根据课程ID获取该课程课程证书的信息
    */
    public static function getCourseCertification($course_id = NULL,$status = NULL)
    {
        if(!$course_id)
        {
            return NULL;
        }
        $c          = LnCertification::realTableName();
        $cc         = LnCourseCertification::realTableName();
        $where      = " and cc.course_id ='{$course_id}' "; 
        if(in_array($status,array('1','2')))
        {
            $where.= " and cc.`status` = '{$status}' ";
        }
        $sql    = "
SELECT cc.kid,cc.course_id,c.certification_name,cc.certification_id,cc.`status` FROM
eln_ln_course_certification cc
INNER JOIN eln_ln_certification c ON cc.certification_id = c.kid
where cc.is_deleted = '0' {$where};
        ";
        $connection     = Yii::$app->db;
        $data           = $connection->createCommand($sql)->queryOne();
        return $data;
    }
    
    /*
     * 根据课程ID以及修改后的课程标签信息来更新课程标签信息
    */
    public static function manageCourseTag($course_id = NULL,$tag = array())
    {
        if(!$course_id)
        {
            return NULL;
        }
        //第一步停用该课程原来所有的标签
        $r_obj      = new FwTagReference();
        $sult1      = $r_obj->updateAll(array('`status`'=>'2'), " subject_id = '{$course_id}'");
        //第二步更新课程标签，原来有的修改状态，没有的增加标签
        if($tag)
        {
            $t_obj      = new FwTag();
            foreach($tag as $t_key=>$t_value)
            {
                $find   =  $r_obj->findOne(array('subject_id'=>$course_id,'tag_value'=>$t_value,'is_deleted'=>'0'));
                if(isset($find['kid']))
                {
                    //存在的直接修改状态值
                    $sult2 = $r_obj->updateAll(array('`status`'=>'1'), " kid = '{$find['kid']}'");
                }else{
                    //不存在的增加新标签
                    $find2  =  $t_obj->findOne(array('tag_value'=>$t_value,'is_deleted'=>'0'));
                    $r_obj                  = new FwTagReference();
                    $r_obj->tag_id          = $find2['kid'];
                    $r_obj->tag_category_id = $find2['tag_category_id'];
                    $r_obj->tag_value       = $find2['tag_value'];
                    $r_obj->subject_id      = $course_id;
                    $r_obj->start_at        = time();
                    $sult3                  = $r_obj->save();
                }
                unset($find,$find2,$sult2,$sult3);
            }   
        }
        return true;
    }
    
    
    /***********************************************面授课程信息的更改结束*******************************/
    
    /*****************************闯关专区使用开始****************************************/
    /*
     * 成绩（总分）排名统计排行榜 TOP (limit) 5 == ==暂时停用
    */
    public static function getCourseExamTop($params = array(),$create_mode = 1){
        if(!isset($params['course_id']) || !$params['course_id'] ||!is_array($params['course_id']))
        {
            return NULL;
        }
        $limit      = isset($params['limit'])&&$params['limit']?$params['limit']:'';
        $limit      = $limit?" limit 0,{$limit} ":"";
        $course_id  = "'".implode("','",$params['course_id'])."'";
        $cache_arr  = $params;
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$create_mode) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($create_mode || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S        
          $a    = LnCourseactivity::realTableName();
          $e    = LnExamination::realTableName();
          $c    = LnCourse::realTableName();
          $m    = LnCourseMods::realTableName();
          $l    = LnCourseEnroll::realTableName();
          $cr   = LnCourseReg::realTableName();
          $u    = FwUser::realTableName();
          $h    = BoeSubjectHabitUser::realTableName();
          $r    = LnExaminationResultUser::realTableName();
          if(isset($params['orgnization_id'])&&$params['orgnization_id'] ==1 )
          {//连队排名统计S
          $sql  = "
select h.t_orgnization_id as orgnization_id, sum(r.examination_score) as total_score,count(h.user_kid) as user_num ,(sum(r.examination_score)/count(h.user_kid)) as average_score
from {$a} a
join {$e} e on a.object_id = e.kid and e.is_deleted = '0'
join {$c} c on a.course_id = c.kid and c.is_deleted = '0' and c.kid in ({$course_id})
join {$m} m on a.mod_id = m.kid and m.is_deleted = '0'
join {$cr} cr on a.course_id = cr.course_id and cr.reg_state = '1' and cr.is_deleted = '0'
join {$h} h on cr.user_id = h.user_kid and h.is_deleted = '0'
left join {$r} r on cr.user_id = r.user_id and r.examination_id = e.kid and r.examination_status = '2' and r.result_type='1' and a.kid = r.courseactivity_id and r.is_deleted = '0'
where a.object_type = 'examination' and a.is_deleted = '0'
and exists( select 1 from {$r} r1 where r1.examination_id = e.kid and r1.examination_status = '2' and r1.result_type='1' and a.kid = r1.courseactivity_id and r1.is_deleted = '0')
group by orgnization_id order by total_score desc {$limit};";  
          }//连队排名统计E
          if(isset($params['user_id'])&&$params['user_id'] ==1)
          {//个人排名统计S
             $org_where ="";
             if(isset($params['orgnization_id'])&&$params['orgnization_id'])
             {
                 $org_where = " and h.t_orgnization_id = '{$params['orgnization_id']}' ";
             }
            $sql    = "
select h.user_kid as user_id, sum(r.examination_score) as total_score
from {$a} a
join {$e} e on a.object_id = e.kid and e.is_deleted = '0'
join {$c} c on a.course_id = c.kid and c.is_deleted = '0' and c.kid in ({$course_id})
join {$m} m on a.mod_id = m.kid and m.is_deleted = '0'
join {$cr} cr on a.course_id = cr.course_id and cr.reg_state = '1' and cr.is_deleted = '0'
join {$h} h on cr.user_id = h.user_kid and h.is_deleted = '0'
left join {$r} r on cr.user_id = r.user_id and r.examination_id = e.kid and r.examination_status = '2' and r.result_type='1' and a.kid = r.courseactivity_id and r.is_deleted = '0'
where a.object_type = 'examination' and a.is_deleted = '0' 
and exists( select 1 from {$r} r1 where r1.examination_id = e.kid and r1.examination_status = '2' and r1.result_type='1' and a.kid = r1.courseactivity_id and r1.is_deleted = '0')
group by user_id order by total_score desc {$limit};";  
          }//个人排名统计E
          //////////////////////////////////////////////////////////
          //return $sql;
          $connection       = Yii::$app->db;
          $data         = $connection->createCommand($sql)->queryAll();
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;   
    }
    /*
     * 获取闯关课程考试成绩信息=====正在使用
     * eln_ln_courseactivity a 活动资源表  course_id object_id  mod_id
     * a.object_type = 'examination'
     * a.is_deleted = '0'
     * eln_ln_examination e 考试表 e.kid==a.object_id  e.is_deleted = '0'              
     * eln_ln_course c 课程信息表 c.kid==a.course_id   c.is_deleted = '0'
     * eln_ln_course_mods m 课程模块表 m.kid==a.mod_id  m.is_deleted = '0'
     * eln_ln_course_enroll l 课程报名表 a.course_id==l.course_id  l.approved_state = '1'  l.is_deleted = '0'
     * eln_fw_user u    用户表 u.kid==l.user_id u.is_deleted = '0'
     * eln_boe_subject_habit_user h 用户逻辑关系表 h.user_kid==l.user_id h.is_deleted = '0'
     * eln_ln_examination_result_user r 个人考试结果表 
     *          l.user_id = r.user_id 
     *          r.examination_id = e.kid  
     *          r.examination_status = '2' 
     *          r.result_type='1'
     *          a.kid = r.courseactivity_id
     *          r.is_deleted = '0'
    */
    public static function getCourseExam($course_id = NULL,$user_id = NULL,$create_mode = 1){
        if(!$course_id)
        {
            return NULL;
        }
        $cache_arr = array(
            'course_id' =>$course_id,
            'user_id'   =>$user_id
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$create_mode) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($create_mode || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S        
          $a    = LnCourseactivity::realTableName();
          $e    = LnExamination::realTableName();
          $c    = LnCourse::realTableName();
          $m    = LnCourseMods::realTableName();
          $l    = LnCourseEnroll::realTableName();
          $cr   = LnCourseReg::realTableName();
          $u    = FwUser::realTableName();
          $h    = BoeSubjectHabitUser::realTableName();
          $r    = LnExaminationResultUser::realTableName();
          $c_course = $c_user   = "";
          if(is_array($course_id))
          {
              $course_id    = "'".implode("','",$course_id)."'";
              $c_course     = " c.kid in ({$course_id})";
          }else{
              $c_course     = " c.kid = '{$course_id}' ";
          }
          if($user_id)
          {
                if(is_array($user_id))
                {
                    $user_id        = "'".implode("','",$user_id)."'";
                    $c_user         = " and h.user_kid in ({$user_id})";
                }else{
                    $c_user         = " and h.user_kid = '{$user_id}' ";
                }
          }
          //return $c_where;
          //////////////////////////////////////////////////////////
          $sql  = "
select c.kid as course_id, c.course_name,c.release_at,m.kid as mod_id, m.mod_name, h.user_kid as user_id, a.activity_name, h.kid as hu_kid ,h.user_real_name as real_name,h.user_no, h.t_kid,r.examination_score, r.start_at, r.end_at
from {$a} a
join {$e} e on a.object_id = e.kid and e.is_deleted = '0'
join {$c} c on a.course_id = c.kid and c.is_deleted = '0' and {$c_course}
join {$m} m on a.mod_id = m.kid and m.is_deleted = '0'
join {$cr} cr on a.course_id = cr.course_id and cr.reg_state = '1' and cr.is_deleted = '0'
join {$h} h on cr.user_id = h.user_kid and h.is_deleted = '0' {$c_user}
left join {$r} r on cr.user_id = r.user_id and r.examination_id = e.kid and r.examination_status = '2' and r.result_type='1' and a.kid = r.courseactivity_id and r.is_deleted = '0'
where a.object_type = 'examination' and a.is_deleted = '0' and r.examination_score !='Null'
and exists( select 1 from {$r} r1 where r1.examination_id = e.kid and r1.examination_status = '2' and r1.result_type='1' and a.kid = r1.courseactivity_id and r1.is_deleted = '0')
order by c.open_start_time, a.course_id, m.mod_num, r.end_at desc, cr.reg_time;";
            //return $sql;
            $connection     = Yii::$app->db;
            $data           = $connection->createCommand($sql)->queryAll();
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;   
    }
    /*
     * 获取闯关课程考试成绩信息 =====暂时停用
     * eln_ln_courseactivity a 活动资源表  course_id object_id  mod_id
     * a.object_type = 'examination'
     * a.is_deleted = '0'
     * eln_ln_examination e 考试表 e.kid==a.object_id  e.is_deleted = '0'              
     * eln_ln_course c 课程信息表 c.kid==a.course_id   c.is_deleted = '0'
     * eln_ln_course_mods m 课程模块表 m.kid==a.mod_id  m.is_deleted = '0'
     * eln_ln_course_enroll l 课程报名表 a.course_id==l.course_id  l.approved_state = '1'  l.is_deleted = '0'
     * eln_fw_user u    用户表 u.kid==l.user_id u.is_deleted = '0'
     * eln_ln_examination_result_user r 个人考试结果表 
     *          l.user_id = r.user_id 
     *          r.examination_id = e.kid  
     *          r.examination_status = '2' 
     *          r.result_type='1'
     *          a.kid = r.courseactivity_id
     *          r.is_deleted = '0'
    */
    public static function getCourseExamResult($course_id = NULL,$user_id = NULL,$create_mode = 1){
        if(!$course_id)
        {
            return NULL;
        }
        $cache_arr = array(
            'course_id' =>$course_id,
            'user_id'   =>$user_id
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$create_mode) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($create_mode || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S        
          $a    = LnCourseactivity::realTableName();
          $e    = LnExamination::realTableName();
          $c    = LnCourse::realTableName();
          $m    = LnCourseMods::realTableName();
          $l    = LnCourseEnroll::realTableName();
          $cr   = LnCourseReg::realTableName();
          $u    = FwUser::realTableName();
          $r    = LnExaminationResultUser::realTableName();
          $c_course = $c_user   = "";
          if(is_array($course_id))
          {
              $course_id    = "'".implode("','",$course_id)."'";
              $c_course     = " c.kid in ({$course_id})";
          }else{
              $c_course     = " c.kid = '{$course_id}' ";
          }
          if($user_id)
          {
                if(is_array($user_id))
                {
                    $user_id        = "'".implode("','",$user_id)."'";
                    $c_user         = " and u.kid in ({$user_id})";
                }else{
                    $c_user         = " and u.kid = '{$user_id}' ";
                }
          }
          //return $c_where;
          //////////////////////////////////////////////////////////
          $sql  = "
select c.kid as course_id, c.course_name,c.release_at,m.kid as mod_id, m.mod_name, u.kid as user_id, a.activity_name, u.real_name,u.user_name, r.examination_score, r.start_at, r.end_at
from {$a} a
join {$e} e on a.object_id = e.kid and e.is_deleted = '0'
join {$c} c on a.course_id = c.kid and c.is_deleted = '0' and {$c_course}
join {$m} m on a.mod_id = m.kid and m.is_deleted = '0'
join {$cr} cr on a.course_id = cr.course_id and cr.reg_state = '1' and cr.is_deleted = '0'
join {$h} h on cr.user_id = h.user_kid and h.is_deleted = '0' {$c_user}
left join {$r} r on cr.user_id = r.user_id and r.examination_id = e.kid and r.examination_status = '2' and r.result_type='1' and a.kid = r.courseactivity_id and r.is_deleted = '0'
where a.object_type = 'examination' and a.is_deleted = '0'
and exists( select 1 from {$r} r1 where r1.examination_id = e.kid and r1.examination_status = '2' and r1.result_type='1' and a.kid = r1.courseactivity_id and r1.is_deleted = '0')
order by c.open_start_time, a.course_id, m.mod_num, r.end_at desc, cr.reg_time;";
            $connection     = Yii::$app->db;
            $data           = $connection->createCommand($sql)->queryAll();
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;   
    }
    /******************************闯关专区使用结束***************************************/
    
    /*
     * 判断当前课程的区块是否为课内课程测试
    */
    public static function checkInClassCourseTest($course_id = NULL,$mod_id = NULL){
        if(!$course_id || !$mod_id )
        {
            return false;
        }
        $a_boj      = new LnCourseactivity();
        $where      = array('course_id'=>$course_id,'mod_id'=>$mod_id,'object_type'=>'examination','is_deleted'=>'0');
        $a_info     = $a_boj->find(false)->where($where)->asArray()->one();
        if(isset($a_info['kid'])&&$a_info['kid'])
        {
            return true;
        }else
        {
            return false;
        }
    }
    
    /*
     * 课程课内考试测试信息导出
     * eln_ln_courseactivity a 活动资源表  course_id object_id  mod_id
     * a.object_type = 'examination'
     * a.is_deleted = '0'
     * eln_ln_examination e 考试表 e.kid==a.object_id  e.is_deleted = '0'              
     * eln_ln_course c 课程信息表 c.kid==a.course_id   c.is_deleted = '0'
     * eln_ln_course_mods m 课程模块表 m.kid==a.mod_id  m.is_deleted = '0'
     * eln_ln_course_enroll l 课程报名表 a.course_id==l.course_id  l.approved_state = '1'  l.is_deleted = '0'
     * eln_fw_user u    用户表 u.kid==l.user_id u.is_deleted = '0'
     * eln_ln_examination_result_user r 个人考试结果表 
     *          l.user_id = r.user_id 
     *          r.examination_id = e.kid  
     *          r.examination_status = '2' 
     *          r.result_type='1'
     *          a.kid = r.courseactivity_id
     *          r.is_deleted = '0'
    */
    public static function getCourseExamResultList($course_id = NULL,$mod_id = NULL){
        if(!$course_id || !$mod_id)
        {
            return NULL;
        }
        $cache_arr = array(
            'course_id' =>$course_id,
            'mod_id'    =>$mod_id
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($debug || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S        
          $a    = LnCourseactivity::realTableName();
          $e    = LnExamination::realTableName();
          $c    = LnCourse::realTableName();
          $m    = LnCourseMods::realTableName();
          $l    = LnCourseEnroll::realTableName();
          $cr   = LnCourseReg::realTableName();
          $u    = FwUser::realTableName();
          $r    = LnExaminationResultUser::realTableName();
          //////////////////////////////////////////////////////////
          $sql  = "
select c.course_name, m.mod_name, a.activity_name, u.real_name,u.user_name, r.examination_score, r.start_at, r.end_at
from {$a} a
join {$e} e on a.object_id = e.kid and e.is_deleted = '0'
join {$c} c on a.course_id = c.kid and c.is_deleted = '0' and c.kid='{$course_id}'
join {$m} m on a.mod_id = m.kid and m.is_deleted = '0' and m.kid='{$mod_id}'
join {$cr} cr on a.course_id = cr.course_id and cr.reg_state = '1' and cr.is_deleted = '0'
join {$u} u on cr.user_id = u.kid and u.is_deleted = '0'
left join {$r} r on cr.user_id = r.user_id and r.examination_id = e.kid and r.examination_status = '2' and r.result_type='1' and a.kid = r.courseactivity_id and r.is_deleted = '0'
where a.object_type = 'examination' and a.is_deleted = '0'
and exists( select 1 from {$r} r1 where r1.examination_id = e.kid and r1.examination_status = '2' and r1.result_type='1' and a.kid = r1.courseactivity_id and r1.is_deleted = '0')
order by c.open_start_time, a.course_id, m.mod_num, r.end_at desc, cr.reg_time;";
            $connection     = Yii::$app->db;
            $data           = $connection->createCommand($sql)->queryAll();
            //return $data;
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;   
    }
    
    /**
     * 读取课程信息
     */
    public static function getCourseList($params = array(), $debug = 0) {
        $cache_arr = array();
        $cache_arr['search']        = $search   = BoeBase::array_key_is_nulls($params, array('search', 'q'), NULL);
        $cache_arr['kid']           = $kid      = BoeBase::array_key_is_nulls($params, array('kid', 'id'), NULL);
        $cache_arr['teacher_show']  = $teacher_show = BoeBase::array_key_is_nulls($params, array('teacher_show', 't_show'), 1);
        $skey = BoeBase::array_key_is_nulls($params, array('skey', 's_key'), NULL);
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $sult = NULL;
        if(!$kid)
        {
            return $sult;
        }
        if (!$no_cache && $cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $sult = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($debug || !$sult || !is_array($sult)) {//缓存中没有或是强制生成缓存模式时S
            $debug_text = array();
            $debug_text[] = __METHOD__;
            $ln_table_name = LnCourse::realTableName();
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
            $base_where = array('and',
                array('in', $ln_table_name . '.kid', $kid),
            );
            $query = (new Query())->from($ln_table_name);
            $query->select($select_field_str);
            $query->indexBy('kid');
            $query->andFilterWhere($base_where);
            $ln_db_info = $query->all();
            $command = $query->createCommand();
            if ($debug) {
                $debug_text[] = "$params:";
                $debug_text[] = var_export($params, true);
                $debug_text[] = "base_where:";
                $debug_text[] = var_export($base_where, true);
                $debug_text[] = "Get CourseList Sql:";
                $debug_text[] = $command->getRawSql();
                $debug_text[] = 'CacheArray:';
                $debug_text[] = var_export($cache_arr, true);
                $debug_text[] = "LessonInfo Sult:";
                $debug_text[] = var_export($ln_db_info, true);
            }
            $query = NULL;
            if ($ln_db_info && is_array($ln_db_info)) {//读取到了相关的课程信息S
                $teacher_info = $teacher_list = NULL;
                if ($teacher_show == 1) {//需要读取出老师的信息时间S
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
                    $sult[$key] = self::parseOneCourseInfo($a_info,$teacher_show,$teacher_list,$skey);
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
     * 对接portal
     * 拼接相关的课程
     * @param type $a_info
     * @param type $teacher_num
     * @param type $teacher_list
     * @return string
     */
    private function parseOneCourseInfo($a_info,$teacher_show = 0,$teacher_list = NULL,$skey = NULL) {
        //拼接结果S
        if($teacher_show == 1)
        {
            $a_info['teacher_name'] = self::getCourseTeacherList($a_info['kid'], $teacher_list);
        }
        //获取课程评分
        $m_boj      = new LnCourseMarkSummary();
        $where      = array('course_id'=>$a_info['kid']);
        $mark_info  = $m_boj->find(false)->where($where)->orderBy('created_at desc')->asArray()->one();
        $course_mark= isset($mark_info['course_mark'])?$mark_info['course_mark']:NULL;
        $url        = Yii::$app->urlManager->createUrl(array('resource/course/view', 'id' => $a_info['kid']));
        $unit       = self::$course_period_unit;
        $sult                       = array(
            //'kid'             =>$a_info['kid'],
            'ctype'             =>$a_info['course_type'],
            'title'             =>$a_info['course_name'],
            'cno'               =>$a_info['course_code'],
            'releaseDate'       =>date("Y-m-d H:i:s",$a_info['release_at']),
            'teachingAddr'      =>$a_info['training_address'],
            'openTime'          =>$a_info['open_start_time']?date("Y-m-d H:i:s",$a_info['open_start_time']):"",
            //'period'          =>$a_info['course_period'].$unit[$a_info['course_period_unit']],
            'period'            =>self::boeTime($a_info['course_period'],$a_info['course_period_unit']),
            'credit'            =>$a_info['default_credit'],
            'evaluate'          =>$course_mark,
            'introduction'      =>$a_info['course_desc'],
            'teacher'           =>$a_info['teacher_name'],
            'expiredDate'       =>$a_info['end_time'] ? date("Y-m-d H:i:s",$a_info['end_time']) : Yii::t('frontend', 'forever'),
            'grade'             =>$a_info['course_level'],
            'hits'              =>$a_info['visit_number'],
            'cURL'              =>self::$rurl.'t=1&id='.$a_info['kid']."&skey=".$skey
        );
        return $sult;
    }
    
    /*
     * 课程列表接口
    */
    public static function getCourseTopList($course_type=-1,$skey=NULL,$count1=0,$count2=0,$user_data =array())
    {   
        $domain_id      = isset($user_data['domain_id'])?$user_data['domain_id']:0;
        $work_place_id  = isset($user_data['work_place_id'])?$user_data['work_place_id']:'';
        $sult       = array();
        $params     = array(
            'domain_id'     => $domain_id,
            //'teacher_num'     => 3, //老师的显示数量,为0就不显示任何老师的信息
            'limit_num'     => $count1?$count1:10, //数据数量
            'course_type'   => $course_type, //0=在线课程，1表示面授，-1表示全部
            'order_by'      =>'created_at desc',
            'ignore_open_start_time' => 1,
        );
        $new_params                 =   $hot_params     =   $params;
        //(注册量：register_number desc)
        //(学习量:learned_number desc)
        //(评价量:rated_number desc)
        //(报名成功量:enroll_number desc)
        //(访问量:visit_number desc)
        if($course_type == 1)
        {
            //面授课程信息
            $new_params['limit_num']    =   $count1&&$count1>0?$count1:5;
            $hot_params['limit_num']    =   $count2&&$count2>0?$count2:5;
            $sult['ctype']      = 1;
        }else{
            //$sult['count']    =  self::getLessonInfoByType($params, $skey);
            $new_params['limit_num']    =   $count1&&$count1>0?$count1:10;
            $hot_params['limit_num']    =   $count2&&$count2>0?$count2:0;
            $sult['ctype']      = 0;
        }
        $hot_params['order_by']     =   'register_number desc';//根据注册数量注册量
        
        $sult['count1']             =   self::getLessonInfoByType($new_params,$skey,'n',$user_data);
        if($hot_params['limit_num']>0)
        {
            $sult['count2']     = self::getLessonInfoByType($hot_params,$skey,'h',$user_data);
        }else{
            $sult['count2']     = "";
        }
        $sult['moreURL']    = self::$rurl.'t=2&skey='.$skey;
        return $sult;
    }
    
    /*
      *按类型获取不同字段的数据信息
    */
    public static function getLessonInfoByType($params = array(),$skey = NULL,$mark = NULL,$user_data =array())
    {
        if(!isset($params['course_type']))
        {
            return NULL;
        }
        $course_type    =   $params['course_type'];
        //=======================================================================
        $domain_id      = isset($user_data['domain_id'])?$user_data['domain_id']:0;
        $work_place_id  = isset($user_data['work_place_id'])?$user_data['work_place_id']:'';
        $sult           = array();
        if($course_type == 1 &&$mark =='n' )
        {
            //面授课程的最新获取完之后，按开课时间排列（越接近当前时间的越靠前）；
            //第一步：获取开班课程信息
            $kaiban = FrontService::getIndexLessonInfo($domain_id,$work_place_id,$params['limit_num'],0);
            //$kaiban       = FrontService::getIndexLessonInfo(0,$work_place_id,5,0);
            $kb_array       = $kaiban['全部']['lesson'];
            $sult['list']   = $kb_array;
            $count_kaiban   = count($kb_array);
            if($params['limit_num'] > $count_kaiban)
            {
                $params['limit_num']    = $params['limit_num'] - $count_kaiban;
                $course_array           = array_keys($kb_array);
                $params['course_id']    = $course_array;
                $ms_data                = self::getLessonInfo($params);
                $ms_array               = array();
                //给面授课程信息排序
                foreach($ms_data['list'] as $m_key=>$m_value)
                {
                    $new_key    = $m_value['open_start_time']."_".$m_value['release_at'];
                    //return $m_value;
                    $ms_array[$new_key] =$m_value;
                }
                ksort($ms_array);
                $sult['list']   = array_merge($kb_array,$ms_array);
            }
            //return $kb_array;
        }else{
            $sult           =   self::getLessonInfo($params);
        }
        //=========================================================================
        $data           =   array();
        $unit           =   self::$course_period_unit;
        if($course_type==0)//在线课程
        {
            foreach($sult['list'] as $s_key=>$s_value)
            {
                $data['list'][] = array(
                    'id'        =>$s_value['kid'],
                    'title'     =>$s_value['course_name'],
                    'period'    =>self::boeTime($s_value['course_period'],$s_value['course_period_unit']),
                    'cURL'      =>self::$rurl.'t=1&id='.$s_value['kid'].'&skey='.$skey
                );
            }   
        }elseif($course_type==1)//面授课程
        {
            foreach($sult['list'] as $s_key=>$s_value)
            {
                $data['list'][] = array(
                    'id'        =>$s_value['kid'],
                    'title'     =>$s_value['course_name'],
                    'period'    =>self::boeTime($s_value['course_period'],$s_value['course_period_unit']),
                    'openTime'  =>$s_value['open_start_time']?date("Y-m-d H:i:s", $s_value['open_start_time']):'',
                    'hits'      =>$s_value['visit_number'],
                    'mark'      =>$mark,
                    'cURL'      =>self::$rurl.'t=1&id='.$s_value['kid'].'&skey='.$skey
                );  
            }
        }
        //$data['course_type']      = $course_type;
        //$data['course_url_more']  = self::$host.Url::toRoute(array('resource/course/index'));
        return $data['list'];
    }
    
    /*
      *按关键获取数据信息
    */
    public static function getLessonInfoByKeyword($params)
    {
        if(!isset($params['keyword'])&&!$params['keyword'])
        {
            return NULL;
        }
        $where          =   array(
            'keyword'                   => $params['keyword'],
            'teacher_num'               => 3, //老师的显示数量,为0就不显示任何老师的信息
            'returnTotalCount'          => 1,
            'offset'                    => isset($params['offset'])&&$params['offset']?$params['offset']:($params['currentpage'] - 1) * $params['pageSize'],
            'limit'                     => $params['pageSize'],
            'domain_id'                 => 0,
            'ignore_open_start_time'    => 1,
            'order_by'                  => 'created_at desc',
        );
        $sult           =   self::getLessonInfo($where);
        $data           =   array();
        foreach($sult['list'] as $s_key=>$s_value)
        {
            $data['list'][$s_key]   = array(
                'course_type'       =>$course_type,
                'course_name'       =>$s_value['course_name'],
                'course_code'       =>$s_value['course_code'],
                'release_at'        =>date("Y-m-d",$s_value['release_at']),
                'training_address'  =>$s_value['training_address'],
                'open_start_time'   =>$s_value['open_start_time'],
                'course_period'     =>$s_value['course_period'],
                'default_credit'    =>$s_value['default_credit'],
                'course_mark'       =>$s_value['course_mark'],
                'course_desc'       =>$s_value['course_desc'],
                'course_teacher'    =>$s_value['teacher_name'],
                'time_validity'     =>$s_value['end_time'] ? date("Y-m-d",$s_value['end_time']) : Yii::t('frontend', 'forever'),
                'course_level'      =>$s_value['course_level'],
                'visit_number'      =>$s_value['visit_number'],
                'course_url'        =>self::$host.$s_value['url']
            );
            $data['totalcount']     = $sult['total'];
            $data['pageSize']       = $where['pageSize'];
            $data['currentpage']    = $where['currentpage'];
            $data['offset']         = $where['offset'];
        }
        return $data;
    }
    
    /**
     * 读取课程信息
     */
    public static function getLessonInfo($params = array(), $debug = 0) {
        $cache_arr = array();
        $cache_arr['domain_id']     = $domain_id    = BoeBase::array_key_is_nulls($params, array('domain_id', 'domain_info', 'domainInfo', 'domainId'), NULL);
        $cache_arr['course_id']     = $course_id    = BoeBase::array_key_is_nulls($params, array('course_id', 'course_array'), NULL);
        $cache_arr['limit_num']     = $limit        = BoeBase::array_key_is_numbers($params, array('limit', 'limit_num', 'limitNum'), 0);
        $cache_arr['offset']        = $offset       = BoeBase::array_key_is_numbers($params, array('offset', 'offset_int'), 0);
        $cache_arr['teacher_num']   = $teacher_num  = BoeBase::array_key_is_numbers($params, array('teacher_num', 'teacherNum'), 0);
        $cache_arr['course_type']   = $course_type  = BoeBase::array_key_is_numbers($params, array('course_type', 'course_type'), -1);
        $cache_arr['course_period_text']        = $course_period_text = BoeBase::array_key_is_numbers($params, array('coursePeriodText', 'course_period_text'), 1);
        $cache_arr['ignore_open_start_time']    = $ignore_open_start_time = BoeBase::array_key_is_numbers($params, array('ignore_open_start_time', 'ignoreOpenStartTime', 'no_open_start_time', 'noOpenStartTime'));
        $cache_arr['orderby']                   = $orderby = BoeBase::array_key_is_nulls($params, array('orderBy', 'order_by', 'orderby'), 'open_start_time desc');
        $cache_arr['keyword']       = $keyword      = BoeBase::array_key_is_nulls($params, array('keyword','keyword1'), NULL);
        $no_cache = BoeBase::array_key_is_numbers($params, array('no_cache', 'noCache'));

        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if (!$no_cache && $cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
//            BoeBase::debug("Cache Sult:".var_export($sult,true),1);
        }//需要读取缓存信息时E
        if ($debug || !$data || !is_array($data)) {//缓存中没有或是强制生成缓存模式时S
            $debug_text = array();
            $debug_text[] = __METHOD__;
            $ln_table_name = LnCourse::realTableName();
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
            $base_where = array('and',
                array('=', $ln_table_name . '.is_deleted', 0),
                array('=', $ln_table_name . '.is_display_pc', LnCourse::DISPLAY_PC_YES),
                array('=', $ln_table_name . '.status', LnCourse::STATUS_FLAG_NORMAL),
                array('<=', $ln_table_name . '.start_time', $c_time),
            );
            if($course_id)
            {
                $base_where[] = array('not in', $ln_table_name . '.kid', $course_id);
            }
            if($keyword)
            {
                $base_where[] = array(
                    'or',
                    array('like', $ln_table_name . '.course_name', '%' . $keyword . '%', false),
                    array('like', $ln_table_name . '.course_desc', '%' . $keyword . '%', false)
                );
            }
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
            $query->andFilterWhere($base_where);
            $query->andFilterWhere($time_where_p);
            $total_count    = $query->count();
            if ($offset) {
                $query->offset($offset);
            }
            if ($limit) {
                $query->limit($limit);
            }
            //$where_p = "({$ln_table_name}.end_time=0 or  {$ln_table_name}.end_time is null  or {$c_time}<={$ln_table_name}.end_time)";
           // $query->where($where_p);
            $ln_db_info = $query->all();
            //return $base_where;
            //return $ln_db_info;
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
                    //return $teacher_info;
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
            $data   = array(
                'list'      => $sult,
                'total'     => $total_count
            );
            //return $data;
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        if ($debug) {
            $debug_text[] = "最终结果:";
            $debug_text[] = var_export($data, true);
            BoeBase::debug(implode("\n", $debug_text), 1);
        }
        return $data;
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
        //获取课程评分
        $m_boj      = new LnCourseMarkSummary();
        //$where        = array('course_id'=>'01C8AC29-16A1-A9F5-B973-FB88FB84FCA9');
        $where      = array('course_id'=>$a_info['kid']);
        $mark_info  = $m_boj->find(false)->where($where)->orderBy('created_at desc')->asArray()->one();
        $a_info['course_mark']  = isset($mark_info['course_mark'])?$mark_info['course_mark']:NULL;
        $a_info['url'] = Yii::$app->urlManager->createUrl(array('resource/course/view', 'id' => $a_info['kid']));
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
    
    /*
     * 根据课程获取所有评估结果信息
    */
    public static function getInvestigationResultList($course_id=NULL,$mod_id=NULL,$item_id=NULL,$debug=0)
    {
        $debug      = 0;
        $cache_arr  = array(
            'course_id' =>$course_id,
            'mod_id'    =>$mod_id,
            'item_id'   =>$item_id,
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($debug || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S
            $r              = LnInvestigationResult::realTableName();
            $co             = LnCourseactivity::realTableName();
            $m              = LnCourseMods::realTableName();
            $res            = LnModRes::realTableName();
            
            $r_field        ="r.kid,m.mod_name,co.activity_name,r.course_id,r.mod_id,r.investigation_id,r.user_id,r.question_type,r.question_title,r.option_result,r.investigation_question_id,r.created_at";
            if($mod_id&&$item_id){
                $sql    = "SELECT {$r_field} FROM {$r} r
LEFT JOIN eln_ln_mod_res AS res ON r.courseactivity_id = res.courseactivity_id
LEFT JOIN eln_fw_user AS u ON u.kid = r.user_id 
LEFT JOIN eln_ln_course_mods AS m ON m.kid = r.mod_id
LEFT JOIN eln_ln_courseactivity As co ON co.kid = r.courseactivity_id
WHERE r.course_id = '{$course_id}' AND m.`kid`='{$mod_id}' AND r.courseactivity_id ='{$item_id}'
AND r.is_deleted = '0' AND res.is_deleted = '0' AND u.is_deleted ='0' AND co.is_deleted = '0' ORDER BY r.mod_id asc,r.user_id asc,r.question_type asc;";
            }elseif($mod_id){
                $sql    = "SELECT {$r_field} FROM {$r} r
LEFT JOIN eln_ln_mod_res AS res ON r.courseactivity_id = res.courseactivity_id
LEFT JOIN eln_fw_user AS u ON u.kid = r.user_id 
LEFT JOIN eln_ln_course_mods AS m ON m.kid = r.mod_id
LEFT JOIN eln_ln_courseactivity As co ON co.kid = r.courseactivity_id
WHERE r.course_id = '{$course_id}' AND m.`kid`='{$mod_id}'
AND r.is_deleted = '0' AND res.is_deleted = '0' AND u.is_deleted ='0' AND co.is_deleted = '0' ORDER BY r.mod_id asc,r.user_id asc,r.question_type asc;";
            }else
            {
                $sql    = "SELECT {$r_field} FROM {$r} r
LEFT JOIN eln_ln_mod_res AS res ON r.courseactivity_id = res.courseactivity_id
LEFT JOIN eln_fw_user AS u ON u.kid = r.user_id 
LEFT JOIN eln_ln_course_mods AS m ON m.kid = r.mod_id
LEFT JOIN eln_ln_courseactivity As co ON co.kid = r.courseactivity_id
WHERE r.course_id = '{$course_id}'
AND r.is_deleted = '0' AND res.is_deleted = '0' AND u.is_deleted ='0' AND co.is_deleted = '0' ORDER BY r.mod_id asc,r.user_id asc,r.question_type asc;";
            }
            //return $sql;
            $connection     = Yii::$app->db;
            $data           = $connection->createCommand($sql)->queryAll();
            if ($cache_time) {
                Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
            }
        }//缓存中没有或是强制生成缓存模式时E
        return $data;   
    }
    
    /*
     * 课程评估数据导出
     * @course_id
     * @return array
    */
    public static function getCourseResultCount($course_id=NULL,$mod_id=NULL,$item_id=NULL,$debug=1)
    {
        if(!$course_id)
        {
            return NULL;
        }
        $cache_arr = array(
            'course_id' =>$course_id,
            'mod_id'    =>$mod_id,
            'item_id'   =>$item_id
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($debug || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S
          $course_info      = LnCourse::find(false)->select('kid,course_name,enroll_number')->where(array('kid'=>$course_id))->asArray()->one();
          $result_info      = self::getInvestigationResultList($course_id,$mod_id,$item_id,$debug);
          //boeBase::dump($result_info[0]);
          $investigation_array  = $mod_array    =   $count_array    =   array();
          foreach($result_info as $r_key=>$r_value)
          {
              //获取调查表KID数组
              $investigation_array[$r_value['investigation_id']]    =   $r_value['investigation_id'];
              //获取课程评估模块KID数组
              $mod_array[$r_value['mod_id']]    =   $r_value['mod_id'];
              //获取用户KID数组
              $user_array[$r_value['user_id']]  =   $r_value['user_id'];
              $new_key  =   md5($r_value['course_id'].$r_value['mod_id'].$r_value['investigation_id'].$r_value['investigation_question_id']);
              $count_array[$new_key]['course_id']       =$course_id;
              $count_array[$new_key]['course_name']     =$course_info['course_name'];
              $count_array[$new_key]['mod_id']          =$r_value['mod_id'];
              $count_array[$new_key]['mod_name']        =$r_value['mod_name'];
              $count_array[$new_key]['title']           =$r_value['activity_name'];
              $count_array[$new_key]['investigation_id']=$r_value['investigation_id'];
              $count_array[$new_key]['question_title']  =$r_value['question_title'];
              $count_array[$new_key]['question_type']   =$r_value['question_type'];
              $count_array[$new_key]['enroll_number']   =$course_info['enroll_number'];
              if($r_value['question_type']==0)
              {
                  $count_array[$new_key]['user_array'][$r_value['user_id']]=$r_value['user_id'];
                  $count_array[$new_key]['score_array'][]=str_replace('分','',$r_value['option_result']);
              }
              else
              {
                  $count_array[$new_key]['user_array']='';
                  $count_array[$new_key]['score_total']='';
              }
                   
          }
          //获取调查表数据信息
          foreach($count_array as $c_key=>$c_value)
          {
              $data['list'][]   =array(
                'course_id'     =>$c_value['course_id'],
                'course_name'   =>$c_value['course_name'],
                'mod_name'      =>$c_value['mod_name'],
                'title'         =>$c_value['title'],
                'question_title'=>$c_value['question_title'],
                'type_text'     =>$c_value['question_type']==0?'是':'否',
                'enroll_number' =>$c_value['question_type']==0?$c_value['enroll_number']:'/',
                'user_num'      =>$c_value['question_type']==0?count($c_value['user_array']):'/',
                'score_average' =>$c_value['question_type']==0?round(array_sum($c_value['score_array'])/count($c_value['user_array']),2):'/',
              ); 
              //boeBase::dump($c_value);
              //return $data['list'];
          }
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;
    }
    
    /*
     * 课程评估数据导出
     * @course_id
     * @return array
    */
    public static function getCourseResultList($course_id=NULL,$mod_id=NULL,$item_id=NULL,$debug=0)
    {
        if(!$course_id)
        {
            return NULL;
        }
        $cache_arr = array(
            'course_id' =>$course_id,
            'mod_id'    =>$mod_id,
            'item_id'   =>$item_id
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($debug || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S
          $c    = LnCourse::realTableName();
          $m    = LnCourseMods::realTableName();//eln_ln_course_mode
          $r    = LnInvestigationResult::realTableName();
          $q    = LnInvestigationQuestion::realTableName();
          $i    = LnInvestigation::realTableName();
          $u    = FwUser::realTableName();
          $course_info      = LnCourse::find(false)->select('kid,course_name')->where(array('kid'=>$course_id))->asArray()->one();
          $result_info      = self::getInvestigationResultList($course_id,$mod_id,$item_id,$debug);
          //return $result_info;
          $user_array   =   array();
          foreach($result_info as $r_key=>$r_value)
          {
              //获取用户KID数组
              $user_array[$r_value['user_id']]  =   $r_value['user_id'];  
          }
           
          $data         = array(
              'total'           =>count($result_info),
              'list'            =>$result_info,
              'course'          =>$course_info,
              'user'            =>$user_array
          );
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;
    }   

    /*
     * 课程评估数据导出改造
     * @Author sunyongct
     * @return array
    */
    public static function getCourseResultListFormat($course_id=NULL,$mod_id=NULL,$item_id=NULL,$debug=0)
    {
        if(!$course_id)
        {
            return NULL;
        }
        $cache_arr = array(
            'course_id' =>$course_id,
            'mod_id'    =>$mod_id,
            'item_id'   =>$item_id
        );
        $cache_time = Yii::$app->params['boeIndexCacheTime'];
        $cache_name = __METHOD__ . md5(serialize($cache_arr));
        $data = NULL;
        if ($cache_time && !self::isNoCacheMode() && !$debug) {//需要读取缓存信息时S
            $data = Yii::$app->cache->get($cache_name);
        }//需要读取缓存信息时E
        if ($debug || !$data || !is_array($data)) 
        {//缓存中没有或是强制生成缓存模式时S
          $c    = LnCourse::realTableName();
          $m    = LnCourseMods::realTableName();//eln_ln_course_mode
          $r    = LnInvestigationResult::realTableName();
          $q    = LnInvestigationQuestion::realTableName();
          $i    = LnInvestigation::realTableName();
          $u    = FwUser::realTableName();
          $co               = LnCourseactivity::realTableName();
          $course_info      = LnCourse::find(false)->select('kid,course_name')->where(array('kid'=>$course_id))->asArray()->one();
          $result_info      = self::getInvestigationResultList($course_id,$mod_id,$item_id,$debug);
          $mod_info = LnCourseactivity::find(false)
                    ->select("mod_id,mod_name,$q.kid,$q.question_title,$q.question_type")
                    ->leftJoin($q, $co.".object_id=".$q.".investigation_id")
                    ->leftJoin($m, $co.".mod_id=".$m.".kid")
                    ->andFilterWhere(['=', $co.'.course_id', $course_id])
                    ->andFilterWhere(['=', $co.'.mod_id', $mod_id])
                    ->andFilterWhere(['=', $q.'.is_deleted', 0])
                    ->orderBy($m.".mod_num,".$q.".sequence_number")
                    ->asArray()
                    ->all();
          $tmp = array();
          foreach ($mod_info as $key => $value) {
            $mid = $value['mod_id'];
            
                $tmp[$mid]['mod_id'] = $mid;
            $tmp[$mid]['mod_name'] = $value['mod_name'];
            $tmp[$mid]['list'][] =  
            array('kid'=>$value['kid'],'question_title'=>$value['question_title'],'question_type'=>$value['question_type']);
          
          
          }
          $mod_info = $tmp;
          unset($tmp);
          //调查问卷信息数据拆分
          $user_array = $result_list = $active_arr = array();
          foreach($result_info as $r_key=>$r_value)
          {
            $mod_id = $r_value['mod_id'];
            $user_id = $r_value['user_id'];
            $result_list[$mod_id][$user_id][$r_value['investigation_question_id']]= $r_value;
            $result_list[$mod_id][$user_id]['complete_created_at']= $r_value['created_at'];
            //获取用户和组织信息
            if(!$user_array[$r_value['user_id']]){
                $org = self::getOrgPath($r_value['user_id']);
                $user_array[$r_value['user_id']] = $org ;
            }
          }
          $data         = array(
              'total'           =>count($mod_info),
              'mod_list'        =>$mod_info,
              'course'          =>$course_info,
              'user'            =>$user_array,
              'result_list'     =>$result_list
          );
          if ($cache_time) {
              Yii::$app->cache->set($cache_name, $data, $cache_time); // 设置缓存
          }
        }
        return $data;
    }   

     /*
     * 获取真实的组织全路径[单个用户]
     * $user_id 用户ID
     * $org_id 组织ID
     * $user_no 员工工号
     * @author songsangct
    */
    public static function getOrgPath($user_id = NULL,$org_id = NULL,$user_no = NULL){
        
        if($user_id)
        {
            $user_info          = FwUser::findOne(array('kid'=>$user_id,'is_deleted'=>'0'));
            if(!isset($user_info['kid']))
            {
                return -93;
            }
            $orgnization_id     = $user_info['orgnization_id'];
        }
        elseif($user_no)
        {
            $user_info          = FwUser::findOne(array('user_no'=>$user_no,'is_deleted'=>'0'));
            if(!isset($user_info['kid']))
            {
                return -92;
            }
            $orgnization_id     = $user_info['orgnization_id'];
        }elseif($org_id)
        {
            $orgnization_id     = $org_id;
        }else
        {
            return -91;;
        }
        $orgnization_info       = FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
        if(!isset($orgnization_info['kid']))
        {
            return -90;
        }
        $parent_orgnization_id  =$orgnization_info['parent_orgnization_id'];
        $org_data               = array();
       // $org_data['id'][0]      = $orgnization_id;
        $org_data['name'][0]    = $orgnization_info['orgnization_name'];
        $i                  = 1;
        while ($parent_orgnization_id){
                $orgnization_id         = $parent_orgnization_id;
                $orgnization_info       = FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
                if(!isset($orgnization_info['kid']))
                {
                    return -89;
                }
                $orgnization_level = $orgnization_info['orgnization_level'];
               // $org_data['id'][$orgnization_level]     = $orgnization_id;
                $org_data['name'][$orgnization_level]   = $orgnization_info['orgnization_name'];
                $parent_orgnization_id  =$orgnization_info['parent_orgnization_id'];
                ;
                if($orgnization_info['parent_orgnization_id']==$orgnization_info['kid']){
                    break;
                }//修改PS系统组织传错产生死循环
        }
        $org_data['name']  = ( $org_data['name']+array('user_no'=>$user_info['user_no'],'real_name'=>$user_info['real_name'],'onboard_day'=>$user_info['onboard_day']));
        return  $org_data['name'];
    }
    
}

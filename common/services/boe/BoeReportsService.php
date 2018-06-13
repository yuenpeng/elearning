<?php
namespace common\services\boe;

use common\base\BoeBase;
use common\models\learning\LnCourse;
use common\models\framework\FwUser;
use common\models\framework\FwUserPosition;
use common\models\boe\BoeCourseReport;
use common\services\boe\BoeBaseService;
use yii\db\Expression;
use yii\db\Query;
use Yii;

/**
 * Desc：`elearninglms2`库下面数据服务层 所有操作不走系统框架模型，采用YII2原生+缓存
 * Desc：
 * Frame: 1.配置参数基础操作 2.数据层业务模块 3.报表业务模块
 * User: songsang
 * Date: 2016/6/19
 * Time: 8:10
 */

defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeReportsService {
    private static $cacheTime = 43200; //缓存12小时
    private static $currentLog = array();
    private static $cacheNameFix = 'boeReport_';
    private static $report_db = '`elearninglms2`';
    private static $eln_db = '`elearninglms`';

    private static $tableConfig = array(//
        'LnCourseCategory' => array(
            'real_table'=>'`eln_ln_course_category`',
            'order_by' => 'category_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => 'parent_category_id',
            'field' => 'kid,tree_node_id,parent_category_id,company_id,category_code,category_name,description'
        ),
        'FwOrgnization' => array(
            'real_table'=>'`eln_fw_orgnization`',
            'order_by' => 'orgnization_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => 'parent_orgnization_id',
            'field' => 'kid,tree_node_id,parent_orgnization_id,company_id,domain_id,orgnization_code,description,orgnization_manager_id,orgnization_level,is_make_org,is_service_site,status,orgnization_name'
        ),
        'FwCompany' => array(
            'real_table'=>'`eln_fw_company`',
            'order_by' => 'company_name asc',
            'primary_key' => 'kid',
            'parent_key_name' => 'parent_company_id',
            'field' => 'kid,company_code,company_name'
        ),
        'FwDomain' => array(
            'real_table'=>'eln_fw_domain',
            'order_by' => 'domain_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,tree_node_id,parent_domain_id,company_id,domain_code,share_flag,domain_name,description,status,data_from'
        ),
        'FwPosition' => array(
            'real_table'=>'`eln_fw_position`',
            'order_by' => 'position_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,company_id,position_code,position_name,position_type,position_level,share_flag,data_from'
        ),
        'FwTreeNode' => array(
            'real_table'=>'`eln_fw_tree_node`',
            'order_by' => 'tree_node_code asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,tree_node_code,tree_node_name,node_name_path,node_id_path'
        ),
    );


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
     * updateReport更新相关的报表源数据
     * @return type
     */
    private static function updateReport($debug = 0) {
        $cache_name = __METHOD__ . '_log';
        $debug_mode = $debug || self::isDebugMode();
        $sult = NULL;
        if (!self::isNoCacheMode() && !$debug_mode) {
            $sult = Yii::$app->cache->get($cache_name);
        }
        if ($sult) {//更新数据
            $command = Yii::$app->db->createCommand('call `elearninglms2`.`upload_infor_reg`(@s)');
            $res = $command->execute();
            $s = Yii::$app->db->createCommand("select @s");
            $ret = $s->queryOne();
            $sult = 'Update Time:' . date("Y-m-d H:i:s");
            Yii::$app->cache->set($cache_name, $sult, self::$cacheTime); // 设置缓存
        }
        return $sult;
    }

    /**
     * 将$period以分钟做为单位的学时转换成以小时做单位
     * @param type $period
     */
    static function format_period_value($period) {
        return $period > 0 ? str_replace('.00', '', number_format($period / 60, 2)) : 0;
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
    /******************** 2.数据层业务模块BEGIN ********************/


    /******************** 2.数据层业务模块END ********************/
    /******************** 3.报表业务模块BEGIN ********************/
    /**
     * 统计出某个用户的课程信息
     * @param type $params
     * @return array
     */
    static function getUserReport($params) {
            $start_day = BoeBase::array_key_is_nulls($params, array('start_day', 'startDay'), NULL); //开始时间
            $end_day = BoeBase::array_key_is_nulls($params, array('end_day', 'endDay'), NULL); //结束时间
            $user_id = BoeBase::array_key_is_nulls($params, array('user_id', 'userId', 'userID', 'uid'), NULL); //用户ID
            if (self::isDebugMode()) {
                BoeBase::debug(__METHOD__);
                BoeBase::debug($params);
            }
            if (!$user_id) {
                if (self::isDebugMode()) {
                    BoeBase::debug("No User", 1);
                }
                return NULL;
            }
            //用户信息
            $header = (new Query())
                    ->select(['kid','user_no','real_name as name', 'rank','orgnization_id as orgnization_path'])
                    ->from(self::$report_db.'.'.'eln_fw_user')
                    ->where("kid='".$user_id."'")
                    ->one();
            if (!$header) {
                if (self::isDebugMode()) {
                    BoeBase::debug("User Not Exists", 1);
                }
                return NULL;
            }
            //组织信息
            $header['orgnization_path'] =  self::getOrgnizationPath($header['orgnization_path']);
            //岗位信息（主岗）
            $header['position_name'] = self::getPositionName($header['kid']);


            //统计信息
            $r_table_name = 'eln_ln_course_reg';
            $c_table_name = 'eln_ln_course';
            $cp_table_name = 'eln_ln_course_complete';
            $base_where = array('and',array('=',$r_table_name.'.reg_state', 1),
                array('=',$r_table_name.'.is_deleted', 0),
            );

            if ($user_id) {
                $base_where[] = array('=', $r_table_name.'.user_id', $user_id);
            }
            if ($start_day && $end_day) {//按日期
                $base_where[] = array('between', $r_table_name . '.reg_time', $start_day, $end_day);
            }

            //总课程
            $query =  (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression('1')]);
            $header['course_num'] = $query->where($base_where)->count();
            $header['course_num_sql'] = $query->createCommand()->getRawSql();

            //总费用
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("sum(course_price) as course_price")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $where = $base_where;
            $header['all_price'] = $query->where($where)->one();
            $header['all_price'] = !empty($header['all_price']['course_price']) ?$header['all_price']['course_price'] : 0;
            $header['all_price_sql'] = $query->createCommand()->getRawSql();

            //总学时(小时，分钟需要转换)
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit)) as course_period")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $where = $base_where;
            $header['all_period'] = $query->where($where)->one();
            $header['all_period'] = !empty($header['all_period']['course_period']) ? $header['all_period']['course_period']: 0;
            $header['all_period_f'] = self::format_period_value($header['all_period']);
            $header['all_period_sql'] = $query->createCommand()->getRawSql();


            //面授
            //0.课程数
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("1")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $where = $base_where;
            $where[] = array('=', $c_table_name.'.course_type', '1');
            $header['all_offline_num'] = $query->where($where)->count();
            $header['all_offline_num_sql'] = $query->createCommand()->getRawSql();


            //1.通过课程数
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("1")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, self::$report_db.'.'.$r_table_name.".kid=".self::$report_db.'.'.$cp_table_name.".course_reg_id");
            $where = $base_where;
            $where[] = array('=', $c_table_name.'.course_type', '1');
            $where[] = array('=', $cp_table_name.'.is_passed', '1');
            // $where[] = array('=', $cp_table_name.'.complete_status', '2');
            $where[] = array('=', $cp_table_name.'.complete_type', '1');
            $header['pass_offline_num'] = $query->where($where)->count();
            $header['pass_offline_num_sql'] = $query->createCommand()->getRawSql();

            // //2.未通过课程数
            $header['nopass_offline_num'] =  $header['all_offline_num'] - $header['pass_offline_num'];
            // $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            // $query->select([new \yii\db\Expression("1")]);
            // $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            // $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, self::$report_db.'.'.$r_table_name.".kid=".self::$report_db.'.'.$cp_table_name.".course_reg_id");
            // $where = $base_where;
            // $where[] = array('=', $c_table_name.'.course_type', '1');
            // $where[] = array('!=', $cp_table_name.'.complete_status', '2');
            // $where[] = array('=', $cp_table_name.'.complete_type', '1');
            // $header['nopass_offline_num'] = $query->where($where)->count();
            // $header['nopass_offline_num_sql'] = $query->createCommand()->getRawSql();


            //3.通过学时
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit)) as course_period")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, self::$report_db.'.'.$r_table_name.".kid=".self::$report_db.'.'.$cp_table_name.".course_reg_id");
            $where = $base_where;
            $where[] = array('=', $c_table_name.'.course_type', '1');
            $where[] = array('=', $cp_table_name.'.is_passed', '1');
            // $where[] = array('=', $cp_table_name.'.complete_status', '2');
            $where[] = array('=', $cp_table_name.'.complete_type', '1');
            $header['pass_offline_period'] = $query->where($where)->one();
            $header['pass_offline_period'] = !empty($header['nopass_offline_period']['course_period']) ? $header['nopass_offline_period']['course_period']: 0;
            $header['pass_offline_period_f'] = self::format_period_value($header['nopass_offline_period']);
            $header['pass_offline_period_sql'] = $query->createCommand()->getRawSql();



            //在线
            //0.课程数
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("1")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $where = $base_where;
            $where[] = array('=', $c_table_name.'.course_type', '0');
            $header['all_online_num'] = $query->where($where)->count();
            $header['all_online_sql'] = $query->createCommand()->getRawSql();
            //1.通过课程数
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("1")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, self::$report_db.'.'.$r_table_name.".kid=".self::$report_db.'.'.$cp_table_name.".course_reg_id");
            $where = $base_where;
            $where[] = array('=', $c_table_name.'.course_type', '0');
            $where[] = array('=', $cp_table_name.'.is_passed', '1');
            // $where[] = array('=', $cp_table_name.'.complete_status', '2');
            $where[] = array('=', $cp_table_name.'.complete_type', '1');
            $header['pass_online_num'] = $query->where($where)->count();
            $header['pass_online_num_sql'] = $query->createCommand()->getRawSql();

            //2.未通过课程数
            $header['nopass_online_num'] = $header['all_online_num'] - $header['pass_online_num'];
            // $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            // $query->select([new \yii\db\Expression("1")]);
            // $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            // $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, self::$report_db.'.'.$r_table_name.".kid=".self::$report_db.'.'.$cp_table_name.".course_reg_id");
            // $where = $base_where;
            // $where[] = array('=', $c_table_name.'.course_type', '0');
            // $where[] = array('!=', $cp_table_name.'.complete_status', '2');
            // $where[] = array('=', $cp_table_name.'.complete_type', '1');

            // $header['nopass_online_num'] = $query->where($where)->count();
            // $header['nopass_online_num_sql'] = $query->createCommand()->getRawSql();

            //3.通过学时
            $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
            $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit)) as course_period")]);
            $query->join('INNER JOIN',self::$report_db.'.'.$c_table_name, self::$report_db.'.'.$r_table_name.".course_id=".self::$report_db.'.'.$c_table_name.".kid");
            $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, self::$report_db.'.'.$r_table_name.".kid=".self::$report_db.'.'.$cp_table_name.".course_reg_id");
            $where = $base_where;
            $where[] = array('=', $c_table_name.'.course_type', '0');
            $where[] = array('=', $cp_table_name.'.is_passed', '1');
            // $where[] = array('=', $cp_table_name.'.complete_status', '2');
            $where[] = array('=', $cp_table_name.'.complete_type', '1');
            $header['pass_online_period'] = $query->where($where)->one();
            $header['pass_online_period'] = !empty($header['pass_online_period']['course_period']) ? $header['pass_online_period']['course_period']: 0;
            $header['pass_online_period_f'] = self::format_period_value($header['pass_online_period']);
            $header['pass_online_period_sql'] = $query->createCommand()->getRawSql();
            return $header;
    }
      /**
     * 根据条件筛选出相应的课程统计信息
     * @param type $params
     *  $params = array(
     *  'offset' => 0, //开始位置
     *  'limit_num' => 10, //限制数量，默认值为0
     *  'user_id' => '80BD58D4-137E-47BF-E3F3-367EF2341967', //用户ID，支持多个用户，多个的时候请用数组,可不传
     *  'start_day' => '2016-06-10', //开始日期，可不传,必须要和end_day一起传递
     *  'end_day' => '2016-06-11', //结束日期，可不传,必须要和end_day一起传递
     *);
     * @return array(
     * 'totalCount'=>0,
     * 'list'=>array(),
     * 'sql'=>//查询数据的SQL语句,
     * )
     */
    static function getCourseList($params = array()) {
        $r_table_name = 'eln_ln_course_reg'  ; //记录表
        $c_table_name = 'eln_ln_course' ;//课程表
        $cp_table_name = 'eln_ln_course_complete' ; //学习记录表
        $u_table_name = 'eln_fw_user' ; //用户表


        $offset = BoeBase::array_key_is_numbers($params, array('offset', 'offSet'), NULL);
        $limit = BoeBase::array_key_is_numbers($params, array('limit', 'limit_num', 'limitNum'), 0); //数量限制
        $user_id = BoeBase::array_key_is_nulls($params, array('user_id', 'userId', 'userID', 'uid'), NULL); //用户ID
        $course_id = BoeBase::array_key_is_nulls($params, array('course_id', 'cid', 'courseId'), NULL); //课程ID
        $start_day = BoeBase::array_key_is_nulls($params, array('start_day', 'startDay'), NULL); //开始时间
        $end_day = BoeBase::array_key_is_nulls($params, array('end_day', 'endDay'), NULL); //结束时间
        $day_mode = BoeBase::array_key_is_nulls($params, array('day_mode', 'dayMode'), NULL); //时间取值方式,取值如下：

        $base_where = array('and',
            array('=', $r_table_name . '.is_deleted', 0),
            array('=', 'reg_state', 1),
        );
        if ($user_id) {
            $base_where[] = array(is_array($user_id) ? 'in' : '=', $r_table_name . '.user_id', $user_id);
        }
        if ($start_day && $end_day) {
            $base_where[] = array('between', $r_table_name . '.reg_time', $start_day, $end_day);
        }

        if ($course_id) {
            $base_where[] = array(is_array($course_id) ? 'in' : '=', $r_table_name . '.course_id', $course_id);
        }
        $sult = array();
        $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
        $query->select(['`eln_ln_course_reg`.`kid` as rid','eln_ln_course_reg.course_id as course_id','eln_ln_course_reg.user_id as user_id','course_type as type_text','course_name','open_start_time','course_desc_nohtml','course_price','boe_calc_period(course_period,course_period_unit) as course_period_f',"orgnization_id",'rank','real_name','user_no','category_id as category_path','default_credit','real_name as fix_name','course_code']);
        $query->join('INNER JOIN', self::$report_db.'.'.$c_table_name, "{$r_table_name}.course_id={$c_table_name}.kid");
        $query->join('INNER JOIN', self::$report_db.'.'.$u_table_name, "{$r_table_name}.user_id={$u_table_name}.kid");
        $query->andFilterWhere($base_where);
        $sult['totalCount'] = $query->count();
        $query->orderBy("{$r_table_name}.`reg_time`");
        if ($offset) {
            $query->offset($offset);
        }
        if ($limit) {
            $query->limit($limit);
        }
        $sult['list'] = $query->all();
        //echo  $query->createCommand()->getRawSql();
        if($sult['list']){
            foreach ($sult['list'] as $key => $value) {
                $value['type_text'] = $value['type_text']?'面授':'在线';
                $value['category_path'] = self::getCourseCategoryPath($value['category_path']);
                $value['open_start_day'] = $value['open_start_time']?date('Y-m-d', $value['open_start_time']) : '&nbsp;';
                $value['course_period_f']  =self::format_period_value($value['course_period_f']);
                //课程讲师
                $value['teacher_name'] = self::getCourseTeacher($value['course_id']);
                //培训结果
                $reg_sult = self::getRegSult($value['course_id'],$value['user_id'],$value['course_code']);
                $value['status_text']  = $reg_sult?'已结业':'未结业';
                $value['complete_real_score'] = $reg_sult['complete_real_score']?$reg_sult['complete_real_score']:0;
                $value['orgnization_path'] = self::getOrgnizationPath($value['orgnization_id']);
                $value['position_name'] = self::getPositionName($value['user_id']);
                $sult['list'][$key] = $value;
            }
        }
        $sult['sql'] = $query->createCommand()->getRawSql();
        return $sult;
    }
    /**
     * 统计出某个课程统计信息
     * @param type $params
     * @return array
     */
    static function getCourseReport($params) {
        $reg_table_name  = 'eln_ln_course_reg';
        $user_table_name  = 'eln_fw_user';
        $course_table_name  = 'eln_ln_course';
        $cp_table_name='eln_ln_course_complete';
        $course_id = BoeBase::array_key_is_nulls($params, array('course_id', 'cid', 'courseId'), NULL); //课程ID
        if (!$course_id) {
            if (self::isDebugMode()) {
                BoeBase::debug("No Course", 1);
            }
            return NULL;
        }
        $course_info =  (new Query())
                    ->select(['kid','course_name','course_code','created_by as create_name','course_type','course_desc_nohtml','category_id as category_path','open_start_time','default_credit'])
                    ->from(self::$report_db.'.'.$course_table_name)
                    ->where(array('=', 'kid', $course_id))
                    ->one();
        if (!$course_info) {
            if (self::isDebugMode()) {
                BoeBase::debug("Course Not Exists", 1);
            }
            return NULL;
        }
        $sult = $course_info;
        $sult['type_text'] = $sult['course_type'] ?'面授':'在线';
        $sult['category_path'] =  self::getCourseCategoryPath($sult['category_path']);
        $sult['open_start_day'] = $sult['open_start_time']?date('Y-m-d', $sult['open_start_time']) : '&nbsp;';
        $sult['teacher_name'] = self::getCourseTeacher($sult['kid']);
        $tmp = self::getUserName($sult['create_name']);
        $sult['create_name'] = $tmp['fix_name'];
        $base_where = array('and',array('=',$reg_table_name.'.reg_state', 1),
                array('=',$reg_table_name.'.is_deleted', 0),
            );
        $base_where[] = array('=', $reg_table_name.'.course_id', $course_id);

        //学习人数
        $query = (new Query())->from(self::$report_db.'.'.$reg_table_name)->select([new \yii\db\Expression('1')]);
        $where = $base_where;
        $sult['user_num'] = $query->where($where)->count();
        $sult['user_num_sql'] = $query->createCommand()->getRawSql();

        //通过人数
        $query = (new Query())->from(self::$report_db.'.'.$reg_table_name)->select([new \yii\db\Expression('1')]);
        $where = $base_where;
        $where[] = array('=', 'complete_status', '2');
        //$where[] = array('=', 'complete_type', '1');
        //$where[] = array('=', 'is_passed', '1');
        $query->join('INNER JOIN',self::$report_db.'.'.$cp_table_name, $reg_table_name.".kid=".$cp_table_name.".course_reg_id");
        $query->groupBy($cp_table_name.'.user_id');
        $sult['course_passed_num'] = $query->where($where)->count();
        $sult['course_passed_num_sql'] = $query->createCommand()->getRawSql();
        //总学时
        $query = (new Query())->from(self::$report_db.'.'.$reg_table_name);
        $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit))  as course_period")]);
        $query->join('INNER JOIN',self::$report_db.'.'.$course_table_name, $reg_table_name.".course_id=".$course_table_name.".kid");
        $where = $base_where;
        $sult['all_period'] = $query->where($where)->one();
        $sult['all_period'] = !empty($sult['all_period']['course_period']) ?$sult['all_period']['course_period'] : 0;
        $sult['all_period_f'] = self::format_period_value($sult['all_period']);
         $sult['all_period_sql'] = $query->createCommand()->getRawSql();
        // //总费用
        $query = (new Query())->from(self::$report_db.'.'.$reg_table_name);
        $query->select([new \yii\db\Expression("sum(course_price) as course_price")]);
        $query->join('INNER JOIN',self::$report_db.'.'.$course_table_name, $reg_table_name.".course_id=".$course_table_name.".kid");
        $where = $base_where;
        $sult['all_price'] = $query->where($where)->one();
        $sult['all_price'] = !empty($sult['all_price']['course_price']) ? $sult['all_price']['course_price'] : 0;
        return $sult;
    }


    /**
     * 根据条件筛选出相应的统计信息
     * @param type $params
     *  $params = array(
     * 'offset' => 0, //开始位置
     * 'limit_num' => 10, //限制数量，默认值为0
     * 'orderby' => '{report_tabe}.reg_time desc', //排序语句，可不传，默认值为{report_tabe}.reg_time desc，
     * //其中{report_tabe}{course_table}{user_table}这此特殊字符串会被转换成真实的表名
     * //----------------------以下是隶属于条件语句---------------------------------------
     * 'orgnization_id' => '202B0858-BBFE-FAA9-FAC1-A021967E81CD', //组织ID，支持多个组织，多个的时候请用数组,可不传
     * 'domain_id' => 'E4AA33B5-65BC-2720-3197-41930D08EAE9', //域ID，支持多个域，多个的时候请用数组,可不传
     * 'company_id' => '05D00E92-A065-3A91-61C3-A0EDA16715F9', //公司ID，支持多个公司，多个的时候请用数组,可不传
     * 'position_id' => '0365806A-881F-E915-D4E5-527292BA2796', //岗位ID，支持多个岗位，多个的时候请用数组,可不传
     * 'rank' => '职级', //职级，支持多个职级，多个的时候请用数组,可不传
     * 'start_day' => '2016-06-10', //开始日期，可不传,必须要和end_day一起传递
     * 'end_day' => '2016-06-11', //结束日期，可不传,必须要和end_day一起传递
     * );
     * @return array(
     * 'totalCount'=>0,
     * 'list'=>array(),
     * 'sql'=>//查询数据的SQL语句,
     * )
     */
    static function getUserList($params = array(), $debug = 0) {
        $r_table_name = 'eln_ln_course_reg';
        $u_table_name = 'eln_fw_user';
        $c_table_name = 'eln_ln_course';
        $cp_table_name='eln_ln_course_complete';

        $offset = BoeBase::array_key_is_numbers($params, array('offset', 'offSet'), NULL);
        $limit = BoeBase::array_key_is_numbers($params, array('limit', 'limit_num', 'limitNum'), 0); //数量限制
        $get_position = BoeBase::array_key_is_numbers($params, array('get_position', 'getPosition'), 0); //获取用户的岗位信息

        $orderBy = BoeBase::array_key_is_nulls($params, array('orderBy', 'order_by', 'orderby'), '{report_tabe}.reg_time desc');

        $domain_id = BoeBase::array_key_is_nulls($params, array('domain_id', 'domainID', 'did'), NULL); //域ID
        $company_id = BoeBase::array_key_is_nulls($params, array('company_id', 'companyID'), NULL); //公司ID
        $orgnization_id = BoeBase::array_key_is_nulls($params, array('orgnization_id', 'orgnizationID', 'oid'), NULL); //组织ID
        $position_id = BoeBase::array_key_is_nulls($params, array('position_id', 'positionID', 'pid'), NULL); //岗位ID
        $rank = BoeBase::array_key_is_nulls($params, array('rank', 'u_rank'), NULL); //职务
        $start_day = BoeBase::array_key_is_nulls($params, array('start_day', 'startDay'), NULL); //开始时间
        $end_day = BoeBase::array_key_is_nulls($params, array('end_day', 'endDay'), NULL); //结束时间

        if (!$domain_id && !$company_id && !$orgnization_id && !$position_id && !$rank && !$start_day && !$start_day) {
            return NULL;
        }


        $orderBy = str_ireplace(array('{report_tabe}', '{user_table}', '{position_table}'), array($r_table_name, $u_table_name, $c_table_name), $orderBy);

        $report_select_field = array(
            $r_table_name.'.user_id',
            new \yii\db\Expression('count(*) as course_num'),
            new \yii\db\Expression('sum(course_price) as course_price'),
            new \yii\db\Expression('sum(complete_score) as complete_real_score'),
            new \yii\db\Expression('sum(course_period) as course_period'),
        );

        $u_select_field = array(
            'real_name', 'nick_name', 'user_name', 'email', 'user_no', 'orgnization_id', 'domain_id', 'company_id', 'rank'
        );

        //BoeBase::debug($select_field_str,1);
        $base_where = array('and',);

        if ($domain_id) {//按域过滤
            $base_where[] = array(is_array($domain_id) ? 'in' : '=', $u_table_name . '.domain_id', $domain_id);
        }

        if ($company_id) {//按公司过滤
            $base_where[] = array(is_array($company_id) ? 'in' : '=', $u_table_name . '.company_id', $company_id);
        }

        if ($orgnization_id) {//组织过滤
            $base_where[] = array(is_array($orgnization_id) ? 'in' : '=', $u_table_name . '.orgnization_id', $orgnization_id);
        }
        if ($rank) {//按职务过滤
            $base_where[] = array(is_array($rank) ? 'in' : '=', $u_table_name . '.rank', $rank);
        }
        if ($start_day && $end_day) {//按日期
            $base_where[] = array('between', $r_table_name . '.reg_time', $start_day, $end_day);
        }
        $inner_sult = array();
        $sult = array(
            'all_course_num' => 0,
            'all_course_period' => 0,
            'all_complete_real_score' => 0,
            'all_course_price' => 0,
            'avg_course_num' => 0,
            'avg_course_period' => 0,
            'avg_complete_real_score' => 0,
            'avg_course_price' => 0,
            'sql' => array()
        );


        // if ($position_id) {//根据岗位名称过滤S
        //     $subQuery = (new Query())
        //             ->select([new \yii\db\Expression('1')])
        //             ->from($p_table_name)
        //             ->where("{$r_table_name}.user_id={$p_table_name}.user_id");
        //     $subWhere = array('and',
        //         array('=', $p_table_name . '.is_deleted', 0),
        //         array(is_array($position_id) ? 'in' : '=', $p_table_name . '.position_id', $position_id)
        //     );
        //     $subQuery->andFilterWhere($subWhere);
        //     $subQuery->limit(1);
        //     $query->andFilterWhere(['exists', $subQuery]);
        // }//根据岗位名称过滤E
        //全部课程数量
        $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
        $query->join('INNER JOIN', self::$report_db.'.'.$u_table_name, "{$r_table_name}.user_id={$u_table_name}.kid");
        $query->join('INNER JOIN', self::$report_db.'.'.$c_table_name, "{$r_table_name}.course_id={$c_table_name}.kid");
        $where = $base_where;
        $where[] = array('=',$r_table_name.'.reg_state','1');
        $sult['all_course_num'] =  $query->where($where)->select([new \yii\db\Expression('count(*) as sult')])->one();
        $sult['all_course_num'] = !empty($sult['all_course_num']['sult']) ? $sult['all_course_num']['sult'] : 0;
        $sult['sql']['all_course_num'] = $query->createCommand()->getRawSql();

        //全部学时
        $sult['all_course_period'] = $query->select([new \yii\db\Expression('sum(boe_calc_period(course_period,course_period_unit)) as sult')])->one();
        $sult['all_course_period'] = !empty($sult['all_course_period']['sult']) ? $sult['all_course_period']['sult'] : 0;
        $sult['all_course_period'] = self::format_period_value($sult['all_course_period']);
        $sult['sql']['all_course_period'] = $query->createCommand()->getRawSql();

        //全部费用
        $sult['all_course_price'] = $query->select([new \yii\db\Expression('sum(course_price) as sult')])->one();
        $sult['all_course_price'] = !empty($sult['all_course_price']['sult']) ? $sult['all_course_price']['sult'] : 0;
        $sult['sql']['all_course_price'] = $query->createCommand()->getRawSql();

        //全部成绩
        $query2 = (new Query())->from(self::$report_db.'.'.$r_table_name);
        $query2->join('INNER JOIN', self::$report_db.'.'.$u_table_name, "{$r_table_name}.user_id={$u_table_name}.kid");
        $query2->join('INNER JOIN', self::$report_db.'.'.$cp_table_name, "{$r_table_name}.kid={$cp_table_name}.course_reg_id");
        $where = $base_where;
        $where[] = array('=',$r_table_name.'.reg_state','1');
        $sult['all_complete_real_score'] = $query2->where($where)->select([new \yii\db\Expression('sum(complete_score) as sult')])->one();
        $sult['all_complete_real_score'] = !empty($sult['all_complete_real_score']['sult']) ? $sult['all_complete_real_score']['sult'] : 0;
        $sult['sql']['all_complete_real_score'] = $query2->createCommand()->getRawSql();

        //总人数
        $query->orderBy($orderBy);
        $query->groupBy($r_table_name.'.user_id')->indexBy('user_id');
        $sult['totalCount'] = $query->count();
        if ($sult['totalCount']) {
            $sult['avg_course_num'] = str_replace('.00', '', number_format($sult['all_course_num'] / $sult['totalCount'], 2));
            $sult['avg_course_period'] =str_replace('.00', '', number_format($sult['all_course_period'] / $sult['totalCount'], 2));
            $sult['avg_complete_real_score'] = str_replace('.00', '', number_format($sult['all_complete_real_score'] / $sult['totalCount'], 2));
            $sult['avg_course_price'] = str_replace('.00', '', number_format($sult['all_course_price'] / $sult['totalCount'], 2));
        }

        //结果集列表
        $query3 = (new Query())->from(self::$report_db.'.'.$r_table_name);
        $query3->join('INNER JOIN', self::$report_db.'.'.$u_table_name, "{$r_table_name}.user_id={$u_table_name}.kid");
        $where = $base_where;
        $where[] = array('=',$r_table_name.'.reg_state','1');
        $where[] = array('=',$u_table_name.'.is_deleted','0');

        $query3->where($where);
        $query3->groupBy('user_id')->indexBy('user_id');

        if ($offset) {
            $query3->offset($offset);
        }
        if ($limit) {
            $query3->limit($limit);
        }
        $sult['list']= $query3->select("eln_ln_course_reg.`kid` as rid,user_id,user_no,real_name,rank,orgnization_id")->all();

        $sult['sql']['list'] = $query3->createCommand()->getRawSql();
        if (!empty($sult['list'])) {//有相关的数据时S
            $tmp_user_id = array();
            //统计信息
            $r_table_name = 'eln_ln_course_reg';
            $c_table_name = 'eln_ln_course';
            $cp_table_name = 'eln_ln_course_complete';
            $base_where = array('and',array('=',$r_table_name.'.reg_state', 1),
                array('=',$r_table_name.'.is_deleted', 0),
            );
            foreach ($sult['list'] as $key => $a_info) {
                $a_info['orgnization_path']= self::getOrgnizationPath($a_info['orgnization_id']);
                $a_info['position_name']= self::getPositionName($a_info['user_id']);

                //总课程数
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->select([new \yii\db\Expression("1")]);
                $where = $base_where;
                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $inner_sult['course_num'] = $query->where($where)->count();
                $inner_sult['course_num_sql'] = $query->createCommand()->getRawSql();

                //总费用
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->join('INNER JOIN', self::$report_db.'.'.$c_table_name, "{$r_table_name}.course_id={$c_table_name}.kid");
                $query->select([new \yii\db\Expression("sum(course_price) as course_price")]);
                $where = $base_where;
                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $inner_sult['course_price'] = $query->where($where)->one();
                $inner_sult['course_price'] = $inner_sult['course_price']['course_price']?$inner_sult['course_price']['course_price']:0;
                $inner_sult['course_price_sql'] = $query->createCommand()->getRawSql();

                //总学时
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->join('INNER JOIN', self::$report_db.'.'.$c_table_name, "{$r_table_name}.course_id={$c_table_name}.kid");
                $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit)) as course_period")]);
                $where = $base_where;
                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $inner_sult['course_period'] = $query->where($where)->one();
                $inner_sult['course_period'] = $inner_sult['course_period']['course_period']?$inner_sult['course_period']['course_period']:0;
                $inner_sult['course_period'] = self::format_period_value($inner_sult['course_period']);
                $inner_sult['course_period_sql'] = $query->createCommand()->getRawSql();

                //已通过数
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->join('INNER JOIN', self::$report_db.'.'.$cp_table_name, "{$r_table_name}.kid={$cp_table_name}.course_reg_id");
                $query->select([new \yii\db\Expression("1")]);
                $where = $base_where;

                $where[] = array('=', $cp_table_name.'.is_passed', '1');
                // $where[] = array('=', $cp_table_name.'.complete_status', '2');
                $where[] = array('=', $cp_table_name.'.complete_type', '1');

                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $inner_sult['passed_num'] = $query->where($where)->count();
                $inner_sult['passed_num_sql'] = $query->createCommand()->getRawSql();


                //未通过数
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->join('INNER JOIN', self::$report_db.'.'.$cp_table_name, "{$r_table_name}.kid={$cp_table_name}.course_reg_id");
                $query->select([new \yii\db\Expression("1")]);
                $where = $base_where;

                $where[] = array('!=', $cp_table_name.'.complete_status', '2');
                $where[] = array('=', $cp_table_name.'.complete_type', '1');

                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $inner_sult['no_passed_num'] = $query->where($where)->count();
                $inner_sult['no_passed_num_sql'] = $query->createCommand()->getRawSql();

                //在线学时
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->join('INNER JOIN', self::$report_db.'.'.$c_table_name, "{$r_table_name}.course_id={$c_table_name}.kid");
                $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit)) as sult")]);
                $where = $base_where;
                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $where[] = array('=',$c_table_name.'.course_type', 0);
                $inner_sult['online_period'] = $query->where($where)->one();
                $inner_sult['online_period'] = $inner_sult['online_period']['sult']?$inner_sult['online_period']['sult']:0;
                $inner_sult['online_period'] = self::format_period_value($inner_sult['online_period']);
                $inner_sult['online_period_sql'] = $query->createCommand()->getRawSql();

                //线下学时
                $query = (new Query())->from(self::$report_db.'.'.$r_table_name);
                $query->join('INNER JOIN', self::$report_db.'.'.$c_table_name, "{$r_table_name}.course_id={$c_table_name}.kid");
                $query->select([new \yii\db\Expression("sum(boe_calc_period(course_period,course_period_unit)) as sult")]);
                $where = $base_where;
                $where[] = array('=',$r_table_name.'.user_id', $a_info['user_id']);
                $where[] = array('=',$c_table_name.'.course_type', 1);
                $inner_sult['offline_period'] = $query->where($where)->one();
                $inner_sult['offline_period'] = $inner_sult['offline_period']['sult']?$inner_sult['offline_period']['sult']:0;
                $inner_sult['offline_period'] = self::format_period_value($inner_sult['offline_period']);
                $inner_sult['offline_period_sql'] = $query->createCommand()->getRawSql();
                $a_info = array_merge($inner_sult,$a_info);
                $sult['list'][$key] = $a_info;
            }
        }
        return $sult;
    }

    static function getMoreUserInfo($user_no = NULL) {
        if (empty($user_no)) {
            return array();
        }
        $where = array('and');
        $where[] = array(is_array($user_no) ? 'in' : '=', 'user_id', $user_no);
        $field = $field ? $field : 'real_name as fix_name,user_id as kid,user_no,orgnization_id,orgnization_name as orgnization_path,position_id,position_name,rank';
        $user_model = (new Query())->from('elearninglms2.eln_report_user')->select($field);
        $user_info = $user_model->where($where)->indexby('kid')->all();
        return $user_info;
    }


    /************************ 基础方法 ************************/
    /**
     * getTableOneInfo
     * 根据ID获取分类的详细或是某个字段的信息
     * @param type $id 分类的ID
     * @param type $key
     */
    static function getTableOneInfo($table_name = '', $id = 0, $key = '*') {
        if (!$table_name||!$id) {
            return NULL;
        }
        $log_key_name = __METHOD__ . $table_name . '_' . $id;
        $table_all_name = $table_name . '_all';
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时
            if (!isset(self::$currentLog[$table_all_name])) {//未初始化全部分类信息时
                self::$currentLog[$table_all_name] = self::getTableAll($table_name);
            }
            self::$currentLog[$log_key_name] = (isset(self::$currentLog[$table_all_name][$id])) ? self::$currentLog[$table_all_name][$id] : false;
        }
        if ($key != "*" && $key != '') {//返回某一个字段的值，比如名称
            return BoeBase::array_key_is_nulls(self::$currentLog[$log_key_name], $key, NULL);
        }
        return self::$currentLog[$log_key_name];
    }


    /**
     * getTableAll获取某个表的全部数据
     * @param type $create_mode 是否强制从数据库读取
     */
    static function getTableAll($table_name, $create_mode = 0) {
        if (!$table_name) {
            return NULL;
        }
        $cache_name = __METHOD__ . $table_name;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $parent_id = !empty(self::$tableConfig[$table_name]['parent_key_name']) ? self::$tableConfig[$table_name]['parent_key_name'] : 'parent_id';
            $sult = (new Query())->from(self::$report_db.'.'.self::$tableConfig[$table_name]['real_table'])->select(self::$tableConfig[$table_name]['field'])->orderBy(self::$tableConfig[$table_name]['order_by'])->indexBy(self::$tableConfig[$table_name]['primary_key'])->all();
            if ($sult && is_array($sult)) {
                foreach ($sult as $key => $a_info) {
                    $a_info[$parent_id] = trim($a_info[$parent_id]);
                    if ($a_info[$parent_id] === '' || $a_info[$parent_id] === NULL) {
                        $sult[$key][$parent_id] = '0';
                    }
                }
            }
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /**
     * getUserAllRank 获取的账号的职级汇总信息
     * @param type $create_mode 是否强制从数据库读取
     */
    static function getUserAllRank($create_mode = 1) {
        $cache_name = __METHOD__;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $query =  (new Query())->from('`elearninglms2`.`eln_fw_user`');
            $sult = $query
            ->where("is_deleted=0 and status=2 and rank<>''")
            ->select(['rank', new \yii\db\Expression('count(*) as num')])
            ->groupBy(['rank'])
            ->orderBy('num desc')
            ->all();
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    static function getOrgnizationListInfo($keyword = '', $other_info = array()) {
        $limit = BoeBase::array_key_is_numbers($other_info, array('limit', 'limit_num', 'limitNum'), 5);
        $filter_company = BoeBase::array_key_is_nulls($other_info, 'filter_company', '');
        $table_name = "FwOrgnization";
        $table_all_name = $table_name . '_all';
        $tmp_sult = array();

        $tmp_match_num_log = $tmp_level_num_log = array();
        if (!isset(self::$currentLog[$table_all_name])) {//未初始化全部分类信息时S
            self::$currentLog[$table_all_name] = self::getTableAll($table_name);

        }

        $tmp_key = 0;
        if (self::$currentLog[$table_all_name] && is_array(self::$currentLog[$table_all_name])) {//有数据并且是个数组时S
            $tmp_key = 0;
            if ($keyword && strpos($keyword, '\\') !== false) {
                $tmp_keyword = explode('\\', trim($keyword, '\\'));
                $keyword = end($tmp_keyword);
            }
            foreach (self::$currentLog[$table_all_name] as $a_info) {
                $tmp_match = true;
                $tmp_match_num = 0;
                if ($filter_company) {
                    if (is_array($filter_company)) {
                        $tmp_match = in_array($a_info['company_id'], $filter_company);
                    } else {
                        $tmp_match = $a_info['company_id'] == $filter_company;
                    }
                }
                if ($tmp_match && $keyword) {
                    $tmp_name = str_ireplace($keyword, '', $a_info['orgnization_name']);
                    $tmp_match_num = strlen($a_info['orgnization_name']) - strlen($tmp_name);
                    $tmp_match = $tmp_match_num > 0;
                }
                if ($tmp_match) {
                    $a_info['orgnization_path'] = self::getOrgnizationPath($a_info['kid']);
                    $a_info['match_num'] = $tmp_match_num;
                    $tmp_name = str_replace('\\', '', $a_info['orgnization_path']);
                    $a_info['level_num'] = strlen($a_info['orgnization_path']) - strlen($tmp_name) + 1;

                    $tmp_sult[$tmp_key] = $a_info;
                    $tmp_level_num_log[$tmp_key] = $a_info['level_num'];
                    $tmp_match_num_log[$tmp_key] = $a_info['match_num'];
                    $tmp_key++;
                }
            }

            if ($tmp_key) {//有结果了 S
                array_multisort($tmp_level_num_log, SORT_ASC, $tmp_match_num_log, SORT_DESC, $tmp_sult);
                if ($limit) {
                    $tmp_sult = array_slice($tmp_sult, 0, $limit);
                }
                return $tmp_sult;
            }//有结果了 E
        }//有数据并且是个数组时E
        return NULL;
    }

     /**
     * 根据用户kid，获取用户信息
     * @param type $oid
     * @return string
     */
    static function getUserName($kid) {
        $query = (new Query())->from(self::$report_db.'.'.'`eln_fw_user`');
        $sult = $query
            ->select(['real_name as fix_name','user_no'])
            ->where("kid = '".$kid."'")
            ->one();
        //echo $query->createCommand()->getRawSql();;
        return $sult;
    }

      /**
     * 根据调查kid，获取调查信息
     * @param type $oid
     * @return string
     */
    static function getInvestigation($kid) {
        $query = (new Query())->from(self::$report_db.'.`eln_ln_investigation`');
        $sult = $query
            ->select(['title'])
            ->where("kid = '".$kid."'")
            ->one();
        return $sult;
    }



    /**
     * 根据课程注册kid，获取对应培训结果成绩
     * @param type $oid
     * @return string
     */
    static function getRegSult($course_id,$user_id,$course_code) {
        $query = (new Query())->from(self::$report_db.'.'.'`eln_ln_course_complete`');
        $sult = $query
            ->select(['kid','is_passed as status_text','complete_score as complete_real_score'])
            ->where("is_deleted=0 and complete_status = 2  and course_id = '".$course_id."' and user_id='".$user_id."'")
            ->orderBy('is_passed  desc,is_edited desc')
            ->one();

        $score = self::jyCourseUserCompleteScore($course_id,$user_id,$course_code);
        if($sult&&$score['code']){
            $sult['complete_real_score']  = $score['score'];
        }
        $query->createCommand()->getRawSql();
        return $sult;
    }
    //处理特殊课程成绩
    static function jyCourseUserCompleteScore($course_id,$user_id,$course_code){
        $tl = array('20171129023','20171129022','20171129021','20171129020','20171129007','20171129006','20171129019','20171129018','20171129017','20171129016','20171129015','20171129014','20171129012','20171129005','20171129013','20171129009','20171129008','20171129011','20171129010','20171212003','20171212004','20171212005','20171212006','20171212018','20171212019','20171212007','20171212008','20171212009','20171212010','20171212011','20171212012','20171212013','20171212020','20171212002','20171212016','20171212017','20171212014','20171212015'); 
        if(in_array($course_code,$tl)){
            $sult['code']=1;
            $query = (new Query())->from(self::$eln_db.'.'.'`eln_ln_examination_result_user`');
            $re = $query
                ->select(['kid','examination_score'])
                ->where("is_deleted=0 and examination_status = 2  and course_id = '".$course_id."' and user_id='".$user_id."'")
                ->orderBy('created_at  desc')
                ->one();  
            $sult['score']=$re['examination_score'];
        }     
        
        return $sult;
    }

    /**
     * 根据课程注册kid，获取对应培训注册信息
     * @param type $oid
     * @return string
     */
    static function getRegInfo($kid) {
        $query = (new Query())->from(self::$report_db.'.'.'`eln_ln_course_reg`');
        $sult = $query
            ->select(['*'])
            ->where("is_deleted=0 and kid = '".$kid."'")
            ->one();
        return $sult;
    }

    static function getNationality($key,$create_mode){
        $cache_name = __METHOD__.$key;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $query = (new Query())->from('`eln_fw_dictionary`');
            $sult = $query
                ->select(['dictionary_name','dictionary_value'])
                ->where("is_deleted=0 and status=1 and dictionary_category_id = '00000000-0000-0000-0000-000000000025'")
                ->indexby('dictionary_value')
                ->all();
            if($key){
                $sult = $sult[$key]['dictionary_name'];
            }
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    static function getTrainingForm($key,$create_mode){
        $cache_name = __METHOD__.$key;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $query = (new Query())->from('`eln_fw_dictionary`');
            $sult = $query
                ->select(['dictionary_name','dictionary_value'])
                ->where("is_deleted=0 and status=1 and dictionary_category_id = '45009B2D-2EDA-E3CE-4598-8E72EC49796F'")
                ->indexby('dictionary_value')
                ->all();
            if($key){
                $sult = $sult[$key]['dictionary_name'];
            }
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }



    static function getCodeTxt($type,$code){
        switch ($type) {
            case 'learning_form':
                $sult = array('01'=>'认证','02'=>'考试');
                break;
            case 'training_type':
                $sult = array('01'=>'内训','02'=>'外训');
                break;
            case 'mandatory_level':
                $sult = array('01'=>'必修','02'=>'选修');
                break;
            case 'training_area':
                $sult = array('01'=>'境内','02'=>'境外');
                break;
            default:
                $sult = array();
                break;
        }
        return $sult[$code];
    }


    /**
     * 根据课程kid，模块kid，获取对应讲师信息
     * @param type $oid
     * @return string
     */
    static function getCourseModTeacherName($course_id,$mod_id) {
        $where = array('and',array('=','eln_ln_course_teacher.is_deleted',0));
        $where[] = array('=','course_id',$course_id);
        if($mod_id){
            $where[] = array('=','mod_id',$mod_id);
        }
        $query = (new Query())->from(self::$report_db.'.'.'`eln_ln_course_teacher`');
        $sult = $query
            ->join('INNER JOIN', self::$report_db.'.'.'`eln_ln_teacher`', "eln_ln_course_teacher.teacher_id=eln_ln_teacher.kid")
            ->select(['teacher_name','eln_ln_teacher.kid as tid','`eln_ln_teacher`.teacher_type,`eln_ln_course_teacher`.kid as ctid','mod_id','course_time','course_period_unit'])
            ->where($where)
            ->all();
        //$query->createCommand()->getRawSql();
        return $sult;
    }

    /**
     * 根据课程kid，模块kid，模块对应的评分
     * @param type $oid
     * @return string
     */
    static function getModelScores($course_id,$mod_id) {
        $result = '';
        $key[] = '整体上，您对本次培训项目满意程度是';
        $key[] = '您对课程的整体满意程度是';
        $where = array('and',);
        $where[] = array('=','course_id',$course_id);
        if($mod_id){
            $where[] = array('=','mod_id',$mod_id);
        }

        $keyword_where = array('or');
        foreach ($key as $a_info) {
            $keyword_where[] = array('like', 'question_title', '%'.$a_info.'%', false);

        }
        $where[] =  $keyword_where;

        $query = (new Query())->from(self::$report_db.'.'.'`eln_ln_investigation_result`');
        $sult = $query
            ->select(['*'])
            ->where($where)
            ->all();
        $arr = array();
        if(!empty($sult)){
            foreach ($sult as $key => $value) {
                if($value['question_type']==0){
                    $arr['score_array'][]=str_replace('分','',$value['option_result']);
                    $arr['user_array'][]= $value['user_id'];
                }
            }
            $result = round(array_sum($arr['score_array'])/count($arr['score_array']),2);
        }
        // print_r($arr);
       // echo  $query->createCommand()->getRawSql();
       return $result;
    }

     /**
     * 根据课程kid，模块kid，获取对应讲师信息
     * @param type $oid
     * @return string
     */
    static function getCourseTeacher($course_id) {
        $where = array('and',array('=','eln_ln_course_teacher.is_deleted',0));
        $where[] = array('=','course_id',$course_id);
        $query = (new Query())->from(self::$report_db.'.'.'`eln_ln_course_teacher`');
        $sult = $query
            ->join('INNER JOIN', self::$report_db.'.'.'`eln_ln_teacher`', "eln_ln_course_teacher.teacher_id=eln_ln_teacher.kid")
            ->select(['teacher_name'])
            ->where($where)
            ->all();
        if(!empty($sult)){
            foreach ($sult as $key => $value) {
                $sult[$key] = $value['teacher_name'];
            }
        }
        //$query->createCommand()->getRawSql();
        return implode(',', $sult);
    }

    /**
     * 根据用户kid，获取对应岗位信息
     * @param type $oid
     * @return string
     */
    static function getPositionName($kid) {
        $query = (new Query())->from(self::$report_db.'.'.'`eln_fw_user_position`');
        $sult = $query
            ->select(['position_name'])
            ->leftJoin(self::$report_db.'.'.'`eln_fw_position`', '`eln_fw_user_position`.position_id = `eln_fw_position`.kid')
            ->where("is_master=1 and `eln_fw_user_position`.user_id= '".$kid."'")
            ->orderby('is_master desc')
            ->limit(1)
            ->all();
          //  echo $query->createCommand()->getRawSql();
        return $sult[0]['position_name'];

    }


     /**
     * 根据课程kid，获取对应课程信息
     * @param type $oid
     * @return string
     */
    static function getCoursse($kid) {
        $query = (new Query())->from(self::$report_db.'.'.'`eln_ln_course`');
        $sult = $query
            ->select(['kid','course_name','enroll_number'])
            ->where("is_deleted=0 and kid = '".$kid."'")
            ->one();
        return $sult;
    }
    /**
     * 根据组织的ID，获取其对应的路径
     * @param type $oid
     * @return string
     */
    static function getOrgnizationPath($oid) {
        $log_key_name = __METHOD__ . '_' . $oid;
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时
            $tree_node_id = self::getTableOneInfo('FwOrgnization', $oid, 'tree_node_id');
            $path_info = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'node_name_path');
            $c_name = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'tree_node_name');
            self::$currentLog[$log_key_name] = trim(str_replace('/', '\\', $path_info), '\\') . '\\' . trim($c_name, '/');
            self::$currentLog[$log_key_name] = trim(self::$currentLog[$log_key_name], '\\');
        }
        return self::$currentLog[$log_key_name];
    }



     /**
     * 根据组织的ID，获取其对应的路径
     * @param type $kid
     * @return string
     */
    static function getCourseCategoryPath($kid) {
        $log_key_name = __METHOD__ . '_' . $kid;
        if (!isset(self::$currentLog[$log_key_name])) {//当前线程中没有相关的数据时
            $tree_node_id = self::getTableOneInfo('LnCourseCategory', $kid, 'tree_node_id');
            $path_info = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'node_name_path');
            $c_name = self::getTableOneInfo('FwTreeNode', $tree_node_id, 'tree_node_name');
            self::$currentLog[$log_key_name] = trim(str_replace('/', '\\', $path_info), '\\') . '\\' . trim($c_name, '/');
            self::$currentLog[$log_key_name] = trim(self::$currentLog[$log_key_name], '\\');
        }
        return self::$currentLog[$log_key_name];
    }

    static function getUserInfoFromAtString($key,$other_info = array()) {
        $field = BoeBase::array_key_is_nulls($other_info,'field', 'kid,user_name,real_name,nick_name,user_no,email,is_deleted,status');
        $filter_domain = BoeBase::array_key_is_nulls($other_info, 'filter_domain', '');
        $filter_company = BoeBase::array_key_is_nulls($other_info, 'filter_company', '');
        $limit = BoeBase::array_key_is_numbers($other_info, array('limit', 'limit_num', 'limitNum'), 5);

        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status',2);
        if ($exclude_user_id) {//指定了不读取的用户ID时S
            $where[] = array(is_array($exclude_user_id) ? 'not in' : '<>', 'kid', $exclude_user_id);
        }//指定了不读取的用户ID时E

        if ($filter_domain) {//指定了域信息时S
            $where[] = array(is_array($filter_domain) ? 'in' : '=', 'domain_id', $filter_domain);
        }//指定了域信息时E
        if ($filter_company) {//指定了公司信息时S
            $where[] = array(is_array($filter_company) ? 'in' : '=', 'company_id', $filter_company);
        }//指定了公司信息时E

        $keyword_where = array('or');
        foreach ($arr as $key => $a_info) {
            $a_info = trim($a_info);
            if (!$a_info) {
                unset($arr[$key]);
            }
        }
        $keyword_where[] = array('like', 'real_name', $key . '%', false);
        $keyword_where[] = array('like', 'user_name', $key . '%', false);
        $keyword_where[] = array('like', 'user_no', $key . '%', false);
        $keyword_where[] = array('like', 'nick_name', $key . '%', false);

        $query =  (new Query())->from('`elearninglms2`.`eln_fw_user`')->select($field);
        $query->andWhere($where);
        $query->andWhere($keyword_where);
        $result = $query->indexby('kid')->limit($other_info['limit'])->all();
        $sult = array(
            'data' => $result,
            'sql' => $query->createCommand()->getRawSql()
        );
        return $sult;
    }
    static function getCourseListInfo($key,$other_info = array()){
        $field = BoeBase::array_key_is_nulls($other_info, 'field', 'kid,course_name,course_type');
        $filter_domain = BoeBase::array_key_is_nulls($other_info, 'filter_domain', '');
        $filter_company = BoeBase::array_key_is_nulls($other_info, 'filter_company', '');
        $limit = BoeBase::array_key_is_numbers($other_info, array('limit', 'limit_num', 'limitNum'), 5);
        $ln_table_name ='`eln_ln_course`';
        $select_field = array(
            'course_name', 'kid', 'course_code', 'short_code'
        );
        $select_field_str = array();
        foreach ($select_field as $a_info) {
            $select_field_str[] = "{$ln_table_name}.{$a_info} as {$a_info}";
        }
        $select_field_str = implode(',', $select_field_str);

        $sult = array();
        $base_where = array('and',
            array('=', $ln_table_name . '.is_deleted', 0),
            array('=', $ln_table_name . '.is_display_pc', LnCourse::DISPLAY_PC_YES),
        );
        $query = (new Query())->from(self::$report_db.'.'.$ln_table_name);
        if ($filter_domain) {//指定了域信息时S
            $domain_table_name = '`eln_ln_resource_domain`';
            $query->distinct();
            $query->join('INNER JOIN', self::$report_db.'.'.$domain_table_name, "{$domain_table_name}.resource_id={$ln_table_name}.kid");
            $base_where[] = array('=', $domain_table_name . '.is_deleted', 0);
            $base_where[] = array(is_array($filter_domain) ? 'in' : '=', $domain_table_name . '.domain_id', $filter_domain);
        }//指定了域信息时E
        $keyword_where = array('or');
        foreach ($arr as $key => $a_info) {
            $a_info = trim($a_info);
            if (!$a_info) {
                unset($arr[$key]);
            }
        }
        $keyword_where[] = array('like', $ln_table_name . '.course_code', $key . '%', false);
        $keyword_where[] = array('like', $ln_table_name . '.course_name', '%'.$key . '%',false);

        $query->select($select_field_str);
        $query->indexBy();
        $query->limit($limit);
        $query->andFilterWhere($base_where);
        $query->andFilterWhere($keyword_where);
        $sult = array(
            'data' => $query->all(),
            'sql' => $query->createCommand()->getRawSql(),
        );
        return $sult;
    }
    //课程状态判断
    static function course_status($start_time,$end_time){
        $status_text = '';
        $current_time = time();
        if($current_time <$start_time){
            $status_text='未开始';
        }elseif ($current_time >$end_time) {
            if($end_time){
                $status_text = '已结束';
            }else{
                $status_text = '进行中';
            }

        }elseif ($current_time >=$start_time&&$current_time <=$end_time) {
            $status_text ='进行中';
        }
        return $status_text;
    }

    static function getCourseUser($course_id){
        $sult = array();
        $where = array('and',);
        $query =  (new \yii\db\Query())->from('`elearninglms2`.`eln_ln_course_reg`');
        $query->join('LEFT JOIN','`elearninglms2`.`eln_fw_user`', "eln_ln_course_reg.user_id=eln_fw_user.kid");
        $where[] = array('=','eln_ln_course_reg.is_deleted','0');
        $where[] = array('=','eln_ln_course_reg.course_id',$course_id);
        $where[] = array('=','reg_state','1');
        $sult  = $query->where($where)->select(['user_no','real_name','eln_ln_course_reg.kid as reg_id','user_id','employee_status','orgnization_id as orgnization_path'])->all();
        if(!empty($sult)){
            foreach ($sult as $key=>$value) {
                $reg_sult = self::getRegSult($value['reg_id']);
                $value['is_passed']  = $reg_sult['status_text']?$reg_sult['status_text']:0;
                $value['status_text']  = $reg_sult['status_text']?'已结业':'未结业';
                $value['complete_score'] = $reg_sult['complete_real_score']?floatval($reg_sult['complete_real_score']):0;
                $value['orgnization_path'] = BoeReportsService::getOrgnizationPath($value['orgnization_path']);
                $value['employee_status'] = self::getEmployeeStatu($value['employee_status']);
                $sult[$key] = $value;
            }
        }
        return $sult;
    }

    static function getEmployeeStatu($code){

        $cache_name = __METHOD__;
        $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        if (!$sult) {//从数据库读取
            $query =  (new \yii\db\Query())->from('`elearninglms2`.`eln_fw_dictionary`');
            $where  = array('and',);
            $where[] = array('=','is_deleted','0');
            $where[] = array('=','dictionary_category_id','00000000-0000-0000-0000-000000000016');
            $where[] = array('=','company_id','05D00E92-A065-3A91-61C3-A0EDA16715F9');
            $where[] = array('=','status','1');
            $result  = $query->where($where)->select(['dictionary_code','dictionary_name'])->all();
            $sult = array();
            foreach ($result as $key => $value) {
                $code = $value['dictionary_code'];
                $sult[$code]=$value['dictionary_name'];
            }
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult[$code];
    }
}

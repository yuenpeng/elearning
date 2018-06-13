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

 * User: Zheng lk
 * Date: 2016/6/19
 * Time: 8:10
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeReportService {

    private static $cacheTime = 86400;
    private static $currentLog = array();

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
        if (!$sult) {//更新数据
            $command = Yii::$app->db->createCommand('call upload_boe_course_report(@s)');
            $res = $command->execute();
            $s = Yii::$app->db->createCommand("select @s");
            $ret = $s->queryOne();
            $sult = 'Update Time:' . date("Y-m-d H:i:s");
            Yii::$app->cache->set($cache_name, $sult, self::$cacheTime); // 设置缓存
        }
        return $sult;
    }

    /**
     * 根据条件筛选出相应的课程统计信息
     * @param type $params
     *  $params = array(
      'offset' => 0, //开始位置
      'limit_num' => 10, //限制数量，默认值为0
      'get_teacher' => 1, //是否获取课程的老师信息，为0是不获取，其它值获取，默认值为0
      'get_user' => 1, //是否获取用户的组织路径、公司名称、域名称信息，为0是不获取，其它值获取，默认值为0
      'get_category_path' => 1, //是否获取课程目录的路径信息，为0是不获取，其它值获取，默认值为0
      'orderby' => '{report_tabe}.reg_time desc', //排序语句，可不传，默认值为{report_tabe}.reg_time desc，
      //其中{report_tabe}{course_table}{user_table}这此特殊字符串会被转换成真实的表名
      //----------------------以下是隶属于条件语句---------------------------------------
      'user_id' => '80BD58D4-137E-47BF-E3F3-367EF2341967', //用户ID，支持多个用户，多个的时候请用数组,可不传
      'course_id' => '0880922B-D56E-A3F2-DF67-3DDAAAA44240', //课程ID，支持多个课程，多个的时候请用数组,可不传
      'orgnization_id' => '202B0858-BBFE-FAA9-FAC1-A021967E81CD', //组织ID，支持多个组织，多个的时候请用数组,可不传
      'domain_id' => 'E4AA33B5-65BC-2720-3197-41930D08EAE9', //域ID，支持多个域，多个的时候请用数组,可不传
      'company_id' => '05D00E92-A065-3A91-61C3-A0EDA16715F9', //公司ID，支持多个公司，多个的时候请用数组,可不传
      'position_id' => '0365806A-881F-E915-D4E5-527292BA2796', //岗位ID，支持多个岗位，多个的时候请用数组,可不传
      'rank' => '职级', //职级，支持多个职级，多个的时候请用数组,可不传
      'start_day' => '2016-06-10', //开始日期，可不传,必须要和end_day一起传递
      'end_day' => '2016-06-11', //结束日期，可不传,必须要和end_day一起传递
      'day_mode' => '1', //日期模式
      );
     * @return array(
      'totalCount'=>0,
      'list'=>array(),
      'sql'=>//查询数据的SQL语句,
      )
     */
    static function getCourseList($params = array()) {
        self::updateReport(); //初始化数据
        $r_table_name = BoeCourseReport::realTableName(); //
        $ln_table_name = LnCourse::realTableName();
        $u_table_name = FwUser::realTableName();
        $p_table_name = FwUserPosition::realTableName();

        $offset = BoeBase::array_key_is_numbers($params, array('offset', 'offSet'), NULL);

        $limit = BoeBase::array_key_is_numbers($params, array('limit', 'limit_num', 'limitNum'), 0); //数量限制
        $get_teacher = BoeBase::array_key_is_numbers($params, array('get_teacher', 'getTeacher'), 0); //获取老师信息
        $get_user = BoeBase::array_key_is_numbers($params, array('get_user', 'getUser'), 0); //否获取用户的组织路径、公司名称、域名称信息
        $get_category_path = BoeBase::array_key_is_numbers($params, array('get_category_path', 'getCategoryPath'), 0); //获取分类路径
        $get_position = BoeBase::array_key_is_numbers($params, array('get_position', 'getPosition'), 0); //获取用户的岗位信息

        $orderBy = BoeBase::array_key_is_nulls($params, array('orderBy', 'order_by', 'orderby'), '{report_tabe}.reg_time desc');
        $user_id = BoeBase::array_key_is_nulls($params, array('user_id', 'userId', 'userID', 'uid'), NULL); //用户ID
        $course_id = BoeBase::array_key_is_nulls($params, array('course_id', 'cid', 'courseId'), NULL); //课程ID
        $domain_id = BoeBase::array_key_is_nulls($params, array('domain_id', 'domainID', 'did'), NULL); //域ID
        $company_id = BoeBase::array_key_is_nulls($params, array('company_id', 'companyID'), NULL); //公司ID     

        $start_day = BoeBase::array_key_is_nulls($params, array('start_day', 'startDay'), NULL); //开始时间
        $end_day = BoeBase::array_key_is_nulls($params, array('end_day', 'endDay'), NULL); //结束时间    
        $day_mode = BoeBase::array_key_is_nulls($params, array('day_mode', 'dayMode'), NULL); //时间取值方式,取值如下：


        $orderBy = str_ireplace(array('{report_tabe}', '{course_table}', '{user_table}', '{position_table}'), array($r_table_name, $ln_table_name, $u_table_name, $p_table_name), $orderBy);
        $report_select_field = array(
            'user_id',
            'course_id',
            'course_period',
            'course_price',
            'reg_time',
            'complete_status',
            'complete_real_score',
            'create_day',
            'create_time'
        );
        $ln_select_field = array(
            'category_id',
            'course_name',
            'course_desc_nohtml',
            'course_type',
            'start_time',
            'end_time',
            'enroll_start_time',
            'enroll_end_time',
            'open_start_time',
            'open_end_time',
        );
        $u_select_field = array(
            'real_name', 'nick_name', 'user_name', 'email', 'user_no', 'orgnization_id', 'domain_id', 'company_id', 'rank'
        );

        $select_field_str = array();
        foreach ($report_select_field as $a_info) {
            $select_field_str[] = "{$r_table_name}.{$a_info} as {$a_info}";
        }
        foreach ($ln_select_field as $a_info) {
            $select_field_str[] = "{$ln_table_name}.{$a_info} as {$a_info}";
        }
        foreach ($u_select_field as $a_info) {
            $select_field_str[] = "{$u_table_name}.{$a_info} as {$a_info}";
        }
//        BoeBase::debug($select_field_str,1);
        $base_where = array('and',
            array('=', $ln_table_name . '.is_deleted', 0),
        );
        if ($user_id) {
            $base_where[] = array(is_array($user_id) ? 'in' : '=', $r_table_name . '.user_id', $user_id);
        }
        if ($course_id) {
            $base_where[] = array(is_array($course_id) ? 'in' : '=', $r_table_name . '.course_id', $course_id);
        }

        if ($orgnization_id) {//组织过滤
            $base_where[] = array(is_array($orgnization_id) ? 'in' : '=', $u_table_name . '.orgnization_id', $orgnization_id);
        }

        if ($domain_id) {//按域过滤
            $base_where[] = array(is_array($domain_id) ? 'in' : '=', $u_table_name . '.domain_id', $domain_id);
        }


        if ($company_id) {//按公司过滤
            $base_where[] = array(is_array($company_id) ? 'in' : '=', $u_table_name . '.company_id', $company_id);
        }

        if ($rank) {//按职务过滤
            $base_where[] = array(is_array($rank) ? 'in' : '=', $u_table_name . '.rank', $rank);
        }

        if ($start_day && $end_day) {//按日期 
            switch ($day_mode) {
                case 1: case 'start_time'://开始时间 case 2: case 'end_time'://结束时间 case 3: case 'enroll_start_time'://报名开始时间
                    $base_where[] = array('between', $ln_table_name . '.start_time', strtotime($start_day), strtotime($end_day));
                    break;
                case 2: case 'end_time'://结束时间
                    $base_where[] = array('between', $ln_table_name . '.end_time', strtotime($start_day), strtotime($end_day));
                    break;
                case 3: case 'enroll_start_time'://报名开始时间
                    $base_where[] = array('between', $ln_table_name . '.enroll_start_time', strtotime($start_day), strtotime($end_day));
                    break;
                case 4: case 'enroll_end_time'://报名结束时间
                    $base_where[] = array('between', $ln_table_name . '.enroll_end_time', strtotime($start_day), strtotime($end_day));
                    break;
                case 5: case 'open_start_time'://开班时间
                    $base_where[] = array('between', $ln_table_name . '.open_start_time', strtotime($start_day), strtotime($end_day));
                    break;
                case 6: case 'open_end_time'://开班结束时间
                    $base_where[] = array('between', $ln_table_name . '.open_end_time', strtotime($start_day), strtotime($end_day));
                    break;
                case 7: case 'release_at': case 'release_time'://发布时间
                    $base_where[] = array('between', $ln_table_name . '.release_at', strtotime($start_day), strtotime($end_day));
                    break;
                case 8: case 'created_at': case 'created_time': case 'create_time'://创建时间
                    $base_where[] = array('between', $ln_table_name . '.created_at', strtotime($start_day), strtotime($end_day));
                    break;
                default://用户登录时间 
                    $base_where[] = array('between', $r_table_name . '.reg_time', $start_day, $end_day);
                    break;
            }
        }

        $sult = array();
        $query = (new Query())->from($r_table_name);
        $query->select($select_field_str);
        $query->join('INNER JOIN', $ln_table_name, "{$r_table_name}.course_id={$ln_table_name}.kid");
        $query->join('INNER JOIN', $u_table_name, "{$r_table_name}.user_id={$u_table_name}.kid");
        $query->andFilterWhere($base_where);

        $sult['totalCount'] = $query->count();
        $query->orderBy($orderBy);
        if ($offset) {
            $query->offset($offset);
        }
        if ($limit) {
            $query->limit($limit);
        }
        $sult['list'] = $query->all();
        if ($sult['list']) {
            $tmp_user_id = array();
            $tmp_course_id = array();
            $teacher_db_list = $user_info_arr = array();
            foreach ($sult['list'] as $key => $a_info) {
//                if ($get_user) {//需要获取用户信息S
//                    $tmp_user_id[] = $a_info['user_id'];
//                }//需要获取用户信息E
                $tmp_course_id[] = $a_info['course_id'];
            }

            if ($get_teacher) {//获取老师的时候S
                $teacher_db_list = BoeBaseService::getCourseListTeacherInfo($tmp_course_id, 0);
                // BoeBase::debug($teacher_db_list);
            }//获取老师的时候E

            foreach ($sult['list'] as $key => $a_info) {//整理相关的信息For start
                $a_info = self::parseCourseInfo($a_info, $get_category_path);

                if ($get_teacher) {
                    $a_info['teacher_name_list'] = BoeBaseService::getCourseTeacherNameArr($a_info['course_id'], $teacher_db_list['teacher_list']);
                    $a_info['teacher_name'] = implode(',', $a_info['teacher_name_list']);
                }

                if ($get_position) {
                    $a_info['position_name'] = BoeBaseService::getUserPositonInfo($a_info['user_id'], 1);
                }

                $sult['list'][$key] = $a_info;
            }//整理相关的信息For End
            if ($get_user) {//获取的详细信息S
                $sult['list'] = BoeBaseService::parseUserListInfo($sult['list']);
            }
        }

        $sult['sql'] = $query->createCommand()->getRawSql();
        return $sult;
    }

    private static function parseCourseInfo($a_info, $get_category_path = 0) {
        if (!isset(self::$currentLog['course_type_value'])) {
            self::$currentLog['course_type_value'] = Yii::t('boe', 'report_course_type_value');
        }
        if (!isset(self::$currentLog['complete_status_value'])) {
            self::$currentLog['complete_status_value'] = Yii::t('boe', 'report_complete_status_value');
        }
        $status_text = &self::$currentLog['complete_status_value'];
        $type_text = &self::$currentLog['course_type_value'];

        $a_info['start_time'] = $a_info['start_time'] ? date('Y-m-d H:i:s', $a_info['start_time']) : '&nbsp;';
        $a_info['end_time'] = $a_info['end_time'] ? date('Y-m-d H:i:s', $a_info['end_time']) : '&nbsp;';
        $a_info['enroll_start_day'] = $a_info['enroll_start_time'] ? date('Y-m-d', $a_info['enroll_start_time']) : '&nbsp;';
        $a_info['enroll_end_day'] = $a_info['enroll_end_time'] ? date('Y-m-d', $a_info['enroll_end_time']) : '&nbsp;';
        $a_info['open_start_day'] = $a_info['open_start_time'] ? date('Y-m-d', $a_info['open_start_time']) : '&nbsp;';
        $a_info['open_end_day'] = $a_info['open_end_time'] ? date('Y-m-d', $a_info['open_end_time']) : '&nbsp;';
        $a_info['enroll_start_time'] = $a_info['enroll_start_time'] ? date('Y-m-d H:i:s', $a_info['enroll_start_time']) : '&nbsp;';
        $a_info['enroll_end_time'] = $a_info['enroll_end_time'] ? date('Y-m-d H:i:s', $a_info['enroll_end_time']) : '&nbsp;';
        $a_info['open_start_time'] = $a_info['open_start_time'] ? date('Y-m-d H:i:s', $a_info['open_start_time']) : '&nbsp;';
        $a_info['open_end_time'] = $a_info['open_end_time'] ? date('Y-m-d H:i:s', $a_info['open_end_time']) : '&nbsp;';
        $a_info['type_text'] = BoeBase::array_key_is_nulls($type_text, $a_info['course_type'], '&nbsp;');
        $a_info['status_text'] = BoeBase::array_key_is_nulls($status_text, $a_info['complete_status'], '&nbsp;');
        $a_info['course_period_f'] = self::format_period_value($a_info['course_period']);
        if ($get_category_path) {
            $a_info['category_path'] = BoeBaseService::getCourseCategoryPath($a_info['category_id']);
        }
        return $a_info;
    }

    /**
     * 统计出某个课程统计信息
     * @param type $params
     * @return array
     */
    static function getCourseReport($params) {
        self::updateReport(); //初始化数据
        $course_id = BoeBase::array_key_is_nulls($params, array('course_id', 'cid', 'courseId'), NULL); //课程ID
        if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug($params);
        }
        if (!$course_id) {
            if (self::isDebugMode()) {
                BoeBase::debug("No Course", 1);
            }
            return NULL;
        }
        $course_info = LnCourse::find(false)->where(array('=', 'kid', $course_id))->asArray()->one();
        if (!$course_info) {
            if (self::isDebugMode()) {
                BoeBase::debug("Course Not Exists", 1);
            }
            return NULL;
        }
        $type_text = Yii::t('boe', 'report_course_type_value');
        $status_text = Yii::t('boe', 'report_complete_status_value');
        //获取老师的时候S
        $teacher_db_list = BoeBaseService::getCourseListTeacherInfo($course_id, 0);
        $teacher_name_list = BoeBaseService::getCourseTeacherNameArr($course_id, $teacher_db_list['teacher_list']);
        $creater_list = BoeBaseService::getMoreUserInfo($course_info['created_by'], 0);

        $sult = self::parseCourseInfo($course_info, 1);
        $sult['teacher_name'] = implode(',', $teacher_name_list);
        $sult['create_name'] = BoeBase::array_key_is_nulls($creater_list, array($course_info['created_by'] => 'real_name'), '&nbsp;');

        $r_table_name = BoeCourseReport::realTableName();
        $base_where = array('and',);
        $base_where[] = array('=', $r_table_name . '.course_id', $course_id);
        //学习人数
        $query = (new Query())->from($r_table_name)->select([new \yii\db\Expression('1')]);
        $sult['user_num'] = $query->where($base_where)->count();
        $sult['user_num_sql'] = $query->createCommand()->getRawSql();
        //通过人数
        $where = $base_where;
        $where[] = array('=', 'complete_status', '1');
        $sult['course_passed_num'] = $query->where($where)->count();
        $sult['course_passed_num_sql'] = $query->createCommand()->getRawSql();
        //总学时
        $query = (new Query())->from($r_table_name);
        $sult['all_period'] = $query->select([new \yii\db\Expression('sum(course_period) as course_period ')])->where($base_where)->one(); //全部的学时
        $sult['all_period'] = !empty($sult['all_period']['course_period']) ? $sult['all_period']['course_period'] : 0;
        $sult['all_period_f'] = self::format_period_value($sult['all_period']);
        $sult['all_period_sql'] = $query->createCommand()->getRawSql();
        //总费用
        $query = (new Query())->from($r_table_name);
        $sult['all_price'] = $query->select([new \yii\db\Expression('sum(course_price) as course_price ')])->where($base_where)->one(); //全部的学时
        $sult['all_price'] = !empty($sult['all_price']['course_price']) ? $sult['all_price']['course_price'] : 0;
        $sult['all_price_sql'] = $query->createCommand()->getRawSql();

        if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug($params);
        }
        if (self::isDebugMode()) {
            BoeBase::debug($sult, 1);
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
     * 统计出某个用户的课程信息
     * @param type $params
     * @return array
     */
    static function getUserReport($params) {
        self::updateReport(); //初始化数据
        $start_day = BoeBase::array_key_is_nulls($params, array('start_day', 'startDay'), NULL); //开始时间
        $end_day = BoeBase::array_key_is_nulls($params, array('end_day', 'endDay'), NULL); //结束时间    
        $day_mode = BoeBase::array_key_is_nulls($params, array('day_mode', 'dayMode'), NULL); //时间取值方式,取值如下：
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
        $user_info = FwUser::find(false)->where(array(is_array($user_id) ? 'in' : '=', 'kid', $user_id))->asArray()->one();

        if (!$user_info) {
            if (self::isDebugMode()) {
                BoeBase::debug("User Not Exists", 1);
            }
            return NULL;
        }
        $sult = array(
            'name' => $user_info['real_name'],
            'rank' => $user_info['rank'],
            'orgnization_path' => BoeBaseService::getOrgnizationPath($user_info['orgnization_id']),
            'position_name' => BoeBaseService::getUserPositonInfo($user_id, 1),
        );
        $r_table_name = BoeCourseReport::realTableName();
        $ln_table_name = LnCourse::realTableName();
        $base_where = array('and',);
        if ($user_id) {
            $base_where[] = array('=', $r_table_name . '.user_id', $user_id);
        }
        if ($start_day && $end_day) {//按日期 
            $base_where[] = array('between', $r_table_name . '.reg_time', $start_day, $end_day);
        }
        $query = (new Query())->from($r_table_name)->select([new \yii\db\Expression('1')]);
        $sult['course_num'] = $query->where($base_where)->count();
        $sult['course_num_sql'] = $query->where($base_where)->createCommand()->getRawSql();
        $where = $base_where;
        $where[] = array('=', 'complete_status', '1');
        $sult['course_passed_num'] = $query->where($where)->count();
        $sult['course_passed_num_sql'] = $query->where($where)->createCommand()->getRawSql();
        $where = $base_where;
        $where[] = array('=', 'complete_status', '0');
        $sult['course_no_passed_num'] = $query->where($where)->count();
        $sult['course_no_passed_num_sql'] = $query->where($where)->createCommand()->getRawSql();


        $query = (new Query())->from($r_table_name);
        $query->select([new \yii\db\Expression("sum(boe_calc_period({$ln_table_name}.course_period,{$ln_table_name}.course_period_unit))  as course_period ")]);
        $query->join('INNER JOIN', $ln_table_name, "{$r_table_name}.course_id={$ln_table_name}.kid");
        $where = $base_where;
        $where[] = array('=', "{$ln_table_name}.course_type", '0');
        $sult['all_online_period'] = $query->where($where)->one(); //在线教育的学时
        $sult['all_online_period'] = !empty($sult['all_online_period']['course_period']) ? $sult['all_online_period']['course_period'] : 0;
        $sult['all_online_period_f'] = self::format_period_value($sult['all_online_period']);
        $sult['all_online_period_sql'] = $query->createCommand()->getRawSql();

        $where = $base_where;
        $where[] = array('=', "{$ln_table_name}.course_type", '1');
        $sult['all_offline_period'] = $query->where($where)->one(); //面授的学时
        $sult['all_offline_period'] = !empty($sult['all_offline_period']['course_period']) ? $sult['all_offline_period']['course_period'] : 0;
        $sult['all_offline_period_f'] = self::format_period_value($sult['all_offline_period']);
        $sult['all_offline_period_sql'] = $query->createCommand()->getRawSql();

        $query = (new Query())->from($r_table_name);
        $sult['all_period'] = $query->select([new \yii\db\Expression('sum(course_period) as course_period ')])->where($base_where)->one(); //全部的学时
        $sult['all_period'] = !empty($sult['all_period']['course_period']) ? $sult['all_period']['course_period'] : 0;
        $sult['all_period_f'] = self::format_period_value($sult['all_period']);
        $sult['all_period_sql'] = $query->createCommand()->getRawSql();


        $query = (new Query())->from($r_table_name);
        $sult['all_price'] = $query->select([new \yii\db\Expression('sum(course_price) as course_price ')])->where($base_where)->one(); //全部的学时
        $sult['all_price'] = !empty($sult['all_price']['course_price']) ? $sult['all_price']['course_price'] : 0;
        $sult['all_price_sql'] = $query->createCommand()->getRawSql();
        if (self::isDebugMode()) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug($params);
        }
        if (self::isDebugMode()) {
            BoeBase::debug($sult, 1);
        }
        return $sult;
    }

    /**
     * 根据条件筛选出相应的统计信息
     * @param type $params
     *  $params = array(
      'offset' => 0, //开始位置
      'limit_num' => 10, //限制数量，默认值为0
      'orderby' => '{report_tabe}.reg_time desc', //排序语句，可不传，默认值为{report_tabe}.reg_time desc，
      //其中{report_tabe}{course_table}{user_table}这此特殊字符串会被转换成真实的表名
      //----------------------以下是隶属于条件语句---------------------------------------
      'orgnization_id' => '202B0858-BBFE-FAA9-FAC1-A021967E81CD', //组织ID，支持多个组织，多个的时候请用数组,可不传
      'domain_id' => 'E4AA33B5-65BC-2720-3197-41930D08EAE9', //域ID，支持多个域，多个的时候请用数组,可不传
      'company_id' => '05D00E92-A065-3A91-61C3-A0EDA16715F9', //公司ID，支持多个公司，多个的时候请用数组,可不传
      'position_id' => '0365806A-881F-E915-D4E5-527292BA2796', //岗位ID，支持多个岗位，多个的时候请用数组,可不传
      'rank' => '职级', //职级，支持多个职级，多个的时候请用数组,可不传
      'start_day' => '2016-06-10', //开始日期，可不传,必须要和end_day一起传递
      'end_day' => '2016-06-11', //结束日期，可不传,必须要和end_day一起传递
      );
     * @return array(
      'totalCount'=>0,
      'list'=>array(),
      'sql'=>//查询数据的SQL语句,
      )
     */
    static function getUserList($params = array(), $debug = 0) {
        self::updateReport(); //初始化数据
        $r_table_name = BoeCourseReport::realTableName();
        $u_table_name = FwUser::realTableName();
        $p_table_name = FwUserPosition::realTableName();

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


        $orderBy = str_ireplace(array('{report_tabe}', '{user_table}', '{position_table}'), array($r_table_name, $u_table_name, $p_table_name), $orderBy);
        $report_select_field = array(
            'user_id',
            new \yii\db\Expression('count(*) as course_num'),
            new \yii\db\Expression('sum(course_price) as course_price'),
            new \yii\db\Expression('sum(complete_real_score) as complete_real_score'),
            new \yii\db\Expression('sum(course_period) as course_period'),
        );

        $u_select_field = array(
            'real_name', 'nick_name', 'user_name', 'email', 'user_no', 'orgnization_id', 'domain_id', 'company_id', 'rank'
        );

//        BoeBase::debug($select_field_str,1);
        $base_where = array('and',
            array('=', $u_table_name . '.is_deleted', 0),
        );

        if ($orgnization_id) {//组织过滤
            $base_where[] = array(is_array($orgnization_id) ? 'in' : '=', $u_table_name . '.orgnization_id', $orgnization_id);
        }

        if ($domain_id) {//按域过滤
            $base_where[] = array(is_array($domain_id) ? 'in' : '=', $u_table_name . '.domain_id', $domain_id);
        }


        if ($company_id) {//按公司过滤
            $base_where[] = array(is_array($company_id) ? 'in' : '=', $u_table_name . '.company_id', $company_id);
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
        $query = (new Query())->from($r_table_name);
        $query->join('INNER JOIN', $u_table_name, "{$r_table_name}.user_id={$u_table_name}.kid");
        $query->where($base_where);

        if ($position_id) {//根据岗位名称过滤S
            $subQuery = (new Query())
                    ->select([new \yii\db\Expression('1')])
                    ->from($p_table_name)
                    ->where("{$r_table_name}.user_id={$p_table_name}.user_id");
            $subWhere = array('and',
                array('=', $p_table_name . '.is_deleted', 0),
                array(is_array($position_id) ? 'in' : '=', $p_table_name . '.position_id', $position_id)
            );
            $subQuery->andFilterWhere($subWhere);
            $subQuery->limit(1);
            $query->andFilterWhere(['exists', $subQuery]);
        }//根据岗位名称过滤E
        //全部课程数量
        $sult['all_course_num'] = $query->select([new \yii\db\Expression('count(*) as sult')])->one();
        $sult['all_course_num'] = !empty($sult['all_course_num']['sult']) ? $sult['all_course_num']['sult'] : 0;
        $sult['sql']['all_course_num'] = $query->createCommand()->getRawSql();
//全部学时
        $sult['all_course_period'] = $query->select([new \yii\db\Expression('sum(course_period) as sult')])->one();
        $sult['all_course_period_num'] = !empty($sult['all_course_period']['sult']) ? $sult['all_course_period']['sult'] : 0;
        $sult['all_course_period'] = self::format_period_value($sult['all_course_period_num']);
        $sult['sql']['all_course_period'] = $query->createCommand()->getRawSql();
//全部成绩
        $sult['all_complete_real_score'] = $query->select([new \yii\db\Expression('sum(complete_real_score) as sult')])->one();
        $sult['all_complete_real_score'] = !empty($sult['all_complete_real_score']['sult']) ? $sult['all_complete_real_score']['sult'] : 0;
        $sult['sql']['all_complete_real_score'] = $query->createCommand()->getRawSql();
//全部费用
        $sult['all_course_price'] = $query->select([new \yii\db\Expression('sum(course_price) as sult')])->one();
        $sult['all_course_price'] = !empty($sult['all_course_price']['sult']) ? $sult['all_course_price']['sult'] : 0;
        $sult['sql']['all_course_price'] = $query->createCommand()->getRawSql();

        $query->select($report_select_field);
        $query->orderBy($orderBy);
        $query->groupBy('user_id')->indexBy('user_id');
        $sult['totalCount'] = $query->count();
        if ($sult['totalCount']) {
            $sult['avg_course_num'] = str_replace('.00', '', number_format($sult['all_course_num'] / $sult['totalCount'], 2));
            $sult['avg_course_period'] = self::format_period_value($sult['all_course_period_num'] / $sult['totalCount']);
            $sult['avg_complete_real_score'] = str_replace('.00', '', number_format($sult['all_complete_real_score'] / $sult['totalCount'], 2));
            $sult['avg_course_price'] = str_replace('.00', '', number_format($sult['all_course_price'] / $sult['totalCount'], 2));
        }
        if ($offset) {
            $query->offset($offset);
        }
        if ($limit) {
            $query->limit($limit);
        }
        $sult['list'] = $query->all();
        $sult['sql']['list'] = $query->createCommand()->getRawSql();
        if ($sult['list']) {//有相关的数据时S
            $tmp_user_id = array();
            foreach ($sult['list'] as $key => $a_info) {
                $tmp_user_id[] = $a_info['user_id'];
            }
            $user_where = array('and', array('in', $r_table_name . '.user_id', $tmp_user_id));

            $report_select_count_field = array(
                'user_id',
                new \yii\db\Expression('count(*) as sult'),
            );


            $pass_where = $user_where;
            $pass_where[] = array('=', 'complete_status', '1');
            //已经通过的数量
            $tmp_query = (new Query())->from($r_table_name)->select($report_select_count_field)->where($pass_where)->groupBy('user_id')->indexBy('user_id');
            $inner_sult['passed_list'] = $tmp_query->all();
            $sult['sql']['passed'] = $tmp_query->createCommand()->getRawSql();

            //未通过的数量
            $no_pass_where = $user_where;
            $no_pass_where[] = array('=', 'complete_status', '0');
            $tmp_query = (new Query())->from($r_table_name)->select($report_select_count_field)->where($no_pass_where)->groupBy('user_id')->indexBy('user_id');
            $inner_sult['no_passed_list'] = $tmp_query->all();
            $sult['sql']['no_passed'] = $tmp_query->createCommand()->getRawSql();

            //====统计线上的学时和线下的学时
            $ln_table_name = LnCourse::realTableName();
            $report_select_period_field = array(
                new \yii\db\Expression("{$r_table_name}.user_id as user_id"),
                new \yii\db\Expression("sum(boe_calc_period({$ln_table_name}.course_period,{$ln_table_name}.course_period_unit))  as sult "),
            );

            $tmp_query = (new Query($r_table_name))->from($r_table_name)->select($report_select_period_field);
            $tmp_query->join('INNER JOIN', $ln_table_name, "{$r_table_name}.course_id={$ln_table_name}.kid");
            $tmp_query->groupBy('user_id')->indexBy('user_id');

            //在线学习的学时统计
            $period_offline_where = $user_where;
            $period_offline_where[] = array('=', "{$ln_table_name}.course_type", '0');
            $inner_sult['all_online_period_list'] = $tmp_query->where($period_offline_where)->all(); //在线教育的学时 
            $sult['sql']['all_online_period'] = $tmp_query->createCommand()->getRawSql();
            //面授学习的学时统计
            $period_online_where = $user_where;
            $period_online_where[] = array('=', "{$ln_table_name}.course_type", '1');
            $inner_sult['all_offline_period_list'] = $tmp_query->where($period_online_where)->all(); //面授教育的学时 
            $sult['sql']['all_offline_period'] = $tmp_query->createCommand()->getRawSql();

            $inner_sult['user_info_list'] = BoeBaseService::getMoreUserInfo($tmp_user_id, 1, 'real_name,nick_name,user_name,kid,email,user_no,orgnization_id,domain_id,rank,company_id');

            foreach ($sult['list'] as $key => $a_info) {//整理相关的信息For start 
                if ($get_position) {
                    $a_info['position_name'] = BoeBaseService::getUserPositonInfo($a_info['user_id'], 1);
                }

                $a_info['passed_num'] = BoeBase::array_key_is_nulls($inner_sult['passed_list'], array($a_info['user_id'] => 'sult'), 0);
                $a_info['no_passed_num'] = BoeBase::array_key_is_nulls($inner_sult['no_passed_list'], array($a_info['user_id'] => 'sult'), 0);
                $a_info['online_period'] = self::format_period_value(BoeBase::array_key_is_nulls($inner_sult['all_online_period_list'], array($a_info['user_id'] => 'sult'), 0));
                $a_info['offline_period'] = self::format_period_value(BoeBase::array_key_is_nulls($inner_sult['all_offline_period_list'], array($a_info['user_id'] => 'sult'), 0));
                $a_info['course_period'] = self::format_period_value($a_info['course_period']);
                $sult['list'][$key] = array_merge($a_info, $inner_sult['user_info_list'][$a_info['user_id']]);
            }//整理相关的信息For End
        }//有相关的数据时E 
        if ($debug) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug($inner_sult['user_info_list']);
            BoeBase::debug($sult, 1);
        }
        return $sult;
    }

}

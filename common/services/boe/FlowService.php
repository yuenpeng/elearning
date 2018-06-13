<?php

namespace common\services\boe;

use common\base\BoeBase;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseCategory;
use common\models\learning\LnResourceDomain;
use common\models\learning\LnCourseComplete;
use common\models\framework\FwUser;
use common\models\learning\LnCourseTeacher;
use common\models\learning\LnTeacher;
use common\models\learning\LnCourseEnroll;
use common\services\framework\DictionaryService;
use common\services\interfaces\service\RightInterface;
use common\services\interfaces\service\CourseInterface;
use common\models\framework\FwApprovalFlow;
use common\models\framework\FwUserSpecialApprover;
use yii\db\Query;
use Yii;

/**

 * User: Zheng lk
 * Date: 2016/2/26
 * Time: 14:10
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class FlowService {

    static $loadedObject = array();
    static $initedLog = array();
    private static $checkExpirsTime = false;
    private static $developmentMode = true;

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

    private static function getOneCourseInfo($course_id) {
        if (!isset(self::$initedLog['course_' . $course_id])) {
            $ln_info = LnCourse::find(true)->where(['kid' => $course_id])->asArray()->one();
            self::$initedLog['course_' . $course_id] = $ln_info ? $ln_info : NULL;
        }
        return self::$initedLog['course_' . $course_id];
    }

    /**
     * 根据用户ID，和课程ID判断能否请假
     * @param type $user_id
     * @param type $course_id
     */
    public static function getUserCourseLeaveStatus($user_id, $course_id) {
        if (!$user_id || !$course_id) {
            $sult = array('check_value' => 0);
        }
        $e_table_name = LnCourseEnroll::realTableName();
        $l_table_name = LnCourse::realTableName();
        $f_table_name = FwApprovalFlow::realTableName();
        $base_where = array(
            'and',
            array('=', $l_table_name . '.is_deleted', 0),
            array('=', $l_table_name . '.course_type', 1),
            array('=', $l_table_name . '.open_status', 0),
            array('>', $l_table_name . '.open_start_time', time()),
            array('=', $e_table_name . '.is_deleted', 0),
            array('=', $e_table_name . '.approved_state', 1),
            array('=', $e_table_name . '.enroll_type', 1),
            array('=', $e_table_name . '.enroll_user_id', $user_id),
            array('=', $e_table_name . '.course_id', $course_id),
        );

        $query = (new Query())->from($l_table_name);
        $query->join('INNER JOIN', $e_table_name, "{$e_table_name}.course_id={$l_table_name}.kid");
        $query->where($base_where);
        $query->select([new \yii\db\Expression('1')]);
        $ln_course_count = $query->count();
        $sult = array(
            'ln_course_count' => $ln_course_count,
            'ln_course_sql' => $query->createCommand()->getRawSql(),
            'check_value' => $ln_course_count ? 1 : -100,
        );
        if ($ln_course_count) {//如果用户已经报名并审核通过，查看请假记录
            $flow_where = array(
                'and',
                array('=', 'is_deleted', 0),
                array('=', 'applier_id', $user_id),
                array('=', 'event_type', 1),
                array('=', 'event_id', $course_id),
            );
            $query = (new Query())->from($f_table_name);
            $query->where($flow_where);
            $db_sult = $query->select('approval_status')->one();
            if (!$db_sult) {//没有记录
                $sult['check_value'] = 1; //有记录的时候，表示不能再请假了,没有记录的时候，可以请假
            } else {
                switch ($db_sult['approval_status']) {
                    case 0://审批中
                        $sult['check_value'] = -99;
                        break;
                    case 1://审批同意
                        $sult['check_value'] = -98;
                        break;
                    case 2://审批不同意
                        $sult['check_value'] = -97;
                        break;
                }
            }
            $sult['flow_sql'] = $query->createCommand()->getRawSql();
        }
//      BoeBase::debug($sult,1);
        return $sult;
    }

    /**
     * 获取请假的审批人ID
     * @param type $user_id
     * @param type $course_id
     * @return string
     */
    public static function getCourseLeaveApprovalUserInfo($user_id, $course_id) {
        $ln_info = self::getOneCourseInfo($course_id);
        if (!empty($ln_info) && is_array($ln_info)) {
            $ln_info['approval_rule'] = strtolower($ln_info['approval_rule']);
            if ($ln_info['approval_rule'] != 'l1' && $ln_info['approval_rule'] != 'l2') { //对于不需要审核的人课程,请假审批人为课程的创建人
                return $ln_info['created_by'];
            } else {
                return self::getFlowNextApprovalUserInfo($user_id);
            }
        }
        return '';
    }

    /**
     * 根据条件筛选出相应的审核信息
     * @param type $params
     * @return array(
      'totalCount'=>0,
      'list'=>array(),
      'sql'=>//查询数据的SQL语句,
      )
     */
    static function getFlowList($params = array()) {
        $offset = BoeBase::array_key_is_numbers($params, array('offset', 'offSet'), NULL); //开始位置
        $user_id = BoeBase::array_key_is_nulls($params, array('user_id', 'userId', 'userID'), NULL); //用户ID
        $limit = BoeBase::array_key_is_numbers($params, array('limit', 'limit_num', 'limitNum'), 0); //数量限制
        $user_type = BoeBase::array_key_is_nulls($params, array('user_type', 'userType',), 'request'); //获取user_id对应的申请信息还是对应的审核信息
        $orderBy = BoeBase::array_key_is_nulls($params, array('orderBy', 'order_by',), '{flow_tabe}.created_at desc'); //获取user_id对应的申请信息还是对应的审核信息
        $event_type = BoeBase::array_key_is_numbers($params, array('event_type', 'eventType'), -1); //哪一类的数据，0=课程申请数据,1=课程取消申请
        $approval_status = BoeBase::array_key_is_numbers($params, array('approval_status', 'approvalStatus'), -1); //状态的类型
        $get_applier_name = BoeBase::array_key_is_numbers($params, array('get_applier_name', 'getApplierName'), 0); //是否获取申请人员的名称
        $get_approved_name = BoeBase::array_key_is_numbers($params, array('get_approved_name', 'getApprovedName'), 0); //是否获取审核人员的名称

        $flow_table_name = FwApprovalFlow::realTableName();
        $ln_table_name = LnCourse::realTableName();
        $orderBy = str_ireplace(array('{flow_tabe}', '{course_table}'), array($flow_table_name, $ln_table_name), $orderBy);
        $flow_select_field = array('kid',
            'event_id', 'event_type',
            'applier_id', 'approval_rule', 'approved_reason',
            'approved_by', 'approval_status',
            'preflow_id', 'approved_at',
            'approved_reason',
            'created_at'
        );
        $ln_select_field = array(
            'course_name',
            'course_type', 'theme_url',
            'start_time', 'end_time',
            'status', 'open_status',
            'enroll_start_time',
            'enroll_end_time',
            'open_start_time',
            'open_end_time',
        );
        $select_field_str = array();
        foreach ($flow_select_field as $a_info) {
            $select_field_str[] = "{$flow_table_name}.{$a_info} as {$a_info}";
        }
        foreach ($ln_select_field as $a_info) {
            $select_field_str[] = "{$ln_table_name}.{$a_info} as {$a_info}";
        }
        $select_field_str[] = "{$ln_table_name}.`kid` as c_id";
//        BoeBase::debug($select_field_str,1);
        $base_where = array('and',
            array('=', $flow_table_name . '.is_deleted', 0),
        );

        $sult = array();
        if ($event_type != -1) {
            $base_where[] = array('=', $flow_table_name . '.event_type', $event_type);
        }

        if ($user_type == 'reply') {//待我审核的流程S
            $base_where[] = array(is_array($user_id) ? 'in' : '=', $flow_table_name . '.approved_by', $user_id);
        } else {//我申请的流程S
            $base_where[] = array(is_array($user_id) ? 'in' : '=', $flow_table_name . '.applier_id', $user_id);
        }
        if ($approval_status != -1) {
            $base_where[] = array('=', $flow_table_name . '.approval_status', $approval_status);
        }
        $query = (new Query())->from($flow_table_name);
        $query->select($select_field_str);
        $query->limit($limit);
        $query->indexBy('kid');
        $query->join('INNER JOIN', $ln_table_name, "{$flow_table_name}.event_id={$ln_table_name}.kid");
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

        if ($get_applier_name || $get_approved_name && $sult['list']) {
            $tmp_user_id = array();
            foreach ($sult['list'] as $key => $a_info) {
                $tmp_user_id[] = $a_info['applier_id'];
                $tmp_user_id[] = $a_info['approved_by'];
            }
            $user_name_arr = self::getMoreUserName($tmp_user_id);
            foreach ($sult['list'] as $key => $a_info) {
                if ($get_applier_name) {
                    $sult['list'][$key]['applier_name'] = BoeBase::array_key_is_nulls($user_name_arr, $a_info['applier_id']);
                }
                if ($get_approved_name) {
                    $sult['list'][$key]['approved_name'] = BoeBase::array_key_is_nulls($user_name_arr, $a_info['approved_by']);
                }
            }
        }

        $sult['sql'] = $query->createCommand()->getRawSql();

        return $sult;
    }

    /**
     * 根据ID获取审核的详细信息
     * @param type $id
     */
    static function getFlowDetail($id, $get_course_info = 1) {
//             $flow_table_name = FwApprovalFlow::realTableName();
//        $ln_table_name = LnCourse::realTableName();
        $sult = array();
        $sult['flow_info'] = FwApprovalFlow::find(false)->where(['kid' => $id])->asArray()->one();
        if (!$sult['flow_info'] || !is_array($sult['flow_info'])) {
            return -100;
        }
        if ($get_course_info) {//获取课程信息时
            $tmp_course_info = LnCourse::find(false)->where(['kid' => $sult['flow_info']['event_id']])->asArray()->one();
            if (!$tmp_course_info) {
                return -99;
            }
            $tmp_course_info['course_level_text'] = self::getDictionaryText('course_level', $tmp_course_info['course_level']);
            $tmp_course_info['category_text'] = self::getCourseCategoryText($tmp_course_info['category_id']);
            $tmp_course_info['last_theme_url'] = self::getCourseCover($tmp_course_info['theme_url']);
            $tmp_course_info['course_period_text'] = $tmp_course_info['course_period'] . self::getCoursePeriodUnits($tmp_course_info['course_period_unit']);
            $tmp_course_info['course_language_text'] = self::getDictionaryText('course_language', $tmp_course_info['course_language']);
            $tmp_course_info['currency_text'] = self::getPriceUnit($tmp_course_info['currency']);
            $tmp_course_info['course_type_text'] = $tmp_course_info['course_type'] == LnCourse::COURSE_TYPE_ONLINE ? Yii::t('frontend', 'course_online') : Yii::t('frontend', 'course_face');
            $sult['course_info'] = $tmp_course_info;
        }
        $sult['flow_info']['approval_rule'] = strtoupper($sult['flow_info']['approval_rule']);
        $sult['flow_info']['event_type_text'] = BoeBase::array_key_is_nulls(Yii::t('boe', 'flow_event_type_text'), $sult['flow_info']['event_type']);
        $sult['flow_info']['last_approved'] = self::checkFlowLastApproved($sult['flow_info']);
        $t_text = Yii::t('boe', 'flow_last_approved_text');
        $sult['flow_info']['last_approved_text'] = $t_text[$sult['flow_info']['last_approved']];
        $user_id = array(
            $sult['flow_info']['applier_id'],
        );

        if ($sult['flow_info']['preflow_id']) {//上一轮流程IDS
            $tmp_flow_info = FwApprovalFlow::find(false)->where(['kid' => $sult['flow_info']['preflow_id']])->asArray()->one();
            if ($tmp_flow_info) {
                $user_id[] = $tmp_flow_info['approved_by'];
                $sult['flow_info']['preflow'] = $tmp_flow_info;
                $tmp_flow_info = null;
            }
        }//上一轮流程IDS\E
        $user_name_arr = self::getMoreUserName($user_id);
        $sult['flow_info']['applier_name'] = BoeBase::array_key_is_nulls($user_name_arr, $sult['flow_info']['applier_id']);
        if ($sult['flow_info']['preflow']) {
            $sult['flow_info']['preflow']['approved_name'] = BoeBase::array_key_is_nulls($user_name_arr, $sult['flow_info']['preflow']['approved_by']);
        }
        //BoeBase::debug($sult, 1);
        if ($get_course_info) {
            return $sult;
        } else {
            return $sult['flow_info'];
        }
    }

    /**
     * 审核某个流程信息是否最终审批状态
     * @param type $flow_info
     * return int
     */
    private static function checkFlowLastApproved($flow_info) {
        $flow_info['approval_rule'] = strtoupper($flow_info['approval_rule']);
        return $flow_info['preflow_id'] ? 1 : ($flow_info['approval_rule'] != 'L2' ? 1 : 0);
    }

    /**
     * 保存审批的信息
     * @param type $save_params //审核的参数值信息 
     * @return int
     */
    static function saveFlowApproved($save_params = array()) {
        $log_text = array(
            'params' => $save_params,
        );
        if (empty($save_params['kid'])) {
            $log_text['error'] = 'empty($save_params[\'kid\']),-101';
            self::writeLog($log_text);
            return -101;
        }

        if ($save_params['approval_status']) {//指定了审核意见时S
            $flow_info = self::getFlowDetail($save_params['kid'], 0);
            if (!is_array($flow_info)) {
                if ($flow_info == -100) {
                    $log_text['error'] = 'flow_info == -100';
                    self::writeLog($log_text);
                    return -100;
                }
            } else {
                $log_text['flow_info'] = $flow_info;
                if ($flow_info['approved_by'] != Yii::$app->user->getId()) {//审批人不对
                    $log_text['error'] = '-99,审批人不对:' . Yii::$app->user->getId();
                    self::writeLog($log_text);
                    return -99;
                } else {
                    if ($flow_info['approval_status'] != 0) {//已经审核过了
                        $log_text['error'] = '-98,已经审核过了:' . Yii::$app->user->getId();
                        self::writeLog($log_text);
                        return -98;
                    }
                }
            }
        }//指定了审核意见时S
        else {
            $log_text['error'] = '1,没有指定审核意见时:';
            self::writeLog($log_text);
            return 1;
        }

        $approved_sult = $save_params['approval_status'];
        $go_next = false;
        $go_update_info = false;
        $go_cancel = false;
        if ($approved_sult == 1) {//如果已经审核同意S 
            if (!self::checkFlowLastApproved($flow_info)) {//对于指定的流程需要进行入到下一轮的审批时 
                $go_next = true; //需要添加下一轮的审核记录
                $go_update_info = false; //可以更新相关的数据了      
            } else {//不需要再次审核时
                $go_next = false; //不需要添加下一轮的审核记录
                $go_update_info = true; //可以更新相关的数据了       
            }
        }//如果已经审核同意S
        else {
            $go_update_info = true; //可以更新相关数据
        }

        $next_user_id = $current_user_id = '';
        if ($go_next) {//需要添加下一轮的审核记录,需要事先读取出下次审批人的信息S
            $current_user_id = $flow_info['approved_by'];
            $next_user_id = self::getFlowNextApprovalUserInfo($current_user_id); //读取出下次审批人的信息 
            if (!$next_user_id) {
                $log_text['error'] = '-97,读取不到下次审批人的信息:';
                self::writeLog($log_text);
                return -97;
            }
        }//需要添加下一轮的审核记录,需要事先读取出下次审批人的信息E

        $api_sult = true;
        if ($go_update_info) {//需要更新数据的时候S
            if (!isset(self::$loadedObject['course_interface'])) {
                self::$loadedObject['course_interface'] = new CourseInterface();
            }
            if ($flow_info['event_type'] == 0) {//课程审核
                /**
                 * 报名课程审批
                 * @param string $courseId 课程ID
                 * @param string $userId 用户ID
                 * @param string $approvedby 审批人ID
                 * @param string $approvedReason 审批理由
                 * @param string $approvedState 审批状态
                 * @return bool 成功与否
                 */
                $approved_sult = strval($approved_sult);
                $api_sult = self::$loadedObject['course_interface']->approveCourse($flow_info['event_id'], $flow_info['applier_id'], $flow_info['approved_by'], $flow_info['approved_reason'], $approved_sult);
                $log_text['api'] = array(
                    'name' => 'approveCourse',
                    'event_id' => $flow_info['event_id'],
                    'applier_id' => $flow_info['applier_id'],
                    'approved_by' => $flow_info['approved_by'],
                    'approved_reason' => $flow_info['approved_reason'],
                    'approved_sult' => $approved_sult,
                    'api_sult' => $api_sult,
                );
            } else {//请假审核
                if ($approved_sult == 1) {//只有请假同意后，才能取消课程报名S
                    /**
                     * 取消课程
                     * @param string $courseId 课程ID
                     * @param string $userId 用户ID
                     * @param string $cancelBy 取消人ID
                     * @param string $cancelReason 取消理由
                     * @param string $cancelState 取消状态
                     * @return bool 成功与否
                     */
                    $approved_sult = strval($approved_sult);
                    $api_sult = self::$loadedObject['course_interface']->cancelCourse(
                            $flow_info['event_id'], $flow_info['applier_id'], $flow_info['approved_by'], $save_params['approved_reason'], $approved_sult
                    );
                    $log_text['api'] = array(
                        'name' => 'cancelCourse',
                        'courseId' => $flow_info['event_id'],
                        'userId' => $flow_info['applier_id'],
                        'cancelBy' => $flow_info['approved_by'],
                        'cancelReason' => $save_params['approved_reason'],
                        'approved_sult' => $approved_sult,
                        'api_sult' => $api_sult,
                    );
                }//只有请假同意后，才能取消课程报名S
            }
        }//需要更新数据的时候E

        if ($api_sult) {//API更新成功S
            //操作记录
            $model = new FwApprovalFlow();
            $currentObj = $model->findOne(['kid' => $save_params['kid']]);
            $opreateSult = false;
            $save_params['approved_at'] = time();
            $save_params['approval_status'] = strval($save_params['approval_status']);
            foreach ($save_params as $key => $a_value) {
                if ($key != 'kid') {
                    $currentObj->$key = $a_value;
                }
            }
            if ($currentObj->validate()) {
                $opreateSult = $currentObj->save();
            } else {
                $error = $currentObj->getErrors();
                $log_text['error'] = "数据库字段信息不正确.\n" . var_export($error, true);
                self::writeLog($log_text);
                return var_export($error, true);
            }
            if (!$opreateSult) {//操作成功S
                $log_text['error'] = '-96,更新审核信息表失败:';
                self::writeLog($log_text);
                return -96;
            }
            $currentObj = NULL;
            if ($go_next) {//需要添加下一轮的审核记录S
                $new_data = array(
                    'event_id' => $flow_info['event_id'],
                    'event_type' => $flow_info['event_type'],
                    'applier_id' => $flow_info['applier_id'],
                    'applier_at' => time(),
                    'approval_status' => '0',
                    'flow_number' => 2,
                    'preflow_id' => $flow_info['kid'],
                    'approval_rule' => $flow_info['approval_rule'],
                    'approved_by' => $next_user_id,
                );
                $log_text['new_log'] = $new_data;
                foreach ($new_data as $key => $a_value) {
                    $model->$key = $a_value;
                }
                if ($model->validate()) {
                    $model->needReturnKey = true;
                    $opreateSult = $model->save();
                } else {
                    $error = $model->getErrors();
                    $log_text['error'] = "添加下一轮的记录出错" . var_export($error, true);
                    self::writeLog($log_text);
                    return var_export($error, true);
                }
            }//需要添加下一轮的审核记录E
        }//API更新成功E 
        else {//API更新失败 
            self::writeLog($log_text);
            return -95;
        }
        self::writeLog($log_text);
        return 1;
    }

    /**
     * 添加请假记录
     * @param type $uid
     * @param type $cid
     * @return type
     */
    static function addLeave($u_id, $c_id) {
        $log_text = array(
            'user_id' => $u_id,
            'course_id' => $c_id,
        );
        if (!$u_id || !$c_id) {
            $log_text['error'] = '-101,没有指定参数.';
            self::writeLog($log_text, 'addLeave');
            return -101;
        }
        $tmp_sult = self::getUserCourseLeaveStatus($u_id, $c_id); //-100,1,-99,-98,-97
        $add_statu = !empty($tmp_sult['check_value']) ? $tmp_sult['check_value'] : 0;
        $add_statu = 1;
        if ($add_statu != 1) {
            $log_text['error'] = '-100,不能添加请假记录.';
            $log_text['statu_sult'] = $tmp_sult;
            self::writeLog($log_text, 'addLeave');
            return -100;
        }
        $ln_info = self::getOneCourseInfo($c_id);
        if (!$ln_info) {
            $log_text['error'] = '-102,课程信息不存在';
            self::writeLog($log_text, 'addLeave');
            return 1;
        }
        $next_user_id = self::getCourseLeaveApprovalUserInfo($u_id, $c_id); //取出请假审批人的用户ID
        if (!$next_user_id) {
            $log_text['error'] = '-99,读取不到下次审批人的信息:';
            self::writeLog($log_text, 'addLeave');
            return -99;
        }
        $new_data = array(
            'event_id' => $c_id,
            'event_type' => '1',
            'applier_id' => $u_id,
            'applier_at' => time(),
            'approval_rule' => $ln_info['approval_rule'],
            'approved_by' => $next_user_id,
        );
        $log_text['new_log'] = $new_data;
        $model = new FwApprovalFlow();
        foreach ($new_data as $key => $a_value) {
            $model->$key = $a_value;
        }
        $opreateSult = true;
        if ($model->validate()) {
            $model->needReturnKey = true;
            $opreateSult = $model->save();
        } else {
            $error = $model->getErrors();
            $log_text['error'] = "-98,添加记录出错" . var_export($error, true);
            self::writeLog($log_text, 'addLeave');
            return -98;
        }

        if (!$opreateSult) {//操作成功S
            $log_text['error'] = '-97,数据库出错:';
            self::writeLog($log_text, 'addLeave');
            return -97;
        }
        self::writeLog($log_text, 'addLeave');
        return 1;
    }

    /**
     * 写入日志
     * @param type $text
     */
    static function writeLog($text, $dir = 'boe_flow_log') {
        $debug = self::$developmentMode;
        if (!$debug) {
            $debug = Yii::$app->request->get('development_mode') == 1 ? true : false;
        }
        if (!$debug) {
            $debug = YII_DEBUG ? true : false;
        }
        //  exit("Debug:".$debug);
        if ($debug) {
            $log_dir = BoeWebRootDir . '/' . $dir;
            if (!is_dir($log_dir)) {
                @mkdir($log_dir);
            }
            $log_file = $log_dir . '/' . date("YmdHi") . '.log';
            $log_content = "\n==========================" . date("Y-m-d H:i:s");
            $log_content.="==================================\n";
            $log_content.=(!is_scalar($text) ? var_export($text, true) : $text);
            $log_content.="\n=========================================================\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
        }
    }

    /**
     * 根据用户ID，获取其特殊的审核人ID
     * @param type $user_id
     */
    static function getUserLeader($user_id = '', $get_name = 0) {
        $sult = FwUserSpecialApprover::find(false)->where(['user_id' => $user_id])->asArray()->one();
        if ($sult) {
            $user_name_arr = self::getMoreUserName($sult['approver_id'], 1);
            $leader_info = BoeBase::array_key_is_nulls($user_name_arr, $sult['approver_id']);
            if ($leader_info) {
                $sult['approver_name'] = BoeBase::array_key_is_nulls($leader_info, 'fix_name');
                $sult['approver_text'] = BoeBase::array_key_is_nulls($leader_info, 'name_text');
            } else {
                $sult['approver_name'] = '';
                $sult['approver_text'] = '';
                $sult['approver_id'] = '';
            }
        }
//        BoeBase::debug(__METHOD__);
//        BoeBase::debug($sult);
//        BoeBase::debug($leader_info, 1);
        return $sult;
    }

    /**
     * 保存特定审批的领导
     * @param type $data
     */
    static function saveUserLeader($data) {
        //操作记录
        $model = new FwUserSpecialApprover();
        $delete_p = array(
            'and',
            array('=', 'user_id', $data[user_id]),
        );
        $delete_sult = $model->physicalDeleteAll($delete_p);
        if (!empty($data['approver_id'])) {
            $opreateSult = false;
            if (!empty($data['kid'])) {//修改的时候
                $currentObj = $model->where(array('kid' => $data['kid'], 'user_id' => $data['user_id']))->one();
                if ($currentObj) {
                    $currentObj->approver_id = $data['approver_id'];
                    if ($currentObj->validate()) {
                        $opreateSult = $currentObj->save();
                    } else {
                        $error = $currentObj->getErrors();
                    }
                } else {
                    $opreateSult = false;
                }
            } else {//添加的时候S
                foreach ($data as $key => $a_value) {
                    if ($key != 'kid') {
                        $model->$key = $a_value;
                    }
                }

                if ($model->validate()) {
                    $opreateSult = $model->save();
                } else {
                    $error = $model->getErrors();
                }
            }//添加的时候E
            return $opreateSult ? 1 : -1;
        } else {
            return 1;
        }
    }

    /**
     * 根据用户ID，读取下一轮审核的用户信息
     * @param type $user_id
     * @return array
     */
    static function getFlowNextApprovalUserInfo($user_id) {
        if (!isset(self::$loadedObject['right_interface'])) {
            self::$loadedObject['right_interface'] = new RightInterface();
        }
        $user_info = self::$loadedObject['right_interface']->getApproverByUserId($user_id, false);
        if ($user_info) {//有特定的审批人信息时 
            return $user_info;
        } else {//没有指定的审批人信息时E,读取该用户的直属经理
            $user_info = self::$loadedObject['right_interface']->getReportingManagerByUserId($user_id);
            if ($user_info && isset($user_info[0]) && is_array($user_info[0])) {//读取到用户的直属经理信息时
                return $user_info[0]['kid'];
            }
        }
        return NULL;
    }

    /**
     * 得到课时单位
     * @author baoxianjian 15:12 2016/1/14
     * @param int $type
     */
    static function getCoursePeriodUnits($unitVal = 0) {
        if (!isset(self::$loadedObject['ln_course'])) {
            self::$loadedObject['ln_course'] = new LnCourse();
        }
        return self::$loadedObject['ln_course']->getCoursePeriodUnits($unitVal);
    }

    static function getCourseCategoryText($id) {
        $category = LnCourseCategory::findOne($id);
        if ($category) {
            return $category->category_name;
        } else {
            return "";
        }
    }

    static function getCourseCover($url) {
        return $url ? $url : '/static/frontend/images/course_theme_big.png';
//        if (!isset(self::$loadedObject['ln_course'])) {
//            self::$loadedObject['ln_course'] = new LnCourse();
//        }
//        return self::$loadedObject['ln_course']->getCourseCover($url);
    }

    /*
     * 根据字典分类与值获取字典详细信息
     * @return string
     */

    static function getDictionaryText($cate_code, $val) {
        if (empty($cate_code)) {
            return "";
        } else {
            if (!isset(self::$loadedObject['dictionaryService'])) {
                self::$loadedObject['dictionaryService'] = new DictionaryService();
            }
            return self::$loadedObject['dictionaryService']->getDictionaryNameByValue($cate_code, $val);
        }
    }

    /**
     * 货币单位
     * @param $code
     */
    static function getPriceUnit($dictionaryCode = null) {
        if (!isset(self::$loadedObject['dictionaryService'])) {
            self::$loadedObject['dictionaryService'] = new DictionaryService();
        }
        return self::$loadedObject['dictionaryService']->getDictionaryValueByCode('currency_symbol', $dictionaryCode);
    }

    /**
     * 读取多个文档对应的共享用户信息 
     * @param type $user_id
     * @param type $detail_info
     * @return type
     */
    static function getMoreUserName($user_id = NULL, $detail_info = 0) {
        //   BoeBase::debug(__METHOD__);
        if (empty($user_id)) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
        $where[] = array(is_array($user_id) ? 'in' : '=', 'kid', $user_id);
        $user_model = FwUser::find(false)->select('real_name,nick_name,user_name,kid,email,user_no');
        $user_info = $user_model->where($where)->indexby('kid')->asArray()->all();
        $sult = array();
        if ($user_info && is_array($user_info)) {//有结果的时候S
            //   BoeBase::debug($user_info);
            foreach ($user_info as $key => $a_info) {//找出用户名称S
                $a_info = BoeBase::parseUserListName($a_info);
                if ($detail_info) {
                    $sult[$key] = $a_info;
                } else {
                    $sult[$key] = $a_info['fix_name'];
                }
            }//找出用户名称E
//            BoeBase::debug($sult, 1);
        }//有结果的时候E  
        return $sult;
    }

}

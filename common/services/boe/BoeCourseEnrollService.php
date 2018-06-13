<?php
namespace common\services\boe;
use common\services\learning\CourseEnrollService;
use common\models\framework\FwUser;
use common\models\framework\FwUserDisplayInfo;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseComplete;
use common\models\learning\LnCourseEnroll;
use common\models\learning\LnCourseReg;
use common\models\learning\LnUserCertification;
use common\models\message\MsTimeline;
use common\services\framework\PointRuleService;
use common\services\message\MessageService;
use common\services\message\PushMessageService;
use common\services\message\TimelineService;
use components\widgets\TPagination;
use Yii;
use yii\db\Query;
use common\services\framework\DictionaryService;
/**
 * Desc: 课程报名服务
 * User: songsang
 * Date: 28/9/17
 */
 

class BoeCourseEnrollService  extends CourseEnrollService {

	 /**
     * 获取面授课件报名数据
     * @param $courseId
     * @param $params
     * @return array
     */
    public function searchCourseEnroll($courseId, $params = null, $justReturnCount = false){
        $enrollModel = new Query();
        $enrollModel->from(LnCourseEnroll::tableName() . ' as len')
            ->leftJoin(FwUserDisplayInfo::tableName() . ' as t1', 't1.user_id = len.user_id')
            ->distinct()
            ->select('t1.rank,t1.mobile_no,t1.gender,t1.real_name,t1.orgnization_name,t1.orgnization_name_path,t1.user_no,t1.location,t1.position_name,t1.email,len.kid,len.user_id,len.enroll_time,len.enroll_type,len.enroll_method,len.approved_state,t1.position_mgr_level_txt,t1.onboard_day');
        $enrollModel->andWhere("len.is_deleted='0'")
            ->andWhere("t1.status='1' and t1.is_deleted='0'");
        if (!empty($params['keyword'])) {
            $params['keyword'] = trim($params['keyword']);
            $enrollModel->where("t1.real_name like '%{$params['keyword']}%' or t1.user_no like '%{$params['keyword']}%' or t1.orgnization_name like '%{$params['keyword']}%' or t1.position_name like '%{$params['keyword']}%'");
        }
        if (isset($params['enroll_type']) && is_array($params['enroll_type'])) {
            $enrollModel->andFilterWhere(['in', 'len.enroll_type', $params['enroll_type']]);
        } elseif (isset($params['enroll_type']) && !is_array($params['enroll_type'])) {
            $enrollModel->andFilterWhere(['=', 'len.enroll_type', $params['enroll_type']]);
        }
        if (isset($params['approved_state'])) {
            $enrollModel->andFilterWhere(['=', 'len.approved_state', $params['approved_state']]);
        }

        if (isset($params['isDemo']) && $params['isDemo'] === '0') {
            $enrollModel->andFilterWhere(['<>', 'len.approved_state', LnCourseEnroll::APPROVED_STATE_APPLING]);
        }

        $enrollModel->andFilterWhere(['=', 'len.course_id', $courseId])
            ->andFilterWhere(['=', 'len.is_deleted', LnCourseEnroll::DELETE_FLAG_NO]);

        if (isset($params['filter']) && $params['filter'] == 2) {
            if (isset($params['isDemo']) && $params['isDemo'] === '1') {
                $enrollModel->andFilterWhere(['or', ['=', 'approved_state', LnCourseEnroll::APPROVED_STATE_APPLING], ['=', 'enroll_type', LnCourseEnroll::ENROLL_TYPE_REG]]);
            } else {
                $enrollModel->andFilterWhere(['=', 'approved_state', LnCourseEnroll::APPROVED_STATE_APPROVED])
                    ->andFilterWhere(['=', 'enroll_type', LnCourseEnroll::ENROLL_TYPE_REG]);
            }
        } elseif (isset($params['filter']) && $params['filter'] == 3) {
            $enrollModel->andFilterWhere(['=', 'enroll_type', LnCourseEnroll::ENROLL_TYPE_ALLOW]);
        } elseif (isset($params['filter']) && $params['filter'] == 4) {
            $enrollModel->andFilterWhere(['=', 'approved_state', LnCourseEnroll::APPROVED_STATE_REJECTED]);
        } elseif (isset($params['filter']) && $params['filter'] == 5) {
            $enrollModel->andFilterWhere(['=', 'enroll_type', LnCourseEnroll::ENROLL_TYPE_DISALLOW]);
        } else {

        }

        if (isset($params['sort']) && $params['sort'] == 2) {
            $enrollModel->orderBy('len.enroll_method asc,t1.orgnization_name desc,len.enroll_time');
        } else {
            $enrollModel->orderBy('len.approved_state,len.enroll_type,len.enroll_time');
        }
        $count = $enrollModel->count();
        if ($justReturnCount) {
            return $count;
        }

        if (isset($params['showAll']) && $params['showAll'] === 'True') {
            $pages = new TPagination(['defaultPageSize' => $count, 'totalCount' => $count]);
            $data = $enrollModel->all();
        } else {
            $pages = new TPagination(['defaultPageSize' => 12, 'totalCount' => $count]);
            $data = $enrollModel->offset($pages->offset)->limit($pages->limit)->all();
        }
        $result = array(
            'count' => $count,
            'pages' => $pages,
            'data' => $data,
        );
        return $result;
    }

    //员工发薪地
    public static function UserPayrollPlace($user_id){
    	$payrollPlace = '';
    	if(!$user_id){
    		return $payrollPlace;
    	}
    	//代码
    	$payroll_code =  $category_id = (new \yii\db\Query())->from('eln_fw_user_attribute')->where(array('=','userId',$user_id))->select(['payrollPlace'])->one();
    	if($payroll_code){
    		//发薪地
	    	$dictionaryService = new DictionaryService();
	    	$payrollPlace  = $dictionaryService->getDictionaryNameByCode('payroll_place',$payroll_code['payrollPlace']);
    	}
    	return $payrollPlace;
    }

}
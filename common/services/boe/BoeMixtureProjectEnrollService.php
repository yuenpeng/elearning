<?php
/**
 * Created by PhpStorm.
 * User: adophper
 * Date: 2018/5/25
 * Time: 13:43
 */

namespace common\services\boe;

use common\models\boe\BoeEmbed;
use common\models\boe\BoeEmbedCourse;
use common\models\boe\BoeEmbedOption;
use common\models\boe\BoeEmbedUserResult;
use common\models\boe\BoeEnterprise;
use common\models\boe\BoeMixtureProject;
use common\models\boe\BoeMixtureProjectComplete;
use common\models\framework\FwUser;
use common\models\framework\FwUserDisplayInfo;
use common\services\framework\DictionaryService;
use yii;
use common\models\boe\BoeMixtureProjectEnroll;
use yii\helpers\ArrayHelper;

class BoeMixtureProjectEnrollService extends  BoeMixtureProjectEnroll
{

    /**
     * 判断用户是否注册并审批通过课程
     * @param $uid
     * @param $courseId
     * @return bool
     */
    public function isUserRegProjectRegState($uid, $projectId, &$enrollProjectId, $withSession = true)
    {
        if (!empty($uid)) {
            $sessionKey = "UserCourseRegState_UserId_" . $uid . "_CourseId_" . $projectId;

            if ($withSession && Yii::$app->session->has($sessionKey)) {
                $enrollProjectId = Yii::$app->session->get($sessionKey);
                return true;
            } else {
                $result = $this->getUserRegInfoRegState($uid, $projectId);

                if ($result == null) {
                    return false;
                } else {
                    $enrollProjectId = $result->kid;
                    if ($withSession) {
                        Yii::$app->session->set($sessionKey, $enrollProjectId);
                    }
                    return true;
                }
            }
        }
    }

    /**
     * 获取用户注册信息是否审批通过
     * @param $uid
     * @param $courseId
     * @return array|null|yii\db\ActiveRecord
     */
    public function getUserRegInfoRegState($uid, $projectId)
    {
        $model = new BoeMixtureProjectEnroll();
        $query = $model->find(false)
            ->andFilterWhere(['=', 'program_id', $projectId])
            ->andFilterWhere(['=', 'user_id', $uid])
            ->andFilterWhere(['=', 'approved_state', BoeMixtureProjectEnroll::APPROVED_STATE_APPROVED]);
        $result = $query->addOrderBy(['updated_at' => SORT_DESC])
            ->one();
        return $result;
    }

    /**
     * 获取用户报名数据
     * @param $uid
     * @param $projectId
     * @return array|null|yii\db\ActiveRecord
     */
    public function getUserProjectEnrollModel($uid, $projectId) {
        $model = new BoeMixtureProjectEnroll();
        $result = $model->find(false)
            ->andFilterWhere(['=', 'program_id', $projectId])
            ->andFilterWhere(['=', 'user_id', $uid])
            ->andFilterWhere(['<>', 'approved_state', BoeMixtureProjectEnroll::APPROVED_STATE_CANCELED])
            ->addOrderBy(['updated_at' => SORT_DESC])
            ->one();

        return $result;
    }

    public function getUserProjectEnrollState(BoeMixtureProjectEnroll $model){
        if (empty($model)) {
            return ['btn' => 'disable', 'txt' => ''];
        }

        //申请中
        if ($model->enroll_type == BoeMixtureProjectEnroll::ENROLL_TYPE_REG) {
            return ['btn' => 'reg', 'txt' => Yii::t('frontend', 'enroll_allowing')];
        }elseif ($model->enroll_type == BoeMixtureProjectEnroll::ENROLL_TYPE_ALLOW) {
            //同意申请
            //判断项目状态
            $completeService = new BoeMixtureProjectCompleteService();
            $complete = $completeService->getUserProjectComplete($model->user_id, $model->program_id, $model->kid);
            if (empty($complete)) {
                //这里需要对数据进行修正，报名成功就会像完成表里面添加一条待完成数据,如若没有则要添加
                return ['btn' => 'disable', 'txt' => ''];
            }
            if ($complete->complete_status == BoeMixtureProjectComplete::COMPLETE_STATUS_NOTSTART) {
                return ['btn' => 'learning', 'txt' => Yii::t('frontend', 'start_learning')];
            }elseif ($complete->complete_status == BoeMixtureProjectComplete::COMPLETE_STATUS_DOING) {
                return ['btn' => 'learning', 'txt' => Yii::t('frontend', 'continue_learning')];
            }else {
                return ['btn' => 'complete', 'txt' => Yii::t('frontend', 'complete_status_done')];
            }
        }elseif ($model->enroll_type == BoeMixtureProjectEnroll::ENROLL_TYPE_ALTERNATE){
            //候补
            //判断审批状态
            return ['btn' => 'alternate', 'txt' => Yii::t('frontend', 'enroll_allowing')];
        }elseif ($model->enroll_type == BoeMixtureProjectEnroll::ENROLL_TYPE_DISALLOW){
            //拒绝
            return ['btn' => 'disallow', 'txt' => Yii::t('frontend', 'enroll_failed')];
        }else{
            //
        }
    }

    /**
     * 获取用户报名信息
     * @param $uid
     * @param $projectId
     * @return array
     */
    public function getUserApprovedState($uid, $projectId){
        $project = BoeMixtureProject::findOne($projectId);
        //禁止报名
        if (empty($project) || $project->status != BoeMixtureProject::STATUS_FLAG_NORMAL) {
            return ['btn' => 'disable', 'txt' => ''];
        }
        //判断是否报名
        $result = $this->getUserProjectEnrollModel($uid, $projectId);

        //审批
        $enrollService = new BoeEnrollService();

        if (!empty($result)) {
            //获取报名表状态
            if ($project->approval_rule == 'NONE') {
                $status = $this->getUserProjectEnrollState($result);
                return $status;
            } else {
                //获取报名审批表状态

            }
        }else{
            $time = time();
            //未到报名时间
            if ($project->enroll_start_time > $time) {
                return ['btn' => 'unstart', 'txt' => Yii::t('common', 'complete_eroll_status_0')];
            } elseif ($project->enroll_end_time < $time) {
                //报名已结束
                return ['btn' => 'end', 'txt' => Yii::t('common', 'status_enroll_2')];
            } else {
                //判断是否需要前置任务
                if ($project->approval_rule == 'NONE') {
                    return ['btn' => 'enroll', 'txt' => Yii::t('frontend', 'i_want_enroll')];
                }else{
                    //填写报名信息
                    //判断是否关联前置任务
                    $embed = $this->hasProjectRelatedEmbed($projectId);
                    if (!$embed) {
                        //未关联前置任务
                        return ['btn' => 'unrelated', 'txt' => Yii::t('common', 'not_related_embed')];
                    }else{
                        $embedOption = $this->getEmbedOption($uid, $embed->embed_id, $projectId);
                        if (empty($embedOption)) {
                            //已经确认过信息
                            return ['btn' => 'enroll', 'txt' => Yii::t('frontend', 'i_want_enroll')];
                        }else{
                            return ['btn' => 'enroll', 'txt' => Yii::t('frontend', 'i_want_enroll'), 'embed' => $embedOption];
                        }
                    }
                }
            }
        }

    }

    /**
     * @param $projectId
     * @return bool
     */
    public function hasProjectRelatedEmbed($projectId){
        $model = BoeEmbedCourse::findOne(['course_id' => $projectId, 'embed_type' => BoeEmbedCourse::EMBED_TYPE_PROJECT, 'status' => BoeEmbedCourse::STATUS_FLAG_NORMAL]);
        if (empty($model)) {
            return false;
        }
        return $model;
    }

    /**
     * 获取前置任务表单信息
     */
    public function getEmbedOption($userId, $embedId, $projectId){
        $sult = array();
        //前置任务信息
        $boe_embed = new BoeEmbed();
        $bed_embed_info = $boe_embed->getInfo($embedId);
        //问卷结果集
        //同一个用户只做一份问卷，即使这份问卷被多个课程引用
        $boeEmbedUserResult = new BoeEmbedUserResult();
        $data_result = $boeEmbedUserResult->find(false)
            ->where(['user_id' => $userId, 'embed_id'=> $embedId, 'embed_type' => BoeEmbedUserResult::EMBED_TYPE_PROJECT, 'course_id'=> $projectId, 'is_deleted'=>0])
            ->asArray()
            ->one();
        if(!empty($data_result)){//已经填完问卷
            //return false;
        }else{
            //没有填问卷，准备问卷
            $data = json_decode($bed_embed_info['configure'],true);
            $boeEmbedOption = new BoeEmbedOption();
            //用户信息
            $user_info = FwUser::find(false)
                ->where(['kid' => $userId])
                ->asArray()
                ->one();
            //直线经理工号
            if($user_info['reporting_manager_id']){
                $where = array('=','kid',$user_info['reporting_manager_id']);
                $reporting = FwUser::find(false)->select('user_no,real_name')->where($where)->asArray()->one();

                $user_info['reporting_manager_no'] = $reporting['user_no'];
            }
            //hrbp
            $hrbp =  BoeOrgnizationService::getUserBp(NULL,NULL,$user_info['user_name']);
            //组织
            $orgnization = BoeEmbedService::getOrgPath($userId);

            foreach ($data as $key => &$value) {
                if(!$value['is_read']) continue;
                $name = $boeEmbedOption->getInfo($key,'option_name');
                $value['key'] = $key;
                $value['name'] = $name;
                switch ($name) {
                    case '姓名':
                        $value['value'] = $user_info['real_name'].'('.$user_info['user_no'].')';
                        break;
                    case '工号':
                        $value['value'] = $user_info['user_no'];
                        break;
                    case '邮箱':
                        $value['value'] = $user_info['email'];
                        break;
                    case '手机号码':
                        $value['value'] = trim($user_info['mobile_no']);
                        break;
                    case '身份证':
                        $value['value'] = $user_info['id_number'];
                        break;
                    case '组织':
                        $value['value'] = $orgnization['name'][30];
                        break;
                    case '体系':
                        $value['value'] = $orgnization['name'][20];
                        break;
                    case '岗位':
                        $value['value'] =  $va= BoeBaseService::getUserPositonInfo($userId,1);
                        break;
                    case '直线经理':
                        $value['value'] = $reporting['real_name'].($reporting['user_no']?'('.$reporting['user_no'].')':'');
                        break;
                    case 'HRBP':
                        $value['value'] = $hrbp[0]['real_name'].($hrbp[0]['user_no']?'('.$hrbp[0]['user_no'].')':'');
                        break;
                    case '工作地':
                        $value['value'] = $user_info['work_place_txt'];
                        break;
                    case '发薪地':
                        $value['value'] = BoeEmbedService::getPayrollPlaceTxt($userId);
                        break;
                        // case '费用结算地':
                        // 	$value['value'] = $user_info['memo1'];
                        //break;
                    default:
                        # code...
                        break;
                }
                $input = '';
                //is_select: 1通过input显示select,2直接显示select
                if($name =='直线经理'){
                    $value['is_select'] = 1;
                    $value['data_url'] = Yii::$app->request->hostInfo.'/boe/common/search-people.html?u=1&d=1&c=1&user_no=1&l=200';
                }elseif($name =='费用结算地'){
                    //缴费地 使用HRBP配置字典
                    $boeEnterpriseMoedel = new BoeEnterprise();
                    $jfd = $boeEnterpriseMoedel->find(false)->where(['status'=>1,'is_deleted'=>0])->all();
                    if($jfd){
                        $value['is_select'] = 2;
                        $option = [];
                        foreach ($jfd as  $val) {
                            if($val->enterprise_name==$value['value']){
                                $option[] = array(
                                    'selected' => 1,
                                    'value' => $val->enterprise_name,
                                );
                            }else{
                                $option[] = array(
                                    'selected' => 0,
                                    'value' => $val->enterprise_name,
                                );
                            }

                        }
                        if($value['value']=='其他'){
                            $option[] = array(
                                'selected' => 1,
                                'value' => '其他',
                            );
                        }else{
                            $option[] = array(
                                'selected' => 0,
                                'value' => '其他',
                            );
                        }
                        $value['option'] = $option;
                    }
                }elseif($name =='培训地'){
                    //培训地 和 发薪地 用同一字典
                    $dictionaryService = new DictionaryService();
                    $jfd = $dictionaryService->getDictionariesByCategory('payroll_place');
                    if($jfd){
                        $value['is_select'] = 2;
                        $option = [];
                        foreach ($jfd as  $val) {
                            if($val->dictionary_name==$value['value']){
                                $option[] = array(
                                    'selected' => 1,
                                    'value' => $val->dictionary_name,
                                );
                            }else{
                                $option[] = array(
                                    'selected' => 0,
                                    'value' => $val->dictionary_name,
                                );
                            }
                        }
                        if($value['value']=='其他'){
                            $option[] = array(
                                'selected' => 1,
                                'value' => '其他',
                            );
                        }else{
                            $option[] = array(
                                'selected' => 0,
                                'value' => '其他',
                            );
                        }
                        $value['option'] = $option;
                    }
                }else{
                    $value['is_select'] = 0;
                    $value['option'] = [];
                    //
                }
            }
            $sult = array(
                'desc' => $bed_embed_info['desc'] ? $bed_embed_info['desc'] : '',
                'list' => $data,
                'embed_id' => $embedId,
                'course_id' => $projectId,
            );
        }

        return $sult;
    }

    /**
     * 保存用户报名信息
     * @param $projectId
     * @param $uid
     * @param $params
     * @param string $enrollUserId
     * @return array
     */
    public function insertUserProjectEnroll($projectId, $uid, $params = array(), $enrollUserId = ''){
        $model = BoeMixtureProject::findOne($projectId);
        //数据不存在
        if (empty($model)) {
            return ['result' => '', 'msg' => 'failure', 'code' => -5];
        }

        $find = $this->getUserProjectEnrollModel($uid, $projectId);
        //已经报名
        if (!empty($find)) {
            return ['result' => '', 'msg' => 'failure', 'code' => -6];
        }
        //判断是否在受众范围内
        $audienceService = new BoeMixtureProjectAudienceService();
        if (!$audienceService->isProjectAudience($uid, $projectId)) {
            return ['result' => '', 'msg' => 'failure', 'code' => -9];
        }

        //保存报名数据
        $enrollModel = new BoeMixtureProjectEnroll();
        $enrollModel->program_id = $projectId;
        $enrollModel->user_id = $uid;
        $enrollModel->enroll_type = BoeMixtureProjectEnroll::ENROLL_TYPE_REG;
        $enrollModel->enroll_user_id = empty($enrollUserId) ? $uid : $enrollUserId;
        $enrollModel->enroll_time = time();
        $enrollModel->approved_state = BoeMixtureProjectEnroll::APPROVED_STATE_APPLING;

        if ($enrollModel->save()){
            //需要审批
            if ($model->approval_rule != 'NONE') {
                //判断是否关联前置任务
                if (!$this->hasProjectRelatedEmbed($projectId)) {
                    return ['result' => '', 'msg' => 'failure', 'code' => -8];
                }
                //保存前置任务输入信息
                $embedUserResult = new BoeEmbedUserResult();
                $embedUserResult->embed_id = $params['embed_id'];
                $embedUserResult->embed_type = BoeEmbedUserResult::EMBED_TYPE_PROJECT;
                $embedUserResult->course_id = $projectId;
                $embedUserResult->user_id = $uid;
                $embedUserResult->pay_place = '';
                $embedUserResult->result = '';
                $embedUserResult->status = BoeEmbedUserResult::STATUS_FLAG_NORMAL;
                $embedUserResult->save();
            }
            return ['result' => '', 'msg' => 'ok', 'code' => 0];
        }else{
            return ['result' => '', 'msg' => 'failure', 'code' => -7];
        }
    }

}
<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/5/3
 * Time: 14:18
 */

namespace common\services\api;

use common\traits\ResponseTrait;
use common\traits\ValidatorTrait;
use common\models\learning\LnExamination;
use common\services\learning\ExaminationService;
use common\models\learning\LnExaminationResultUser;
use common\models\learning\LnExamQuestionUser;
use common\models\learning\LnExamQuestOptionUser;
use common\models\learning\LnExaminationQuestion;
use common\models\learning\LnExamResultDetail;
use common\models\learning\LnExamQuestionOption;

class ExamService extends ExaminationService{

    const LEARNING_DURATION = 30;
    use ResponseTrait,ValidatorTrait;
    
    protected $userId;
    protected $companyId;
    public $systemKey;
    public function __construct($system_key,$user_id = null,$company_id = null,array $config = [])
    {
        $this->systemKey = $system_key;
        $this->userId = $user_id;
        $this->companyId = $company_id;
        parent::__construct($config);
    }

    /**
     * @param $examination_id
     * @param null $user_id
     * @param null $company_id
     * @return int|string
     */
    public function userCount($examination_id,$user_id = null,$company_id = null) {
        return LnExaminationResultUser::find(false)
            ->andFilterWhere(['examination_id' => $examination_id])
            ->andFilterWhere(['user_id' => $user_id ? $user_id : $this->userId,'company_id' => $company_id ? $company_id : $this->companyId])
            ->andFilterWhere(['=', 'course_id', ''])
            ->andFilterWhere(['=', 'course_reg_id', ''])
            ->andFilterWhere(['=', 'mod_id', ''])
            ->andFilterWhere(['=', 'result_type', LnExaminationResultUser::RESULT_TYPE_PROCESS])
            ->count();
    }

    /**
     * @param $examination_id
     * @param null $user_id
     * @param null $company_id
     * @return array|null|\yii\db\ActiveRecord
     */
    public function last($examination_id,$user_id = null,$company_id = null) {
        return LnExaminationResultUser::find(false)
            ->andFilterWhere(['examination_id' => $examination_id])
            ->andFilterWhere(['user_id' => $user_id ? $user_id : $this->userId,'company_id' => $company_id ? $company_id : $this->companyId])
            ->andFilterWhere(['=', 'course_id', ''])
            ->andFilterWhere(['=', 'course_reg_id', ''])
            ->andFilterWhere(['=', 'mod_id', ''])
            ->andFilterWhere(['=', 'result_type', LnExaminationResultUser::RESULT_TYPE_PROCESS])
            ->andFilterWhere(['in', 'examination_status', array(LnExaminationResultUser::STATUS_FLAG_TEMP,LnExaminationResultUser::STATUS_FLAG_NORMAL)])
            ->one();
    }

    /**
     * @param $examination_id
     * @param LnExaminationResultUser $model
     */
    public function preExamRecord($examination_id,LnExaminationResultUser $model) {
        $now = time();
        LnExaminationResultUser::updateAll(['examination_status' => '1', 'start_at' => $now], "kid=:kid", [':kid' => $examination_id]);
        $findFinalResult = LnExaminationResultUser::find(false)->andFilterWhere([
            'examination_id' => $model->examination_id,
            'examination_paper_user_id' => $model->examination_paper_user_id,
            'user_id' => $model->user_id,
            'company_id' => $model->company_id,
            'result_type' => LnExaminationResultUser::RESULT_TYPE_FINALLY,
            'course_id' => $model->course_id,
            'course_reg_id' => $model->course_reg_id,
            'mod_id' => $model->mod_id,
            'mod_res_id' => $model->mod_res_id,
            'courseactivity_id' => $model->courseactivity_id,
            'course_complete_id' => $model->course_complete_id,
        ])->one();
        LnExaminationResultUser::updateAll(['examination_status' => '1', 'start_at' => $now, 'examination_duration' => 0], "kid=:kid", [':kid' => $findFinalResult->kid]);
    }

    /**
     * @param $result_id
     * @param $examination_id
     * @param $examination_question_user_id
     * @param $examination_paper_user_id
     * @param $options_id
     * @param $params
     * @param $checked
     * @param null $user_id
     * @param null $company_id
     * @return array
     */
    public function saveAnswer($result_id,$examination_id,$examination_question_user_id,$examination_paper_user_id,$options_id,$params,$checked,$user_id = null,$company_id = null) {
        $user_id = $user_id ? $user_id : $this->userId;
        $company_id = $company_id ? $company_id : $this->companyId;
        $resultProcessUser = LnExaminationResultUser::findOne($result_id);
        $examinationModel = LnExamination::findOne($examination_id);
        $examQuestionUser = LnExamQuestionUser::findOne($examination_question_user_id);
        if ($examQuestionUser->examination_question_type == LnExaminationQuestion::EXAMINATION_QUESTION_TYPE_JUDGE){/*判断题因为传入的options_id为1或0所以根据存储时的定义查询*/
            $examinationQuestionOptionUser = LnExamQuestOptionUser::findOne(['examination_question_user_id' => $examination_question_user_id]);
        }else{
            $examinationQuestionOptionUser = LnExamQuestOptionUser::findOne($options_id);
        }

        $resultFinalUser = LnExaminationResultUser::find(false)
            ->andFilterWhere([
                'examination_id' => $examination_id,
                'examination_paper_user_id' => $examination_paper_user_id,
                'company_id' => $company_id,
                'user_id' => $user_id,
                'course_id' => $resultProcessUser->course_id,
                'mod_res_id' => $resultProcessUser->mod_res_id,
                'courseactivity_id' => $resultProcessUser->courseactivity_id,
                'result_type' => LnExaminationResultUser::RESULT_TYPE_FINALLY])
            ->one();

        if ($examQuestionUser->examination_question_type == LnExaminationQuestion::EXAMINATION_QUESTION_TYPE_RADIO){
            /*判断是否储存过*/
            $findResultUserDetail = LnExamResultDetail::find(false)
                ->andFilterWhere([
                    'examination_paper_user_id'=>$examination_paper_user_id,
                    'examination_question_user_id'=> $examination_question_user_id,
                    'examination_option_user_id' => $options_id,
                    'examination_result_process_id' => $resultProcessUser->kid,
                    'examination_result_final_id' => $resultFinalUser->kid,
                    'company_id' => $company_id,
                    'user_id' => $user_id,
                    'examination_id' => $examination_id
                ])->one();

            if ($findResultUserDetail){
                return ['result' => 'success', 'errmsg' => 'already'];
            }else{
                LnExamResultDetail::physicalDeleteAll([
                    'user_id' => $user_id,
                    'company_id' => $company_id,
                    'examination_id' => $examination_id,
                    'examination_question_user_id' => $examination_question_user_id,
                    'examination_result_process_id' => $resultProcessUser->kid,
                    'examination_result_final_id' => $resultFinalUser->kid
                ]);
                if ($examinationQuestionOptionUser->is_right_option == LnExamQuestionOption::IS_RIGHT_OPTION_YES){
                    $option_result = ExaminationService::IS_RIGHT_YES;
                }else{
                    $option_result = ExaminationService::IS_RIGHT_NO;
                }
                $this->saveResultUserDetail($examination_question_user_id, $params, $options_id, $resultProcessUser, $resultFinalUser, $company_id, $user_id, $examQuestionUser, $examinationQuestionOptionUser, $examinationModel->examination_version, $option_result);
            }
        }else if ($examQuestionUser->examination_question_type == LnExaminationQuestion::EXAMINATION_QUESTION_TYPE_JUDGE){
            if ($examinationQuestionOptionUser->option_stand_result == LnExamQuestionOption::JUDGE_OPTION_RESULT_RIGHT){
                if ($options_id == LnExamQuestionOption::IS_RIGHT_OPTION_YES){/*回答正确*/
                    $option_result = ExaminationService::IS_RIGHT_YES;
                }else{/*回答错误*/
                    $option_result = ExaminationService::IS_RIGHT_NO;
                }
            }else{
                if ($options_id == LnExamQuestionOption::IS_RIGHT_OPTION_YES){/*回答错误*/
                    $option_result = ExaminationService::IS_RIGHT_NO;
                }else{/*回答正确*/
                    $option_result = ExaminationService::IS_RIGHT_YES;
                }
            }
            if ($option_result == ExaminationService::IS_RIGHT_YES) {
                $is_right_option = LnExamQuestionOption::IS_RIGHT_OPTION_YES;
            }else{
                $is_right_option = LnExamQuestionOption::IS_RIGHT_OPTION_NO;
            }
            $examinationQuestionOptionUser = LnExamQuestOptionUser::findOne([
                'examination_question_user_id'=> $examination_question_user_id,
                'is_right_option' => $is_right_option
            ]);
            /*判断是否储存过*/
            $findResultUserDetail = LnExamResultDetail::find(false)
                ->andFilterWhere([
                    'examination_paper_user_id'=>$examination_paper_user_id,
                    'examination_question_user_id'=> $examination_question_user_id,
                    'examination_option_user_id' => $examinationQuestionOptionUser->kid,
                    'examination_result_process_id' => $resultProcessUser->kid,
                    'examination_result_final_id' => $resultFinalUser->kid,
                    'company_id' => $company_id,
                    'user_id' => $user_id,
                    'examination_id' => $examination_id
                ])->one();
            if ($findResultUserDetail){
                return ['result' => 'success', 'errmsg' => 'already'];
            }else{
                LnExamResultDetail::physicalDeleteAll([
                    'user_id' => $user_id,
                    'company_id' => $company_id,
                    'examination_id' => $examination_id,
                    'examination_question_user_id' => $examination_question_user_id,
                    'examination_result_process_id' => $resultProcessUser->kid,
                    'examination_result_final_id' => $resultFinalUser->kid
                ]);
                $this->saveResultUserDetail(
                    $examination_question_user_id,
                    $params,
                    $examinationQuestionOptionUser->kid,
                    $resultProcessUser,
                    $resultFinalUser,
                    $company_id,
                    $user_id,
                    $examQuestionUser,
                    $examinationQuestionOptionUser,
                    $examinationModel->examination_version,
                    $option_result
                );
            }
        }else if ($examQuestionUser->examination_question_type == LnExaminationQuestion::EXAMINATION_QUESTION_TYPE_CHECKBOX){
            $isRightOptions = LnExamQuestOptionUser::findOne($options_id);
            if ($isRightOptions && $isRightOptions->is_right_option == LnExamQuestionOption::IS_RIGHT_OPTION_YES){
                $option_result = ExaminationService::IS_RIGHT_YES;
            }else{
                $option_result = ExaminationService::IS_RIGHT_NO;
            }

            $findResultUserDetail = LnExamResultDetail::find(false)
                ->andFilterWhere([
                    'examination_paper_user_id'=>$examination_paper_user_id,
                    'examination_question_user_id'=> $examination_question_user_id,
                    'examination_option_user_id' => $options_id,
                    'examination_result_process_id' => $resultProcessUser->kid,
                    'examination_result_final_id' => $resultFinalUser->kid,
                    'company_id' => $company_id,
                    'user_id' => $user_id,
                    'examination_id' => $examination_id
                ])->one();
            if ($checked == 'true'){
                if (!empty($findResultUserDetail->kid)) {
                    return ['result' => 'success', 'errmsg' => 'already'];
                }else{
                    /*添加记录*/
                    $this->saveResultUserDetail(
                        $examination_question_user_id,
                        $params,
                        $options_id,
                        $resultProcessUser,
                        $resultFinalUser,
                        $company_id,
                        $user_id,
                        $examQuestionUser,
                        $examinationQuestionOptionUser,
                        $examinationModel->examination_version,
                        $option_result
                    );
                }
            }else {
                if ($findResultUserDetail) {
                    /*删除记录*/
                    LnExamResultDetail::physicalDeleteAll(['kid' => $findResultUserDetail->kid]);
                }
            }
        }else{
            return ['result' => 'fail', 'errmsg' => '没有此类型试题'];
        }
    }
}
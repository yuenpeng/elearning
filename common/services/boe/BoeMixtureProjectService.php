<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/16
 * Time: 16:48
 */

namespace common\services\boe;


use common\helpers\TArrayHelper;
use common\helpers\TStringHelper;
use common\helpers\TURLHelper;
use common\models\boe\BoeMixtureProject;
use common\models\boe\BoeMixtureProjectCategory;
use common\models\boe\BoeMixtureProjectDomain;
use common\models\boe\BoeMixtureProjectTask;
use common\models\boe\BoeMixtureTaskCourse;
use common\models\learning\LnCourse;
use common\models\learning\LnResourceDomain;
use common\services\framework\UserDomainService;
use components\widgets\TPagination;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class BoeMixtureProjectService extends BoeMixtureProject
{
    /**
     * 受众列表
     * @param $params
     * @return array
     */
    public function getCourseProjectList($params, $isTaskCount = true){
        $model = BoeMixtureProject::find(false);
        if (!empty($params['keyword'])){
            $keyword = htmlspecialchars($params['keyword']);
            $model->andFilterWhere(['or', ['like', 'program_name', $keyword], ['like', 'program_code', $keyword]]);
        }
        $model->andFilterWhere(['company_id'=>$params['companyId']]);

        if (!empty($params['TreeNodeKid']) && $params['TreeNodeKid'] != '-1'){
            $categoryId = $this->getTreeNodeIdToCategoryId($params['TreeNodeKid']);
            if (!empty($categoryId)){
                $model->andFilterWhere(['=', 'category_id', $categoryId]);
            }
        }
        if (isset($params['status']) && $params['status'] != ""){
            $model->andFilterWhere(['=', 'status', $params['status']]);
        }


        $count = $model->count('kid');
        if ($count > 0) {
            if ($isTaskCount) {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy("created_at DESC")->asArray()->all();

                foreach ($result as $key => $item) {
                    $taskCount = BoeMixtureProjectTask::find(false)->andFilterWhere(['=', 'program_id', $item['kid']])->count('kid');
                    $result[$key]['taskCount'] = $taskCount;
                }
            }else{
                $page = null;
                $result = $model->orderBy("created_at DESC")->all();
            }
            return ['data' => $result, 'page' => $page];
        }else{
            return ['data' => null, 'page' => null];
        }
    }


    /**
     * tree_node_id 转 category_id
     * @param $tree_node_id
     * @return array|null|string
     **/
    public function getTreeNodeIdToCategoryId($tree_node_id){
        if (empty($tree_node_id)) return null;
        $categories = BoeMixtureProjectCategory::findAll(['tree_node_id'=>$tree_node_id],false);
        if (is_array($tree_node_id)){
            $result = array();
            foreach ($categories as $value){
                $result[] = $value->kid;
            }
            return $result;
        }else{
            return $categories ? $categories[0]->kid : '';
        }
    }

    /**
     * category_id 转 tree_node_id
     * @param $category_id
     * @return null|string
     **/
    public function getCategoryIdToTreeNodeId($category_id){
        if (empty($category_id)) return null;
        $find = BoeMixtureProjectCategory::findOne($category_id);
        return $find ? $find->tree_node_id : '';
    }

    /**
     * 根据树节点ID获取课程项目目录ID
     * @param $id
     * @return null|string
     */
    public function getProjectCategoryIdByTreeNodeId($id)
    {
        if ($id != null && $id != "") {
            $projectCategoryModel = new BoeMixtureProjectCategory();
            $projectCategoryResult = $projectCategoryModel->findOne(['tree_node_id' => $id]);
            if ($projectCategoryResult != null)
            {
                $courseProjectCategoryId = $projectCategoryResult->kid;
            }
            else
            {
                $courseProjectCategoryId = null;
            }
        }
        else
        {
            $courseProjectCategoryId = null;
        }
        return $courseProjectCategoryId;
    }

    /**
     * 获取项目列表（检索）
     * @param $params
     * @return ActiveDataProvider
     */

    public function Search($params)
    {

        $query = BoeMixtureProject::find(false);
        $domain = [];
        $domain_id = isset($params['domain_id']) && $params['domain_id'] ? $params['domain_id'] : "";
        if (isset($params['TreeNodeKid']) && $params['TreeNodeKid']) {
            $projectCategoryService = new BoeMixtureProjectService();
            $categories = $projectCategoryService->getProjectCategoryIdByTreeNodeId($params['TreeNodeKid']);
            if ($categories) {
                $query->andFilterWhere(['=', 'category_id', $categories]);
            }
        }
        if (empty($domain_id)) {
            $userId = \Yii::$app->user->getId();
            $userDomainService = new UserDomainService();
            $GetSearchListByUserId = $userDomainService->getManagedListByUserId($userId);
            if ($GetSearchListByUserId) {
                $domain = TArrayHelper::map($GetSearchListByUserId, 'kid', 'kid');
                $domain = array_keys($domain);
            }
        } else {
            $domain[] = $domain_id;
        }

        if (isset($params['visable']) && $params['visable'] != "") {
            $query->andFilterWhere([$params['visable'] => '1']);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!empty($domain)) {

            $query->innerJoinWith('boeMixtureProjectDomain')
                ->andFilterWhere(['in',BoeMixtureProjectDomain::tableName() . '.domain_id', $domain])
                ->andFilterWhere(['=', BoeMixtureProjectDomain::tableName() . '.status', LnResourceDomain::STATUS_FLAG_NORMAL])
                ->distinct();
        } else {
            $query->andWhere('kid is null');
        }

        if (!empty($params['program_name'])) {
            $keywords = TStringHelper::clean_xss($params['program_name']);
            $query->andWhere("program_code like '%{$keywords}%' OR program_name like '%{$keywords}%' OR program_desc_nohtml like '%{$keywords}%'");
        }
        $dataProvider->setSort(false);
        $query->addOrderBy([BoeMixtureProject::tableName() . '.created_at' => SORT_DESC]);
        return $dataProvider;
    }

    /**
     * 查找同企业下相同项目名称
     * @param $companyId
     * @param $courseName
     * @param null $kid
     * @return int|string
     */
    public function getSimilarProject($companyId, $project_name, $kid = null)
    {
        if (empty($project_name)) return 0;
        $model = BoeMixtureProject::find(false);
        if (!empty($kid)) {
            $model->andWhere("kid <> '{$kid}'");
        }
        $count = $model->andFilterWhere(['=', 'company_id', $companyId])
            ->andFilterWhere(['=', 'program_name', $project_name])
            ->count('kid');
        return $count;
    }

    /**
     * 生成项目短代码
     * @return string
     */
    public function GenerateShortCode()
    {
        $totalModel = new  BoeMixtureProject();
        $totalCount = $totalModel->find(true)->count('kid');
        $number = $totalCount + 1;
        $shortCode = TURLHelper::generateShortCode($number);

        $unCheckedExist = true;
        do {

            $model = new BoeMixtureProject();
            $result = $model->find(true)
                ->andFilterWhere(['=', 'short_code', $shortCode])
                ->count('kid');

            if (!empty($result) && $result > 0) {
                $shortCode = TURLHelper::generateShortCode();
            } else {
                $unCheckedExist = false;
            }

        } while ($unCheckedExist);

        return $shortCode;
    }
    /*
     * 设置项目编号
     * 规则：日期+sprintf("%03d", $count);
     * @param string $courseId
     * @return string
     */
    public function setCourseCode($kid=""){
        if (!empty($kid)){
            $info = BoeMixtureProject::findOne($kid);
            return $info->program_code;
        }
        $start_at = strtotime(date('Y-m-d'));
        $end_at = $start_at+86399;
        $count = BoeMixtureProject::find(false)->where(['>','created_at',$start_at])->andFilterWhere(['<','created_at',$end_at])->count();
        $count = $count+1;/*默认成1开始*/
        return date('Ymd').sprintf("%03d", $count);
    }

    public function projectNameExists($name){
        $model = BoeMixtureProject::find(false);
        $model->where(['=','program_name',$name]);
        $result = $model->one();
        if(!empty($result)){
            return false;
        }
        return true;
    }

    public function projectPublish($id){
        if(isset($id) && !empty($id)){
            $model = BoeMixtureProject::findOne($id);
            $model->status = self::STATUS_FLAG_NORMAL;
            $model->update();
        }
    }

    /**
     * 删除项目
     * @param $id
     */

    public function projectDelete($id){
        if(isset($id) && !empty($id)){
            $model = BoeMixtureProject::findOne($id);
            $model->is_deleted = self::DELETE_FLAG_YES;
            $model->update();
            BoeMixtureProjectTask::deleteAll("project_id=:project", [':project_id'=>$id]);
        }
    }

    public function getTaskCredit($task_id){
        $model = BoeMixtureTaskCourse::find(false);
        $course_ids = $model->where(['task_id'=>$task_id])
            ->andFilterWhere(['=','task_type',BoeMixtureProjectTask::TASK_IS_COURSE])
            ->select('object_id')->asArray()->all();
        $ids = ArrayHelper::getColumn($course_ids,'object_id');
        $credit = 0;
        if(!empty($ids)){
            $credit = LnCourse::find(false)->where(['in','kid',$ids])->sum('default_credit');
        }
        return $credit;
    }

    /**
     * 课程详情
     * @param $user_id
     * @param $projectId
     * @param bool|false $is_manager
     * @param bool|false $require_menu
     * @param bool|false $require_enroll_info
     * @return array
     */
    public function detail($user_id, $projectId, $is_manager = false, $mode = 'normal')
    {
        $projectModel = BoeMixtureProject::findOne($projectId);
        $now = time();
        //项目不存的
        if (empty($projectModel)) {
            return ['number' => '404', 'code' => 'fail', 'param' => Yii::t('frontend', 'project_does_not_exist')];
        }
        $userDomainService = new UserDomainService();
        $domain = $userDomainService->getSearchListByUserId($user_id);
        if (empty($domain)) {
            return ['number' => '403', 'code' => 'fail', 'param' => Yii::t('common', 'no_permission_to_view_this_project')];
        }
        $domain_id = TArrayHelper::map($domain, 'kid', 'kid');
        $domain_id = array_keys($domain_id);

        $projectDomainService = new BoeMixtureProjectDomainService();
        //查询是否存当前域里面
        if (!$projectDomainService->IsRelationshipDomainValidated($projectId, $domain_id)) {
            return ['number' => '403', 'code' => 'fail', 'param' => Yii::t('frontend', 'invalid_field')];
        }

        $courseRegId = null;
        /*判断是否注册*/
        $projectEnrollService = new BoeMixtureProjectEnrollService();
        $isReg = $projectEnrollService->isUserRegProjectRegState($user_id, $projectId, $courseRegId);
        $openStatus = $projectModel->open_status;
        $isSignin = false;

        if (!$isReg) {
            /*判断是否有受众关系*/
            $projectAudienceService = new BoeMixtureProjectAudienceService();
            $isProjectAudience = $projectAudienceService->isProjectAudience($user_id, $projectId);
            if (!$isProjectAudience) {
                return ['number' => '400', 'code' => 'fail', 'param' => Yii::t('frontend', 'is_not_within_the_audience')];
            }

            if (!empty($projectModel->start_time) && $projectModel->start_time > $now) {
                return ['number' => '400', 'code' => 'fail', 'param' => Yii::t('frontend', 'not_to_start_time')];
            }

            if (!empty($projectModel->end_time) && $projectModel->end_time < $now) {
                return ['number' => '400', 'code' => 'fail', 'param' => Yii::t('frontend', 'due_date')];
            }
            $isCourseDoing = false;
            $isCourseComplete = false;
            $isCourseRetake = false;
            $currentAttempt = 0;
        }
        $courseCompleteService = new CourseCompleteService();
//        $courseCompleteService->initCourseCompleteInfo($courseRegId, $course_id, $user_id);
        $courseCompleteFinalModel = $courseCompleteService->getLastCourseCompleteInfo($courseRegId, LnCourseComplete::COMPLETE_TYPE_FINAL);
        $courseCompleteFinalId = $courseCompleteFinalModel->kid;
        $currentAttempt = $courseCompleteFinalModel->attempt_number;
        $courseCompleteService->checkCourseStatus($courseCompleteFinalModel, $isCourseDoing, $isCourseComplete, $isCourseRetake);

        $canRating = !$this->isRating($user_id, $course_id);
        if (!$isOnlineCourse) {
            $allow_enroll = $this->isEnroll($user_id, $course_id);
            if (!$allow_enroll) $canRating = false;

            $isSignin = $this->isSigninInToday($user_id, $course_id);
        }

        $rating = number_format($this->getCourseMarkByID($course_id), 1);
        $rating_count = $this->getCourseMarkCountByID($course_id);

        LnCourse::addFieldNumber($course_id, 'visit_number');

        /*获取课程证书*/
        $certificationModel = new LnCourseCertification();
        $certificationTemplatesUrl = $certificationModel->getTemplatesUrl($courseModel->kid);
        /*获取课程讲师*/
        $teacherModel = new LnCourseTeacher();
        $teacher = $teacherModel->getTeacherAll($courseModel->kid);
        /*获取报名人数*/
        $enrollInfo = null;
        $sign_status_data = null;
        if (!$isOnlineCourse) {
            $enrollRegNumber = $this->getEnrollNumber($courseModel->kid, [LnCourseEnroll::ENROLL_TYPE_REG, LnCourseEnroll::ENROLL_TYPE_ALLOW]);
            $enrollAlternatenNumber = $this->getEnrollNumber($courseModel->kid, LnCourseEnroll::ENROLL_TYPE_ALTERNATE);
            if ($require_enroll_info) $enrollInfo = $this->getUserEnrollInfo($user_id, $courseModel->kid);
            $allow_enroll = $this->isEnroll($user_id, $course_id);
            if (!$allow_enroll) $canRating = false;
            $courseSignInSettingService = new CourseSignInSettingService();
            $getSignData = $courseSignInSettingService->getRecentSignInSettingId($course_id, $now);/*查询签到配置*/
            if (!empty($getSignData)) {
                $courseSignInService = new CourseSignInService();
                $sign_status_data = $courseSignInService->getStudentSignInStatus($course_id, $user_id);
            }
        } else {
            $enrollRegNumber = 0;
            $enrollAlternatenNumber = 0;
        }

        $catalogMenu = [];
        if ($require_menu) {
            $catalogMenu = $this->genCatalogMenu(
                $courseCompleteFinalId,
                $course_id,
                $isReg,
                $isCourseComplete,
                $mode,
                $isOnlineCourse,
                $isRandom,
                $openStatus,
                $courseMods,
                $modResId,
                $require_score
            );
            if ($sort_menu) {
                $tmp = [];
                foreach ($catalogMenu as $m) {
                    $tmp[] = $m;
                }
                $catalogMenu = $tmp;
                unset($tmp);
            }
        }
        /*学习按钮*/
        $learnStatus = $this->learnStatus($user_id, $course_id, $modResId);

        $fields = [
            'theme_url',
            'kid',
            'course_code',
            'category_id',
            'course_name',
            'default_credit',
            'course_period',
            'course_period_unit',
            'course_desc_nohtml',
            'course_level',
            'course_type',
            'open_status',
            'currency',
            'course_language',
            'is_display_mobile',
            'approval_rule',
            'created_at',
            'updated_at',
            'course_price',
            'training_address',
            'start_time',
            'end_time',
            'enroll_start_time',
            'enroll_end_time',
            'open_start_time',
            'open_end_time'
        ];
        $_course = [];
        foreach ($fields as $field) {
            if (!isset($courseModel->{$field})) {
                continue;
            }
            $_course[$field] = $courseModel->{$field};
        }
        $_course['course_Category_Name'] = $courseModel->getCourseCategoryText();

        $dictionaryService = new DictionaryService();
        $_course['course_level'] = $dictionaryService->getDictionaryNameByValue('course_level', $courseModel->course_level);

        $data = [
            'course' => $_course,
            'courseCompleteFinalId' => $courseCompleteFinalId,
            'isReg' => $isReg,
            'courseRegId' => $courseRegId,
            'isCourseComplete' => $isCourseComplete,
            'isCourseRetake' => $isCourseRetake,
            'rating' => $rating,
            'rating_count' => $rating_count,
            'canRating' => $canRating,
            'catalogMenu' => $catalogMenu,
            'modResId' => $modResId,
            'isManager' => $is_manager,
            'certificationTemplatesUrl' => $certificationTemplatesUrl,
            'teacher' => $teacher,
            'isOnlineCourse' => $isOnlineCourse,
            'isRandom' => $isRandom,
            'openStatus' => $openStatus,
            'enrollRegNumber' => $enrollRegNumber,
            'enrollAlternatenNumber' => $enrollAlternatenNumber,
            'learnStatus' => $learnStatus,
            'enrollInfo' => $enrollInfo,
            'isSignin' => $isSignin,
            'currentAttempt' => $currentAttempt,
            'sign_status_data' => $sign_status_data,
            'limit_number' => $courseModel->limit_number == null ? 0 : $courseModel->limit_number,
            'last_number' => $courseModel->limit_number - $enrollRegNumber,
            'allow_over_number' => $courseModel->allow_over_number == null ? 0 : $courseModel->allow_over_number,


        ];

        return ['code' => 'OK', 'param' => '', 'data' => $data, 'number' => 200];
    }

    /**
     * 根据项目ID获取项目内容
     * @param $projectId
     * @return array
     */
    public function getProjectContentByProjectId($projectId, $uid = '') {
        $projectModel = BoeMixtureProject::findOne($projectId);
        //项目不存的
        /*if (empty($projectModel) || $project->status != BoeMixtureProject::STATUS_FLAG_NORMAL) {
            return ['result' => '', 'msg' => 'failure', 'code' => -3];
        }*/
        $projectTaskService = new BoeMixtureTaskService();
        //获取任务列表
        $task_list = $projectTaskService->getTaskByProjectId($projectTaskService);
        //获取用户报名信息
        $projectEnrollService = new BoeMixtureProjectEnrollService();
        $enrollStatus = $projectEnrollService->getUserApprovedState($uid, $projectId);

        $data = [
            'kid' => $projectId,
            'program_name' => '学习项目名称',
            'program_desc' => '本组课程是针对谁，要干嘛，课程介绍，项目介绍，项目介绍，项目结介绍。本组课程是针对谁，要干嘛，课程介绍，项目介绍，项目介绍，项目结介绍。 本组课程是针对谁，要干嘛，课程介绍，项目介绍，项目介绍，项目结介绍',
            'thumb' => '',
            'enroll_start_time' => time(),
            'enroll_end_time' => time()+86400,
            'open_start_time' => time(),
            'open_end_time' => time()+86400,
            'approval_rule' => 'NONE',
            'task_list' => $task_list,
            'enroll_status' => $enrollStatus,
        ];

        return ['result' => $data, 'msg' => 'ok', 'code' => 0];
    }


}
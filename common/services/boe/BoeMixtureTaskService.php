<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/31
 * Time: 9:29
 */

namespace common\services\boe;


use common\base\BaseActiveRecord;
use common\models\boe\BoeMixtureCourseGroup;
use common\models\boe\BoeMixtureCourseTemp;
use common\models\boe\BoeMixtureGroupTemp;
use common\models\boe\BoeMixtureProjectTask;
use common\models\boe\BoeMixtureTaskCourse;
use common\models\boe\BoeMixtureTaskCourseTemp;
use common\models\boe\BoeMixtureTaskTemp;
use common\models\learning\LnCourse;
use components\widgets\TPagination;
use vakata\database\Exception;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Response;

class BoeMixtureTaskService extends BoeMixtureProjectTask
{
    const STATUS_YES = "1";
    const STATUS_NO  = "0";
    /**
     * 保存任务及下面课程到临时表
     * @param $params
     * @return array
     */
    public function saveTaskTemp($params){
        if (empty($params['task_name'])){
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'task_name_not_empty')];
        }
        $course_batch = $params['course_batch'];
        if (empty($course_batch)) {
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'loading_fail')];
        }
        if($params['is_group'] == BoeMixtureProjectTask::TASK_IS_COURSE){
            if(empty($params['pass_credit']) || !is_numeric($params['pass_credit'])){
                return ['result' => 'fail', 'errmsg' => Yii::t('common', 'input_complete_rule')];
            }
        }

        $ownerId = $params['owner_id'];
        if(empty($params['task_id'])){
            $res = $this->isExistsTaskName($params['task_name']);
            if ($res){
                return ['result' => 'fail', 'errmsg' => Yii::t('common', 'having_task_name')];
            }
        }
        $complete_rule = 0;
        $course_credit = 0;
        // 如果关联的为课程
        if($params['is_group'] == BoeMixtureProjectTask::TASK_IS_COURSE){
            $complete_rule = $params['pass_credit'];
            $course_credit = $this->getCourseCredit($ownerId,$course_batch);
            if($complete_rule >= $course_credit){
                return ['result' => 'fail', 'errmsg' => Yii::t('common', 'credit_error')];
            }
            $courseTempModel = BoeMixtureCourseTemp::find(false);
            $courseTempModel->andFilterWhere(['=', 'course_batch', $course_batch])
                ->andFilterWhere(['=', 'owner_id', $ownerId])
                ->andFilterWhere(['=','course_type',BoeMixtureCourseTemp::COURSE_IS_TASK]);
            $course_ids = $courseTempModel->select('course_id')->orderBy('created_at desc')->asArray()->all();
            if (empty($course_ids)){
                return ['result' => 'fail', 'errmsg' => Yii::t('common', 'course_not_empty')];
            }
        }elseif($params['is_group'] == BoeMixtureProjectTask::TASK_IS_GROUP){ //关联的为课程组
            $groupTempModel = BoeMixtureGroupTemp::find(false);
            $groupTempModel->andFilterWhere(['=', 'course_batch', $course_batch])
                ->andFilterWhere(['=', 'owner_id', $ownerId]);
            $group_ids = $groupTempModel->select('group_id')->orderBy('created_at desc')->asArray()->all();
            if (empty($group_ids)){
                return ['result' => 'fail', 'errmsg' => Yii::t('common', 'group_not_empty')];
            }
        }else{
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'loading_fail')];
        }

        $task_id = !empty($params['task_id']) ? $params['task_id'] : "";
        $model = empty($task_id) ? new BoeMixtureTaskTemp() : BoeMixtureTaskTemp::find(false);
        $model->task_batch = $params['course_batch'];
        $model->is_group = $params['is_group'];
        $model->task_code = BoeMixtureTaskTemp::setTaskCode();
        $model->task_name = $params['task_name'];
        $model->task_desc = $params['desc'];
        $model->complete_rule = $complete_rule;
        $model->task_score = $course_credit;
        $model->saveEncode = true;
        if (empty($task_id)) {
            $model->needReturnKey = true;
            $result = $model->save();
        }else{
            $result = $model->update();
        }
        if ($result !== false){
            $task_id = $model->kid;
            $modelArray = array();
            if($params['is_group'] == BoeMixtureProjectTask::TASK_IS_COURSE ) {//课程处理开始
                if (!empty($task_id)) {
                    //更新
                    $updateArray = array();
                    foreach ($course_ids as $item) {
                        $modelTemp = BoeMixtureTaskCourseTemp::findOne(['temp_task_id' => $task_id, 'object_id' => $item['course_id']]);
                        if (empty($modelTemp)) {
                            $modelTemp = new BoeMixtureTaskCourseTemp();
                            $modelTemp->temp_task_id = $task_id;
                            $modelTemp->object_id = $item['course_id'];
                            array_push($modelArray, $modelTemp);
                        } else {
                            array_push($updateArray, $modelTemp);
                        }
                    }
                    $insertArray = array();
                    $errMsg = "";
                    $resultId = array();
                    if (!empty($modelArray)) {
                        BaseActiveRecord::batchInsertNormalMode($modelArray, $errmsg, true, $resultId);
                        $insertArray = $resultId;
                    }
                    $errMsg = "";
                    $updateResult = array();
                    if (!empty($updateArray)) {
                        BaseActiveRecord::batchUpdateNormalMode($updateArray, $errmsg, true, $updateResult);
                        if (!empty($insertArray)) {
                            $insertArray = array_merge($insertArray, $updateResult);
                        } else {
                            $insertArray = $updateResult;
                        }
                    }
                    $resultIdArray = array_filter($insertArray);
                    $resultIdArray = array_unique($resultIdArray);
                    if (!empty($resultIdArray)) {
                        $resultIdArraySql = "'" . join("','", $resultIdArray) . "'";
                        BoeMixtureTaskCourseTemp::updateAll(
                            [
                                'is_deleted' => 1
                            ],
                            'kid not in (' . $resultIdArraySql . ') and temp_task_id=:temp_task_id',
                            [':temp_task_id' => $task_id]
                        );
                    }
                    //return ['result' => 'success'];
                } else {
                    //新添加课程
                    foreach ($course_ids as $item) {
                        $modelTemp = new BoeMixtureTaskCourseTemp();
                        $modelTemp->temp_task_id = $task_id;
                        $modelTemp->object_id = $item['course_id'];
                        array_push($modelArray, $modelTemp);
                    }
                    $errMsg = "";
                    if (!empty($modelArray)) {
                        BaseActiveRecord::batchInsertNormalMode($modelArray, $errmsg, true, $resultId);
                    }
                }
                $this->deleteCourseTempAll($ownerId);
                return ['result' => 'success'];
            }elseif($params['is_group'] == BoeMixtureProjectTask::TASK_IS_GROUP){//课程组处理开始
                if (!empty($task_id)) {
                    //更新
                    $updateArray = array();
                    foreach ($group_ids as $group) {
                        $modelTemp = BoeMixtureTaskCourseTemp::findOne(['temp_task_id' => $task_id, 'object_id' => $group['group_id']]);
                        if (empty($modelTemp)) {
                            $modelTemp = new BoeMixtureTaskCourseTemp();
                            $modelTemp->temp_task_id = $task_id;
                            $modelTemp->object_id = $group['group_id'];
                            $modelTemp->task_type = BoeMixtureProjectTask::TASK_IS_GROUP;
                            array_push($modelArray, $modelTemp);
                        } else {
                            array_push($updateArray, $modelTemp);
                        }
                    }
                    $insertArray = array();
                    $errMsg = "";
                    $resultId = array();
                    if (!empty($modelArray)) {
                        BaseActiveRecord::batchInsertNormalMode($modelArray, $errmsg, true, $resultId);
                        $insertArray = $resultId;
                    }
                    $errMsg = "";
                    $updateResult = array();
                    if (!empty($updateArray)) {
                        BaseActiveRecord::batchUpdateNormalMode($updateArray, $errmsg, true, $updateResult);
                        if (!empty($insertArray)) {
                            $insertArray = array_merge($insertArray, $updateResult);
                        } else {
                            $insertArray = $updateResult;
                        }
                    }
                    $resultIdArray = array_filter($insertArray);
                    $resultIdArray = array_unique($resultIdArray);
                    if (!empty($resultIdArray)) {
                        $resultIdArraySql = "'" . join("','", $resultIdArray) . "'";
                        BoeMixtureTaskCourseTemp::updateAll(
                            [
                                'is_deleted' => 1
                            ],
                            'kid not in (' . $resultIdArraySql . ') and temp_task_id=:temp_task_id',
                            [':temp_task_id' => $task_id]
                        );
                    }
                    //return ['result' => 'success'];
                } else {
                    //新添
                    foreach ($group_ids as $group) {
                        $modelTemp = new BoeMixtureTaskCourseTemp();
                        $modelTemp->temp_task_id = $task_id;
                        $modelTemp->object_id = $group['group_id'];
                        $modelTemp->task_type = BoeMixtureProjectTask::TASK_IS_GROUP;
                        array_push($modelArray, $modelTemp);
                    }
                    $errMsg = "";
                    if (!empty($modelArray)) {
                        BaseActiveRecord::batchInsertNormalMode($modelArray, $errmsg, true, $resultId);
                    }
                }
                $this->deleteTaskGroupTempAll($ownerId);
                return ['result' => 'success'];
            }
        }else{
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'SaveFail')];
        }
    }

    /**
     * 检查任务名称是否存在
     * @param $task_name
     */

    public function isExistsTaskName($task_name){
        $model = BoeMixtureProjectTask::find(false)
            ->andFilterWhere(['=', 'task_name', $task_name]);
        $result = $model->one();

        if (!empty($result)){
            $tempModel = BoeMixtureTaskTemp::find(false)
                ->andFilterWhere(['=','task_name',$task_name]);
            if(!empty($tempModel)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    /**
     * 获取临时表中课程的总学分
     */

    public function getCourseCredit($userId,$course_batch){
        $model = BoeMixtureCourseTemp::find(false);
        $model->andFilterWhere(['=','owner_id',$userId])
            ->andFilterWhere(['=','course_batch',$course_batch])
            ->andFilterWhere(['=','course_type',BoeMixtureCourseTemp::COURSE_IS_TASK]);
        $credit = $model->sum('course_credit');
        return $credit;
    }
    /**
     * 刷新页面清空数据
     * @param $userId
     * @param $companyId
     */
    public function deleteCourseTempAll($userId){
        $condition = [
            'owner_id' => $userId,
            'course_type'=>BoeMixtureCourseTemp::COURSE_IS_TASK,
        ];
        BoeMixtureCourseTemp::physicalDeleteAll($condition);
    }
    /**
     * 删除任务下面课程组临时表数据
     * @param $userId
     * @param $companyId
     */
    public function deleteTaskGroupTempAll($userId){
        $condition = [
            'owner_id' => $userId,
        ];
        BoeMixtureGroupTemp::physicalDeleteAll($condition);
    }

    /**
     * 获取任务下面的课程或课程组
     * @param $params
     * @return array|\yii\db\ActiveRecord[]
     */

    public function getTempTaskList($params){
        $model = BoeMixtureTaskTemp::find(false);
        $model->andFilterWhere(['=','created_by',$params['owner_id']])
            ->andFilterWhere(['=','task_batch',$params['course_batch']]);
        $task_list = $model->orderBy('created_at desc')->asArray()->all();
        if(empty($task_list)){
            return [];
        }else{
            foreach($task_list as $key=>$task){
                if($task['is_group'] == BoeMixtureProjectTask::TASK_IS_GROUP){
                    $group = BoeMixtureTaskCourseTemp::find(false)
                        ->select([BoeMixtureCourseGroup::realTableName().'.kid',BoeMixtureCourseGroup::realTableName().'.group_name',BoeMixtureCourseGroup::realTableName().'.group_score',BoeMixtureCourseGroup::realTableName().'.complete_rule',BoeMixtureCourseGroup::realTableName().'.created_at',BoeMixtureCourseGroup::realTableName().'.created_by'])
                        ->joinWith('boeMixtureCourseGroup')
                        ->andFilterWhere(['=',BoeMixtureTaskCourseTemp::realTableName().'.temp_task_id',$task['kid']])
                        ->asArray()->all();
                    $task_list[$key]['group'] = $group;
                }else{
                    $course = BoeMixtureTaskCourseTemp::find(false)
                        ->select([LnCourse::realTableName().'.kid',LnCourse::realTableName().'.course_name',LnCourse::realTableName().'.open_status',LnCourse::realTableName().'.created_at as course_created_at',LnCourse::realTableName().'.created_by as course_created_by'])
                        ->joinWith('lnCourse')
                        ->andFilterWhere(['=',BoeMixtureTaskCourseTemp::realTableName().'.temp_task_id',$task['kid']])
                        ->asArray()->all();
                    $task_list[$key]['course'] = $course;
                }

            }
            return $task_list;
        }
    }

    /**
     * 批量插入
     * @param $project_id
     * @param $course_batch
     * @return bool
     */

    public function batchInsertTask($project_id,$course_batch){
        $user_id = Yii::$app->user->getId();
        $params['owner_id'] = $user_id;
        $params['course_batch'] = $course_batch;

        $boeMixtureTaskCourseService = new BoeMixtureTaskCourseService();
        $task_list = $this->getTempTaskList($params);
        foreach($task_list as $key=>$list){
            $taskModel = new BoeMixtureProjectTask();
            $taskModel->project_id = $project_id;
            $taskModel->task_name = $list['task_name'];
            $taskModel->task_code = BoeMixtureProjectTask::setTaskCode();
            $taskModel->task_desc = $list['task_desc'];
            $taskModel->status = self::STATUS_YES;
            $taskModel->is_group = $list['is_group'];
            $taskModel->task_score = isset($list['task_score'])&& is_numeric($list['task_score']) ? $list['task_score'] : 0;
            $taskModel->complete_rule = isset($list['complete_rule']) && is_numeric($list['complete_rule']) ? $list['complete_rule'] : 0 ;
            $taskModel->needReturnKey = true;
            if($taskModel->save()){
                $task_id = $taskModel->kid;
                if($taskModel->is_group == BoeMixtureProjectTask::TASK_IS_COURSE){
                    $save_result =  $boeMixtureTaskCourseService->batchInsertTaskCourse($task_id,$list['course']);
                }else{
                    $save_result = $boeMixtureTaskCourseService->batchInsertTaskCourse($task_id,$list['group']);
                }
                if(!$save_result){
                    return false;
                }
            }else{
                return false;
            }

        }
        return true;


    }


    /**
     * 查询临时课程组列表
     * @param $params
     * @return array
     */
    public function getGroupTemp($params){
        $model = BoeMixtureGroupTemp::find(false);
        if ($params['ownerId']) {
            $model->andFilterWhere(['=', 'owner_id', $params['ownerId']]);
        }

        if (!empty($params['course_batch'])){
            $model->andFilterWhere(['=', 'course_batch', $params['course_batch']]);
        }

        if (!empty($params['keyword'])) {
            $keyword = htmlspecialchars($params['keyword']);
            $model->andFilterWhere(['like', 'group_name', $keyword]);
        }

        $count = $model->count('kid');
        if ($count) {
            if (!empty($params['format'])){
                $result = $model->orderBy('created_at desc')->all();
                return ['data' => $result];
            }else {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy('created_at desc')->all();
                return ['data' => $result, 'page' => $page];
            }
        }else{
            return ['data' => null, 'page' => null];
        }
    }

    /**
     * 批量插入课程组临时数据
     * @param $params
     * @return bool|string
     */
    public function betchInsertGroupTemp($params){
        $group = $params['group'];
        if (empty($group)){
            return false;
        }
        if (!empty($group)){
            $ownerId = Yii::$app->user->getId();
            $batchModel = array();
            $errMsg = "";
            foreach ($group as $v){
                $group_item = BoeMixtureCourseGroup::findOne($v);
                $hasData = BoeMixtureGroupTemp::findOne(['group_id'=>$v,'course_batch'=>$params['course_batch'],'owner_id' => $ownerId]);
                if (empty($hasData)) {
                    $model = new BoeMixtureGroupTemp();
                    $model->course_batch = $params['course_batch'];
                    $model->owner_id = $ownerId;
                    $model->group_id = $group_item->kid;
                    $model->group_name = $group_item->group_name;
                    $model->group_score = $group_item->group_score;
                    $model->complete_rule = $group_item->complete_rule;
                    array_push($batchModel, $model);
                }else{
                    $errMsg = $hasData->getErrors();
                }
            }
            if (!empty($batchModel)) {
                BaseActiveRecord::batchInsertSqlArray($batchModel, $errmsg);
            }
            return $errMsg;
        }else{
            return false;
        }
    }

    /**
     * 批量删除任务临时表及下属课程或组
     * @param $task_batch
     */

    public function deleteTempTask($task_batch){
        $user_id = Yii::$app->user->getId();
        if(isset($task_batch) && !empty($task_batch)){
            $tasks = BoeMixtureTaskTemp::find(false)->where(['=','task_batch',$task_batch])->andFilterWhere(['=','created_by',$user_id])->asArray()->all();
            foreach($tasks as $key=>$list){
                BoeMixtureTaskCourseTemp::deleteAll("temp_task_id=:temp_task_id", [':temp_task_id'=>$list['kid']]);
            }
            BoeMixtureTaskTemp::deleteAll("task_batch=:task_batch and created_by=:created_by",[':task_batch'=>$task_batch,':created_by'=>$user_id]);
        }

    }

    /**
     *   根据项目ID获取项目下面的任务
     * @param $id
     */

    public function getProjectTask($id){
        $task_list = BoeMixtureProjectTask::find(false)
            ->where(['=','project_id',$id])
            ->andFilterWhere(['=','is_deleted',self::DELETE_FLAG_NO])
            ->asArray()->all();
        $taskCourseService = new BoeMixtureTaskCourseService();
        $task_list = $taskCourseService->getListByTaskId($task_list);
        return $task_list;
    }

    /**
     * 任务列表
     * @param $params
     * @return array
     */
    public function getTaskList($params, $isTaskCount = true){
        $model = BoeMixtureProjectTask::find(false);
        $model->andFilterWhere(['=', 'project_id', $params['id']]);
        $count = $model->count('kid');
        if ($count > 0) {
            if ($isTaskCount) {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy("created_at DESC")->asArray()->all();

                foreach ($result as $key => $item) {
                    $courseCount = BoeMixtureTaskCourse::find(false)->andFilterWhere(['=', 'task_id', $item['kid']])->count('kid');
                    $result[$key]['course_count'] = $courseCount;
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
     *  任务保存
     * @return array
     */

    public function saveTask($params){
        if (empty($params['task_name'])){
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'task_name_not_empty')];
        }
        $complete_rule = 0;
        $course_credit = 0;
        $model = isset($params['task_id']) && !empty($params['task_id']) ? BoeMixtureProjectTask::findOne($params['task_id']) : new BoeMixtureProjectTask();
        $model->project_id = $params['project_id'];
        $model->task_name = $params['task_name'];
        $model->task_code = BoeMixtureProjectTask::setTaskCode();
        $model->task_desc = $params['task_desc'];
        $model->is_group = $params['is_group'];
        $model->end_time = isset($params['task_end_time']) ? (int)strtotime($params['task_end_time']) : 0;
        $model->task_score = $course_credit;
        $model->complete_rule = $complete_rule;
        if($model->save()){
            return ['result'=>'success'];
        }
        return ['result' => 'fail', 'errmsg' => Yii::t('common', 'SaveFail')];
    }

    public function setCompleteRule($params){
        $model = BoeMixtureProjectTask::findOne($params['task_id']);
        if($model){
            $model->task_score = $params['task_score'];
            $model->complete_rule = $params['complete_rule'];
            if($model->save()){
                return true;
            }
        }
        return false;
    }

    /**
     * 根据项目ID获取任务
     * @param $projectId
     * @return array
     */
    public function getTaskByProjectId($projectId){
        $task_list = [
            0 => [
                'kid' => 'E1A6C0D9-C337-3AA8-82B7-502FB3FA3E08',
                'task_name' => '任务名称一',
                'complete_rule' => '5/5',
                'end_at' => time(),
                'complete_status' => 2,
                'task_desc' => '京东方的未来与挑战旨在......这里是一段任务介绍，大概两三行的任务里是一个介绍，这里是一段任务介绍，大概两三行四的任务里是一个介绍，这里是一段任务介绍，大概两三行的任务里是一个介绍，这里是一段任务介绍，大概两三行的任务里加什么',
            ],
            1 => [
                'kid' => 'E569FEE1-D991-9E40-E3BD-EACE248EACC2',
                'task_name' => '任务名称二',
                'complete_rule' => '5/5',
                'end_at' => '',
                'complete_status' => 0,
                'task_desc' => '京东方的未来与挑战旨在......这里是一段任务介绍，大概两三行的任务里是一个介绍，这里是一段任务介绍，大概两三行四的任务里是一个介绍，这里是一段任务介绍，大概两三行的任务里是一个介绍，这里是一段任务介绍，大概两三行的任务里加什么',
            ],
            2 => [
                'kid' => 'E8BAF9BD-B681-34C9-CEC3-44BD28F2F331',
                'task_name' => '任务名称三',
                'complete_rule' => '5/5',
                'end_at' => '',
                'complete_status' => 1,
                'task_desc' => '京东方的未来与挑战旨在......这里是一段任务介绍，大概两三行的任务里是一个介绍，这里是一段任务介绍，大概两三行四的任务里是一个介绍，这里是一段任务介绍，大概两三行的任务里是一个介绍，这里是一段任务介绍，大概两三行的任务里加什么',
            ],
        ];

        return $task_list;
    }

    /**
     * 根据任务ID获取任务详情
     * @param $taskId
     * @return array
     */
    public function getTaskContentByTaskId($taskId){
        $taskModel = BoeMixtureProjectTask::findOne($taskId);
        /*if (empty($taskModel)) {
            return ['result' => '', 'msg' => 'failure', 'code' => -2];
        }*/
        //任务与课程、课程组关系
        $taskCourseService = new BoeMixtureTaskCourseService();
        if ($taskModel->is_group == BoeMixtureProjectTask::TASK_IS_COURSE) {
            $course_list = $taskCourseService->getCourseByTaskId($taskId);
        }else{
            $course_list = $taskCourseService->getCourseGroupByTaskId($taskId);
        }
        //
        $data = [
            'kid' => '00001',
            'task_name' => '任务名称',
            'task_desc' => '任务描述',
            'complete_rule' => '5/5',
            'is_group' => 1,
            'approved_state' => 0,
            'complete_grade' => 0,
            'course_list' => $course_list
        ];

        return ['result' => $data, 'msg' => 'ok', 'code' => 0];
    }

}
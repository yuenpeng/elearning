<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/6/5
 * Time: 9:03
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureCourseGroup;
use common\models\boe\BoeMixtureProjectTask;
use common\models\boe\BoeMixtureTaskCourse;
use common\models\learning\LnCourse;
use yii;

class BoeMixtureTaskCourseService extends BoeMixtureTaskCourse
{
    /**
     * 批量插入课程或课程组
     * @param $task_id
     * @param $courses
     * @return bool
     */

    public function batchInsertTaskCourse($task_id,$courses){
        foreach($courses as $key=>$course){
            $model = new BoeMixtureTaskCourse();
            $model->task_type = BoeMixtureProjectTask::TASK_IS_COURSE;
            $model->task_id = $task_id;
            $model->object_id = $course['kid'];
            $result = $model->save();
            if(!$result){
                return false;
            }
        }
        return true;
    }

    /**
     *  根据任务ID获取下面的数据列表
     * @param $id
     * @return array|\yii\db\ActiveRecord[]
     */

    public function getListByTaskId($task_list){
        foreach($task_list as $key=>$item) {
            if ($item['is_group']== BoeMixtureProjectTask::TASK_IS_COURSE) {
                $course = BoeMixtureTaskCourse::find(false)
                    ->select([LnCourse::realTableName() . '.kid', LnCourse::realTableName() . '.course_name', LnCourse::realTableName() . '.open_status', LnCourse::realTableName() . '.created_at as course_created_at', LnCourse::realTableName() . '.created_by as course_created_by'])
                    ->joinWith('lnCourse')
                    ->andFilterWhere(['=', BoeMixtureTaskCourse::realTableName() . '.task_id', $item['kid']])
                    ->asArray()->all();
                $task_list[$key]['course'] = $course;
            }elseif($item['is_group'] == BoeMixtureProjectTask::TASK_IS_GROUP){
                $group = BoeMixtureTaskCourse::find(false)
                    ->select([BoeMixtureCourseGroup::realTableName() . '.kid', BoeMixtureCourseGroup::realTableName() . '.group_name', BoeMixtureCourseGroup::realTableName() . '.group_score', BoeMixtureCourseGroup::realTableName() . '.complete_rule', BoeMixtureCourseGroup::realTableName() . '.created_at', BoeMixtureCourseGroup::realTableName() . '.created_by'])
                    ->joinWith('boeMixtureCourseGroup')
                    ->andFilterWhere(['=', BoeMixtureTaskCourse::realTableName() . '.task_id', $item['kid']])
                    ->asArray()->all();
                $task_list[$key]['group'] = $group;
            }

        }
        return $task_list;

    }

    /**
     * 任务绑定课程
     * @param $task_id
     * @param $object_id
     * @return bool
     */

    public function objectBind($task_id,$object_id){
        $taskModel = BoeMixtureProjectTask::findOne($task_id);
        $model = new BoeMixtureTaskCourse();
        $model->object_id = $object_id;
        $model->task_id = $task_id;
        if($taskModel->is_group == BoeMixtureProjectTask::TASK_IS_COURSE){
            $model->task_type = BoeMixtureProjectTask::TASK_IS_COURSE;
        }else{
            $model->task_type = BoeMixtureProjectTask::TASK_IS_GROUP;
        }

        if($model->save()){
            return true;
        }
        return false;
    }

    /**
     *解除绑定
     * @param $task_id
     * @param $object_id
     * @return bool
     */

    public function objectRemoveBind($task_id,$object_id){
        BoeMixtureTaskCourse::physicalDeleteAll("task_id=:task_id and object_id=:object_id", [':task_id'=>$task_id,':object_id'=>$object_id]);
        return true;
    }

    /**
     * 根据任务ID获取课程组列表
     * @param $taskId
     * @return array
     */
    public function getCourseGroupByTaskId($taskId){
        $group_list = [
            0 => [
                'kid' => '10001',
                'name' => '课程组一',
                'score' => '1000',
                'complete_rule' => '5/5',
                'complete_score' => '10',
                'complete_status' => 0,
                'complete_status_txt' => '未开始',
            ],
            1 => [
                'kid' => '10002',
                'name' => '课程组二',
                'score' => '1000',
                'complete_rule' => '5/5',
                'complete_score' => '10',
                'complete_status' => 1,
                'complete_status_txt' => '进行中',
            ],
            2 => [
                'kid' => '10003',
                'name' => '课程组三',
                'score' => '1000',
                'complete_rule' => '5/5',
                'complete_score' => '10',
                'complete_status' => 2,
                'complete_status_txt' => '已完成',
            ],
        ];
        return $group_list;
    }

    /**
     * 根据任务ID获取课程信息
     * @param $taskId
     * @return array
     */
    public function getCourseByTaskId($taskId) {
        $course_list = [
            0 => [
                'kid' => '100001',
                'name' => '课程组一',
                'score' => '1000',
                'complete_rule' => '5/5',
                'complete_score' => '10',
                'complete_status' => 0,
                'complete_status_txt' => '未开始',
                //课程
                'enroll_status' => 0,
                'enroll_status_txt' => '未选课',
                'type_txt' => '在线',
                'open_start_time' => '2018年03月06日',
                'open_end_time' => '05月10日',
                'status_txt' => '已结束',
                'link' => Yii::$app->request->hostInfo.Yii::$app->urlManager->createUrl(['/resource/course/view', 'id' => 1]),
            ],
            1 => [
                'kid' => '100002',
                'name' => '课程组二',
                'score' => '1000',
                'complete_rule' => '5/5',
                'complete_score' => '10',
                'complete_status' => 1,
                'complete_status_txt' => '进行中',
                //课程
                'enroll_status' => 0,
                'enroll_status_txt' => '未选课',
                'type_txt' => '在线',
                'open_start_time' => '2018年03月06日',
                'open_end_time' => '05月10日',
                'status_txt' => '已结束',
                'link' => Yii::$app->request->hostInfo.Yii::$app->urlManager->createUrl(['/resource/course/view', 'id' => 1]),
            ],
            2 => [
                'kid' => '100003',
                'name' => '课程组三',
                'score' => '1000',
                'complete_rule' => '5/5',
                'complete_score' => '10',
                'complete_status' => 2,
                'complete_status_txt' => '已完成',
                //课程
                'enroll_status' => 0,
                'enroll_status_txt' => '未选课',
                'type_txt' => '在线',
                'open_start_time' => '2018年03月06日',
                'open_end_time' => '05月10日',
                'status_txt' => '已结束',
                'link' => Yii::$app->request->hostInfo.Yii::$app->urlManager->createUrl(['/resource/course/view', 'id' => 1]),
            ],
        ];
        return $course_list;
    }
}
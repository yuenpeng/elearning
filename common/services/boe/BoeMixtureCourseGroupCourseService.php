<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/24
 * Time: 13:18
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureCourseGroup;
use common\models\boe\BoeMixtureCourseGroupCourse;
use Yii;
use common\models\treemanager\FwTreeNode;
use common\base\BaseActiveRecord;

class BoeMixtureCourseGroupCourseService extends BoeMixtureCourseGroupCourse
{

    /**
     * 根据课程组ID获取课程
     * @param $groupId
     * @return array
     */
    public function getCourseByGroupId($groupId){
        $course_list = [
            0 => [
                'kid' => '',
                'name' => '课程一',
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
                'kid' => '',
                'name' => '课程二',
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
                'kid' => '',
                'name' => '课程三',
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
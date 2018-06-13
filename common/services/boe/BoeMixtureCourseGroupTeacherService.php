<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/16
 * Time: 16:51
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureCourseGroupTeacher;

class BoeMixtureCourseGroupTeacherService extends BoeMixtureCourseGroupTeacher
{
    public function stopCourseTeacher($courseGroupId)
    {
        $attributes = ['status' => self::STATUS_FLAG_STOP];
        $condition = "course_group_id=:course_group_id and status =:status";
        $param = [
            ':course_group_id' => $courseGroupId,
            ':status' => self::STATUS_FLAG_NORMAL
        ];
        return BoeMixtureCourseGroupTeacher::updateAll($attributes, $condition, $param);
    }
}
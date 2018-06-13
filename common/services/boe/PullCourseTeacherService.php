<?php
namespace common\services\boe;
/**
 * Created by PhpStorm.
 * User: Jay Jiang
 * Date: 2016/2/26
 * Time: 14:10
 */
class PullCourseTeacherService extends Object{
    public function queryAll($sql,$params = null)
    {
        return eLearningLMS::queryAll($sql,$params);
    }
}
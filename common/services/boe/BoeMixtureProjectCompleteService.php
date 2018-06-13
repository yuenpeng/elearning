<?php
/**
 * Created by PhpStorm.
 * User: adophper
 * Date: 2018/5/16
 * Time: 16:48
 */

namespace common\services\boe;

use yii;
use common\models\boe\BoeMixtureProjectComplete;

class BoeMixtureProjectCompleteService extends BoeMixtureProjectComplete
{

    /**
     * 获取学员项目完成数据
     * @param $uid
     * @param $projectId
     * @param $enrollId
     * @return BoeMixtureProjectComplete|mixed|null
     */
    public function getUserProjectComplete($uid, $projectId, $enrollId){
        $model = BoeMixtureProjectComplete::findOne(['user_id' => $uid, 'program_id' => $projectId, 'program_enroll_id' => $enrollId]);
        return $model;
    }

}
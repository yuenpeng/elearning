<?php
/**
 * Created by PhpStorm.
 * User: t62539
 * Date: 2/20/2016
 * Time: 11:17 PM
 */

namespace common\services\framework;


use common\models\framework\FwUserManager;
use common\base\BaseActiveRecord;

class UserManagerService extends FwUserManager
{
    /**
     * 批量启用关系
     * @param FwUserManager $targetModel
     */
    public function batchStartRelationship($targetModels)
    {
        if (isset($targetModels) &&  $targetModels != null && count($targetModels) > 0)
        {
            BaseActiveRecord::batchInsertSqlArray($targetModels);
        }
    }

    /**
     * 停用用户相关所有经理
     * @param $userId
     */
    public function stopRelationshipByUserId($userId)
    {
        $sourceMode = new FwUserManager();

        $params = [
            ':user_id'=>$userId,
            ':status'=> self::STATUS_FLAG_NORMAL,
        ];

        $condition = BaseActiveRecord::getQuoteColumnName("user_id") . ' = :user_id' .
            ' and ' . BaseActiveRecord::getQuoteColumnName("status") . '  = :status';

        $attributes = [
            'status' => self::STATUS_FLAG_STOP,
            'end_at' => time(),
        ];

        $sourceMode->updateAll($attributes,$condition,$params);
    }
}
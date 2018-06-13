<?php

namespace common\models\boe;

use common\models\learning\LnCourse;
use Yii;

/**
 * This is the model class for table "{{%boe_mixture_task_course}}".
 *
 * @property string $kid
 * @property string $task_type
 * @property string $task_id
 * @property string $object_id
 * @property integer $version
 * @property string $created_by
 * @property integer $created_at
 * @property string $created_from
 * @property string $created_ip
 * @property string $updated_by
 * @property integer $updated_at
 * @property string $updated_from
 * @property string $updated_ip
 * @property string $is_deleted
 */
class BoeMixtureTaskCourse extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_task_course}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['task_id', 'object_id'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'task_id', 'object_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['task_type', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'task_type' => Yii::t('boe', 'Task Type'),
            'task_id' => Yii::t('boe', 'Task ID'),
            'object_id' => Yii::t('boe', 'Object ID'),
            'version' => Yii::t('boe', 'Version'),
            'created_by' => Yii::t('boe', 'Created By'),
            'created_at' => Yii::t('boe', 'Created At'),
            'created_from' => Yii::t('boe', 'Created From'),
            'created_ip' => Yii::t('boe', 'Created Ip'),
            'updated_by' => Yii::t('boe', 'Updated By'),
            'updated_at' => Yii::t('boe', 'Updated At'),
            'updated_from' => Yii::t('boe', 'Updated From'),
            'updated_ip' => Yii::t('boe', 'Updated Ip'),
            'is_deleted' => Yii::t('boe', 'Is Deleted'),
        ];
    }

    /**
     * @return $this
     */

    public function getLnCourse(){
        return $this->hasOne(LnCourse::className(),['kid'=>'object_id'])
            ->onCondition([LnCourse::realTableName() . '.is_deleted' => self::DELETE_FLAG_NO]);
    }

    public function getBoeMixtureCourseGroup(){
        return $this->hasOne(BoeMixtureCourseGroup::className(),['kid'=>'object_id'])
            ->onCondition([BoeMixtureCourseGroup::realTableName().'.is_deleted'=>self::DELETE_FLAG_NO]);
    }
}

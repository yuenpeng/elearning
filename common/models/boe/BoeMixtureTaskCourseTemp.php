<?php

namespace common\models\boe;

use common\models\learning\LnCourse;
use Yii;

/**
 * This is the model class for table "{{%boe_mixture_task_course_temp}}".
 *
 * @property string $kid
 * @property string $task_type
 * @property string $temp_task_id
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
class BoeMixtureTaskCourseTemp extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_task_course_temp}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['temp_task_id', 'object_id'], 'required'],
            [['version'], 'integer'],
            [['kid', 'temp_task_id', 'object_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('common', 'Kid'),
            'task_type' => Yii::t('common', 'Task Type'),
            'temp_task_id' => Yii::t('common', 'Temp Task ID'),
            'object_id' => Yii::t('common', 'Object ID'),
            'version' => Yii::t('common', 'Version'),
            'created_by' => Yii::t('common', 'Created By'),
            'created_at' => Yii::t('common', 'Created At'),
            'created_from' => Yii::t('common', 'Created From'),
            'created_ip' => Yii::t('common', 'Created Ip'),
            'updated_by' => Yii::t('common', 'Updated By'),
            'updated_at' => Yii::t('common', 'Updated At'),
            'updated_from' => Yii::t('common', 'Updated From'),
            'updated_ip' => Yii::t('common', 'Updated Ip'),
            'is_deleted' => Yii::t('common', 'Is Deleted'),
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

<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_task_complete}}".
 *
 * @property string $kid
 * @property string $program_complete_id
 * @property string $group_id
 * @property string $program_id
 * @property string $program_enroll_id
 * @property string $user_id
 * @property string $task_id
 * @property string $complete_score
 * @property string $complete_grade
 * @property string $complete_status
 * @property string $is_passed
 * @property string $program_version
 * @property string $task_version
 * @property integer $end_at
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
class BoeMixtureTaskComplete extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_task_complete}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['program_complete_id', 'group_id', 'program_id', 'program_enroll_id', 'user_id', 'task_id', 'program_version', 'task_version'], 'required'],
            [['complete_score', 'complete_grade'], 'number'],
            [['end_at', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'program_complete_id', 'group_id', 'program_id', 'program_enroll_id', 'user_id', 'task_id', 'program_version', 'task_version', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['complete_status', 'is_passed', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'program_complete_id' => Yii::t('boe', 'Program Complete ID'),
            'group_id' => Yii::t('boe', 'Group ID'),
            'program_id' => Yii::t('boe', 'Program ID'),
            'program_enroll_id' => Yii::t('boe', 'Program Enroll ID'),
            'user_id' => Yii::t('boe', 'User ID'),
            'task_id' => Yii::t('boe', 'Task ID'),
            'complete_score' => Yii::t('boe', 'Complete Score'),
            'complete_grade' => Yii::t('boe', 'Complete Grade'),
            'complete_status' => Yii::t('boe', 'Complete Status'),
            'is_passed' => Yii::t('boe', 'Is Passed'),
            'program_version' => Yii::t('boe', 'Program Version'),
            'task_version' => Yii::t('boe', 'Task Version'),
            'end_at' => Yii::t('boe', 'End At'),
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
}

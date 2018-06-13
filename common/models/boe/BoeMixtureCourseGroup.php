<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_course_group}}".
 *
 * @property string $kid
 * @property string $group_name
 * @property string $description
 * @property string $owner_id
 * @property string $category_id
 * @property string $course_batch
 * @property integer $group_score
 * @property integer $complete_rule
 * @property integer $course_number
 * @property string $status
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
class BoeMixtureCourseGroup extends \common\base\BaseActiveRecord
{
    const GROUP_STATUS_USED = 1;
    const GROUP_STATUS_STOP = 2;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_course_group}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['group_name', 'owner_id', 'category_id', 'course_batch'], 'required'],
            [['description'], 'string'],
            [['group_score', 'complete_rule', 'course_number', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'group_name', 'owner_id', 'category_id', 'course_batch', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['status', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'group_name' => Yii::t('boe', 'Group Name'),
            'description' => Yii::t('boe', 'Description'),
            'owner_id' => Yii::t('boe', 'Owner ID'),
            'category_id' => Yii::t('boe', 'Category ID'),
            'course_batch' => Yii::t('boe', 'Course Batch'),
            'group_score' => Yii::t('boe', 'Group Score'),
            'complete_rule' => Yii::t('boe', 'Complete Rule'),
            'course_number' => Yii::t('boe', 'Course Number'),
            'status' => Yii::t('boe', 'Status'),
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

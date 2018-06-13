<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_course_temp}}".
 *
 * @property string $kid
 * @property string $course_id
 * @property string $course_name
 * @property string $course_type
 * @property integer $course_credit
 * @property integer $course_created_time
 * @property integer $course_open_status
 * @property string $course_batch
 * @property string $owner_id
 * @property string $user_id
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
class BoeMixtureCourseTemp extends \common\base\BaseActiveRecord
{
    const COURSE_IS_TASK = 0;
    const COURSE_IS_GROUP = 1;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_course_temp}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['course_id', 'course_name', 'course_credit', 'course_created_time', 'course_open_status', 'course_batch', 'owner_id', 'user_id'], 'required'],
            [['course_credit', 'course_created_time', 'course_open_status', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'course_id', 'course_batch', 'owner_id', 'user_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['course_name'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'course_id' => Yii::t('boe', 'Course ID'),
            'course_name' => Yii::t('boe', 'Course Name'),
            'course_type'=>Yii::t('boe','Course Type'),
            'course_credit' => Yii::t('boe', 'Course Credit'),
            'course_created_time' => Yii::t('boe', 'Course Created Time'),
            'course_open_status' => Yii::t('boe', 'Course Open Status'),
            'course_batch' => Yii::t('boe', 'Course Batch'),
            'owner_id' => Yii::t('boe', 'Owner ID'),
            'user_id' => Yii::t('boe', 'User ID'),
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

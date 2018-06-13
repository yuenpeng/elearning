<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_group_temp}}".
 *
 * @property string $kid
 *  * @property string $group_id
 * @property string $group_name
 * @property string $description
 * @property string $owner_id
 * @property string $course_batch
 * @property integer $group_score
 * @property integer $complete_rule
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
class BoeMixtureGroupTemp extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_group_temp}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['group_name', 'owner_id', 'course_batch','group_id'], 'required'],
            [['description'], 'string'],
            [['group_score', 'complete_rule', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid','owner_id', 'course_batch','group_id','created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
            [['group_name'],'string','max'=>150],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('common', 'Kid'),
            'group_id' => Yii::t('common', 'Group Id'),
            'group_name' => Yii::t('common', 'Group Name'),
            'description' => Yii::t('common', 'Description'),
            'owner_id' => Yii::t('common', 'Owner ID'),
            'course_batch' => Yii::t('common', 'Course Batch'),
            'group_score' => Yii::t('common', 'Group Score'),
            'complete_rule' => Yii::t('common', 'Complete Rule'),
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
}

<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_project_enroll}}".
 *
 * @property string $kid
 * @property string $program_id
 * @property string $user_id
 * @property string $enroll_type
 * @property string $enroll_user_id
 * @property integer $enroll_time
 * @property string $approved_state
 * @property string $approved_by
 * @property integer $approved_at
 * @property string $approved_reason
 * @property string $cancel_state
 * @property string $cancel_by
 * @property integer $cancel_at
 * @property string $cancel_reason
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
class BoeMixtureProjectEnroll extends \common\base\BaseActiveRecord
{
    //报名类型
    const ENROLL_TYPE_REG = '0'; /*注册*/
    const ENROLL_TYPE_ALLOW = '1'; /*成功*/
    const ENROLL_TYPE_ALTERNATE = '2'; /*候补*/
    const ENROLL_TYPE_DISALLOW = '3'; /*拒绝*/
    //审批状态
    const APPROVED_STATE_APPLING = '0'; /*申请中*/
    const APPROVED_STATE_APPROVED = '1'; /*审批同意*/
    const APPROVED_STATE_REJECTED = '2'; /*审批不同意*/
    const APPROVED_STATE_CANCELED = '3'; /*作废*/
    //取消状态
    const CANCEL_STATE_APPLING = '0'; /*申请中*/
    const CANCEL_STATE_APPROVED = '1'; /*审批同意*/
    const CANCEL_STATE_REJECTED = '2'; /*审批不同意*/
    const CANCEL_STATE_CANCELED = '3'; /*作废*/
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_project_enroll}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['program_id', 'user_id', 'enroll_user_id', 'enroll_time'], 'required'],
            [['enroll_time', 'approved_at', 'cancel_at', 'version', 'created_at', 'updated_at'], 'integer'],
            [['approved_reason', 'cancel_reason'], 'string'],
            [['kid', 'program_id', 'user_id', 'enroll_user_id', 'approved_by', 'cancel_by', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['enroll_type', 'approved_state', 'cancel_state', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'program_id' => Yii::t('boe', 'Program ID'),
            'user_id' => Yii::t('boe', 'User ID'),
            'enroll_type' => Yii::t('boe', 'Enroll Type'),
            'enroll_user_id' => Yii::t('boe', 'Enroll User ID'),
            'enroll_time' => Yii::t('boe', 'Enroll Time'),
            'approved_state' => Yii::t('boe', 'Approved State'),
            'approved_by' => Yii::t('boe', 'Approved By'),
            'approved_at' => Yii::t('boe', 'Approved At'),
            'approved_reason' => Yii::t('boe', 'Approved Reason'),
            'cancel_state' => Yii::t('boe', 'Cancel State'),
            'cancel_by' => Yii::t('boe', 'Cancel By'),
            'cancel_at' => Yii::t('boe', 'Cancel At'),
            'cancel_reason' => Yii::t('boe', 'Cancel Reason'),
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

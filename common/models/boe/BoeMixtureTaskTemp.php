<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_task_temp}}".
 *
 * @property string $kid
 * @property string $task_batch
 * @property integer $task_code
 * @property string $task_name
 * @property string $task_desc
 * @property string $is_group
 * @property double $task_score
 * @property double $complete_rule
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
class BoeMixtureTaskTemp extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_task_temp}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['task_batch', 'task_name', 'task_score', 'complete_rule'], 'required'],
            [['task_code', 'version', 'created_at', 'updated_at'], 'integer'],
            [['task_desc'], 'string'],
            [['task_score', 'complete_rule'], 'number'],
            [['kid', 'task_batch', 'task_name', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_group', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('common', 'Kid'),
            'task_batch' => Yii::t('common', 'Task Batch'),
            'task_code' => Yii::t('common', 'Task Num'),
            'task_name' => Yii::t('common', 'Task Name'),
            'task_desc' => Yii::t('common', 'Task Desc'),
            'is_group' => Yii::t('common', 'Is Group'),
            'task_score' => Yii::t('common', 'Task Score'),
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

    /*
 * 设置任务编号
 * 规则：日期+sprintf("%03d", $count);
 * @param string $courseId
 * @return string
 */
    public static function setTaskCode($kid=""){
        if (!empty($kid)){
            $info = BoeMixtureTaskTemp::findOne($kid);
            return $info->task_code;
        }
        $start_at = strtotime(date('Y-m-d'));
        $end_at = $start_at+86399;
        $count = BoeMixtureProjectTask::find()->where("created_at>".$start_at)->andWhere("created_at<".$end_at)->count();
        $count = $count+1;/*默认从1开始*/
        return date('Ymd').sprintf("%03d", $count);
    }
}

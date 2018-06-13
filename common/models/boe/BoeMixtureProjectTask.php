<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_project_task}}".
 *
 * @property string $kid
 * @property string $project_id
 * @property integer $task_code
 * @property string $task_name
 * @property string $task_desc
 * @property string $is_group
 * @property integer $end_time
 * @property double $task_score
 * @property double $complete_rule
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
class BoeMixtureProjectTask extends \common\base\BaseActiveRecord
{
    const TASK_IS_COURSE = "0";
    const TASK_IS_GROUP = "1";
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_project_task}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['project_id', 'task_name'], 'required'],
            [['task_code', 'version', 'created_at', 'updated_at','end_time'], 'integer'],
            [['task_desc'], 'string'],
            [['task_score', 'complete_rule'], 'number'],
            [['kid', 'project_id', 'task_name', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_group', 'status', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'project_id' => Yii::t('boe', 'Project ID'),
            'task_code' => Yii::t('boe', 'Task Code'),
            'task_name' => Yii::t('boe', 'Task Name'),
            'task_desc' => Yii::t('boe', 'Task Desc'),
            'is_group' => Yii::t('boe', 'Is Group'),
            'end_time'=>Yii::t('boe','end time'),
            'task_score' => Yii::t('boe', 'Task Score'),
            'complete_rule' => Yii::t('boe', 'Complete Rule'),
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

    /*
* 设置任务编号
* 规则：日期+sprintf("%03d", $count);
* @param string $courseId
* @return string
*/
    public static function setTaskCode($kid=""){
        if (!empty($kid)){
            $info = BoeMixtureProjectTask::findOne($kid);
            return $info->task_code;
        }
        $start_at = strtotime(date('Y-m-d'));
        $end_at = $start_at+86399;
        $count = BoeMixtureProjectTask::find()->where("created_at>".$start_at)->andWhere("created_at<".$end_at)->count();
        $count = $count+1;/*默认从1开始*/
        return date('Ymd').sprintf("%03d", $count);
    }

}

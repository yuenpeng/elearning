<?php

namespace common\models\boe;

use Yii;

/**
 * This is the model class for table "{{%boe_mixture_project_certification}}".
 *
 * @property string $kid
 * @property string $certification_id
 * @property string $program_id
 * @property string $get_condition
 * @property string $certification_price
 * @property string $status
 * @property integer $start_at
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
class BoeMixtureProjectCertification extends \common\base\BaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_mixture_project_certification}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['certification_id', 'program_id', 'start_at'], 'required'],
            [['start_at', 'end_at', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'certification_id', 'program_id', 'get_condition', 'certification_price', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
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
            'certification_id' => Yii::t('boe', 'Certification ID'),
            'program_id' => Yii::t('boe', 'Program ID'),
            'get_condition' => Yii::t('boe', 'Get Condition'),
            'certification_price' => Yii::t('boe', 'Certification Price'),
            'status' => Yii::t('boe', 'Status'),
            'start_at' => Yii::t('boe', 'Start At'),
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

    /**
     * 添加项目证书
     */
    public function addRelation(BoeMixtureProject $project, $certification_id){
        if (empty($certification_id)) return ;
        $result = $this->findOne(['program_id'=>$project->kid, 'certification_id'=>$certification_id],false);
        $model = $result ? BoeMixtureProjectCertification::findOne($result->kid) : $model = new BoeMixtureProjectCertification();
        $model->status = self::STATUS_FLAG_NORMAL;
        $model->start_at = $project->start_time;
        $model->end_at = $project->end_time;
        if ($result){
            $model->update();
        }else{
            $model->program_id = $project->kid;
            $model->certification_id = $certification_id;
            $model->save();
        }
    }
    /**
     * 停用课程证书关系
     */
    public function stopRelation($project_id){
        $attributes = ['status'=>self::STATUS_FLAG_STOP];
        $condition = "program_id=:program_id";
        $param = [
            ':program_id'=>$project_id
        ];
        $this->updateAll($attributes,$condition,$param);
    }
}

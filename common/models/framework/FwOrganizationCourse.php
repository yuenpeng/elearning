<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2017/12/14
 * Time: 11:18
 */

namespace common\models\framework;

use common\base\BaseActiveRecord;
use common\models\learning\LnCourse;
use Yii;
use yii\db\ActiveRecord;

class FwOrganizationCourse extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public $course_id;
    public $kid;
    public $course_name;
    public $orgnization_id;
    public $orgnization_name;
    public $created_at;
    public $created_from;
    public $state;
    public $created_ip;


    public static function tableName()
    {
        return '{{%fw_organization_course}}';
    }

    /**
     * @inheritdoc
     * course_id
     * course_name
     * orgnization_id
     * orgnizetion_name
     *
     */
    public function rules()
    {
        return [
            [['kid','course_id','orgnization_id', 'orgnization_name','course_name'], 'required'],
            [['created_at'], 'integer'],
            [['course_id','orgnization_id'], 'string', 'max' => 50],
            [['created_from'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid'=>Yii::t('common','kid'),
            'course_id'=>Yii::t('common','course_id'),
            'course_name'=>Yii::t('common','course_name'),
            'orgnization_id'=>Yii::t('common','orgnization_id'),
            'orgnization_name'=>Yii::t('common','orgnization_name'),
            'state'=>Yii::t('common','state_source'),
            'created_by' => Yii::t('common', 'created_by'),
            'created_at' => Yii::t('common', 'created_at'),
            'created_from' => Yii::t('common', 'created_from'),
            'updated_by' => Yii::t('common', 'updated_by'),
            'updated_at' => Yii::t('common', 'updated_at'),
            'updated_from' => Yii::t('common', 'updated_from'),
            'is_deleted' => Yii::t('common', 'is_deleted'),
        ];
    }

    /**
     * 获取用户可学习的课程
     * @param $org_id
     */

    public function getUserCourse($org_id,$state){
        return $this->find()->andWhere(['status'=>0])
            ->andFilterWhere(['state'=>$state])
            ->andFilterWhere(['orgnization_id'=>$org_id])
            ->select('course_id')
            ->orderBy('sort DESC')
            ->asArray()
            ->all();
    }
    /**
     * 获取学员需要学习的所有的课程
     */
    public function getUserAllCourse($org_id){
        return $this->find()->andWhere(['status'=>0])
            ->andFilterWhere(['orgnization_id'=>$org_id])
            ->select('course_id,state')
            ->orderBy('sort DESC')
            ->asArray()->all();
    }

}
<?php
namespace common\models\boe;
use common\base\BaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeEnterprise extends BaseActiveRecord{

    public static function tableName(){
        return 'eln_boe_enterprise';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('common', 'kid'),
            'enterprise_code' =>  Yii::t('boe', 'enterprise_code'),
            'enterprise_name' => Yii::t('boe', 'enterprise_name'),
            'enterprise_type' => Yii::t('boe', 'enterprise_type'),
            'hrbp_no' => Yii::t('boe', 'hrbp_no'),
            'hrbp_name' => Yii::t('boe', 'hrbp_name'),
            'status' => Yii::t('common', 'status'),
            'is_oa' => Yii::t('common', 'is_oa'),
            'version' => Yii::t('common', 'version'),
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
     * @inheritdoc
     */
    public function rules() {
        return [
            [['enterprise_code','enterprise_name','enterprise_type','hrbp_no'], 'required', 'on' => 'manage'],
            [['enterprise_code','enterprise_name'], 'string','max'=>30],
            [['hrbp_name','hrbp_no'], 'string'],
            [[ 'enterprise_type','version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['status'], 'string', 'max' => 1],
            [['status'], 'in', 'range' => [self::STATUS_FLAG_TEMP, self::STATUS_FLAG_NORMAL, self::STATUS_FLAG_STOP]],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }
    
    public function getEnterpriseTypeText()
    {
        $single = $this->enterprise_type;
        if ($single == 1)
            return Yii::t('boe', 'type_out');
        else
            return Yii::t('boe', 'type_inner');
    }
}
?>
<?php
namespace common\models\boe;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeGrowUser extends BoeBaseActiveRecord {
	

    public static function tableName(){
		return 'eln_boe_grow_user';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'year' => Yii::t('common', '年份'),
            'orgnization_id' => Yii::t('common', 'orgnization_id'),
            'organizer' => Yii::t('common', '组织者'),
            'user_no' => Yii::t('common', 'user_no'),
            'id_number' => Yii::t('common', 'id_number'),
            'real_name' => Yii::t('common', 'real_name'),
            'step1_score' => Yii::t('common', '职场准备学分'),
            'step2_score' => Yii::t('common', '特训营学分'),
            'step3_score' => Yii::t('common', '入模培训学分'),
            'step4_score' => Yii::t('common', '职场进阶学分'),
            'step4_other' => Yii::t('common', '其他'),
            'shifu_id' => Yii::t('common', '师父ID'),
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
            [['year','user_no','id_number','real_name','step1_score','step2_score'], 'required'],
            [['user_no','id_number','real_name'], 'string'],
            [[ 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
            [['user_no'],'unique'],
            [['step3_score','step4_score','step4_other'], 'default', 'value'=> 0],
 
        ];

	}

	public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
		return $this->CommonGetInfo($id, $key, $create_mode, $debug);
	}

	public function saveInfo($data, $debug = 0) {
		return $this->CommonSaveInfo($data, $debug);
	}

	public function deleteInfo($id = 0) {
		return $this->CommonDeleteInfo($id);
	}
}
?>
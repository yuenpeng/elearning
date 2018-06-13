<?php
namespace common\models\train;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeCourseAdditional extends BoeBaseActiveRecord{

	public static function tableName(){
		return 'elearninglms2.eln_ln_course_additional';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'course_id' => '课程编号',
            'learning_form' => '学习形式',
            'training_type' => '培训类型',
            'training_fees_public' => '培训费用-公费',
            'training_costs_own' => '培训费用-自费',
            'training_form' => '培训形式',
            'training_institution' => '培训机构',
            'training_organization_unit' => '培训组织单位',
            'mandatory_level' => '选修必修',
            'training_area' => '培训所在区域',
            'training_country' => '培训机构国别',
            'remarks' => '备注',
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
            [['course_id'], 'required'],
            [['training_fees_public','training_costs_own'], 'number'],
            [[ 'version', 'created_at', 'updated_at'], 'integer'],
            [['training_institution','training_organization_unit'], 'string', 'max' => 100],
            [['kid','course_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip','training_country'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];

	}

     public function getAll($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $sult = $this->find(false)->orderBy('created_at')->asArray()->indexBy($this->tablePrimaryKey)->all();
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
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
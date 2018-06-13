<?php
namespace common\models\train;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeCourseReg extends BoeBaseActiveRecord{
    const REG_TYPE_SELF = 'self';
    const REG_TYPE_POS = 'pos';
    const REG_TYPE_ORG = 'org';
    const REG_TYPE_MANAGER = 'manager';

    const REG_STATE_APPLING = '0';
    const REG_STATE_APPROVED = '1';
    const REG_STATE_REJECTED = '2';
    const REG_STATE_CANCELED = '3';

	public static function tableName(){
		return 'elearninglms2.eln_ln_course_reg';
	}

	public function rules(){
        return [
            [['course_id', 'user_id', 'reg_time', 'reg_type', 'reg_state'], 'required'],
            [['reg_time', 'created_at', 'updated_at','approved_at'], 'integer'],
            [['kid', 'course_id', 'user_id', 'sponsor_id', 'reg_type', 'created_by', 'updated_by','approved_by'], 'string', 'max' => 50],
            [['reg_state', 'is_deleted'], 'string', 'max' => 1],
            [['created_from','updated_from'], 'string', 'max' => 50],

            [['reg_state'], 'string', 'max' => 1],
            [['reg_state'], 'in', 'range' => [self::REG_STATE_APPLING, self::REG_STATE_APPROVED, self::REG_STATE_REJECTED, self::REG_STATE_CANCELED]],
            [['reg_state'], 'default', 'value'=> self::REG_STATE_APPLING],

            [['version'], 'number'],
            [['version'], 'default', 'value'=> 1],

            [['is_deleted'], 'string', 'max' => 1],
            [['is_deleted'], 'in', 'range' => [self::DELETE_FLAG_NO, self::DELETE_FLAG_YES]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('common', 'kid'),
            'course_id' => Yii::t('common', 'course_id'),
            'user_id' => Yii::t('common', 'user_id'),
            'sponsor_id' => Yii::t('common', 'sponsor_id'),
            'reg_time' => Yii::t('common', 'reg_time'),
            'reg_type' => Yii::t('common', 'reg_type'),
            'reg_state' => Yii::t('common', 'reg_state'),
            'approved_by' => Yii::t('common', 'approved_by'),
            'approved_at' => Yii::t('common', 'approved_at'),
            'version' => Yii::t('common', 'version'),
            'created_by' => Yii::t('common', 'created_by'),
            'created_at' => Yii::t('common', 'created_at'),
            'created_from' => Yii::t('common', 'created_from'),
            'updated_by' => Yii::t('common', 'updated_by'),
            'updated_at' => Yii::t('common', 'updated_at'),
            'updated_from' => Yii::t('common', 'updated_from'),
            'is_deleted' => Yii::t('common', 'is_deleted'),
            'is_edited'=>'is_edited'
        ];
    }

     public function getAll($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $sult = $this->find(false)->orderBy('created_at')->asArray()->where(array('is_deleted'=>0))->indexBy($this->tablePrimaryKey)->all();
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
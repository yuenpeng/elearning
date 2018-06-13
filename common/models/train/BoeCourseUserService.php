<?php
namespace common\models\train;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeCourseUserService extends BoeBaseActiveRecord{

	public static function tableName(){
		return 'elearninglms2.eln_ln_course_user_service';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'course_id' => '课程编号',
            'user_id' => '学员编号',
            'service_period_agreement' => '是否存在服务期',
            'service_start_time' => '服务期开始时间',
            'service_end_time' => '服务期结束时间',
            'service_life' => '服务年限',
            'service_penalty' => '服务期违约金',
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
            [['course_id','user_id','service_period_agreement'], 'required'],
            [[ 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid','course_id','user_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
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
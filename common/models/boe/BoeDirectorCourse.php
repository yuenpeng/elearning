<?php
namespace common\models\boe;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

class BoeDirectorCourse extends BoeBaseActiveRecord {
	

    public static function tableName(){
		return 'eln_boe_director_course';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'year' => Yii::t('common', '年份'),
            'course_id' => Yii::t('common', '分类'),
            'course_id' => Yii::t('common', '课程编号'),
            'course_name' => Yii::t('common', '课程名称'),
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
            [['course_id','course_name','year','fenlei'], 'required'],
            [[ 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
            // [['course_id','year'],'unique']
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
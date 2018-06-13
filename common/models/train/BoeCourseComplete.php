<?php
namespace common\models\train;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeCourseComplete extends BoeBaseActiveRecord{
    const COMPLETE_STATUS_NOTSTART = '0';
    const COMPLETE_STATUS_DOING = '1';
    const COMPLETE_STATUS_DONE = '2';

    const COMPLETE_TYPE_PROCESS = '0';
    const COMPLETE_TYPE_FINAL = '1';
    const COMPLETE_TYPE_BACKUP = '2';

    const IS_PASSED_NO = "0";
    const IS_PASSED_YES = "1";

    const IS_DIRECT_COMPLETED_NO = "0";
    const IS_DIRECT_COMPLETED_YES = "1";

    const IS_RETAKE_NO = "0";
    const IS_RETAKE_YES = "1";

    const COMPLETE_METHOD_MASTER = "0";//掌握通过
    const COMPLETE_METHOD_COMPLETE = "1";//完成通过

	public static function tableName(){
		return 'elearninglms2.eln_ln_course_complete';
	}

	   /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'course_id', 'complete_status'], 'required'],
            [['complete_grade','complete_score','real_score','complete_score_last','real_score_last','complete_grade_last'], 'number'],
            [['created_at', 'updated_at', 'start_at', 'end_at' ,'last_record_at', 'learning_duration','attempt_number',
                'start_at_last', 'end_at_last' ,'last_record_at_last', 'learning_duration_last','attempt_number_last'], 'integer'],
            [['kid','course_version', 'course_version_last', 'user_id', 'course_id', 'course_reg_id', 'course_version', 'created_by', 'updated_by'], 'string', 'max' => 50],
            [['created_from','updated_from'], 'string', 'max' => 50],

            [['complete_status'], 'string', 'max' => 1],
            [['complete_status'], 'in', 'range' => [self::COMPLETE_STATUS_NOTSTART, self::COMPLETE_STATUS_DOING, self::COMPLETE_STATUS_DONE]],
            [['complete_status'], 'default', 'value'=> self::COMPLETE_STATUS_NOTSTART],

            [['is_passed','is_passed_last'], 'string', 'max' => 1],
            [['is_passed','is_passed_last'], 'in', 'range' => [self::IS_PASSED_NO, self::IS_PASSED_YES]],
            [['is_passed','is_passed_last'], 'default', 'value'=> self::IS_PASSED_NO],

            [['is_direct_completed','is_direct_completed_last'], 'string', 'max' => 1],
            [['is_direct_completed','is_direct_completed_last'], 'in', 'range' => [self::IS_DIRECT_COMPLETED_NO, self::IS_DIRECT_COMPLETED_YES]],
            [['is_direct_completed','is_direct_completed_last'], 'default', 'value'=> self::IS_DIRECT_COMPLETED_NO],

            [['complete_type'], 'string', 'max' => 1],
            [['complete_type'], 'in', 'range' => [self::COMPLETE_TYPE_PROCESS, self::COMPLETE_TYPE_FINAL, self::COMPLETE_TYPE_BACKUP]],
            [['complete_type'], 'default', 'value'=> self::COMPLETE_TYPE_PROCESS],

            [['complete_method'], 'in', 'range' => [self::COMPLETE_METHOD_COMPLETE, self::COMPLETE_METHOD_MASTER]],
            [['complete_method'], 'default', 'value'=> self::COMPLETE_METHOD_COMPLETE],

            [['is_retake'], 'string', 'max' => 1],
            [['is_retake'], 'in', 'range' => [self::IS_RETAKE_NO, self::IS_RETAKE_YES]],
            [['is_retake'], 'default', 'value'=> self::IS_RETAKE_NO],

            [['is_noshow'], 'string', 'max' => 1],
            [['is_noshow'], 'in', 'range' => [self::NO, self::YES]],
            [['is_noshow'], 'default', 'value'=> self::NO],

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
            'user_id' => Yii::t('common', 'user_id'),
            'course_id' => Yii::t('common', 'course_id'),
            'course_reg_id' => Yii::t('common', 'course_reg_id'),
            'complete_status' => Yii::t('common', 'complete_status'),
            'complete_grade' => Yii::t('common', 'complete_grade'),
            'complete_score' => Yii::t('common', 'complete_score'),
            'real_score' => Yii::t('common', 'real_score'),
            'complete_grade_last' => Yii::t('common', 'complete_grade_last'),
            'complete_score_last' => Yii::t('common', 'complete_score_last'),
            'real_score_last' => Yii::t('common', 'real_score_last'),
            'complete_type' => Yii::t('common', 'complete_type'),
            'complete_method' => Yii::t('common', 'complete_method'),
            'is_passed' => Yii::t('common', 'is_passed'),
            'is_direct_completed' => Yii::t('common', 'is_direct_completed'),
            'course_version' => Yii::t('common', 'course_version'),
            'start_at' => Yii::t('common', 'start_at'),
            'end_at' => Yii::t('common', 'end_at'),
            'last_record_at' => Yii::t('common', 'last_record_at'),
            'learning_duration' => Yii::t('common', 'learning_duration'),
            'attempt_number' => Yii::t('common', 'attempt_number'),
            'is_noshow' => Yii::t('common', 'is_noshow'),
            'is_direct_completed_last' => Yii::t('common', 'is_direct_completed_last'),
            'is_passed_last' => Yii::t('common', 'is_passed_last'),
            'course_version_last' => Yii::t('common', 'course_version_last'),
            'start_at_last' => Yii::t('common', 'start_at_last'),
            'end_at_last' => Yii::t('common', 'end_at_last'),
            'last_record_at_last' => Yii::t('common', 'last_record_at_last'),
            'learning_duration_last' => Yii::t('common', 'learning_duration_last'),
            'attempt_number_last' => Yii::t('common', 'attempt_number_last'),
            'complete_method_last' => Yii::t('common', 'complete_method_last'),
            'is_retake' => Yii::t('common', 'is_retake'),
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
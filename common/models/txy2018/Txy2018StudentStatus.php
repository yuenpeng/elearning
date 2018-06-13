<?php

namespace common\models\txy2018;

use common\models\boe\BoeBaseActiveRecord;
use Yii;

/**
 * This is the model class for table "eln_txy2018_student_status".
 *
 * @property string $kid
 * @property string $investigator_id
 * @property string $investigator_date
 * @property string $student_id
 * @property string $orgnization_id
 * @property string $version
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
class Txy2018StudentStatus extends BoeBaseActiveRecord {

    private $allInfo = NULL;

    /**
     * @inheritdoc
     */

    public static function tableName() {
        return 'eln_txy2018_student_status';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['student_id','orgnization_id'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'student_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return array(
            'kid' 				=> Yii::t('txy', 'txy_student_status_kid'),
			'student_id' 		=> Yii::t('txy', 'txy_student_status_student_id'),
			'orgnization_id' 	=> Yii::t('txy', 'txy_student_status_orgnization_id'),
			'object_type' 		=> Yii::t('txy', 'txy_student_status_object_type'),
			'body_status' 		=> Yii::t('txy', 'txy_student_status_body_status'),
			'mood_status' 		=> Yii::t('txy', 'txy_student_status_mood_status'),
			'investigator_date' 	=> Yii::t('txy', 'txy_student_status_investigator_date'),
			'investigator_id' 	=> Yii::t('txy', 'txy_student_status_investigator_id'),
            'version' 		=> Yii::t('common', 'version'),
            'created_by' 	=> Yii::t('common', 'created_by'),
            'created_at' 	=> Yii::t('common', 'created_at'),
            'created_from' 	=> Yii::t('common', 'created_from'),
            'updated_by' 	=> Yii::t('common', 'updated_by'),
            'updated_at' 	=> Yii::t('common', 'updated_at'),
            'updated_from'	=> Yii::t('common', 'updated_from'),
            'is_deleted' 	=> Yii::t('common', 'is_deleted'),
        );
    }
	
	/**
     * 获取列表
     * @param investigator_date $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,student_id,orgnization_id,object_type,body_status,mood_status,investigator_id,investigator_date'
            . ',created_at,created_by,updated_by,updated_at';
        }
        $sult = parent::getList($params);
        $tmp_arr = NULL;
        if (isset($sult['totalCount'])) {
            if ($sult['list']) {
                $tmp_arr = &$sult['list'];
            }
        } else {
            $tmp_arr = &$sult;
        }
        if ($tmp_arr) {
            foreach ($tmp_arr as $key => $a_info) {//整理出关键信息
                $tmp_arr[$key] = $this->parseKeywordToString($a_info);
            }
        }
        //  BoeBase::debug($sult,1);
        return $sult;
    }

    /**
     * getInfo
     * 根据ID获取的详细或是某个字段的信息
     * @param investigator_date $id 课程配置的ID
     * @param investigator_date $key 
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        return $this->CommonGetInfo($id, $key, $create_mode, $debug);
    }

    public function saveInfo($data, $debug = 0) {
        return $this->CommonSaveInfo($data, $debug);
    }

    /**
     * deleteInfo 
     * 根据ID删除单个信息，
     * @param investigator_date $id
     * @return int 删除结果如下
     * 1=成功
     * -1=信息不存在了
     * -2=数据库操作失败
     */
    public function deleteInfo($id = 0) {
        return $this->CommonDeleteInfo($id);
    }
	
}

<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_subject_habit_user".
 * @property string $kid
 * @property string $user_kid
 * @property string $user_real_name
 * @property string $user_no
 * @property string $user_id_no
 * @property string $t_kid
 * @property string $is_test
 * @property string $t_orgnization_id
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
class BoeSubjectHabitUser extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    /**
     * @xinpeng
     */
    public static function tableName() {
        return 'eln_boe_subject_habit_user';
    }

    /**
     * @xinpeng
     */
    public function rules() {
        return [
            [['user_kid','user_real_name','user_no','user_id_no','t_kid','t_orgnization_id'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid','user_kid','t_kid','t_orgnization_id','user_real_name', 'user_no','user_id_no', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted','is_test'], 'string', 'max' => 1]
        ];
    }

    /**
     * @xinpeng
     */
    public function attributeLabels() {
        return [
            'kid' 				=> Yii::t('boe', 'habit_user_kid'),
			'user_kid' 			=> Yii::t('boe', 'habit_user_user_kid'),
			'user_real_name' 	=> Yii::t('boe', 'habit_user_user_real_name'),
            'user_no' 			=> Yii::t('boe', 'habit_user_user_no'),
            'user_id_no' 		=> Yii::t('boe', 'habit_user_user_id_no'),
			't_kid' 			=> Yii::t('boe', 'habit_user_t_kid'),
			'is_test' 			=> Yii::t('boe', 'habit_user_is_test'),
			't_orgnization_id' 	=> Yii::t('boe', 'habit_user_t_orgnization_id'),
            'version' 			=> Yii::t('common', 'version'),
            'created_by' 		=> Yii::t('common', 'created_by'),
            'created_at' 		=> Yii::t('common', 'created_at'),
            'created_from' 		=> Yii::t('common', 'created_from'),
            'updated_by' 		=> Yii::t('common', 'updated_by'),
            'updated_at' 		=> Yii::t('common', 'updated_at'),
            'updated_from' 		=> Yii::t('common', 'updated_from'),
            'is_deleted' 		=> Yii::t('common', 'is_deleted'),
        ];
    }

    /**
     * 获取列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,user_kid,user_real_name,user_no,user_id_no,t_kid,is_test,t_orgnization_id'
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
     * 根据ID获取详细或是某个字段的信息
     * @param type $id 日志群的ID
     * @param type $key 
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
     * @param type $id
     * @return int 删除结果如下
     * 1=成功
     * -1=信息不存在了
     * -2=数据库操作失败
     */
    public function deleteInfo($id = 0) {
        return $this->CommonDeleteInfo($id);
    }

}

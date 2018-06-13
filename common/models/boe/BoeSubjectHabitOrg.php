<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_subject_habit_org".
 * @property string $kid
 * @property string $t_orgnization_id
 
 * @property integer $count_time
 * @property integer $gold_num
 * @property number $gold_average_num
 * @property number $study_time
 * @property integer $gold_num2
 * @property number $gold_average_num2
 * @property number $study_time2
 * @property integer $is_publish
 
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
class BoeSubjectHabitOrg extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    /**
     * @xinpeng
     */
    public static function tableName() {
        return 'eln_boe_subject_habit_org';
    }

    /**
     * @xinpeng
     */
    public function rules() {
        return [
            [['t_orgnization_id'], 'required'],
            [['gold_num','gold_num2','is_publish','version', 'created_at', 'updated_at'], 'integer'],
            [['kid','t_orgnization_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
			[['study_time','gold_average_num','study_time2','gold_average_num2'], 'number'],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @xinpeng
     */
    public function attributeLabels() {
        return [
            'kid' 				=> Yii::t('boe', 'habit_org_kid'),
			't_orgnization_id' 	=> Yii::t('boe', 'habit_org_t_orgnization_id'),
            'gold_num' 			=> Yii::t('boe', 'habit_org_gold_num'),
			'gold_average_num' 	=> Yii::t('boe', 'habit_org_gold_average_num'),
            'study_time' 		=> Yii::t('boe', 'habit_org_study_time'),
			'gold_num2' 		=> Yii::t('boe', 'habit_org_gold_num2'),
			'gold_average_num2' => Yii::t('boe', 'habit_org_gold_average_num2'),
            'study_time2' 		=> Yii::t('boe', 'habit_org_study_time2'),
			'is_publish' 		=> Yii::t('boe', 'habit_org_is_publish'),
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
            $params['select'] = 'kid,t_orgnization_id,gold_num,gold_average_num,study_time,gold_num2,gold_average_num2,study_time2,is_publish'
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

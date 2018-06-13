<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_enroll_flow".
 *
 * @property string $kid
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
class BoeEnrollFlow extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    /**
     * @xinpeng
     */
    public static function tableName() {
        return 'eln_boe_enroll_flow';
    }

    /**
     * @xinpeng
     */
    public function rules() {
        return [
            [['enroll_id'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 1024],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @xinpeng
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'boe_ef_kid'),
            'enroll_id' => Yii::t('boe', 'boe_ef_enroll_id'),
			'oa_confirm' => Yii::t('boe', 'boe_ef_oa_confirm'),
			'oa_no' => Yii::t('boe', 'boe_ef_oa_no'),
			'oa_mark' => Yii::t('boe', 'boe_ef_oa_mark'),
			'oa_by' => Yii::t('boe', 'boe_ef_oa_by'),
			'oa_at' => Yii::t('boe', 'boe_ef_oa_at'),
			'enroll_status_code' => Yii::t('boe', 'boe_ef_enroll_status_code'),
			'enroll_mark' => Yii::t('boe', 'boe_ef_enroll_mark'),
			'base_confirm' => Yii::t('boe', 'boe_ef_base_confirm'),
			'base_mark' => Yii::t('boe', 'boe_ef_base_mark'),
			'base_by' => Yii::t('boe', 'boe_ef_base_by'),
			'base_at' => Yii::t('boe', 'boe_ef_base_at'),
			'is_voucher' => Yii::t('boe', 'boe_ef_is_voucher'),
			'voucher_pic' => Yii::t('boe', 'boe_ef_voucher_pic'),
			'voucher_confirm' => Yii::t('boe', 'boe_ef_voucher_confirm'),
			'voucher_at' => Yii::t('boe', 'boe_ef_voucher_at'),
			'voucher_by' => Yii::t('boe', 'boe_ef_voucher_by'),
			'voucher_mark' => Yii::t('boe', 'boe_ef_voucher_mark'),
			'approval_confirm' => Yii::t('boe', 'boe_ef_approved_confirm'),
			'approved_by' => Yii::t('boe', 'boe_ef_approved_by'),
			'approved_at' => Yii::t('boe', 'boe_ef_approved_at'),
			'approved_mark' => Yii::t('boe', 'boe_ef_approved_mark'),
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
     * 获取列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,enroll_id,oa_no,oa_mark,base_confirm,base_mark,base_by,base_at,is_voucher,voucher_pic,voucher_confirm,voucher_at,voucher_by,voucher_mark,approved_confirm,approved_by,approved_at,approved_mark'
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

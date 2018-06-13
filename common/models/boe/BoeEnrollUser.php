<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_enroll_user".
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
class BoeEnrollUser extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    /**
     * @xinpeng
     */
    public static function tableName() {
        return 'eln_boe_enroll_user';
    }

    /**
     * @xinpeng
     */
    public function rules() {
        return [
            [['enroll_id','user_id'], 'required'],
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
            'kid' => Yii::t('boe', 'boe_ec_kid'),
            'enroll_id' => Yii::t('boe', 'boe_ec_enroll_id'),
			'course_id' => Yii::t('boe', 'boe_ec_course_id'),
			'course_name' => Yii::t('boe', 'boe_ec_course_name'),
			'user_id' => Yii::t('boe', 'boe_ec_user_id'),
            'user_no' => Yii::t('boe', 'boe_ec_user_no'),
			'real_name' => Yii::t('boe', 'boe_ec_real_name'),
			'organization_name' => Yii::t('boe', 'boe_ec_organization_name'),
			'organization_name_path' => Yii::t('boe', 'boe_ec_organization_name_path'),
			'organization_tx' => Yii::t('boe', 'boe_ec_organization_tx'),
			'organization_zz' => Yii::t('boe', 'boe_ec_organization_zz'),
			'position_name' => Yii::t('boe', 'boe_ec_position_name'),
			'email' => Yii::t('boe', 'boe_ec_email'),
			'mobile_no' => Yii::t('boe', 'boe_ec_mobile_no'),
			'invoice_id' => Yii::t('boe', 'boe_ec_invoice_id'),
			'invoice_place' => Yii::t('boe', 'boe_ec_invoice_place'),
			'hrbp_user_id' => Yii::t('boe', 'boe_ec_hrbp_user_id'),
			'hrbp_user_no' => Yii::t('boe', 'boe_ec_hrbp_user_no'),
			'hrbp_user_name' => Yii::t('boe', 'boe_ec_hrbp_user_name'),
			'hrbp_email' => Yii::t('boe', 'boe_ec_hrbp_email'),
			'expense' => Yii::t('boe', 'boe_ec_expense'),
			'charge_status' => Yii::t('boe', 'boe_ec_charge_status'),
			'is_charge' => Yii::t('boe', 'boe_ec_is_charge'),
			'charge_id' => Yii::t('boe', 'boe_ec_charge_id'),
			'detail_id' => Yii::t('boe', 'boe_ec_detail_id'),
			'charge_apply_code' => Yii::t('boe', 'boe_ec_charge_apply_code'),
			'charge_apply_num' => Yii::t('boe', 'boe_ec_charge_apply_num'),
			'enroll_time' => Yii::t('boe', 'boe_ec_enroll_time'),
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
            $params['select'] = 'kid,enroll_id,course_id,course_name,user_id,user_no,real_name,organization_name,organization_name_path,position_name,invoice_id,invoice_place,hrbp_user_id,hrbp_user_no,hrbp_user_name,hrbp_email,expense,charge_status,is_charge,charge_id,detail_id,charge_apply_code,charge_apply_num,enroll_time'
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
     * @param type $id 日志群成员的ID
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

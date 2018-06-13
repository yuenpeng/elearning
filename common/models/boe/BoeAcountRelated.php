<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_acount_related".
 *
 * @property string  $kid
 * @property string  $user_no
 * @property integer $id_number
 * @property integer $mobile_no
 * @property integer $email
 * @property integer $version
 * @property string  $created_by
 * @property integer $created_at
 * @property string  $created_from
 * @property string  $created_ip
 * @property string  $updated_by
 * @property integer $updated_at
 * @property string  $updated_from
 * @property string  $updated_ip
 * @property string  $is_deleted
 */
class BoeAcountRelated extends BoeBaseActiveRecord {

    protected $hasKeyword = true;

    const ACOUNT_TYPE_NULL = "0"; //默认未设置
    const ACOUNT_TYPE_USER_NO = "1"; //默认账号-工号
    const ACOUNT_TYPE_ID_NUMBER = "2"; //默认账号-身份证
    const ACOUNT_TYPE_PHONE = "3"; //默认账号-手机号码
    const ACOUNT_TYPE_EMAIL = "4"; //默认账号-邮箱

    /**
     * @songsang
     */
    public static function tableName() {
        return 'eln_boe_acount_related';
    }

    /**
     * @songsang
     */
    public function rules() {
        return [
            [['user_no'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @songsang
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'account_kid'),
            'user_no' => Yii::t('boe', 'account_user_no'),
            'id_number' => Yii::t('boe', 'account_id_number'),
            'mobile_no' => Yii::t('boe', 'account_mobile_no'),
            'email' => Yii::t('boe', 'account_email'),
            'account_default' => Yii::t('boe', 'account_defaul'),
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
     * @param type $id 留言的ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        return $this->CommonGetInfo($id, $key, $create_mode, $debug);
    }

    public function saveInfo($data, $debug = 0) {
        return $this->CommonSaveInfo($data, $debug);
    }
    /**
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

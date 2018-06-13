<?php

namespace common\models\txy2018;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_txy2018_manager".
 *
 * @property string $kid
 * @property string $organization_id
 * @property string $user_id
 * @property integer $level
 * @property string $mark
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
class Txy2018Manager extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_txy2018_manager';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        /*return [
            [['organization_id','user_id','level'], 'required'],
            [['level','version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'organization_id','user_id','mark', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1],
        ];*/
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' 				=> Yii::t('txy', 'txy_manager_kid'),
			'organization_id' 	=> Yii::t('txy', 'txy_manager_organization_id'),
			'user_id' 			=> Yii::t('txy', 'txy_manager_user_id'),
            'level' 			=> Yii::t('txy', 'txy_manager_level'),
            'mark' 				=> Yii::t('txy', 'txy_manager_mark'),
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
            $params['select'] = 'kid,organization_id,user_id,level,mark,created_by,created_at';
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
//  BoeBase::debug($sult,1);
        return $sult;
    }

    /**
     * getInfo
     * 根据ID获取详细或是某个字段的信息
     * @param type $id ID
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
    public function deleteInfo($id = 0,$user_id='') {
        return $this->CommonDeleteInfo($id,$user_id);
    }

}

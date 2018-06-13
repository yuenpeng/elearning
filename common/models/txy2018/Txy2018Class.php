<?php

namespace common\models\txy2018;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_txy_class".
 *
 * @property string $kid
 * @property string $image_url
 * @property string $orgnization_id
 * @property integer $name
 * @property integer $slogan
 * @property string $target
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
class Txy2018Class extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_txy2018_class';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['name'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'name', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['image_url', 'slogan', 'target'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return array(
            'kid' => Yii::t('boe', 'class_kid'),
			'organization_id' => Yii::t('boe', 'class_organization_id'),
			'image_url' => Yii::t('boe', 'class_image_url'),
            'name' => Yii::t('boe', 'class_name'),
            'slogan' => Yii::t('boe', 'class_slogan'),
			'target' => Yii::t('boe', 'class_target'),
            'version' => Yii::t('common', 'version'),
            'created_by' => Yii::t('common', 'created_by'),
            'created_at' => Yii::t('common', 'created_at'),
            'created_from' => Yii::t('common', 'created_from'),
            'updated_by' => Yii::t('common', 'updated_by'),
            'updated_at' => Yii::t('common', 'updated_at'),
            'updated_from' => Yii::t('common', 'updated_from'),
            'is_deleted' => Yii::t('common', 'is_deleted'),
        );
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
            $params['select'] = 'kid,image_url,orgnization_id,name,target,slogan,created_by,created_at';
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
     * 根据ID获取图片的详细或是某个字段的信息
     * @param type $id 资讯的ID
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

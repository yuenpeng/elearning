<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/4/28
 * Time: 9:03
 */

namespace common\models\framework;


use common\base\BaseActiveRecord;
use common\models\boe\BoeBaseActiveRecord;
use \Yii;

class FwSeriesCategory extends  BoeBaseActiveRecord
{
    /**
     * @return string 返回该AR类关联的数据表名
     */
    public static function tableName()
    {
        return 'eln_fw_series_category';
    }
    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['name'], 'required'],
            // [['kid', 'name', 'created_by', 'created_at'], 'required'],
            [['number', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'name',  'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => 'kid',
            'name' => 'name',
            'number' => 'number',
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

    public function getAll() {
        $sult = $this->find(false)->orderBy()->asArray()->indexBy()->all();
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
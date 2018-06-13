<?php

namespace common\models\txy2018;

use common\models\boe\BoeBaseActiveRecord;
use Yii;

/**
 * This is the model class for table "eln_txy2018_task".
 *
 * @property string $kid
 * @property string $type_id
 * @property string $name
 * @property string $bg
 * @property string $target
 * @property integer $is_begin
 * @property integer $recommend_sort1
 * @property integer $type
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
class Txy2018Task extends BoeBaseActiveRecord {

    private $allInfo = NULL;

    /**
     * @inheritdoc
     */

    public static function tableName() {
        return 'eln_txy2018_task';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['name','type','type_id'], 'required'],
			[['bg', 'target'], 'string'],
            [['is_begin', 'recommend_sort1', 'type', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'type_id', 'name', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return array(
            'kid' 			=> Yii::t('txy', 'txy_task_kid'),
			'name' 			=> Yii::t('txy', 'txy_task_name'),
			'bg' 			=> Yii::t('txy', 'txy_task_bg'),
			'target' 		=> Yii::t('txy', 'txy_task_target'),
			'is_begin' 		=> Yii::t('txy', 'txy_task_is_begin'),
			'begin_date' 	=> Yii::t('txy', 'txy_task_begin_date'),
			'end_date' 		=> Yii::t('txy', 'txy_task_end_date'),
			'recommend_sort1' 	=> Yii::t('txy', 'txy_task_recommend_sort1'),
			'resource_type' => Yii::t('txy', 'txy_task_resource_type'),
			'area_type' 	=> Yii::t('txy', 'txy_task_area_type'),
			'type' 			=> Yii::t('txy', 'txy_task_type'),
			'type_id' 		=> Yii::t('txy', 'txy_task_type_id'),
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
     * getAll获取全部的闯关课程配置信息分类信息
     * @param type $create_mode 是否强制从数据库读取
     * @param type $debug 调试模式
     */
    public function getAll($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取 
            $sult = $this->find(false)->orderBy('type asc,recommend_sort1 asc')->asArray()->indexBy($this->tablePrimaryKey)->all();
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }
	
	/**
     * 获取闯关课程配置列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,name,type_id,bg,target,is_begin,begin_date,end_date,recommend_sort1,type,resource_type,area_type'
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
     * 根据ID获取闯关课程配置的详细或是某个字段的信息
     * @param type $id 课程配置的ID
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
     * 根据ID删除单个闯关课程配置信息，
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

<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * Boe视频栏位的模型
 * @date 2017-08-08
 * @author Zhenglk
 * @property string $kid
 * @property string $category_id
 * @property string $abstract
 * @property string $image_url
 * @property integer $has_image
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
class BoeVideoColumn extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    protected $createdByIdField = 'user_id';

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_video_column';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['category_id'], 'required', 'on' => ['manage']],
            [['has_image', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid','created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['abstract', 'image_url'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'video_kid'),
            'category_id' => Yii::t('boe', 'video_category_id'),
			'abstract' => Yii::t('boe', 'video_abstract'),
            'image_url' => Yii::t('boe', 'video_image_url'),
            'has_image' => 'Has Image',
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
     * getInfo
     * 根据ID获取视频栏位的详细或是某个字段的信息
     * @param type $id ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        return $this->CommonGetInfo($id, $key, $create_mode, $debug);
    }

    /**
     * deleteInfo 
     * 根据ID删除单个或多个文件信息，
     * @param type $id
     * @return int 删除结果如下
     * 1=成功
     * -1=数据库操作失败
     */
    public function deleteInfo($id = 0, $user_id = 0, $physicalDelete = 0) {
        return $this->CommonDeleteInfo($id, $user_id, $physicalDelete);
    }

    /**
     * 获取视频栏位列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {
        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,category_id,abstract,image_url,has_image,'
                    . 'created_by,created_at,updated_by,updated_at,is_deleted';
        }
        $sult = parent::getList($params);
        $tmp_arr = NULL;
        //  BoeBase::debug(__METHOD__.var_export($params,true)."\n sult:\n".var_export($sult,true),1);
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

    //return parent::getList($params);
    public function saveInfo($data, $scenarios = 'manage', $debug = 0) {
        $currnetKid = NULL;
        $opreateSult = false;
        if ($this->hasKeyword) {
            $data = $this->parseKeywordSaveArray($data);
        }
        $error = '';
        if (!empty($data[$this->tablePrimaryKey])) {//修改的时候  
            $currnetKid = $data[$this->tablePrimaryKey];
            $currentObj = $this->findOne([$this->tablePrimaryKey => $currnetKid]);
            foreach ($data as $key => $a_value) {
                if ($key != $this->tablePrimaryKey) {
                    $currentObj->$key = $a_value;
                }
            }
            if ($currentObj->validate()) {
                $opreateSult = $currentObj->save();
            } else {
                $error = $currentObj->getErrors();
            }
        } else {//添加的时候
            foreach ($data as $key => $a_value) {
                if ($key != $this->tablePrimaryKey) {
                    $this->$key = $a_value;
                }
            }
            $this->needReturnKey = true;
            if ($this->validate()) {
                $opreateSult = $this->save();
            } else {
                $error = $this->getErrors();
            }
        }
        if ($opreateSult) {//操作成功
            if (!$currnetKid) {//添加的时候
                $currnetKid = $this->kid;
            } else {
                $this->getInfo($currnetKid, '*', 2); //更新缓存
            }
        } else {//操作失败
            if ($debug) {
                $text = ("最终结果:\n" . var_export($currnetKid, true) . "\n");
                $text.=("参数:\n");
                $text.=($data);
                $text.=("错误\n");
                $text.=($error);
                BoeBase::debug($text, 1);
            } else {
                return $error;
            }
        }
        return $currnetKid;
    }

}

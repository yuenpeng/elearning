<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_subject_config".
 *
 * @property string $kid
 * @property string $name
 * @property string $key
 * @property string $content
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
class BoeSubjectConfig extends BoeBaseActiveRecord {

    protected $allInfo = NULL;
    protected $infoLog = array();

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_subject_config';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['key'], 'required'],
            [['content'], 'string'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'name', 'key', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'Kid',
            'name' => Yii::t('boe', 'config_name'),
            'key' => Yii::t('boe', 'config_key'),
            'content' => Yii::t('boe', 'config_content'),
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

    private function getKeyNameMap() {
        return array(
            'subject_short_name' => Yii::t('boe', 'subject_short_name'),
            'subject_full_name' => Yii::t('boe', 'subject_full_name'),
            'subject_begin_date' => Yii::t('boe', 'subject_begin_date'),
			'subject_end_date' => Yii::t('boe', 'subject_end_date'),
			'course_info' => array('name' => Yii::t('boe', 'course_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			'countdown_info' => array('name' => Yii::t('boe', 'countdown_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			'attached_info' => array('name' => Yii::t('boe', 'attached_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			'postcard_info' => array('name' => Yii::t('boe', 'postcard_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			'linevideo_info' => array('name' => Yii::t('boe', 'linevideo_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			'summaryvideo_info' => array('name' => Yii::t('boe', 'summaryvideo_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			'banner_info' => array('name' => Yii::t('boe', 'banner_info'), 'write_func' => 'encode', 'read_func' => 'decode'),	
			'habit_short_name' => Yii::t('boe', 'habit_short_name'),
            'habit_full_name' => Yii::t('boe', 'habit_full_name'),
            'habit_begin_date' => Yii::t('boe', 'habit_begin_date'),
			'habit_end_date' => Yii::t('boe', 'habit_end_date'),
			'habit_course_info' => array('name' => Yii::t('boe', 'habit_course_info'), 'write_func' => 'encode', 'read_func' => 'decode'),
			
			
        );
    }

    private function getWriteKeyValue($key, $value) {
        $map = $this->getKeyNameMap();
        if (isset($map[$key]['write_func'])) {
            $method_name = $map[$key]['write_func'];
//            BoeBase::debug($key);
//            BoeBase::debug($value);
//            BoeBase::debug($this->$method_name($value), 1);
            return $this->$method_name($value);
        }
        return $value;
    }

    private function getReadKeyValue($key, $value) {
        $map = $this->getKeyNameMap();
        if (isset($map[$key]['read_func'])) {
            $method_name = $map[$key]['read_func'];
//            BoeBase::debug($key);
//            BoeBase::debug($method_name);
//            BoeBase::debug($this->$method_name($value),1);

            return $this->$method_name($value);
        }
        return $value;
    }

    private function getWriteKeyName($key) {
        $map = $this->getKeyNameMap();
        if (isset($map[$key]['name'])) {
            return $map[$key]['name'];
        }
        return isset($map[$key]) ? $map[$key] : $key;
    }

    private function decode($content) {
        $content = urldecode($content);
        $content = json_decode($content, true);
        return $content;
    }

    private function encode($content) {
        $content = json_encode($content);
        $content = urlencode($content);
        return $content;
    }

    /**
     * getAll获取全部的配置信息
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
            $sult = $this->find(false)->asArray()->indexBy('key')->all();
            foreach ($sult as $key => $a_config) {
                $sult[$key]['content'] = $this->getReadKeyValue($key, $a_config['content']);
            }
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }

    /**
     * getInfo
     * 根据KEY匹配配置信息的的详细或是某个字段的信息
     * @param type key配置信息的Key
     * @param type $field  获取的字段值
     */
    public function getInfo($key = 0, $field = '*') {
        if (!$key) {
            return NULL;
        }
        $log_key_name = __METHOD__ . $key . "_field_" . $field;
        if (isset($this->infoLog[$log_key_name])) {//当前线程已有相关的数据时直接返回
            return $this->infoLog[$log_key_name];
        }
        $this->infoLog[$log_key_name] = false;
        if (!$this->allInfo) {//未初始化全部分类信息时
            $this->allInfo = $this->getAll();
        } 
        
        if (isset($this->allInfo[$key])) {
            if ($field != "*" && $field != '') {//返回某一个字段的值，比如名称
                $this->infoLog[$log_key_name] = isset($this->allInfo[$key][$field]) ? $this->allInfo[$key][$field] : NULL;
            } else {
                $this->infoLog[$log_key_name] = $this->allInfo[$key]; //返回全部信息
            }
        }
        return $this->infoLog[$log_key_name];
    }

    public function saveInfo($data, $debug = 0) {
        $all = $this->getAll(1);
        $error = array();
        foreach ($data as $key => $a_config) {
            if (isset($all[$key])) {//修改
                $currentObj = $this->findOne([$this->tablePrimaryKey => $all[$key][$this->tablePrimaryKey]]);
                $currentObj->content = $this->getWriteKeyValue($key, $a_config);
                if ($currentObj->validate()) {
                    $currentObj->save();
                } else {
                    $error[] = $currentObj->getErrors();
                }
            } else {//添加
                $this->setIsNewRecord(true);
                $this->name = $this->getWriteKeyName($key);
                $this->key = $key;
                $this->content = $this->getWriteKeyValue($key, $a_config);
                $this->needReturnKey = true;
                if ($this->validate()) {
                    $this->save();
                } else {
                    $error[] = $this->getErrors();
                }
            }
        }
        if (!$error) {
            $this->getAll(1); //重建缓存
            return true;
        }
        if ($debug) {
            BoeBase::debug("参数:\n");
            BoeBase::debug($data);
            BoeBase::debug("错误\n");
            BoeBase::debug($error);
        } else {
            return $error;
        }
        return false;
    }

}

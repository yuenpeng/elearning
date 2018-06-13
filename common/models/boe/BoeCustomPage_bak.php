<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "eln_boe_custom_page".
 *
 * @property string $kid
 * @property string $title
 * @property string $name
 * @property string $parent_id
 * @property string $config
 * @property string $abstract
 * @property string $content
 * @property string $image_url
 * @property integer $has_image
 * @property integer $recommend_sort1
 * @property integer $recommend_sort2
 * @property integer $recommend_sort3
 * @property integer $recommend_sort4
 * @property integer $recommend_sort5
 * @property integer $recommend_sort6
 * @property integer $recommend_sort7
 * @property integer $recommend_sort8
 * @property integer $recommend_sort9
 * @property integer $recommend_sort10
 * @property string $keyword1
 * @property string $keyword2
 * @property string $keyword3
 * @property string $keyword4
 * @property string $keyword5
 * @property string $keyword6
 * @property string $keyword7
 * @property string $keyword8
 * @property string $keyword9
 * @property string $keyword10
 * @property string $visit_num
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
class BoeCustomPage extends BoeBaseActiveRecord {

    protected $hasKeyword = true;

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_custom_page';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            //  [['kid', 'created_by', 'created_at'], 'required'],
            [['name'], 'required'],
            [['config', 'content'], 'string'],
            [['has_image', 'recommend_sort1', 'recommend_sort2', 'recommend_sort3', 'recommend_sort4', 'recommend_sort5', 'recommend_sort6', 'recommend_sort7', 'recommend_sort8', 'recommend_sort9', 'recommend_sort10', 'visit_num', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'keyword1', 'keyword2', 'keyword3', 'keyword4', 'keyword5', 'keyword6', 'keyword7', 'keyword8', 'keyword9', 'keyword10', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['title', 'name', 'abstract', 'image_url'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1],
            [['name'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'title' => Yii::t('boe', 'custom_page_title'),
            'name' => Yii::t('boe', 'custom_page_name'),
            'config' => Yii::t('boe', 'custom_page_config'),
            'abstract' => Yii::t('boe', 'custom_page_abstract'),
            'content' => Yii::t('boe', 'custom_page_content'),
            'image_url' => Yii::t('boe', 'custom_page_image_url'),
            'has_image' => 'Has Image',
            'recommend_sort1' => 'Recommend Sort1',
            'recommend_sort2' => 'Recommend Sort2',
            'recommend_sort3' => 'Recommend Sort3',
            'recommend_sort4' => 'Recommend Sort4',
            'recommend_sort5' => 'Recommend Sort5',
            'recommend_sort6' => 'Recommend Sort6',
            'recommend_sort7' => 'Recommend Sort7',
            'recommend_sort8' => 'Recommend Sort8',
            'recommend_sort9' => 'Recommend Sort9',
            'recommend_sort10' => 'Recommend Sort10',
            'keyword1' => 'Keyword1',
            'keyword2' => 'Keyword2',
            'keyword3' => 'Keyword3',
            'keyword4' => 'Keyword4',
            'keyword5' => 'Keyword5',
            'keyword6' => 'Keyword6',
            'keyword7' => 'Keyword7',
            'keyword8' => 'Keyword8',
            'keyword9' => 'Keyword9',
            'keyword10' => 'Keyword10',
            'visit_num' => 'Visit Num',
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
     * 获取页面列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {
        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,title,name,abstract,image_url,has_image,'
                    . 'recommend_sort1,recommend_sort2,recommend_sort3,recommend_sort4,recommend_sort5,'
                    . 'recommend_sort6,recommend_sort7,recommend_sort8,recommend_sort9,recommend_sort10,'
                    . 'keyword1,keyword2,keyword3,keyword4,keyword5,'
                    . 'keyword6,keyword7,keyword8,keyword9,keyword10,'
                    . 'visit_num,version,created_by,created_at,created_from,created_ip,'
                    . 'updated_by,updated_at,updated_from,updated_ip,is_deleted,';
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
     * 根据ID获取页面的详细或是某个字段的信息
     * @param type $id 资讯的ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        if (!$id) {
            return NULL;
        }
        $current_kid = NULL;
        if (strpos($id, '-') !== false) {//Kid去找的时候
            $current_kid = $id;
        } else {
            $all_page = $this->getAll();
            if ($all_page && is_array($all_page)) {
                foreach ($all_page as $a_info) {
                    if ($a_info['name'] == $id) {
                        $current_kid = $a_info['kid'];
                        break;
                    }
                }
            }
            if (!$current_kid) {
                return NULL;
            }
        }
        return $this->CommonGetInfo($current_kid, $key, $create_mode, $debug);
    }

    /**
     * getAll获取全部的页面信息
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
            $sult = $this->find(false)->orderBy('recommend_sort1 asc')->select('kid,name')->asArray()->indexBy($this->tablePrimaryKey)->all();
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }

    public function saveInfo($data, $debug = 0) {
        $sult = $this->CommonSaveInfo($data, $debug);
        if (!is_array($sult)) {
            $this->getAll(1);
            $this->getNavPage(1);
        }
        return $sult;
    }

    /**
     * deleteInfo 
     * 根据ID删除单个资讯信息，
     * @param type $id
     * @return int 删除结果如下
     * 1=成功
     * -1=信息不存在了
     * -2=数据库操作失败
     */
    public function deleteInfo($id = 0) {
        $sult = $this->CommonDeleteInfo($id, 0, 1);
        if ($sult == 1) {
            $this->getAll(1);
            $this->getNavPage(1);
        }
        return $sult;
    }

    /**
     * getNavPage
     * 根据recommend_sort1读取需要在左侧显示的分类信息
     * @param type $id 数据ID
     * @param type $key 
     */
    public function getNavPage($create_mode = 0, $debug = 0) {
        $cache_name = __METHOD__;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S
            $log_key_name = $cache_name;
            if (!$create_mode && isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
                return $this->log[$log_key_name];
            }
            $this->log[$log_key_name] = $create_mode == 1 ? NULL : $this->getCache($cache_name, $debug);
            if (!$this->log[$log_key_name]) {//缓存中没有数据的时候S  
                $this->log[$log_key_name] = $this->find(false)->select('kid,name,title,recommend_sort1')->orderBy('recommend_sort1 asc')->andFilterWhere(array('>', 'recommend_sort1', 0))->asArray()->indexBy($this->tablePrimaryKey)->all();
                $this->setCache(__METHOD__, $this->log[$log_key_name], 0, $debug); // 设置缓存
            }//缓存中没有数据的时候S 
            return $this->log[$log_key_name];
        }//读取数据的时候E
    }

}

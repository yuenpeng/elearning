<?php

namespace common\models\txy2018;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_txy2018_news".
 *
 * @property string $kid
 * @property string $title
 * @property string $source
 * @property string $author
 * @property string $category_id
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
 * @property integer $visit_num
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
class Txy2018News extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_txy2018_news';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['title', 'category_id'], 'required'],
            [['content'], 'string'],
            [['has_image', 'recommend_sort1', 'recommend_sort2', 'recommend_sort3', 'recommend_sort4', 'recommend_sort5', 'recommend_sort6', 'recommend_sort7', 'recommend_sort8', 'recommend_sort9', 'recommend_sort10', 'visit_num', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'keyword1', 'keyword2', 'keyword3', 'keyword4', 'keyword5', 'keyword6', 'keyword7', 'keyword8', 'keyword9', 'keyword10', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['title', 'source', 'abstract', 'image_url'], 'string', 'max' => 255],
            [['author'], 'string', 'max' => 100],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'news_kid'),
            'title' => Yii::t('boe', 'news_title'),
            'source' => Yii::t('boe', 'news_source'),
            'author' => Yii::t('boe', 'news_author'),
            'category_id' => Yii::t('boe', 'news_category_id'),
            'abstract' => Yii::t('boe', 'news_abstract'),
            'content' => Yii::t('boe', 'news_content'),
            'image_url' => Yii::t('boe', 'news_image_url'),
            'has_image' => 'Has Image',
            'recommend_sort1' => Yii::t('boe', 'news_index_sort'),
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
            'visit_num' => Yii::t('boe', 'news_visit_num'),
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
     * 获取资讯列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,title,source,author,category_id,abstract,image_url,has_image,visit_num,'
                    . 'recommend_sort1,recommend_sort2,recommend_sort3,recommend_sort4,recommend_sort5'
                    . ',recommend_sort6,recommend_sort7,recommend_sort8,recommend_sort9,recommend_sort10'
                    . ',keyword1,keyword2,keyword3,keyword4,keyword5,keyword6,keyword7,keyword8,keyword9,keyword10'
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
     * 根据ID获取资讯的详细或是某个字段的信息
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
     * 根据ID删除单个资讯信息，
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

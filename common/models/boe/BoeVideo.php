<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Boe视频的数据模型
 * @date 2017-08-08
 * @author Zhenglk
 * @property string $kid
 * @property string $title
 * @property string $user_id
 * @property string $file_id
 * @property string $category_id
 * @property string $abstract
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
 * @property integer $file_class
 * @property integer $reprot_num
 * @property integer $down_num
 * @property integer $visit_num
 * @property integer $allow_down
 * @property integer $is_private
 * @property integer $share_num
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
class BoeVideo extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    protected $createdByIdField = 'user_id';

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_video';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['title', 'category_id', 'file_id'], 'required', 'on' => ['manage']],
            [['has_image', 'recommend_sort1', 'recommend_sort2', 'recommend_sort3', 'recommend_sort4', 'recommend_sort5', 'recommend_sort6', 'recommend_sort7', 'recommend_sort8', 'recommend_sort9', 'recommend_sort10', 'file_class', 'allow_down', 'is_private', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'user_id', 'file_id','lecturer','','position', 'keyword1', 'keyword2', 'keyword3', 'keyword4', 'keyword5', 'keyword6', 'keyword7', 'keyword8', 'keyword9', 'keyword10', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['abstract','audience_id', 'image_url'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    public function scenarios() {
        return [
            'manage' => ['title', 'category_id', 'file_id'],
            'default' => [],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'video_kid'),
            'title' => Yii::t('boe', 'video_title'),
            'user_id' => 'User ID',
            'file_id' => 'File ID',
			'lecturer' => Yii::t('boe', 'video_lecturer'),
            'position' => "讲师职位",
            'category_id' => Yii::t('boe', 'video_category_id'),
			'abstract' => Yii::t('boe', 'video_abstract'),
            'audience_id' => Yii::t('boe', 'audience_id'),
            'image_url' => Yii::t('boe', 'video_image_url'),
            'has_image' => 'Has Image',
            'recommend_sort1' => Yii::t('boe', 'video_index_sort'),
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
            'file_class' => 'File Class',
            'reprot_num' => 'Reprot Num',
            'down_num' => 'Down Num',
            'visit_num' => Yii::t('boe', 'video_visit_num'),
            'allow_down' => 'Allow Down',
            'is_private' => 'Is Private',
            'share_num' => 'Share Num',
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
     * 根据ID获取视频的详细或是某个字段的信息
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
     * 获取视频列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {
        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,title,position,lecturer,user_id,file_id,category_id,audience_id,abstract,image_url,has_image,'
                    . 'recommend_sort1,recommend_sort2,recommend_sort3,recommend_sort4,recommend_sort5,recommend_sort6,recommend_sort7,recommend_sort8,recommend_sort9,recommend_sort10,'
                    . 'keyword1,keyword2,keyword3,keyword4,keyword5,keyword6,keyword7,keyword8,keyword9,keyword10,'
                    . 'file_class,reprot_num,down_num,visit_num,allow_down,is_private,share_num,'
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
        $this->scenario = $scenarios;
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

    /**
     * 更新视频的分享数量
     * @param type $kid
     * @param type $debug
     * @return boolean
     */
    public function updateShareNum($kid = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $currentObj->share_num = new Expression("get_video_share_num('{$kid}')");
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的视频的分享数量失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 更新视频的举报数量
     * @param type $kid
     * @param type $debug
     * @return boolean
     */
    public function updateReportNum($kid = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $currentObj->reprot_num = new Expression("get_video_report_num('{$kid}')");
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的视频的举报数量失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 更新视频的下载数量
     * @param type $kid
     * @param type $debug
     * @return boolean
     */
    public function updateDownNum($kid = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $currentObj->down_num = new Expression("down_num+1");
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的视频的下载数量失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 更新视频的下载数量
     * @param type $kid
     * @param type $debug
     * @return boolean
     */
    public function updateVisitNum($kid = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $currentObj->visit_num = new Expression("visit_num+1");
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的视频的浏览数量失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 获取分类下的所有讲师
     * @param type $params
     */
    public function getCategoryLecturers($params = array()) {
        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'lecturer';
        }
        $lecturerList = parent::getList($params);
        $lecturers = ArrayHelper::getColumn($lecturerList,'lecturer');
        $lecturers = array_unique($lecturers);
        foreach($lecturers as $key=>$value){
            $lecturers[$key] = str_replace(" ","，",$value);
        }
        return $lecturers;
    }


}

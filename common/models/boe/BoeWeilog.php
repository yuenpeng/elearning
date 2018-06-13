<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_weilog".
 *
 * @property string  $kid
 * @property string  $content
 * @property integer $recommend_status
 * @property integer $publish_status
 * @property integer $like_num
 * @property string  $group_id
 
 * @property string $keyword1
 * @property integer $comment_show
 * @property string $comment_info
 * @property string $comment_by
 * @property integer $comment_at
 * @property string $comment_from
 * @property string $comment_ip
 
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
class BoeWeilog extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
	
    /**
     * @xinpeng
     */
    public static function tableName() {
        return 'eln_boe_weilog';
    }

    /**
     * @xinpeng
     */
    public function rules() {
        return [
            [['content'], 'required'],
            [['recommend_status', 'publish_status', 'like_num', 'version', 'comment_show', 'comment_at', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'group_id', 'keyword1','comment_ip', 'comment_info', 'comment_by', 'comment_from', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 1024],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @xinpeng
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'boe_weilog_kid'),
            'content' => Yii::t('boe', 'boe_weilog_content'),
            'recommend_status' => Yii::t('boe', 'boe_weilog_recommend'),
            'publish_status' => Yii::t('boe', 'boe_weilog_publish'),
            'like_num' => Yii::t('boe', 'boe_weilog_like'),
			'group_id' => Yii::t('boe', 'boe_weilog_group_id'),
            'keyword1' => Yii::t('boe', 'boe_weilog_keyword'),
			'comment_show' => Yii::t('boe', 'boe_weilog_comment_show'),
			'comment_info' => Yii::t('boe', 'boe_weilog_comment_info'),
			'comment_by' => Yii::t('boe', 'boe_weilog_comment_by'),
			'comment_at' => Yii::t('boe', 'boe_weilog_comment_at'),
			'comment_from' => Yii::t('boe', 'boe_weilog_comment_from'),
			'comment_ip' => Yii::t('boe', 'boe_weilog_comment_ip'),
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
     * 获取日志列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,content,keyword1,recommend_status,publish_status,like_num,comment_show,comment_info'
                    . ',comment_at,comment_by,created_at,created_by,updated_by,updated_at';
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
     * 根据ID获取日志的详细或是某个字段的信息
     * @param type $id 日志的ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        return $this->CommonGetInfo($id, $key, $create_mode, $debug);
    }

    public function saveInfo($data, $debug = 0) {
        return $this->CommonSaveInfo($data, $debug);
    }

	public function getSystemKey() {
        return self::$defaultKey;
    }
	
    /**
     * deleteInfo 
     * 根据ID删除单个日志信息，
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

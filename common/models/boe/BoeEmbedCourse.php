<?php
namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "{{%boe_embed_course}}".
 *
 * @property string $kid
 * @property string $embed_id
 * @property string $embed_type
 * @property string $course_id
 * @property string $status
 * @property integer $version
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
class BoeEmbedCourse extends BoeBaseActiveRecord{

    const EMBED_TYPE_COURSE = '1';//课程
    const EMBED_TYPE_PROJECT = '2';//项目

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%boe_embed_course}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['embed_id', 'course_id'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'embed_id', 'course_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['embed_type', 'status', 'is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => Yii::t('boe', 'Kid'),
            'embed_id' => Yii::t('boe', 'Embed ID'),
            'embed_type' => Yii::t('boe', 'Embed Type'),
            'course_id' => Yii::t('boe', 'Course ID'),
            'status' => Yii::t('common', 'status'),
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
     * 获取前置任务列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {
        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['field'])) {
            $params['select'] = 'kid,embed_id,course_id,created_at';
        }
        if($debug){
        	$params['debug'] = $debug;
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
            foreach ($tmp_arr as $key => $a_info) {
            //整理出关键信息
                $tmp_arr[$key] = $this->parseKeywordToString($a_info);
            }
        }
        //  BoeBase::debug($sult,1);
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
?>
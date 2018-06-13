<?php
namespace common\models\boe;
use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;


class BoeEmbedOption extends BoeBaseActiveRecord{

	public static function tableName(){
		return 'eln_boe_embed_option';
	}

	 /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'option_name' => 'option_name',
            'is_read' => 'is_read',
            'is_edit' => 'is_edit',
            'is_required' =>'is_required',
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
	 * @inheritdoc
	 */
	public function rules() {
		return [
            [['option_name'], 'required'],
            [[ 'is_read','is_edit','is_required','version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];

	}

     public function getAll($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取
            $sult = $this->find(false)->orderBy('created_at')->asArray()->indexBy($this->tablePrimaryKey)->all();
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
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
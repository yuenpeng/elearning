<?php

namespace common\models\txy2018;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_txy2018_images".
 *
 * @property string $kid
 * @property string $title
 * @property string $url
 * @property string $thumb_url
 * @property string $file_size
 * @property integer $pic_width
 * @property integer $pic_height
 * @property string $orgnization_id
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
class Txy2018Images extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_txy2018_images';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['orgnization_id'], 'required'],
            [['file_size', 'pic_width', 'pic_height', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'orgnization_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['title', 'url','thumb_url'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => '图片ID',
            'title' => '图片标题',
            'url' => '图片URL',
			'thumb_url' => '缩略图片URL',
            'file_size' => '文件字节',
            'pic_width' => '图片的文件的宽度,非图片文件该值为0',
            'pic_height' => '图片的文件的高度,非图片文件该值为0',
            'orgnization_id' => '组织ID',
            'version' => '版本信息',
            'created_by' => '创建人ID',
            'created_at' => '创建时间',
            'created_from' => '创建来源',
            'created_ip' => '创建人IP',
            'updated_by' => '编辑人ID',
            'updated_at' => '编辑时间',
            'updated_from' => '编辑来自',
            'updated_ip' => '编辑人IP',
            'is_deleted' => '是否删除',
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
            $params['select'] = 'kid,title,url,thumb_url,file_size,pic_width,pic_height,orgnization_id,created_by,created_at';
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
//  BoeBase::debug($sult,1);
        return $sult;
    }

    /**
     * getInfo
     * 根据ID获取图片的详细或是某个字段的信息
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
     * 根据ID删除单个图片信息，
     * @param type $id
     * @return int 删除结果如下
     * 1=成功
     * -1=信息不存在了
     * -2=数据库操作失败
     */
    public function deleteInfo($id = 0,$user_id='') {
        return $this->CommonDeleteInfo($id,$user_id);
    }

}

<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * Boe视频的文件模型
 *
 * @property string $kid
 * @property string $video_id
 * @property string $user_id
 * @property string $md5_key
 * @property string $file_size
 * @property integer $pic_width
 * @property integer $pic_height
 * @property string $old_file_name
 * @property string $file_type
 * @property string $file_ext
 * @property string $file_name
 * @property string $file_full_path
 * @property string $file_relative_path
 * @property string $file_save_folder
 * @property integer $cloud_status
 * @property integer $cloud_time
 * @property string cloud_adp
 * @property integer $pdf_status
 * @property integer $pdf_time
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
class BoeVideoFileModel extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_video_file';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
//            [['kid', 'created_by', 'created_at'], 'required'],
            [['file_size', 'pic_width', 'pic_height', 'cloud_status', 'cloud_time', 'pdf_status', 'pdf_time', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'video_id', 'user_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['md5_key'], 'string', 'max' => 32],
            [['old_file_name', 'file_type', 'file_name', 'file_full_path', 'file_relative_path', 'file_save_folder'], 'string', 'max' => 255],
            [['file_ext'], 'string', 'max' => 100],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'Kid',
            'video_id' => 'Video ID',
            'user_id' => 'User ID',
            'md5_key' => 'Md5 Key',
            'file_size' => 'File Size',
            'pic_width' => 'Pic Width',
            'pic_height' => 'Pic Height',
            'old_file_name' => 'Old File Name',
            'file_type' => 'File Type',
            'file_ext' => 'File Ext',
            'file_name' => 'File Name',
            'file_full_path' => 'File Full Path',
            'file_relative_path' => 'File Relative Path',
            'file_save_folder' => 'File Save Folder',
            'cloud_status' => 'Cloud Status',
            'cloud_time' => 'Cloud Time',
            'pdf_status' => 'Pdf Status',
            'pdf_time' => 'Pdf Time',
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
     * 根据ID获取文件的详细或是某个字段的信息 
     * @param type $id 文件的ID或是md5_key
     * @param type $key 获取的字段*表示全部信息，以数组的方式返回，其它值表示特定的字段值，例如：user_id表示只返回特定文件信息的user_id
     * @param type $create_mode 重建缓存模式 0表示不重建缓存，1表示重建缓存模式从DB读取再写入缓存，2表示只删除缓存
     * @param type $no_log_mode是否忽略当前线程的记录 0不忽略，只要当前线程读过，同一线程内再读取时，不经过缓存，1，忽略当前线程的结果，从缓存或是DB中读取
     * @param type $debug 调试模式
     * @return type
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $no_log_mode = 0, $debug = 0) {
        if (!$id) {
            return NULL;
        }
        $cache_name = __METHOD__ . $id;
        $where_key = strpos($id, '-') !== false ? $this->tablePrimaryKey : 'md5_key';
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S
            $log_key_name = __METHOD__ . $id . "_field_" . $key;
            if (!$create_mode && !$no_log_mode && isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
                return $this->log[$log_key_name];
            }
            $cacheSult = $create_mode == 1 ? NULL : $this->getCache($cache_name, $debug);
            if (!$cacheSult) {//缓存中没有数据的时候S
                $cacheSult = $this->find(false)->where([$where_key => $id])->asArray()->one();
                if ($cacheSult) {
                    $this->setCache(__METHOD__, $cacheSult, 0, $debug); // 设置缓存
                }
            }//缓存中没有数据的时候S

            if ($key != "*" && $key != '') {//返回某一个字段的值，比如名称
                $this->log[$log_key_name] = isset($cacheSult[$key]) ? $cacheSult[$key] : false;
            } else {
                $this->log[$log_key_name] = $cacheSult;
            }
            return $this->log[$log_key_name];
        }//读取数据的时候E
    }

//-----------------------1大堆和写数据库有关的方法开始------------------------------------------- 
    /**
     * 添加文件上传记录
     * @param type $data
     * @param type $debug
     * @return type
     */
    public function addFile($data, $debug = 0) {
        $opreateSult = false;
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
        if ($opreateSult) {//操作成功
            return array('kid' => $this->kid, 'error' => NULL);
        }
        //操作失败
        if ($debug) {
            $text = ("参数:\n");
            $text.=($data);
            $text.=("错误\n");
            $text.=($error);
            BoeBase::debug($text, 1);
        } else {
            return array('kid' => NULL, 'error' => $error);
        }
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
        return $this->CommonDeleteInfo($id,$user_id,$physicalDelete);
    }

    /**
     * 更新文件的video_id
     * @param type $kid
     * @param type $video_id
     * @param type $debug
     * @return boolean
     */
    public function updateVideoId($kid = '', $video_id = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $md5_key = $currentObj->md5_key;
            $currentObj->video_id = $video_id;
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                $this->getInfo($kid, '*', 2); //删除缓存
                $this->getInfo($md5_key, '*', 2); //删除缓存
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的文件的VideoID值失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 更新文件的上传状态
     * @param type $kid
     * @param type $status
     * @param type $debug
     * @return boolean
     */
    public function updateCloudStatus($kid = '', $status = 0, $task_id = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $md5_key = $currentObj->md5_key;
            $currentObj->cloud_status = $status;
            $currentObj->cloud_time = time();
            if ($task_id) {
                $currentObj->cloud_adp = json_encode(array('task_id' => $task_id));
            }
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                $this->getInfo($kid, '*', 2); //删除缓存
                $this->getInfo($md5_key, '*', 2); //删除缓存
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的文件的上传状态失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 更新文件的上传状态
     * @param type $kid
     * @param type $status
     * @param type $debug
     * @return boolean
     */
    public function updateCloudAdpStatus($kid = '', $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $md5_key = $currentObj->md5_key;
            $cloud_adp = @json_decode($cloud_adp, true);
            if (!$cloud_adp || !is_array($cloud_adp)) {
                $cloud_adp = array(
                );
            }
            $cloud_adp['convert_status'] = 1;
            if ($task_id) {
                $currentObj->cloud_adp = json_encode($cloud_adp);
            }
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                $this->getInfo($kid, '*', 2); //删除缓存
                $this->getInfo($md5_key, '*', 2); //删除缓存
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的文件的上传状态失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

    /**
     * 更新文件的PDF转换状态
     * @param type $kid
     * @param type $status
     * @param type $debug
     * @return boolean
     */
    public function updatePdfStatus($kid = '', $status = 0, $debug = 0) {
        $currentObj = $this->findOne([$this->tablePrimaryKey => $kid]);
        if ($currentObj) {
            $md5_key = $currentObj->md5_key;
            $currentObj->pdf_status = $status;
            $currentObj->pdf_time = time();
            $opreateSult = $currentObj->save();
            if ($opreateSult) {//操作成功
                $this->getInfo($kid, '*', 2); //删除缓存
                $this->getInfo($md5_key, '*', 2); //删除缓存
                return true;
            } else {//操作失败
                $error = $this->getErrors();
                if ($debug) {
                    $text = ("更新ID是{$kid}的文件的PDF转换状态失败!\n");
                    $text.=("错误:\n");
                    $text.=($error);
                    BoeBase::debug($text, 1);
                }
            }
        }
        return false;
    }

}

<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * This is the model class for table "eln_boe_doc_report".
 *
 * @property string $kid
 * @property string $doc_id
 * @property string $user_id
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
class BoeDocReport extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_doc_report';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
//            [['kid', 'created_by', 'created_at'], 'required'],
            [['doc_id', 'content'], 'required'],
            [['content'], 'string'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'doc_id', 'user_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'Kid',
            'doc_id' => 'Doc ID',
            'user_id' => 'User ID',
            'content' => 'Content',
            'version' => 'Version',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
            'created_from' => 'Created From',
            'created_ip' => 'Created Ip',
            'updated_by' => 'Updated By',
            'updated_at' => 'Updated At',
            'updated_from' => 'Updated From',
            'updated_ip' => 'Updated Ip',
            'is_deleted' => 'Is Deleted',
        ];
    }

    /**
     * 读取某个文档对应的投诉信息
     * @param type $id
     * @param type $create_mode
     * @return type
     */
    public function getDocReport($id = 0, $return_name = false, $create_mode = 0) {
        if (!$id) {
            return NULL;
        }
        $cache_name = __METHOD__ . $id;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S 
            $cacheSult = $create_mode == 1 ? NULL : $this->getCache($cache_name, $debug);
            if (!$cacheSult) {//缓存中没有数据的时候S
                $where_p = array('and',);
                $where_p[] = ['doc_id' => $id];
                $where_p[] = ['is_deleted' => 0];
                $cacheSult = $this->find(false)->select('kid,doc_id,user_id,content,created_by,created_at')->where($where_p)->orderBy('created_at desc')->asArray()->all();
                if ($cacheSult) {
                    foreach ($cacheSult as $key => $a_info) {
                        $cacheSult[$key]['create_time'] = date("Y-m-d H:i:s", $a_info['created_at']);
                    }
                    $this->setCache(__METHOD__, $cacheSult, 0, $debug); // 设置缓存
                }
            }//缓存中没有数据的时候S  

            if ($return_name && $cacheSult) {//需要返回用户的相关信息时
                $user_id = array();
                foreach ($cacheSult as $a_info) {
                    $user_id[$a_info['user_id']] = $a_info['user_id'];
                }
                $where = array('and');
                $where[] = array('is_deleted' => 0);
                $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
                $where[] = array('in', 'kid', $user_id);
                $user_model = FwUser::find(false)->select('real_name,nick_name,user_name,kid');
                $user_info = $user_model->where($where)->indexby('kid')->asArray()->all();
                if ($user_info && is_array($user_info)) {//合并用户信息数组S
                    foreach ($cacheSult as $key => $a_info) {
                        if (isset($user_info[$a_info['user_id']])) {
                            unset($user_info[$a_info['user_id']]['kid']);
                            $cacheSult[$key]+=$user_info[$a_info['user_id']];
                        } else {
                            unset($cacheSult[$key]);
                        }
                    }
                }//合并用户信息数组E
            }
            return $cacheSult;
        }//读取数据的时候E
    }

    public function addReport($data, $debug = 0) {
        $currnetKid = NULL;
        $opreateSult = false;
        $error = '';
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
            if (!$currnetKid) {//添加的时候
                $currnetKid = $this->kid;
            } else {
                $this->getDocReport($currnetKid, flase, 2); //更新缓存
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

//-----------------------------------------------类在此结束-------------------------------------------------
}

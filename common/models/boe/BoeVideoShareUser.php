<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use common\models\framework\FwUser;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "eln_boe_video_share_user".
 *
 * @property string $kid
 * @property string $video_id
 * @property string $user_id
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
class BoeVideoShareUser extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_video_share_user';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
//            [['kid', 'created_by', 'created_at'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'video_id', 'user_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
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
     * 读取某个用户收到的共享视频数组
     * @param type $user_id
     * @param type $create_mode
     * @return type
     */
    public function getUserShareVideoInfo($user_id = 0, $create_mode = 0,$debug=false) {
        if (!$user_id) {
            return NULL;
        }
        $cache_name = __METHOD__ . $user_id;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S 
            $cacheSult = $create_mode == 1 ? NULL : $this->getCache($cache_name, $debug);
            if (!$cacheSult) {//缓存中没有数据的时候S
                $where_p = array('and');
                $where_p[] = ['user_id' => $user_id];
                $where_p[] = ['is_deleted' => 0];
                $cacheSult = $this->find(false)->select('user_id,video_id')->where($where_p)->indexby('video_id')->asArray()->all();
                if ($cacheSult) {
                    $this->setCache(__METHOD__, $cacheSult, 0, $debug); // 设置缓存
                }
            }//缓存中没有数据的时候S   
            return $cacheSult;
        }//读取数据的时候E
    }

    /**
     * 读取某个视频对应的共享用户信息数组
     * @param type $id
     * @param type $create_mode
     * @return type
     */
    public function getVideoShareUserInfo($id = 0, $return_name = false, $create_mode = 0,$debug=false) {
        if (!$id) {
            return NULL;
        }
        $cache_name = __METHOD__ . $id;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S 
            $cacheSult = $create_mode == 1 ? NULL : $this->getCache($cache_name, $debug);
            if (!$cacheSult) {//缓存中没有数据的时候S
                $where_p = array('and');
                $where_p[] = ['video_id' => $id];
                $where_p[] = ['is_deleted' => 0];
                $cacheSult = $this->find(false)->select('user_id,video_id')->where($where_p)->indexby('user_id')->asArray()->all();
                if ($cacheSult) {
                    $this->setCache(__METHOD__, $cacheSult, 0, $debug); // 设置缓存
                }
            }//缓存中没有数据的时候S  

            if ($return_name && $cacheSult) {//需要返回用户的相关信息时
                $where = array('and');
                $where[] = array('is_deleted' => 0);
                $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
                $where[] = array('in', 'kid', array_keys($cacheSult));
                $user_model = FwUser::find(false)->select('real_name,nick_name,user_name,kid,user_no,email');
                $user_info = $user_model->where($where)->indexby('kid')->asArray()->all();
                if ($user_info && is_array($user_info)) {
                    foreach ($user_info as $key => $a_user_info) {
                        $user_info[$key]+=$cacheSult[$key];
                    }
                } else {
                    $user_info = array();
                }
                return $user_info;
            }
            return $cacheSult;
        }//读取数据的时候E
    }

    /**
     * 读取某个视频对应的共享用户信息的@字符串
     * @param type $video_id
     * @return type
     */
    public function getOneVideoShareUserAtString($video_id) {
        return  $this->getVideoShareUserInfo($video_id, 1);
       
    }

    /**
     * 更新视频的共享人员信息
     * @param type $video_id
     * @param type $user_info
     * @return boolean
     */
    public function updateVideoShareUserInfo($video_id = 0, $user_info = array()) {
        if (!$video_id) {
            return false;
        }
        $this->physicalDeleteAll(array('video_id' => $video_id)); //删除之前的记录
        if (Yii::$app->user->isGuest) {
            $currentUserId = "00000000-0000-0000-0000-000000000000";
        } else {
            $currentUserId = strval(Yii::$app->user->getId());
        }
        $current_at = time();
        $systemKey = self::$defaultKey;
        $ip = Yii::$app->getRequest()->getUserIP();
        $user_id = array();
        if ($user_info && is_array($user_info)) {
            $rows = array();
            foreach ($user_info as $key => $a_info) {
                $user_id[$key] = BoeBase::array_key_is_nulls($a_info, 'user_id', $a_info);
                $rows[] = array(
                    'kid' => new Expression('UPPER(UUID())'),
                    'user_id' => is_array($a_info)?$user_id[$key]:$a_info,
                    'video_id' => $video_id,
                    'version' => 1,
                    'created_by' => $currentUserId,
                    'created_at' => $current_at,
                    'created_from' => $systemKey,
                    'created_ip' => $ip,
                    'updated_by' => $currentUserId,
                    'updated_at' => $current_at,
                    'updated_from' => $systemKey,
                    'updated_ip' => $ip,
                    'is_deleted' => 0
                );
            }
            Yii::$app->db->createCommand()->batchInsert(self::tableName(), array_keys($rows[0]), $rows)->execute();
        }
        $this->getVideoShareUserInfo($video_id, false, 2); //删除视频对应的用户关系缓存数据
        foreach ($user_id as $a_user_id) {
            $this->getUserShareVideoInfo($a_user_id, 2); //删除用户对应视频的关系缓存数据
        }
        return true;
    }

}

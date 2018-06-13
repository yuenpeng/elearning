<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * @property string $user_id
 * @property string $course_id
 * @property integer $course_period
 * @property integer $course_price
 * @property integer $complete_status
 * @property integer $complete_real_score
 * @property date $create_day
 * @property datetime $create_time 
 */
class BoeCourseReport extends BoeBaseActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'eln_boe_course_report';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['course_period', 'course_price', 'complete_status', 'complete_real_score'], 'integer'],
            [['create_day'], 'date'],
            [['create_time '], 'datetime']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'user_id' => '用户ID',
            'course_id' => '课程ID',
            'course_period' => '课程学时',
            'course_price' => '课程单价',
            'complete_status' => '完成状态',
            'complete_real_score' => '最终成绩',
            'create_day' => '生成日期',
            'create_time' => '生成时间',
        ];
    }
 
    /**
     * 更新文档的共享人员信息
     * @param type $doc_id
     * @param type $user_info
     * @return boolean
     */
    public function updateDocShareUserInfo($doc_id = 0, $user_info = array()) {
        if (!$doc_id) {
            return false;
        }
        $this->physicalDeleteAll(array('doc_id' => $doc_id)); //删除之前的记录
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
                    'user_id' => is_array($a_info) ? $user_id[$key] : $a_info,
                    'doc_id' => $doc_id,
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
        $this->getDocShareUserInfo($doc_id, false, 2); //删除文档对应的用户关系缓存数据
        foreach ($user_id as $a_user_id) {
            $this->getUserShareDocInfo($a_user_id, 2); //删除用户对应文档的关系缓存数据
        }
        return true;
    }

}

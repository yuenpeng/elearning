<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\services\boe\BoeBaseService;
use common\models\framework\FwUser;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "eln_boe_subject_weilog".
 *
 * @property string $kid
 * @property string $student_id
 * @property integer $s_integral
 * @property integer $s_date
 * @property integer $s_sort
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
class BoeSubjectStudent extends BoeBaseActiveRecord {

    protected $hasKeyword = true;

    /**
     * @xinpeng
     */
    public static function tableName() {
        return 'eln_boe_subject_student';
    }

    /**
     * @xinpeng
     */
    public function rules() {
        return [
            [['student_id', 's_integral', 's_date'], 'required'],
            [['s_integral', 's_sort', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'student_id', 's_date', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @xinpeng
     */
    public function attributeLabels() {
        return array(
            'kid' => Yii::t('boe', 'boe_subject_student_kid'),
            'student_id' => Yii::t('boe', 'boe_subject_student_id'),
            's_integral' => Yii::t('boe', 'boe_subject_student_integral'),
            's_date' => Yii::t('boe', 'boe_subject_student_date'),
            's_sort' => Yii::t('boe', 'boe_subject_student_sort'),
            'version' => Yii::t('common', 'version'),
            'created_by' => Yii::t('common', 'created_by'),
            'created_at' => Yii::t('common', 'created_at'),
            'created_from' => Yii::t('common', 'created_from'),
            'updated_by' => Yii::t('common', 'updated_by'),
            'updated_at' => Yii::t('common', 'updated_at'),
            'updated_from' => Yii::t('common', 'updated_from'),
            'is_deleted' => Yii::t('common', 'is_deleted'),
        );
    }

    /* 根据日期获取最佳学员信息列表 */

    public function getDateStudentList($params = array(), $debug = 0) {
        $data = $user_array = array();
		$params = array(
			'orderBy' => 's_sort asc',
		);
        $sult = $this->getList($params, $debug);
        foreach ($sult as $s_key => $s_value) {
            $data['list'][$s_value['s_date']][] = $s_value;
            $user_array[] = $s_value['student_id'];
        }
		krsort($data['list']);
        $data['user_data'] = $this->getMoreUserInfo($user_array, 1);
        return $data;
    }

    public function getMoreUserInfo($user_name = NULL, $expand_info = 1, $field = '') {
        if (empty($user_name)) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
        $where[] = array(is_array($user_name) ? 'in' : '=', 'user_name', $user_name);
        $field = $field ? $field : 'real_name,nick_name,user_name,kid,email,user_no,orgnization_id,domain_id,company_id';

        $user_model = FwUser::find(false)->select($field);
        $user_info = $user_model->where($where)->indexby('user_name')->asArray()->all();
        if ($expand_info) {
            return BoeBaseService::parseUserListInfo($user_info);
        } else {
            return $user_info;
        }
    }
	
	public function getFrontendDateStudentList($params = array(),$create_mode = 0, $cache_time = 300) {
		$log_key_name = $cache_name = __METHOD__ . 'date_student_limit_' . $limit;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S
            if (!$create_mode && isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
                return $this->log[$log_key_name];
            }
            $this->log[$log_key_name] = $create_mode == 1 ? NULL : $this->getCache($cache_name);
            if (!$this->log[$log_key_name]) {//缓存中没有数据的时候S
                $params = array(
                    'orderBy' => 's_sort asc',
                    'indexBy' => 'student_id',
                );
                $tmp_list = $this->getDateStudentList($params);
				if ($tmp_list && is_array($tmp_list)) {
                    foreach ($tmp_list['list'] as $s_key => $s_value) {
						 foreach ($s_value as $ss_key => $ss_value) {
							  if (isset($tmp_list['user_data'][$ss_value['student_id']])) {
								  $tmp_list['list'][$s_key][$ss_key] = array_merge($ss_value, $tmp_list['user_data'][$ss_value['student_id']]);
								  $tmp_list['list'][$s_key][$ss_key]['image_url'] = $ss_value['image_url'] ? BoeBase::getFileUrl($ss_value['image_url'], 'subjectConfig') : '';
								  $orgnization_path = explode('\\', $tmp_list['user_data'][$ss_value['student_id']]['orgnization_path']);
								  if(count($orgnization_path)>2){
									  $orgnization_path=  array_slice($orgnization_path, -2);
								  }
                				  $tmp_list['list'][$s_key][$ss_key]['short_orgnization_path'] = $orgnization_path;  
							  } else {
								  unset($tmp_list['list'][$s_key][$ss_key]);
							  }
						 }	
                    }
                }
                $this->log[$log_key_name]=&$tmp_list['list'];
                if ($this->log[$log_key_name]) {
                    $this->setCache(__METHOD__, $this->log[$log_key_name], $cache_time); // 设置缓存
                }
            }//缓存中没有数据的时候S  
            return $this->log[$log_key_name];
        }//读取数据的时候E
    }

    public function getFrontendStudentList($limit = 10, $create_mode = 0, $cache_time = 300) {
        $log_key_name = $cache_name = __METHOD__ . '_limit_' . $limit;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S
            if (!$create_mode && isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
                return $this->log[$log_key_name];
            }
            $this->log[$log_key_name] = $create_mode == 1 ? NULL : $this->getCache($cache_name);
            if (!$this->log[$log_key_name]) {//缓存中没有数据的时候S
                $params = array(
                    'condition' => array(
                        array('<', 's_date', date('Y-m-d')),
                        array('>', 's_sort', 0),
                    ),
                    'orderBy' => 's_sort asc',
                    'indexBy' => 'student_id',
                    'limit' => $limit,
                );
                $tmp_list = $this->getList($params);
                if ($tmp_list && is_array($tmp_list)) {
                    $user_array = array();
                    foreach ($tmp_list as $s_key => $s_value) {
                        $user_array[] = $s_value['student_id'];
                    }
                    $tmp_user_data = $this->getMoreUserInfo($user_array, 1);
                    foreach ($tmp_list as $s_key => $s_value) {
                        if (isset($tmp_user_data[$s_value['student_id']])) {
                            $tmp_list[$s_key] = array_merge($s_value, $tmp_user_data[$s_value['student_id']]);
                        } else {
                            unset($tmp_list[$s_key]);
                        }
                    }
                }
                $this->log[$log_key_name]=&$tmp_list;
                if ($this->log[$log_key_name]) {
                    $this->setCache(__METHOD__, $this->log[$log_key_name], $cache_time); // 设置缓存
                }
            }//缓存中没有数据的时候S  
            return $this->log[$log_key_name];
        }//读取数据的时候E
    }

    /**
     * 获取最佳学员列表
     * @param type $params
     */
    public function getList($params = array(), $debug = 0) {

        if (!is_array($params)) {
            $params = array();
        }
        if (empty($params['select']) && empty($params['field'])) {
            $params['select'] = 'kid,student_id,s_integral,s_date,s_sort,image_url'
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
     * 根据ID获取最佳学员的详细或是某个字段的信息
     * @param type $id 最佳学员的ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        return $this->CommonGetInfo($id, $key, $create_mode, $debug);
    }

    public function saveInfo($data, $s_date = "", $debug = 0) {
        if ($s_date) {//修改
            $this->physicalDeleteAll(array('s_date' => $s_date)); //删除之前的记录
        }
        if (Yii::$app->user->isGuest) {
            $currentUserId = "00000000-0000-0000-0000-000000000000";
        } else {
            $currentUserId = strval(Yii::$app->user->getId());
        }
        $current_at = time();
        $systemKey = self::$defaultKey;
        $ip = Yii::$app->getRequest()->getUserIP();
        if ($data && is_array($data)) {
            foreach ($data as $key => $a_info) {
                $data[$key] = array(
                    'kid' => new Expression('UPPER(UUID())'),
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
                $data[$key] = array_merge($a_info, $data[$key]);
            }
            //return $data;
            Yii::$app->db->createCommand()->batchInsert(self::tableName(), array_keys($data[0]), $data)->execute();
        }
        return true;
    }

    /**
     * deleteInfo 
     * 根据ID删除单个最佳学员信息，
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

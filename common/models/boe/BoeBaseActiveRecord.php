<?php

namespace common\models\boe;

use common\base\BaseActiveRecord;
use common\base\BoeBase;
use Yii;

/**
 * Description of BoeBaseActiveRecord
 *
 * @author Zhengliu Kun
 */
class BoeBaseActiveRecord extends BaseActiveRecord {

    protected $log = array();
    protected $cacheNameFix = "boe_";
    protected $createdByIdField = "created_by";
    protected $tablePrimaryKey = 'kid'; //当前表的主键 
    protected $keywordMaxNum = 10;
    protected $hasKeyword = false;
    protected $InfoDetailCacheNameFix = NULL;

    function __get($name) {
        switch ($name) {
            case 'tablePrimaryKey': //获取当前表的主键
                $tmp_info = $this->primaryKey();
                if ($tmp_info && is_array($tmp_info)) {
                    return current($tmp_info);
                }
                return 'kid';
                break;
            default:
                return parent::__get($name);
                break;
        }
    }

    /**
     * 读取缓存的封装
     * @param type $cache_name
     * @param type $debug
     * @return type
     */
    protected function getCache($cache_name, $debug = 0) {
        $new_cache_name = $this->cacheNameFix . $cache_name;
        $sult = Yii::$app->cache->get($new_cache_name);
        if ($debug) {
            echo "<pre>\nRead Info From Cache,Cache Name={$new_cache_name}\n";
            if ($sult) {
                print_r($sult);
            } else {
                print_r("Cache Not Hit");
            }
            echo "\n</pre>";
        }
        return $sult;
    }

    /**
     * 修改缓存的封装
     * @param type $cache_name
     * @param type $data
     * @param type $time
     * @param type $debug
     */
    protected function setCache($cache_name, $data = NULL, $time = 0, $debug = 0) {
        $new_cache_name = $this->cacheNameFix . $cache_name;
        Yii::$app->cache->set($new_cache_name, $data, $time); // 设置缓存
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }

    /**
     * 删除缓存的封装
     * @param type $cache_name
     */
    protected function deleteCache($cache_name) {
        $new_cache_name = $this->cacheNameFix . $cache_name;
        Yii::$app->cache->delete($new_cache_name); // 删除缓存 
    }

    /**
     * getList
     * 根据$params获取列表信息
     * $params=array(
      'condition'=>array(),
      'orderby'=>array(),
      'limit'=>0,
      'offset'=>0,
      'select'=>0,
      )
     * @return array
     */
    public function getList($params = array()) {
        $condition = empty($params['condition']) ? NULL : $params['condition'];
        $where = empty($params['where']) ? NULL : $params['where'];
        $addParams = empty($params['addParams']) ? NULL : $params['addParams'];
        $orderBy = empty($params['orderBy']) ? (empty($params['orderby']) ? NULL : $params['orderby']) : $params['orderBy'];
        $limit = empty($params['limit']) ? 0 : intval($params['limit']);
        $offset = empty($params['offset']) ? 0 : intval($params['offset']);
        $select = empty($params['select']) ? (empty($params['field']) ? '' : $params['field']) : $params['select'];
        $index = empty($params['indexBy']) ? (empty($params['indexby']) ? '' : $params['indexby']) : $params['indexBy'];
        $debug = empty($params['debug']) ? 0 : intval($params['debug']);
        $cache_time = intval(empty($params['cache_time']) ? (empty($params['cacheTime']) ? '' : $params['cacheTime']) : $params['cache_time']);
        $show_deleted = boolval(empty($params['show_deleted']) ? (empty($params['showDeleted']) ? false : $params['showDeleted']) : $params['show_deleted']);
        $return_total_count = intval(!isset($params['return_total_count']) ? (!isset($params['returnTotalCount']) ? '' : $params['returnTotalCount']) : $params['return_total_count']);
        $sult = array();
        if ($debug) {
            $cache_time = 0;
        }
        if ($cache_time) {//需要缓存时,拼接缓存Key
            $cache_name = 'condition_' . var_export($condition, true);
            $cache_name.='where_' . var_export($where, true);
            $cache_name.='addParams_' . var_export($addParams, true);
            $cache_name.='orderBy_' . var_export($orderBy, true);
            $cache_name.='limit_' . var_export($limit, true);
            $cache_name.='offset_' . var_export($offset, true);
            $cache_name.='select_' . var_export($select, true);
            $cache_name.='index_' . var_export($index, true);
            $cache_name = md5($cache_name);
            $sult = $this->getCache($cache_name, $debug);
        }
        if (!$sult) {//缓存中没有数据或是不使用缓存时S
            $model = $this->find($show_deleted);
            if (is_array($addParams) && $addParams && $where) {
                $model->where($where);
                $model->addParams($addParams);
            }
            if (is_array($condition) && $condition) {
                foreach ($condition as $a_condition) {
                    $model->andFilterWhere($a_condition);
                }
            }
            $sult['totalCount'] = $model->count();
            if ($select) {
                $model->select($select);
            }
            if ($orderBy) {
                $model->orderBy($orderBy);
            }
            if ($offset) {
                $model->offset($offset);
            }
            if ($limit) {
                $model->limit($limit);
            }
            if ($index) {
                $model->indexBy($index);
            }
            $sult['list'] = $model->asArray()->all();
            $sult['sql'] = $model->createCommand()->getRawSql();
            if ($debug) {
                print_r($sult['sql'] . "\n--------------------------------------------\n");
            } else {
                if ($cache_time) {//需要缓存时S
                    $this->setCache($cache_name, $sult, $cache_time, $debug);
                }//需要缓存时E
            }
        } //缓存中没有数据或是不使用缓存时E

        if (!$return_total_count) {
            $sult = $sult['list'];
        }
        return $sult;
        //$data->offset($pages->offset)->limit($pages->limit)->asArray()->all();
    }

    /**
     * CommonGetInfo
     * 根据ID获取详细信息或是某个字段的信息
     * @param type $id 数据ID
     * @param type $key 
     */
    protected function CommonGetInfo($id = 0, $key = '*', $create_mode = 0, $debug = 0) {
        if (!$id) {
            return NULL;
        }
        if($this->InfoDetailCacheNameFix){
            $cache_name=$this->InfoDetailCacheNameFix;
        }else{
            $cache_name=end(explode('\\',get_class($this))).'_detail_info_';
        }
        $cache_name .=$id;
        if ($create_mode == 2) {//删除缓存模式时S
            $this->deleteCache($cache_name);
        } else {//读取数据的时候S
            $log_key_name = $cache_name . "_field_" . $key;
            if (!$create_mode && isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
                return $this->log[$log_key_name];
            }
            $cacheSult = $create_mode == 1 ? NULL : $this->getCache($cache_name, $debug);
            if (!$cacheSult) {//缓存中没有数据的时候S
                $cacheSult = $this->find(false)->where([$this->tablePrimaryKey => $id])->asArray()->one();
                if ($cacheSult) {
                    if ($this->hasKeyword) {
                        //合并关键词S
                        $cacheSult = $this->parseKeywordToString($cacheSult);
                    }
                    //合并关键词E
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

    /**
     * CommonSaveInfo保存数据
     * @param type $data
     * @param type $debug
     * @return type
     */
    public function CommonSaveInfo($data, $debug = 0) {
        $currnetKid = NULL;
        $opreateSult = false;
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
                print_r("<pre>\n");
                print_r("最终结果:\n" . var_export($currnetKid, true) . "\n");
                print_r("参数:\n");
                print_r($data);
                print_r("错误\n");
                print_r($error);
                print_r("</pre>");
            } else {
                return $error;
            }
        }
        return $currnetKid;
    }

    /**
     * CommonDeleteInfo 
     * 根据ID删除单个文档信息，
     * @param type $id
     * @param type $user_id 
     * @param type $physicalDelete 是否进行物理删除
     * @return int 删除结果如下
     * 1=成功
     * -1=数据库操作失败
     */
    protected function CommonDeleteInfo($id = 0, $user_id = 0, $physicalDelete = 0) {
        if (!$id) {
            return 0;
        }
        $tmp_arr = array(';', '、', '；', ',');
        $id_array = is_array($id) ? $id : explode(',', str_replace($tmp_arr, ',', $id));
        if ($physicalDelete) {//物理删除
            $delete_p = array(
                'and',
                array('in', 'kid', $id_array)
            );
            if ($user_id) {
                $delete_p[] = array('=', $this->createdByIdField, $user_id);
            }
            $delete_sult = $this->physicalDeleteAll($delete_p);
        } else {
            $id_array = "'" . implode("','", $id_array) . "'";
            $id_array = BoeBase::parseSafeKidString($id_array);
            $delete_p = "kid in({$id_array})";
            if ($user_id) {
                $user_id = BoeBase::parseSafeKidString($user_id);
                $delete_p.=" and " . $this->createdByIdField . "='{$user_id}'";
            }
            $delete_sult = $this->deleteAll($delete_p);
        }
        if ($delete_sult) {//删除成功
            foreach ($id_array as $a_info) {
                $this->CommonGetInfo($a_info, '*', 2); //删除的缓存
            }
            return 1;
        }
        return -1;
    }

//---------------几个和关键词有关的方法------------------------------
    /**
     * 将多个关键词字段合并时一个字段
     * @param array $news_info
     */
    public function parseKeywordToString($news_info) {
        $tmp_keyword = array();
        for ($tmp_i = 1; $tmp_i <= $this->keywordMaxNum; $tmp_i++) {
            if (isset($news_info['keyword' . $tmp_i]) && trim($news_info['keyword' . $tmp_i])) {
                $tmp_keyword[] = $news_info['keyword' . $tmp_i];
                unset($news_info['keyword' . $tmp_i]);
            }
        }
        if ($tmp_keyword) {
            $news_info['keyword'] = implode(';', $tmp_keyword);
        }
        return $news_info;
    }

    /**
     * 拼装出关键词搜索条件
     * @param type $keyword
     * @return boolean
     */
    public function parseKeywordCondition($keyword = '') {
        $sult = NULL;
        if ($keyword) {
            $sult = array('or');
            for ($tmp_i = 1; $tmp_i <= $this->keywordMaxNum; $tmp_i++) {
                $sult[] = array('like', 'keyword' . $tmp_i, $keyword . '%', false);
            }
        }
        return $sult;
    }

    /**
     * 组装出供save_info用的关键词信息
     * @param type $data
     */
    public function parseKeywordSaveArray($data = array()) {
        if (!is_array($data)) {
            return array();
        }
        if (!empty($data['keyword'])) {
            //拼装关键词到数据库S
            $data['keyword'] = explode(',', str_replace(array(';', '、', '；', ','), ',', $data['keyword']));
            $tmp_i = 1;
            foreach ($data['keyword'] as $key => $a_keyword) {
                $a_keyword = trim($a_keyword);
                if ($a_keyword) {
                    $data['keyword' . $tmp_i] = $a_keyword;
                    $tmp_i++;
                    if ($tmp_i > $this->keywordMaxNum) {
                        break;
                    }
                }
            }
            for ($tmp_i = 1; $tmp_i <= $this->keywordMaxNum; $tmp_i++) {
                if (!isset($data['keyword' . $tmp_i])) {
                    $data['keyword' . $tmp_i] = '';
                }
            }
//             BoeBase::debug($data,1);
//拼装关键词到数据库E
        }
        if (isset($data['keyword'])) {
            unset($data['keyword']);
        }
        return $data;
    }

}

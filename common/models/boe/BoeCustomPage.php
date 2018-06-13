<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use common\base\BoeBase;
use yii\db\Expression;
use Yii;

/**
 * This is the model class for table "eln_boe_custom_page".
 *
 * @property string $kid
 * @property string $title
 * @property string $name
 * @property string $parent_id
 * @property integer $sort
 * @property string $config
 * @property string $abstract
 * @property string $content
 * @property string $image_url
 * @property integer $has_image
 * @property integer $recommend_sort1
 * @property integer $recommend_sort2
 * @property integer $recommend_sort3
 * @property integer $recommend_sort4
 * @property integer $recommend_sort5
 * @property integer $recommend_sort6
 * @property integer $recommend_sort7
 * @property integer $recommend_sort8
 * @property integer $recommend_sort9
 * @property integer $recommend_sort10
 * @property string $keyword1
 * @property string $keyword2
 * @property string $keyword3
 * @property string $keyword4
 * @property string $keyword5
 * @property string $keyword6
 * @property string $keyword7
 * @property string $keyword8
 * @property string $keyword9
 * @property string $keyword10
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
class BoeCustomPage extends BoeBaseActiveRecord {

    protected $hasKeyword = true;
    private $allInfo = NULL;
    private $baseTree = NULL;
    private $maxLevel = 100; //无限级子页面最大的深度 

    /**
     * @inheritdoc
     */

    public static function tableName() {
        return 'eln_boe_custom_page';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            //  [['kid', 'created_by', 'created_at'], 'required'],
            [['name'], 'required'],
            [['config', 'content'], 'string'],
            [['has_image', 'sort', 'recommend_sort1', 'recommend_sort2', 'recommend_sort3', 'recommend_sort4', 'recommend_sort5', 'recommend_sort6', 'recommend_sort7', 'recommend_sort8', 'recommend_sort9', 'recommend_sort10', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'keyword1', 'keyword2', 'keyword3', 'keyword4', 'keyword5', 'keyword6', 'keyword7', 'keyword8', 'keyword9', 'keyword10', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['title', 'name', 'abstract', 'image_url'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => 'kid',
            'title' => Yii::t('boe', 'custom_page_title'),
            'name' => Yii::t('boe', 'custom_page_name'),
            'parent_id' => Yii::t('boe', 'custom_page_parent_id'),
            'sort' => Yii::t('boe', 'custom_page_sort'),
            'config' => Yii::t('boe', 'custom_page_config'),
            'abstract' => Yii::t('boe', 'custom_page_abstract'),
            'content' => Yii::t('boe', 'custom_page_content'),
            'image_url' => Yii::t('boe', 'custom_page_image_url'),
            'has_image' => 'Has Image',
            'recommend_sort1' => 'Recommend Sort1',
            'recommend_sort2' => 'Recommend Sort2',
            'recommend_sort3' => 'Recommend Sort3',
            'recommend_sort4' => 'Recommend Sort4',
            'recommend_sort5' => 'Recommend Sort5',
            'recommend_sort6' => 'Recommend Sort6',
            'recommend_sort7' => 'Recommend Sort7',
            'recommend_sort8' => 'Recommend Sort8',
            'recommend_sort9' => 'Recommend Sort9',
            'recommend_sort10' => 'Recommend Sort10',
            'keyword1' => 'Keyword1',
            'keyword2' => 'Keyword2',
            'keyword3' => 'Keyword3',
            'keyword4' => 'Keyword4',
            'keyword5' => 'Keyword5',
            'keyword6' => 'Keyword6',
            'keyword7' => 'Keyword7',
            'keyword8' => 'Keyword8',
            'keyword9' => 'Keyword9',
            'keyword10' => 'Keyword10',
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
     * getAll获取全部的自定义页面信息
     * @param type $create_mode 是否强制从数据库读取
     * @param type $debug 调试模式
     */
    public function getAll($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//从数据库读取 
            $sult = $this->find(false)->orderBy('parent_id asc,sort asc')->select('kid,name,title,parent_id,sort')->asArray()->indexBy($this->tablePrimaryKey)->all();
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }

    /**
     * getInfo
     * 根据ID获取子页面的详细或是某个字段的信息
     * @param type $id 子页面的ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*') {
        if (!$id) {
            return NULL;
        }
        $current_kid = NULL;
        if (strpos($id, '-') !== false) {//Kid去找的时候
            $current_kid = $id;
        } else {
            $all_page = $this->getAll();
            if ($all_page && is_array($all_page)) {
                foreach ($all_page as $a_info) {
                    if ($a_info['name'] == $id) {
                        $current_kid = $a_info['kid'];
                        break;
                    }
                }
            }
            if (!$current_kid) {
                return NULL;
            }
        }
        return $this->CommonGetInfo($current_kid, $key, $create_mode, $debug);
    }

    /**
     * getList
     * 根据$params获取子页面的详细列表信息
     * $params=array(
      'condition'=>array(), //条件语句
      'orderby'=>array(),//排序语句 array()
      'limit'=>array(),//限制数量
      )

     * @return array or NULL
     * 调用示例：
      $dbObj = new BoeNewsCategory();
      $p=array(
      'condition' = array(
      'list_sort' => '>0',
      'detail_sort' => '>0',
      ),
      'orderby'=>array(
      'list_sort' => 'desc',
      'detail_sort' => 'asc',
      ),
      'limit'=>0
      );
      $data = $dbObj->getList($p);
      print_r($data);
     */
    public function getList($params = array()) {
        $condition = empty($params['condition']) ? NULL : $params['condition'];
        $orderby = empty($params['orderby']) ? NULL : $params['orderby'];
        $limit = empty($params['limit']) ? 0 : intval($params['limit']);
        if (!$condition) {
            return NULL;
        }
        $log_key_name = __METHOD__ . md5(serialize($condition) . serialize($orderby) . "_{$limit}");
        if (isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
            return $this->log[$log_key_name];
        }
        $this->log[$log_key_name] = false;
        if (!$this->allInfo) {//未初始化全部子页面信息时
            $this->allInfo = $this->getAll();
        }
        $sort_info = $tmp_sult = array();
        $sort_info['sort'] = array();
        $tmp_check = true;
        $i = 0;
        $php_code = '';
        foreach ($this->allInfo as $key => $a_info) {
            $tmp_check = true;
            if ($condition) {
                foreach ($condition as $a_key => $a_value) {
                    if ($tmp_check && (strpos($a_value, '>') !== false || strpos($a_value, '<') !== false)) {
                        $php_code = '$tmp_check=$a_info[$a_key]' . "{$a_value};";
                    } else {
                        $php_code = '$tmp_check=$a_info[$a_key]' . "=={$a_value};";
                    }
                    eval($php_code);
//                    print_r($php_code . "\$a_info[{$a_key}]={$a_info[$a_key]}{$a_value}tmp_check:{$tmp_check}<br />\n");
                    if (!$tmp_check) {//只要有一个条件不满足,就跳出
                        break;
                    }
                }
            }
            if ($tmp_check) {//匹配成功 S
                $sort_info['sort'][$key] = $a_info['sort'];
                $tmp_sult[$key] = $a_info;
                if ($limit) {
                    $i++;
                    if ($i == $limit) {
                        break;
                    }
                }
            }//匹配成功 E
        }
        if ($tmp_sult) {
            if ($orderby) {//需要排序时S
                $p = array();
                foreach ($orderby as $key => $a_info) {
                    if (isset($sort_info[$key])) {
                        $p[] = '$sort_info[\'' . $key . '\']';
                        $a_info = strtolower($a_info);
                        if (strpos($a_info, 'asc') !== false) {
                            $p[] = SORT_ASC;
                        } else {
                            $p[] = SORT_DESC;
                        }
                    }
                }
                if ($p) {
                    $p[] = '$tmp_sult';
                    $php_code = 'array_multisort(' . implode(',', $p) . ');';
                    eval($php_code);
                }
            }//需要排序时E
        }
        $this->log[$log_key_name] = $tmp_sult;
        return $this->log[$log_key_name];
    }

    /**
     * getSubId
     * 根据ID获取子页面的子子孙孙信息，例如：获取江苏的所有的城市时，将会把南京、苏州、工业园区、无锡等信息全部读取出来
     * @param type $id
     * @param type $return_key 如果只返回特定的数组内容，可指定该项
     * @param type $cache_time 缓存时间
     * @param type $debug
     * @return type
     */
    public function getSubId($id = 0, $return_key = 0, $cache_time = 0, $debug = 0) {
        $log_key_name = __METHOD__ . '_' . $id;
        if (isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
            return $this->log[$log_key_name];
        } else {
            if ($cache_time) {//如果需要有缓存的时候
                $this->log[$log_key_name] = $this->getCache($log_key_name, $debug);
            } else {
                $this->log[$log_key_name] = NULL;
            }
        }
        if ($this->log[$log_key_name] === NULL || $this->log[$log_key_name] === false) {//没有数据的时候
            if (!$this->baseTree) {
                $this->baseTree = $this->getBaseTree();
            }
            $this->log[$log_key_name] = array();
            $tmp_info = $this->getInfo($id);
            if ($tmp_info) {
                $this->log[$log_key_name][$id] = $tmp_info;
                $this->log[$log_key_name] = array_merge($this->log[$log_key_name], $this->ParseTreeToBaseArray($id));
            } else {
                $this->log[$log_key_name] = array();
            }
            // $this->baseTree = NULL;
            if ($cache_time) {//配置了缓存的时间时
                $this->setCache($log_key_name, $this->log[$log_key_name], $cache_time, $debug); // 设置缓存
            }
        }
        if ($return_key && is_array($this->log[$log_key_name]) && $this->log[$log_key_name]) {//如果只返回特定的数组内容if ($return_key && is_array($this->log[$log_key_name]) && $this->log[$log_key_name]) {//如果只返回特定的数组内容
            $tmp_sult = array();
            foreach ($this->log[$log_key_name] as $key => $a_info) {
                $tmp_sult[] = isset($a_info[$return_key]) ? $a_info[$return_key] : $key;
            }
            return $tmp_sult;
        }
        return $this->log[$log_key_name];
    }

    /**
     * ParseTreeToBaseArray
     *  根据传入子页面ID，得到其子子孙孙的组成的平行数组，
     * @param int $id
     * @return array
     */
    private function ParseTreeToBaseArray($parent_id = 0) {
        $sult = array();
        if (isset($this->baseTree[$parent_id])) {//有下一级的 
            $tmp_sult = $this->baseTree[$parent_id];
            foreach ($tmp_sult as $key => $a_info) {
                $tmp_sult = array_merge($tmp_sult, call_user_func(__METHOD__, $key));
            }
            $sult = $tmp_sult;
        }
        return $sult;
    }

    /**
     * getParentId
     * 根据ID获取子页面的祖祖辈辈的上级信息，例如：获取工业园区的所有上级时，将会把 华中 江苏， 苏州，  工业园区
     * @param type $id
     * @param type $return_key 如果只返回特定的数组内容，可指定该项
     * @param type $cache_time 缓存时间
     * @param type $debug
     * @return type
     */
    public function getParentId($id = 0, $return_key = '', $cache_time = 0, $debug = 0) {
        $log_key_name = __METHOD__ . '_' . $id;
        if (!isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回 
            if ($cache_time) {//如果需要有缓存的时候
                $this->log[$log_key_name] = $this->getCache($log_key_name, $debug);
            } else {
                $this->log[$log_key_name] = NULL;
            }
        }
        if ($this->log[$log_key_name] === NULL || $this->log[$log_key_name] === false) {//没有数据的时候
            if (!$this->baseTree) {
                $this->baseTree = $this->getBaseTree();
            }
            $this->log[$log_key_name] = array();
            $tmp_info = $this->getInfo($id);
            if ($tmp_info) {//信息是存在的
                $this->log[$log_key_name][$id] = $tmp_info;
                $loop_i = 0;
                while ($tmp_info['parent_id'] != '0' && $loop_i < $this->maxLevel) {
                    $tmp_info = $this->getInfo($tmp_info['parent_id']);
                    if ($tmp_info) {
                        $this->log[$log_key_name][$tmp_info[$this->tablePrimaryKey]] = $tmp_info;
                    } else {
                        break;
                    }
                    $loop_i++;
                }
            } else {
                $this->log[$log_key_name] = array();
            }
            if ($this->log[$log_key_name]) {
                $this->log[$log_key_name] = array_reverse($this->log[$log_key_name], true); //数组倒序
            }
            if ($cache_time) {//配置了缓存的时间时
                $this->setCache($log_key_name, $this->log[$log_key_name], $cache_time, $debug); // 设置缓存
            }
        }
     
        if ($return_key && is_array($this->log[$log_key_name]) && $this->log[$log_key_name]) {//如果只返回特定的数组内容 
            $tmp_sult = array();
            foreach ($this->log[$log_key_name] as $key => $a_info) {
                $tmp_sult[] = isset($a_info[$return_key]) ? $a_info[$return_key] : $key;
            }
            return $tmp_sult;
        }
        return $this->log[$log_key_name];
    }

    /**
     * getHasClidTree
     * 根据ID判断某个子页面是否有子子页面
     * @param type $id
     * @return boolean
     */
    public function getHasClidTree($id) {
        $sult = false;
        if (!$this->baseTree) {
            $this->baseTree = $this->getBaseTree();
        }
        $sult = isset($this->baseTree[$id]);
        return $sult;
    }

    /**
     * getBaseTree 获取最简单的子页面树形结构 
     * @param type $create_mode 是否强制从数据库读取
     * @param type $debug 调试模式
     */
    public function getBaseTree($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {
            if (!$this->allInfo || $create_mode) {//未初始化全部子页面信息时
                $this->allInfo = $this->getAll($create_mode);
            }
            $sult = array();
            foreach ($this->allInfo as $key => $a_info) {
                if (!isset($sult[$a_info['parent_id']])) {
                    $sult[$a_info['parent_id']] = array();
                }
                //  unset($a_info['sort']);
                $a_info['preview_url'] = BoeBase::getBoeUrl(array('name' => $a_info['name'] ? $a_info['name'] : $a_info['kid']), 'CustomPageDetail');
                if (isset($a_info['content'])) {
                    unset($a_info['content']);
                }
                if (isset($a_info['abstract'])) {
                    unset($a_info['abstract']);
                }
                $sult[$a_info['parent_id']][$key] = $a_info;
            }
            $this->setCache(__METHOD__, $sult, 0, $debug);
        }
        return $sult;
    }

    /**
     * getDetailTree 获取完整的自定义页面的树形
     * @param type $create_mode
     * @param type $debug
     */
    public function getDetailTree($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//拼接成读取 
            if (!$this->baseTree || $create_mode) {
                $this->baseTree = $this->getBaseTree($create_mode);
            }
            $sult = $this->ParseTree(0);
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }

    /**
     * ParseTree
     *  根据传入子页面ID，得到子树
     * @param int $id
     * @return array
     */
    private function ParseTree($parent_id = 0) {
        $sult = array();
        if (isset($this->baseTree[$parent_id])) {//有下一级的 
            $tmp_sult = $this->baseTree[$parent_id];
            foreach ($tmp_sult as $key => $a_info) {
                $tmp_sult[$key]['sub_cate'] = call_user_func(__METHOD__, $key);
            }
            $sult = $tmp_sult;
        }
        return $sult;
    }

    /**
     * 获取一个Opitons的树形数组，将kid的子子子页面过滤掉
     * @param type $kid
     * @return type
     */
    public function getScatterTreeArrayAdv($kid = 0) {
        $tree_info = $this->getScatterTreeArray(1);
        if ($kid) {//传递了子页面ID时，就需要将其子子孙孙的ID过滤掉
            $allSubInfo = $this->getSubId($kid, 'kid');
            foreach ($tree_info as $key => $a_info) {
                if (in_array($a_info['kid'], $allSubInfo)) {//解决自己将自己设定父子页面的问题，或将自己的子子页面设定自己的父子页面
                    unset($tree_info[$key]);
                }
            }
        }
        return $tree_info;
    }

    /**
     * getScatterTreeArray
     * 将getDetailTree生成折叠的树形菜单全部打开为一个平行数组，一般用在表单select时使用Options
     */
    public function getScatterTreeArray($create_mode = 0, $debug = 0) {
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = $this->getCache(__METHOD__, $debug); // 读取缓存 ,读取不到时返回false
        }
        if (!$sult) {//拼接成读取  
            $sult = $this->parseTreeToScatter($this->getDetailTree());
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }

    /**
     * 将生成折叠的树形菜单全部打开
     */
    private function parseTreeToScatter($tree = array(), $add_text = '[tab]') {
        $sult = array();
        if (is_array($tree) && !empty($tree)) {
            foreach ($tree as $key => $a_info) {
                $a_info['title'] = $add_text . $a_info['title'];
                $new_add_text = $add_text . $add_text;
                $sult[$key] = $a_info;
                unset($sult[$key]['sub_cate']);
                if (isset($a_info['sub_cate']) && is_array($a_info['sub_cate'])) {//读取下级子页面S
                    $sult = array_merge($sult, call_user_func_array(__METHOD__, array($a_info['sub_cate'], $new_add_text)));
                }//读取下级子页面E
            }
        }
        return $sult;
    }

//-----------------------1大堆和写数据库有关的方法开始------------------------------------------- 
    public function saveInfo($data, $debug = 0) {
        $currnetKid = NULL;
        $opreateSult = false;
        if ($this->hasKeyword) {
            $data = $this->parseKeywordSaveArray($data);
        }
        $error = NULL;
        if (!empty($data[$this->tablePrimaryKey])) {//修改的时候  
            $currnetKid = $data[$this->tablePrimaryKey];
            $allSubInfo = $this->getSubId($currnetKid, 'kid'); //找到子子孙孙
            if (in_array($data['parent_id'], $allSubInfo)) {//如果自己将自己设定父子页面的问题，或将自己的子子页面设定自己的父子页面
                $opreateSult = false;
                $currnetKid = false;
            } else {
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
            }
            $this->reCreateTree(); //重建缓存
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
     * deleteInfo 
     * 根据ID删除相应子页面信息，
     * @param type $id
     * @return int 删除结果如下
     * 1=成功
     * -1=有子子页面
     * -2=子页面信息不存在
     * -3=数据库操作失败
     */
    public function deleteInfo($id = 0) {
        if (!$id) {
            return 0;
        }
        $cate_info = $this->getInfo($id);

        if (!$cate_info) {//信息不存在了
            return -2;
        }
        if ($this->getHasClidTree($id)) {//有子子页面的
            return -1;
        }
        if ($this->CommonDeleteInfo($id, 0, 1)) {//删除成功
            $this->reCreateTree(); //重建缓存
            return 1;
        } else {
            return -3;
        }
    }

    /**
     * recreateTree
     * 重建子页面的树形信息
     */
    public function reCreateTree() {
        $this->getAll(1); //重新缓存全部子页面信息
        $this->getBaseTree(1); //重新缓存简单的子页面树形结构
        $this->getDetailTree(1); //重新缓存详细的子页面树形结构
        $this->getScatterTreeArray(1); //重新散烈的子页面树形结构
    }

}

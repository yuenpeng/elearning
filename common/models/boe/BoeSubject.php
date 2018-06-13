<?php

namespace common\models\boe;

use common\models\boe\BoeBaseActiveRecord;
use Yii;

/**
 * This is the model class for table "eln_boe_subject".
 *
 * @property string $kid
 * @property string $name
 * @property string $parent_id
 * @property integer $list_sort
 * @property integer $detail_sort
 * @property integer $index_sort
 * @property integer $sort
 * @property string $keywords
 * @property string $descript
 * @property string $title
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
class BoeSubject extends BoeBaseActiveRecord {

    private $allInfo = NULL;
    private $baseTree = NULL;
    private $maxLevel = 100; //无限级分类最大的深度 

    /**
     * @inheritdoc
     */

    public static function tableName() {
        return 'eln_boe_subject';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['name'], 'required'],
            [['list_sort', 'detail_sort', 'index_sort', 'sort', 'version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'name', 'parent_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['keywords', 'descript', 'title'], 'string', 'max' => 255],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'subject_kid'),
            'name' => Yii::t('boe', 'subject_name'),
			'tag' => Yii::t('boe', 'subject_tag'),
            'parent_id' => Yii::t('boe', 'subject_parent_id'),
            'sort' => Yii::t('boe', 'subject_sort'),
            'list_sort' => Yii::t('boe', 'subject_list_sort'),
            'detail_sort' => Yii::t('boe', 'subject_detail_sort'),
            'index_sort' => Yii::t('boe', 'subject_index_sort'),
            'keywords' => Yii::t('boe', 'subject_keywords'),
            'descript' => Yii::t('boe', 'subject_descript'),
            'title' => Yii::t('boe', 'subject_title'),
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
     * getAll获取全部的专题信息分类信息
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
            $sult = $this->find(false)->orderBy('parent_id asc,sort asc')->asArray()->indexBy($this->tablePrimaryKey)->all();
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }
	
	
	/**
     * getInfo
     * 根据ID获取分类的详细或是某个字段的信息
     * @param type $id 分类的ID
     * @param type $key 
     */
    public function getInfo($id = 0, $key = '*') {
        if (!$id) {
            return NULL;
        }
        $log_key_name = __METHOD__ . $id . "_field_" . $key;
        if (isset($this->log[$log_key_name])) {//当前线程已有相关的数据时直接返回
            return $this->log[$log_key_name];
        }
        $this->log[$log_key_name] = false;
        if (!$this->allInfo) {//未初始化全部分类信息时
            $this->allInfo = $this->getAll();
        }
		if (strpos($id, '-') !== false) {//Kid去找的时候
			if (isset($this->allInfo[$id])) {
				if ($key != "*" && $key != '') {//返回某一个字段的值，比如名称
					$this->log[$log_key_name] = isset($this->allInfo[$id][$key]) ? $this->allInfo[$id][$key] : NULL;
				} else {
					$this->log[$log_key_name] = $this->allInfo[$id]; //返回全部信息
				}
			}	
        } else {
            $all_page = $this->allInfo;
            if ($all_page && is_array($all_page)) {
                foreach ($all_page as $a_info) {
                    if ($a_info['tag'] == $id) {
						if ($key != "*" && $key != '') {//返回某一个字段的值，比如名称
							$this->log[$log_key_name] = isset($a_info[$key]) ? $a_info[$key] : NULL;
						} else {
							$this->log[$log_key_name] = $a_info; //返回全部信息
						}
                        break;
                    }
                }
            }
        }
        return $this->log[$log_key_name];
    }

    /**
     * getList
     * 根据$params获取分类的详细列表信息
     * $params=array(
      'condition'=>array(), //条件语句
      'orderby'=>array(),//排序语句 array()
      'limit'=>array(),//限制数量
      )

     * @return array or NULL
     * 调用示例：
      $dbObj = new BoeSubject();
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
        if (!$this->allInfo) {//未初始化全部分类信息时
            $this->allInfo = $this->getAll();
        }
        $sort_info = $tmp_sult = array();
        $sort_info['sort'] = array();
        $sort_info['list_sort'] = array();
        $sort_info['index_sort'] = array();
        $sort_info['detail_sort'] = array();
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
                $sort_info['detail_sort'][$key] = $a_info['detail_sort'];
                $sort_info['list_sort'][$key] = $a_info['list_sort'];
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
     * 根据ID获取分类的子子孙孙信息，例如：获取江苏的所有的城市时，将会把南京、苏州、工业园区、无锡等信息全部读取出来
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
     * getSubTag
     * 根据Tag获取基本的树形菜单
     */
	 public function getBaseTreeFromTag($tag = '', $debug = 0) {
		  //$tag_info	=$this->find(false)->where(array('tag'=>$tag))->asArray()->one();
		  $kid	= $this->getInfo($tag,'kid');
		  $sult		= self::getDetailTree();
		  return $sult[$kid]; 
	 }
	
	/**
     * getSubTag
     * 根据Tag获取分类的子子孙孙信息，例如：获取江苏的所有的城市时，将会把南京、苏州、工业园区、无锡等信息全部读取出来
     * @param type $tag
     * @param type $return_key 如果只返回特定的数组内容，可指定该项
     * @param type $cache_time 缓存时间
     * @param type $debug
     * @return type
     */
    public function getSubTag($tag = '', $return_key = 0,$hastagid=0, $cache_time = 0, $debug = 0) {
        $log_key_name = __METHOD__ . '_' . $tag;
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
			$id	= $this->getInfo($tag,'kid');
            $tmp_info = $this->getInfo($id);
			//return $tmp_info;
            if ($tmp_info) {
                if($hastagid>0)
				{
					$this->log[$log_key_name][$id] = $tmp_info;
                	$this->log[$log_key_name] = array_merge($this->log[$log_key_name], $this->ParseTreeToBaseArray($id));
				}
				else
				{
					$this->log[$log_key_name] = $this->ParseTreeToBaseArray($id);
				}
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
     *  根据传入分类ID，得到其子子孙孙的组成的平行数组，
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
     * 根据ID获取分类的祖祖辈辈的上级信息，例如：获取工业园区的所有上级时，将会把 华中 江苏， 苏州，  工业园区
     * @param type $id
     * @param type $return_key 如果只返回特定的数组内容，可指定该项
     * @param type $cache_time 缓存时间
     * @param type $debug
     * @return type
     */
    public function getParentId($id = 0, $return_key = '', $cache_time = 0, $debug = 0) {
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
     * 根据ID判断某个分类是否有子分类
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
     * getBaseTree 获取最简单的分类树形结构 
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
            if (!$this->allInfo) {//未初始化全部分类信息时
                $this->allInfo = $this->getAll();
            }
            $sult = array();
            foreach ($this->allInfo as $key => $a_info) {
                if (!isset($sult[$a_info['parent_id']])) {
                    $sult[$a_info['parent_id']] = array();
                }
                //  unset($a_info['sort']);
                $sult[$a_info['parent_id']][$key] = $a_info;
            }
            $this->setCache(__METHOD__, $sult, 0, $debug);
        }
        return $sult;
    }

    /**
     * getDetailTree 获取完整的专题信息分类的树形
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
            if (!$this->baseTree) {
                $this->baseTree = $this->getBaseTree();
            }
            $sult = $this->ParseTree(0);
            $this->setCache(__METHOD__, $sult, 0, $debug); // 设置缓存
        }
        return $sult;
    }

    /**
     * ParseTree
     *  根据传入分类ID，得到子树
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
     * 获取一个Opitons的树形数组，将kid的子子分类过滤掉
     * @param type $kid
     * @return type
     */
    public function getScatterTreeArrayAdv($kid = 0) {
        $tree_info = $this->getScatterTreeArray();
        if ($kid) {//传递了分类ID时，就需要将其子子孙孙的ID过滤掉
            $allSubInfo = $this->getSubId($kid, 'kid');
            foreach ($tree_info as $key => $a_info) {
                if (in_array($a_info['kid'], $allSubInfo)) {//解决自己将自己设定父分类的问题，或将自己的子分类设定自己的父分类
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
                $a_info['name'] = $add_text . $a_info['name'];
                $new_add_text = $add_text . $add_text;
                $sult[$key] = $a_info;
                unset($sult[$key]['sub_cate']);
                if (isset($a_info['sub_cate']) && is_array($a_info['sub_cate'])) {//读取下级分类S
                    $sult = array_merge($sult, call_user_func_array(__METHOD__, array($a_info['sub_cate'], $new_add_text)));
                }//读取下级分类E
            }
        }
        return $sult;
    }

//-----------------------1大堆和写数据库有关的方法开始------------------------------------------- 
    public function saveInfo($data, $debug = 0) {
        $currnetKid = NULL;
        $opreateSult = false;
        $error=NULL;
        if (!empty($data[$this->tablePrimaryKey])) {//修改的时候  
            $currnetKid = $data[$this->tablePrimaryKey];
            $allSubInfo = $this->getSubId($currnetKid, 'kid'); //找到子子孙孙
            if (in_array($data['parent_id'], $allSubInfo)) {//如果自己将自己设定父分类的问题，或将自己的子分类设定自己的父分类
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
     * 根据ID删除相应分类信息，
     * @param type $id
     * @return int 删除结果如下
     * 1=成功
     * -1=有子分类
     * -2=分类信息不存在
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
        if ($this->getHasClidTree($id)) {//有子分类的
            return -1;
        }
        if ($this->deleteAllByKid("'{$id}'")) {//删除成功
            $this->reCreateTree(); //重建缓存
            return 1;
        } else {
            return -3;
        }
    }

    /**
     * recreateTree
     * 重建分类的树形信息
     */
    public function reCreateTree() {
        $this->getAll(1); //重新缓存全部分类信息
        $this->getBaseTree(1); //重新缓存简单的分类树形结构
        $this->getDetailTree(1); //重新缓存详细的分类树形结构
        $this->getScatterTreeArray(1); //重新散烈的分类树形结构
    }

//-----------------------1大堆和写数据库有关的方法结束------------------------------------------- 
}

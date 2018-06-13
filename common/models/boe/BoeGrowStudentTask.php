<?php

namespace common\models\boe;

use common\helpers\TLoggerHelper;
use common\models\boe\BoeBaseActiveRecord;
use common\models\framework\FwUser;
use Yii;

/**
 * This is the model class for table "eln_boe_make_news_category".
 *
 * @property string $kid
 * @property string $name
 * @property string $parent_id
 * @property integer $list_sort
 * @property string $keywords
 * @property string $descript
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
class BoeGrowStudentTask extends BoeBaseActiveRecord {

    private $allInfo = NULL;
    private $baseTree = NULL;
    private $maxLevel = 100; //无限级分类最大的深度
    const TASK_TYPE_SERIES = 1;//课程系列
    const TASK_TYPE_CATEGORY = 2;//课程目录
    const TASK_TYPE_COURSE = 3;//独立的课程
    const TASK_TYPE_SERIES_TAG = "课程系列";
    const TASK_TYPE_CATEGORY_TAG = "课程目录";//课程目录
    const TASK_TYPE_COURSE_TAG = "课程";

    /**
     * @inheritdoc
     */

    public static function tableName() {
        return 'eln_boe_grow_student_task';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['name','task_type'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'name', 'parent_id', 'created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'kid' => Yii::t('boe', 'txy_kid'),
            'name' => Yii::t('boe', 'txy_name'),
			'task_type' => Yii::t('boe', 'txy_tag'),
            'parent_id' => Yii::t('boe', 'txy_parent_id'),
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

    public function getAll(){
        $data = $this->find(false)
            ->where(['is_deleted'=>0])
            ->orderBy('list_sort asc')
            ->asArray()
            ->all();
        return $data;
    }




	/**
     * getInfo
     * 根据ID获取任务
     * @param type $id 分类的ID
     * @param type $key
     */
    public function getInfo($id = 0, $key = '*') {
        if (!$id) {
            return NULL;
        }
        $data = $this->find(false)->where(['kid'=>$id])->asArray()->one();
        return $data;
    }

    public function getTasksByType($type){
        $tasks = $this->find(false)
            ->where(['is_deleted'=>0])
            ->andWhere(['task_type'=>$type])
            ->asArray()->all();
        if($tasks){
            return $tasks;
        }
        return false;
    }

//-----------------------1大堆和写数据库有关的方法开始-------------------------------------------
    public function saveInfo($data, $debug = 0) {
        $currnetKid = NULL;
        $opreateSult = false;
        $error=NULL;
        if (!empty($data[$this->tablePrimaryKey])) {//修改的时候
                $currentObj = $this->findOne($data['kid']);
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
        if ($this->deleteAllByKid("'{$id}'")) {//删除成功
            return 1;
        } else {
            return -3;
        }
    }



//-----------------------1大堆和写数据库有关的方法结束-------------------------------------------
}

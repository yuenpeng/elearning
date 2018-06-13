<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/4/28
 * Time: 9:14
 */

namespace common\models\framework;


use common\models\boe\BoeBaseActiveRecord;
use \Yii;
use yii\base\Exception;

class FwSeries extends  BoeBaseActiveRecord
{

    /**
     * @return string 返回该AR类关联的数据表名
     */
    public static function tableName()
    {
        return 'eln_fw_series';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['category_id','course_id'], 'required'],
            [['version', 'created_at', 'updated_at'], 'integer'],
            [['kid', 'category_id','course_id','created_by', 'created_from', 'created_ip', 'updated_by', 'updated_from', 'updated_ip'], 'string', 'max' => 50],
            [['is_deleted'], 'string', 'max' => 1]
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'kid' => 'kid',
            'category_id' => 'category_id',
            'course_id' => 'course_id',
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

    public function saveInfoArray($datas,$category_id,$select_type){
        if (!empty($datas) && count($datas) > 0) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                foreach ($datas as $data) {
                    if($select_type==1){
                        $map = array('is_deleted'=>0,'course_id'=>$data,'category_id'=>$category_id);
                        $row = $this->findOne($map,false);
                        if(!$row||!$row->delete()){
                            throw new Exception();
                        }
                    }else{
                        $this->isNewRecord = true;
                        $this->course_id= $data ;
                        $this->category_id = $category_id;
                        $this->kid=0;
                        if (!$this->save()) {
                            throw new Exception();
                        }
                    }
                }
                //更新目录关联课程数目
                $category = new FwSeriesCategory();
                $info = $category->getInfo($category_id);
                $number = $select_type?$info['number']-count($datas):$info['number']+count($datas);
                $number = $number>0?$number:0;
                if(!$category->saveInfo(array('kid'=>$category_id,'number'=>$number))){
                    throw new Exception();
                }
                $transaction->commit();
                return true;
            } catch (Exception $e) {
                $errMsg = $e->getMessage();
                $transaction->rollBack();
                return false;
            }
        }else {
            return false;
        }
    }
    public function getAll() {
        $sult = $this->find(false)->orderBy()->asArray()->indexBy()->all();
        return $sult;
    }
    public function saveInfo($data, $debug = 1) {
        return $this->CommonSaveInfo($data, $debug);
    }
    public function deleteInfo($id = 0) {
        return $this->CommonDeleteInfo($id);
    }

}
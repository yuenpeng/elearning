<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/4/28
 * Time: 10:25
 */

namespace common\services\api;

use common\models\framework\FwDictionary;
use common\models\framework\FwDictionaryCategory;
use common\traits\ResponseTrait;
use common\traits\ParserTrait;
use common\traits\ValidatorTrait;

class DictionaryService extends FwDictionaryCategory{
    use ResponseTrait,ParserTrait,ValidatorTrait;

    public $systemKey;
    public function __construct($system_key,array $config = [])
    {
        $this->systemKey = $system_key;
        parent::__construct($config);
    }

    /**
     * 获取字典分类信息
     * @param null $extra_fields
     * @return array|\yii\db\ActiveRecord[]
     */
    public function categories($extra_fields = null) {
        $default = ['category_id','cate_name','cate_code','sequence_number'];
        if($extra_fields !== null && is_array($extra_fields)) {
            $default = array_merge($default,$extra_fields);
        }
        $model = new FwDictionaryCategory();
        $result = $model->find(false)->select($default)
            ->addOrderBy(['sequence_number' => SORT_ASC])
            ->all();

        return $result;
    }

    /**
     * 根据字典分类代码获取字典列表
     * @param $category_code
     * @param null $extra_fields
     * @param array $map
     * @return array|null|\yii\db\ActiveRecord[]
     */
    public function dictionaries($category_code,$extra_fields = null,$map = []) {
        $default = ['kid','parent_dictionary_id','dictionary_code','dictionary_name','dictionary_value','description','status','sequence_number'];
        if($extra_fields !== null && is_array($extra_fields)) {
            $default = array_merge($default,$extra_fields);
        }

        $dictionary_category_id = $this->dictionary($category_code);
        if (!empty($dictionary_category_id)) {
            $query = FwDictionary::find(false)->select($default);
            $result = $query
                ->andFilterWhere(['=', 'dictionary_category_id', $dictionary_category_id])
                ->addOrderBy(['sequence_number' => SORT_ASC])
                ->all();

            if(!empty($map)) {
                array_walk($result,function(&$val) use($map) {
                    foreach($map as $origin_key => $map_key) {
                        $val->{$map_key} = $val->{$origin_key};
                        unset($val->{$origin_key});
                    }
                });
            }
            return $result;
        }
        else {
            return null;
        }
    }

    /**
     * 根据字典分类代码获取字典分类ID
     * @param $categoryCode
     * @return mixed|null
     */
    public function dictionary($categoryCode) {
        $model = FwDictionaryCategory::find(false);

        $result = $model
            ->andFilterWhere(['=','cate_code', $categoryCode])
            ->one();

        if (!empty($result))
            return $result->kid;
        else
            return null;
    }
}
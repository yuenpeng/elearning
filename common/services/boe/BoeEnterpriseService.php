<?php
namespace common\services\boe;

use common\models\boe\BoeEnterprise;
use common\base\BaseActiveRecord;
use yii\data\ActiveDataProvider;
use common\services\framework\DictionaryService;
use Yii;

class BoeEnterpriseService extends BoeEnterprise
{
    /**
     * 搜索树类型数据
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = BoeEnterprise::find(false);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }
        $query
            ->andFilterWhere(['like', 'enterprise_code',  trim(urldecode($this->enterprise_code))])
            ->andFilterWhere(['like', 'enterprise_name',  trim(urldecode($this->enterprise_name))]);

        $dataProvider->setSort(false);
        $query->addOrderBy(['created_at' => SORT_DESC]);

        return $dataProvider;
    }
    public  static function __initPayPlace($is_cached=0){
        if($is_cached){
            $dictionaryService = new DictionaryService();
            $jfd = $dictionaryService->getDictionariesByCategory('payroll_place');
            foreach ($jfd as $key => $value) {
                $model = BoeEnterprise::findOne(['enterprise_code' => $value->dictionary_code]);
                if(!$model){
                    $model = new BoeEnterprise();
                    $model->hrbp_no = '未知';
                    $model->hrbp_name = '未知' ;
                    $model->enterprise_type = 0 ;
                    $model->enterprise_code = $value->dictionary_code;
                }
                $model->enterprise_name = $value->dictionary_name;
                $model->save();
                Yii::$app->cache->set('__initPayPlace', '1', 3600*24);
            }
        }
    }
    public  static function isExitEnterprise($code,$name){
        $result = BoeEnterprise::find(false)
            ->where(['and', 'is_deleted=0', ['or', "enterprise_code='$code'", "enterprise_name='$name'"]])
            ->select('enterprise_code,enterprise_name')
            ->asArray()
            ->one();
        return $result;
    }
}
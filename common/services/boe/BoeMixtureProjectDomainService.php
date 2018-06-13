<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/25
 * Time: 10:09
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureProjectDomain;
use yii\helpers\ArrayHelper;


class BoeMixtureProjectDomainService extends BoeMixtureProjectDomain
{
    const STATUS_YES = "1";
    const STATUS_NO = "0";
    /**
     * @param BoeMixtureProjectDomain $targetModel
     * @return array
     * 获取相关内容列表
     */
    public function getContentList(BoeMixtureProjectDomain $targetModel)
    {
        if (isset($targetModel) && $targetModel != null) {

            $query = BoeMixtureProjectDomain::find(false);

            $query->andFilterWhere(['=', 'status', self::STATUS_FLAG_NORMAL])
                ->andFilterWhere(['=', 'project_id', $targetModel->project_id]);

            $selectedResult = $query->all();

            $selectedList = ArrayHelper::map($selectedResult, 'domain_id', 'domain_id');

            $selected_keys = array_keys($selectedList);

            return $selected_keys;

        } else {
            return [];
        }
    }

    /**
     * 添加域关系
     * @param $domain_ids
     * @param $project_id
     */
    public function addRelation($domain_ids,$project_id){
        if(isset($domain_ids) && $domain_ids != ''){
            foreach($domain_ids as $list){
                $findOne = BoeMixtureProjectDomain::findOne(['project_id' => $project_id, 'domain_id' => $list]);
                $resourceDomainA = !empty($findOne->kid) ? $findOne : new BoeMixtureProjectDomain();
                $resourceDomainA->project_id = $project_id;
                $resourceDomainA->domain_id = $list;
                $resourceDomainA->status = self::STATUS_YES;/*资源状态*/
                $resourceDomainA->save();
            }
        }
    }
}
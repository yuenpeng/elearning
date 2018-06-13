<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/25
 * Time: 13:43
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureProgramAudience;
use common\models\boe\BoeMixtureProjectAudience;
use yii\helpers\ArrayHelper;

class BoeMixtureProjectAudienceService extends  BoeMixtureProjectAudience
{
    const STATUS_YES = "1";
    const STATUS_NO = "0";

    /**
     *  查询课程项目受众
     * @param $course_project_id
     * @param $companyId
     * @param string $status
     * @return array
     */
    public function getCourseProjectAudience($course_project_id, $companyId,  $status = self::STATUS_FLAG_NORMAL){
        if (empty($course_project_id)) return array();
        $model = BoeMixtureProjectAudience::find(false);
        $result = $model->andFilterWhere(['=', 'program_id', $course_project_id])
            ->andFilterWhere(['=', 'status', $status])
            ->select('audience_id')
            ->all();
        if (!empty($result)) {
            $selectedList = ArrayHelper::map($result, 'audience_id', 'audience_id');
            $selected_keys = array_keys($selectedList);
            return $selected_keys;
        }
        return array();
    }

    /**
     *  添加受众对应关系
     * @param $audience_ids
     * @param $project_id
     */

    public function addRelation($audience_ids,$project_id){
        if(isset($audience_ids) && $audience_ids != ''){
            foreach($audience_ids as $list){
                $findOne = BoeMixtureProjectAudience::findOne(['program_id' => $project_id, 'audience_id' => $list]);
                $resourceDomainA = !empty($findOne->kid) ? $findOne : new BoeMixtureProjectAudience();
                $resourceDomainA->program_id = $project_id;
                $resourceDomainA->audience_id = $list;
                $resourceDomainA->status = self::STATUS_YES;/*资源状态*/
                $resourceDomainA->is_deleted = BoeMixtureProjectAudience::DELETE_FLAG_NO;
                $resourceDomainA->save();
            }
        }
    }
}
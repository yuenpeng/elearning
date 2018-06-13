<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/24
 * Time: 13:18
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureCourseGroup;
use common\models\boe\BoeMixtureCourseGroupCategory;
use common\models\boe\BoeMixtureProjectCategory;
use Yii;
use common\models\treemanager\FwTreeNode;
use common\base\BaseActiveRecord;

class BoeMixtureCourseGroupCategoryService extends BoeMixtureCourseGroupCategory
{


    /**
     * 根据企业ID列表获取相关目录
     * @param $companyIdList
     * @return array|\yii\db\ActiveRecord[]
     */
    public function GetGroupCategoryByCompanyIdList($companyIdList)
    {
        $categoryModel = new BoeMixtureCourseGroupCategory();
        $categoryResult = $categoryModel->find(false)
            ->andFilterWhere(['in','company_id',$companyIdList])
            ->orderBy('created_at')
            ->all();

        return $categoryResult;
    }

    public function GetGroupCategoryCount($treeNodeId, $ListRouteParams = false){
        $model = BoeMixtureCourseGroupCategory::find(false)
            ->andFilterWhere(['=','tree_node_id',$treeNodeId])
            ->select('kid,company_id')
            ->one();

        $categoryAll = $this->getSubCategories($model->kid);
        if (!empty($categoryAll)){
            $categoryAll = array_merge(array($model->kid), $categoryAll);
        }else{
            $categoryAll = array($model->kid);
        }
        $query = BoeMixtureCourseGroup::find(false)
            ->andFilterWhere(['in','category_id',$categoryAll]);
        return $query->count('kid');
    }

    /**
     * 根据树节点ID获取目录ID
     * @param $id
     * @return null|string
     */
    public function GetGroupCategoryIdByTreeNodeId($id)
    {
        if ($id != null && $id != "") {
            $categoryModel = new BoeMixtureCourseGroupCategory();

            $categoryResult = $categoryModel->findOne(['tree_node_id' => $id]);

            if ($categoryResult != null)
            {
                $examinationCategoryId = $categoryResult->kid;
            }
            else
            {
                $examinationCategoryId = null;
            }
        }
        else
        {
            $examinationCategoryId = null;
        }

        return $examinationCategoryId;
    }

    /**
     * 根据树节点ID，删除相关目录ID
     * @param $treeNodeId
     */
    public function deleteRelateData($treeNodeId)
    {
        $model = new BoeMixtureProjectCategory();

        $kids = "";
        if (is_array($treeNodeId)) {
            foreach ($treeNodeId as $key) {
                $kids = $kids . "'" . $key . "',";

                $audienceCategoryKey = $this->GetGroupCategoryIdByTreeNodeId($key);
                BoeMixtureCourseGroupCategory::removeFromCacheByKid($audienceCategoryKey);
            }

            $kids = rtrim($kids, ",");
        }else{
            $kids = "'".$treeNodeId."'";

            $audienceCategoryKey = $this->GetGroupCategoryIdByTreeNodeId($treeNodeId);
            BoeMixtureProjectCategory::removeFromCacheByKid($audienceCategoryKey);
        }

        $model->deleteAll(BaseActiveRecord::getQuoteColumnName("tree_node_id") ." in (".$kids.")");
        FwTreeNode::deleteAll(BaseActiveRecord::getQuoteColumnName("kid") . " in (".$kids.")");

        return true;
    }


    /**
     * 激活父节点
     * @param $kid
     */
    public function ActiveParentNode($kid)
    {
        $model = BoeMixtureProjectCategory::findOne($kid);

        $parent_node_id = $model->parent_category_id;

        if ($parent_node_id != null && $parent_node_id != "")
        {
            $parentModel = BoeMixtureCourseGroupCategory::findOne($parent_node_id);

            if ($parentModel->status != BoeMixtureCourseGroupCategory::STATUS_FLAG_NORMAL)
            {
                $parentModel->status = BoeMixtureCourseGroupCategory::STATUS_FLAG_NORMAL;
                $parentModel->needReturnKey = true;
                $parentModel->save();

                $this->ActiveParentNode($parentModel->kid);
            }
        }
    }

    /**
     * 根据树节点ID，更新上级目录信息
     * @param $treeNodeId
     * @param $targetTreeNodeId
     */
    public function updateParentIdByTreeNodeId($treeNodeId,$targetTreeNodeId)
    {
        $categoryId = $this->GetGroupCategoryIdByTreeNodeId($treeNodeId);
        $targetCategroyId = $this->GetGroupCategoryByCompanyIdList($targetTreeNodeId);
        if ($categoryId != null) {
            $categoryModel = BoeMixtureCourseGroupCategory::findOne($categoryId);
            $categoryModel->parent_category_id = $targetCategroyId;
            $categoryModel->save();
        }
    }

    /**
     * 获取公司的所有目录列表
     * @param $companyId
     * @return array|\yii\db\ActiveRecord[]
     */
    public function GetAllGroupCategoryListByCompanyId($companyId = null)
    {
        $model = BoeMixtureCourseGroupCategory::find(false);
        $userId = Yii::$app->user->getId();

        $query = $model
            ->joinWith('fwTreeNode')
            ->andFilterWhere(['=','owner_id',$userId])
            ->andFilterWhere(['company_id' => $companyId])
            ->andFilterWhere([FwTreeNode::tableName() . '.status' => FwTreeNode::STATUS_FLAG_NORMAL])
            ->addOrderBy([FwTreeNode::tableName() . '.display_number' => SORT_ASC])
            ->addOrderBy([FwTreeNode::tableName() . '.sequence_number' => SORT_ASC])
            ->all();

        return $query;
    }


    /**
     * 根据tree_node_id获取目录的子目录
     * @param $categories array
     * @return string
     */
    public function getCategoriesByTreeNode($tree_node_id){
        $categories = BoeMixtureCourseGroupCategory::findAll(['tree_node_id'=>$tree_node_id],false);
        $result = [];
        foreach($categories as $val){
            $result[] = $val->kid;
            $result = array_merge($result,$this->getSubCategories($val->kid));
        }
        return $result;
    }

    /**
     * 获取目录的子目录
     * @param $categoryid
     * @return array
     */
    private function getSubCategories($categoryid){
        $categories = BoeMixtureCourseGroupCategory::findAll(['parent_category_id'=>$categoryid],false);
        $result = [];
        foreach($categories as $val){
            $result[] = $val->kid;
            $result = array_merge($result,$this->getSubCategories($val->kid));
        }
        return $result;
    }


    /**
     * 目录选择框
     * @return array
     */
    public function ListGroupCategroySelect()
    {
        $companyId=Yii::$app->user->identity->company_id;

        $categories = $this->GetAllGroupCategoryListByCompanyId($companyId);
        $result = array();
        foreach ($categories as $k => $val) {
            if (!$val->parent_category_id) {
                $result[$val->kid] = $val->category_name;
                $result = array_merge($result, $this->getSubGroupCategories($categories, $val->kid, '　'));
            }
        }
        return $result;
    }

    /**
     * 获取目录的子目录
     * @param $categories
     * @param $parentid
     * @param $tab
     * @return array
     */
    private function getSubGroupCategories($categories,$parentid,$tab){
        $result = array();
        foreach ($categories as $k=>$val) {
            if ($parentid == $val->parent_category_id) {
                $result[$val->kid] = $tab.$val->category_name;
                $result = array_merge($result,$this->getSubGroupCategories($categories,$val->kid,$tab.'　'));
            }
        }
        return $result;
    }


    /**
     * 根据树节点ID获取课程目录ID
     * @param $id
     * @return null|string
     */
    public function getCategoryIdByTreeNodeId($id)
    {
        if ($id != null && $id != "") {
            $BoeMixtureCourseGroupCategory = new BoeMixtureCourseGroupCategory();

            $courseCategoryResult = $BoeMixtureCourseGroupCategory->findOne(['tree_node_id' => $id]);

            if ( $courseCategoryResult != null)
            {
                $courseCategoryId =  $courseCategoryResult->kid;
            }
            else
            {
                $courseCategoryId = null;
            }
        }
        else
        {
            $courseCategoryId = null;
        }

        return $courseCategoryId;
    }

}
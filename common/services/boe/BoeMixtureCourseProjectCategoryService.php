<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/24
 * Time: 13:18
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureProject;
use common\models\boe\BoeMixtureProjectCategory;
use Yii;
use common\models\treemanager\FwTreeNode;
use common\base\BaseActiveRecord;


class BoeMixtureCourseProjectCategoryService extends BoeMixtureProjectCategory
{


    /**
     * 根据企业ID列表获取相关目录
     * @param $companyIdList
     * @return array|\yii\db\ActiveRecord[]
     */
    public function GetProjectCategoryByCompanyIdList($companyIdList)
    {
        $categoryModel = new BoeMixtureProjectCategory();
        $courseCategoryResult = $categoryModel->find(false)
            ->andFilterWhere(['in','company_id',$companyIdList])
            ->orderBy('created_at')
            ->all();

        return $courseCategoryResult;
    }

    public function GetProjectCategoryCount($treeNodeId, $ListRouteParams = false){
        $model = BoeMixtureProjectCategory::find(false)
            ->andFilterWhere(['=','tree_node_id',$treeNodeId])
            ->select('kid,company_id')
            ->one();

        $categoryAll = $this->getSubCategories($model->kid);
        if (!empty($categoryAll)){
            $categoryAll = array_merge(array($model->kid), $categoryAll);
        }else{
            $categoryAll = array($model->kid);
        }

        $companyId = $model->company_id;
        $query = BoeMixtureProject::find(false)
            ->andFilterWhere(['in','category_id',$categoryAll])
            ->andFilterWhere(['=','company_id',$companyId]);
        return $query->count('kid');
    }

    /**
     * 根据树节点ID获取目录ID
     * @param $id
     * @return null|string
     */
    public function GetProjectCategoryIdByTreeNodeId($id)
    {
        if ($id != null && $id != "") {
            $categoryModel = new BoeMixtureProjectCategory();

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

                $audienceCategoryKey = $this->GetProjectCategoryIdByTreeNodeId($key);
                BoeMixtureProjectCategory::removeFromCacheByKid($audienceCategoryKey);
            }

            $kids = rtrim($kids, ",");
        }else{
            $kids = "'".$treeNodeId."'";

            $audienceCategoryKey = $this->GetProjectCategoryIdByTreeNodeId($treeNodeId);
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
            $parentModel = BoeMixtureProjectCategory::findOne($parent_node_id);

            if ($parentModel->status != BoeMixtureProjectCategory::STATUS_FLAG_NORMAL)
            {
                $parentModel->status = BoeMixtureProjectCategory::STATUS_FLAG_NORMAL;
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
        $categoryId = $this->GetProjectCategoryIdByTreeNodeId($treeNodeId);
        $targetCategroyId = $this->GetProjectCategoryByCompanyIdList($targetTreeNodeId);
        if ($categoryId != null) {
            $categoryModel = BoeMixtureProjectCategory::findOne($categoryId);
            $categoryModel->parent_category_id = $targetCategroyId;
            $categoryModel->save();
        }
    }



    /**
     * 根据tree_node_id获取目录的子目录
     * @param $categories array
     * @return string
     */
    public function getCategoriesByTreeNode($tree_node_id){
        $categories = BoeMixtureProjectCategory::findAll(['tree_node_id'=>$tree_node_id],false);
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
        $categories = BoeMixtureProjectCategory::findAll(['parent_category_id'=>$categoryid],false);
        $result = [];
        foreach($categories as $val){
            $result[] = $val->kid;
            $result = array_merge($result,$this->getSubCategories($val->kid));
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
    private function getSubAudienceCategories($categories,$parentid,$tab){
        $result = array();
        foreach ($categories as $k=>$val) {
            if ($parentid == $val->parent_category_id) {
                $result[$val->kid] = $tab.$val->category_name;
                $result = array_merge($result,$this->getSubAudienceCategories($categories,$val->kid,$tab.'　'));
            }
        }
        return $result;
    }


    /**
     * 根据树节点ID获取课程目录ID
     * @param $id
     * @return null|string
     */
    public function getCourseCategoryIdByTreeNodeId($id)
    {
        if ($id != null && $id != "") {
            $courseProjectCategoryModel = new BoeMixtureProjectCategory();

            $courseProjectCategoryResult = $courseProjectCategoryModel->findOne(['tree_node_id' => $id]);

            if ($courseProjectCategoryResult != null)
            {
                $courseProjectCategoryId = $courseProjectCategoryResult->kid;
            }
            else
            {
                $courseProjectCategoryId = null;
            }
        }
        else
        {
            $courseProjectCategoryId = null;
        }

        return $courseProjectCategoryId;
    }

}
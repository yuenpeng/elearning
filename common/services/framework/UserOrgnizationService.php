<?php


namespace common\services\framework;

use common\interfaces\MutliTreeNodeInterface;
use common\models\framework\FwCompany;
use common\models\framework\FwOrgnization;
use common\models\framework\FwUser;
use common\models\treemanager\FwCntManageRef;
use common\models\treemanager\FwTreeNode;
use common\services\framework\RbacService;
use common\base\BaseActiveRecord;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class UserOrgnizationService extends FwCntManageRef implements MutliTreeNodeInterface {


    /**
     * 获取当前节点选中状态
     * @param string $kid 用户ID
     * @param string $nodeId 当前节点ID
     * @return boolean
     */
    public function getSelectedStatus($kid, $nodeId)
    {
        if ($nodeId == '-1')
            return false;
        else {
            if ($kid != null) {

                $orgnizationService = new OrgnizationService();
                $orgnizationId = $orgnizationService->getOrgnizationIdByTreeNodeId($nodeId);

                if ($orgnizationId != null) {
                    $cntManageModel = new FwCntManageRef();
                    $cntManageModel->subject_id = $kid;
                    $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                    $cntManageModel->content_id = $orgnizationId;
                    $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_ORGNIZATION;
                    $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                    $cntManageRefService = new CntManageRefService();

                    if ($cntManageRefService->isRelationshipExist($cntManageModel)) {
                        return true;
                    } else {
                        $userOrgnizationId = FwUser::findOne($kid)->orgnization_id;
                        if ($userOrgnizationId == $orgnizationId)
                        {
                            //默认选中用户所属组织
                            return true;
                        }
                        else {
                            $rbacService = new RbacService();
                            if ($rbacService->isSpecialUser($kid) || $rbacService->isSysManager($kid)) {
                                //超级管理员和系统管理员可以管理所有自己可见节点
                                return true;
                            }
                            else {
                                return false;
                            }
                        }
                    }
                }
                else
                {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    /**
     * 当前组织是否为父组织
     * @param string $orgnizationId 父组织ID
     * @param string $currentOrgnizationId 当前组织ID
     */
    private function isParentOrgnization($orgnizationId, $currentOrgnizationId) {
        $parentOrgnizationId = FwOrgnization::findOne($currentOrgnizationId)->parent_orgnization_id;
        if (empty($parentOrgnizationId)) {
            return false;
        }
        else {
            if ($orgnizationId == $parentOrgnizationId) {
                return true;
            }
            else {
                return $this->isParentOrgnization($orgnizationId, $parentOrgnizationId);
            }
        }

    }

    /**
     * 获取当前节点可用状态
     * @param $kid
     * @param $nodeId
     * @return boolean
     */
    public function getDisabledStatus($kid, $nodeId)
    {
        if ($nodeId == '-1')
            return true;
        else {
            return false;
        }
    }

    /**
     * 获取当前节点显示状态
     * @param $kid
     * @param $nodeId
     * @return boolean
     */
    public function getDisplayedStatus($kid, $nodeId)
    {
        return true;
    }

    /**
     * 设置树节点上ID值的模式
     * 对于混合类型的树（即包括2种以上类型节点，则有可能出现ID一致无法判断的情况，所以需要增加树类型，以便区分）
     * 值格式为“树类型_ID”
     * @return boolean
     */
    public function isTreeNodeIdIncludeTreeType($kid, $nodeId)
    {
        return false;
    }

    /**
     * 获取可查询列表文字信息
     * @param $userId
     * @return string
     */
    public function getSearchedListStringByUserId($userId)
    {
        $list = $this->getSearchListByUserId($userId);

        $result = "";
        if ($list != null) {

            foreach ($list as $model )
            {
                $name = $model->orgnization_name;
                $result = $result . $name . ",";
            }

            if ($result != "")
            {
                $result = rtrim($result,",");
            }
        }

        return $result;
    }


    /**
     * 获取可管理列表文字信息
     * @param $userId
     * @return string
     */
    public function getManagedListStringByUserId($userId)
    {
        $list = $this->getManagedListByUserId($userId);

        $result = "";
        if ($list != null) {

            foreach ($list as $model )
            {
                $name = $model->orgnization_name;
                $result = $result . $name . ",";
            }

            if ($result != "")
            {
                $result = rtrim($result,",");
            }
        }

        return $result;
    }

    public function getTreeNodeIdListByOrgnizationId($orgnizationIds) {
        if (!empty($orgnizationIds)) {
            $result = FwOrgnization::find(false)
                ->andFilterWhere(['in', 'kid', $orgnizationIds])
                ->all();

            return $result;
        }
        else {
            return null;
        }
    }

    /**
     * 获取用户可管理的组织部门清单（如有Session优先用）
     * @param $userId
     * @param null $status
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getManagedListByUserId($userId,$status = self::STATUS_FLAG_NORMAL,$withSession = true,
                                           $needReturnAll = true, &$isAll = false, $parentNodeId = null,$includeSubNode = "0",$nodeIdPath = null)
    {
        if (!empty($userId)) {
            $sessionKey = "ManagedOrgnizationList_" . $userId;
            if ($status == null || $parentNodeId != null || !$needReturnAll) {
                $withSession = false;
            }

            if ($withSession && Yii::$app->session->has($sessionKey)) {
                return Yii::$app->session->get($sessionKey);

//            if ($selected_keys_string != null && $selected_keys_string != "")
//                $selected_keys = explode(',',$selected_keys_string);
//            else
//                $selected_keys = null;
            } else {

                $rbacService = new RbacService();

                $isSpecialUser = $rbacService->isSpecialUser($userId, $withSession);

//                $isSysManager = false;
                if ($isSpecialUser) {
                    $selected_keys = null;
                    $isAll = true;
                } else {
                    $selected_keys = $this->getUserManagedOrgnizationList($userId, $withSession);
                }

//            if ($selected_keys != null)
//                $selected_keys_string = implode(',', $selected_keys);//将数组拼接成字符串
//            else
//                $selected_keys_string = "";


                if (!$isAll) {
                    $userCompanyService = new UserCompanyService();
                    $companyIds = $userCompanyService->getManagedListByUserId($userId);

                    if (isset($companyIds) && $companyIds != null) {
                        $companyIds = ArrayHelper::map($companyIds, 'kid', 'kid');

                        $companyIds = array_keys($companyIds);
                    }
                }
                else {
                    $companyIds = null;
                }


                if (!$needReturnAll && $isAll) {
                    return null;//对于超级管理员,不需要进行查询
                }
                else {
                    $query = FwOrgnization::find(false)
                        ->innerJoin(FwTreeNode::realTableName(),
                            FwOrgnization::tableName() . "." . self::getQuoteColumnName("tree_node_id") . " = " . FwTreeNode::tableName() . "." . self::getQuoteColumnName("kid"))
                        ->andFilterWhere(['=', FwOrgnization::realTableName() . '.status', $status]);


                    if (!$needReturnAll) {
                        if ($includeSubNode == "1") {
                            $query->andWhere(BaseActiveRecord::getQuoteColumnName("node_id_path") . " like '" . $nodeIdPath . "'");
                        }
                        else {

                            if ($parentNodeId != null)
                                $query->andFilterWhere(['=', FwTreeNode::realTableName() . '.parent_node_id', $parentNodeId]);
                            else {
                                $query->andWhere(BaseActiveRecord::getQuoteColumnName("parent_node_id") . ' is null');
                            }
                        }
                    }

                    /*受众添加判断*/
                    $ListRouteParams = Yii::$app->request->get('ListRouteParams');

                    if ($selected_keys != null && !isset($ListRouteParams))
                        $query->andFilterWhere(['in', FwOrgnization::realTableName() . '.kid', $selected_keys]);

                    if ($companyIds != null)
                        $query->andFilterWhere(['in', FwOrgnization::realTableName() . '.company_id', $companyIds]);

                    $query
                        ->addOrderBy(['tree_level' => SORT_ASC])
                        ->addOrderBy(['parent_node_id' => SORT_ASC])
                        ->addOrderBy(['display_number' => SORT_ASC])
                        ->addOrderBy(['sequence_number' => SORT_ASC]);

                    $result = $query->all();
                    if ($withSession) {
                        Yii::$app->session->set($sessionKey, $result);
                    }

                    return $result;
                }
            }
        }
        else {
            return null;
        }
    }

    /**
     * 判断用户是否对这个组织有管理权限
     * @param $userId
     * @param $orgnizationId
     * @return bool
     */
    public function isUserManagedOrgnization($userId, $orgnizationId)
    {
        $rbacService = new RbacService();
        $isSysManager = $rbacService->isSysManager($userId);
        $isSpecialUser = $rbacService->isSpecialUser($userId);

        if ($isSpecialUser) {
            return true;//超级管理员对所有组织有管理权限
        }
        else {
            $orgnizationModel = FwOrgnization::findOne($orgnizationId);
            if (!empty($orgnizationModel) && $orgnizationModel->status == FwOrgnization::STATUS_FLAG_NORMAL) {
                $userModel = FwUser::findOne($userId);
                $companyId = $orgnizationModel->company_id;
                if ($isSysManager && $userModel->company_id == $companyId) {
                    //系统管理员对本企业所有组织有管理权限
                    return true;
                }
                else {
                    //普通人员只对可管理的组织 和 自己所在组织，有管理权限

                    //自己所在组织
                    if ($userModel != null && $userModel->orgnization_id != null && $userModel->orgnization_id == $orgnizationId) {
                        return true;
                    } else {
                        //可管理的域
                        $cntManageModel = new FwCntManageRef();
                        $cntManageModel->subject_id = $userId;
                        $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                        $cntManageModel->content_id = $orgnizationId;;
                        $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_ORGNIZATION;
                        $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                        $cntManageRefService = new CntManageRefService();

                        $companyList = $cntManageRefService->getContentList($cntManageModel);

                        if (count($companyList) > 0) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }
            }
            else {
                return false;
            }
        }
    }


    /**
     * 判断用户是否对这个组织有查询权限
     * @param $userId
     * @param $orgnizationId
     * @return bool
     */
    public function isUserSearchedOrgnization($userId, $orgnizationId)
    {
        return $this->isUserManagedOrgnization($userId, $orgnizationId);
    }

    /**
     * 获取用户可查询的组织部门清单（如有Session优先用）
     * @param $userId
     * @param null $status
     * @return mixed
     */
    public function getSearchListByUserId($userId,$status = self::STATUS_FLAG_NORMAL,$withSession = true,$needReturnAll = true, &$isAll = false, $parentNodeId = null)
    {
        return $this->getManagedListByUserId($userId,$status,$withSession,$needReturnAll,$isAll,$parentNodeId);
    }

    /**
     * 获取用户管理的组织部门列表
     * @param $userId
     * @return array
     */
    public function getUserManagedOrgnizationList($userId,$withSession = true)
    {
        $rbacService = new RbacService();
        $isSysManager = $rbacService->isSysManager($userId);
        $orgnizationList = null;

        $userModel = FwUser::findOne($userId);
        if (!$isSysManager) {
            if ($userModel != null && $userModel->orgnization_id != null) {
                $cntManageModel = new FwCntManageRef();
                $cntManageModel->subject_id = $userId;
                $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_ORGNIZATION;
                $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                $cntManageRefService = new CntManageRefService();

                $orgnizationList = $cntManageRefService->getContentList($cntManageModel);

                if (!array_key_exists($userModel->orgnization_id,$orgnizationList)) {
                    array_push($orgnizationList, $userModel->orgnization_id);//当前组织部门
                }
            }
        } else {
            //系统管理员允许全部组织
        }

        return $orgnizationList;
    }

    /**
     * 获取当前节点打开状态
     * @param string $kid 用户ID
     * @param string $nodeId 当前节点ID
     * @return boolean
     */
    public function getOpenedStatus($kid, $nodeId)
    {
        if ($nodeId == '-1')
            return true;
        else {
            $rbacService = new RbacService();
            if ($rbacService->isSpecialUser($kid) || $rbacService->isSysManager($kid)) {
                //超级管理员和系统管理员可以管理所有自己可见节点
                return false;
            }
            else {
                $orgnizationService = new OrgnizationService();
                $orgnizationId = $orgnizationService->getOrgnizationIdByTreeNodeId($nodeId);

                $managOrgnizationList = $this->getManagedListByUserId($kid, BaseActiveRecord::STATUS_FLAG_NORMAL, false);
                $check = false;
                if (!empty($managOrgnizationList) && count($managOrgnizationList) > 0) {
                    foreach ($managOrgnizationList as $orgModel) {
                        if (!$check) {
                            $check = $this->isParentOrgnization($orgnizationId, $orgModel->kid);
                        }
                    }
                }
                return $check;

            }
        }
    }
}
<?php


namespace common\services\framework;


use common\interfaces\MutliTreeNodeInterface;
use common\models\framework\FwCompany;
use common\models\framework\FwDomain;
use common\models\framework\FwUser;
use common\models\treemanager\FwCntManageRef;
use common\models\treemanager\FwTreeNode;
use common\services\framework\RbacService;
use common\base\BaseActiveRecord;
use Yii;
use yii\caching\Cache;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class UserDomainService extends FwCntManageRef implements MutliTreeNodeInterface
{


    /**
     * 获取当前节点选中状态
     * @param $kid
     * @param $nodeId
     * @return boolean
     */
    public function getSelectedStatus($kid, $nodeId)
    {
        if ($nodeId == '-1')
            return false;
        else {
            if ($kid != null) {

                $domianService = new DomainService();
                $domainId = $domianService->getDomainIdByTreeNodeId($nodeId);

                if ($domainId != null) {
                    $cntManageModel = new FwCntManageRef();
                    $cntManageModel->subject_id = $kid;
                    $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                    $cntManageModel->content_id = $domainId;
                    $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_DOMAIN;
                    $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                    $cntManageRefService = new CntManageRefService();

                    if ($cntManageRefService->isRelationshipExist($cntManageModel)) {
                        return true;
                    } else {
                        $userDomainId = FwUser::findOne($kid)->domain_id;
                        if ($userDomainId == $domainId) {
                            //默认选中用户所属域
                            return true;
                        } else {
                            return false;
                        }
                    }
                } else {
                    return false;
                }
            } else {
                return false;
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

            foreach ($list as $model) {
                $name = $model->domain_name;
                $result = $result . $name . ",";
            }

            if ($result != "") {
                $result = rtrim($result, ",");
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

            foreach ($list as $model) {
                $name = $model['domain_name'];
                $result = $result . $name . ",";
            }

            if ($result != "") {
                $result = rtrim($result, ",");
            }
        }

        return $result;
    }

    /**
     * 获取用户可管理的域清单（如有Cache优先用）
     * @param $userId
     * @param string $status
     * @param bool $withCache
     * @param null $shareFlag
     * @param bool $needReturnAll
     * @param bool $isAll
     * @param null $parentNodeId
     * @param string $includeSubNode
     * @param null $nodeIdPath
     * @return array|mixed|null|\yii\db\ActiveRecord[]
     */
    public function getManagedListByUserId($userId, $status = self::STATUS_FLAG_NORMAL, $withCache = true,
                                           $shareFlag = null, $needReturnAll = true, &$isAll = false, $parentNodeId = null,
                                           $includeSubNode = "0", $nodeIdPath = null)
    {
        if (!empty($userId)) {
            $cacheKey = "ManagedDomainList_" . $userId;
            if ($status == null) {
                $withCache = false;
            }

            if ($shareFlag != null) {
                $withCache = false;
            }

            if ($withCache && Yii::$app->cache->exists($cacheKey)) {
                return Yii::$app->cache->get($cacheKey);

//            if ($selected_keys_string != null && $selected_keys_string != "")
//                $selected_keys = explode(',',$selected_keys_string);
//            else
//                $selected_keys = null;
            } else {

                $rbacService = new RbacService();

                $isSpecialUser = $rbacService->isSpecialUser($userId);

                if ($isSpecialUser) {
                    $selected_keys = null;
                    $isAll = true;
                } else {
                    $selected_keys = $this->getUserManagedDomainList($userId, $withCache, $needReturnAll, $parentNodeId,$includeSubNode, $nodeIdPath);
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
                } else {
                    $companyIds = null;
                }

                if (!$needReturnAll && $isAll) {
                    return null;//对于超级管理员,不需要进行查询
                } else {
                    $query = FwDomain::find(false)
                        ->innerJoin(FwTreeNode::realTableName(),
                            FwDomain::tableName() . "." . self::getQuoteColumnName("tree_node_id") . " = " . FwTreeNode::tableName() . "." . self::getQuoteColumnName("kid") )
                        ->andFilterWhere(['=', FwDomain::realTableName() . '.status', $status]);

                    if ($shareFlag != null) {
                        $query->andFilterWhere(['=', FwDomain::realTableName() . '.share_flag', $shareFlag]);
                    }

                    if ($selected_keys != null)
                        $query->andFilterWhere(['in', FwDomain::realTableName() . '.kid', $selected_keys]);

                    if ($companyIds != null)
                        $query->andFilterWhere(['in', FwDomain::realTableName() . '.company_id', $companyIds]);

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

                    $query
                        ->addOrderBy(['tree_level' => SORT_ASC])
                        ->addOrderBy(['parent_node_id' => SORT_ASC])
                        ->addOrderBy(['display_number' => SORT_ASC])
                        ->addOrderBy(['sequence_number' => SORT_ASC]);

                    $result = $query->asArray()->all();

                    if ($withCache) {
                        Yii::$app->cache->add($cacheKey, $result, 3600);
                    }

                    return $result;
                }
            }
        } else {
            return null;
        }
    }

    /**
     * 获取用户可查询的域清单（如有Cache优先用）
     * @param $userId 用户id
     * @param string $status
     * @param bool $withCache
     * @param null $shareFlag
     * @param bool $needReturnAll
     * @param bool $isAll
     * @param null $parentNodeId
     * @param string $includeSubNode
     * @param null $nodeIdPath
     * @return array|mixed|null|\yii\db\ActiveRecord[]
     */
    public function getSearchListByUserId($userId, $status = self::STATUS_FLAG_NORMAL, $withCache = true,
                                          $shareFlag = null, $needReturnAll = true, &$isAll = false, $parentNodeId = null,
                                          $includeSubNode = "0", $nodeIdPath = null)
    {
        if (!empty($userId)) {
            $cacheKey = "SearchedDomainList_" . $userId;
            if ($status == null) {
                $withCache = false;
            }

            if ($shareFlag != null) {
                $withCache = false;
            }

            if ($withCache && Yii::$app->cache->exists($cacheKey)) {
                return Yii::$app->cache->get($cacheKey);

//            if ($selected_keys_string != null && $selected_keys_string != "")
//                $selected_keys = explode(',',$selected_keys_string);
//            else
//                $selected_keys = null;
            } else {

                $rbacService = new RbacService();

                $isSpecialUser = $rbacService->isSpecialUser($userId);


                if ($isSpecialUser) {
                    $selected_keys = null;
                    $isAll = true;
                } else {
                    $selected_keys = $this->getUserSearchedDomainList($userId, $withCache, $needReturnAll, $parentNodeId,$includeSubNode, $nodeIdPath);
                }

//            if ($selected_keys != null)
//                $selected_keys_string = implode(',', $selected_keys);//将数组拼接成字符串
//            else
//                $selected_keys_string = "";
                if (!$isAll) {
                    $userCompanyService = new UserCompanyService();
                    $companyIds = $userCompanyService->getSearchListByUserId($userId);

                    if (isset($companyIds) && $companyIds != null) {
                        $companyIds = ArrayHelper::map($companyIds, 'kid', 'kid');

                        $companyIds = array_keys($companyIds);
                    }
                } else {
                    $companyIds = null;
                }

                if (!$needReturnAll && $isAll) {
                    return null;//对于超级管理员,不需要进行查询
                } else {
                    $query = FwDomain::find(false);
                    $query->innerJoinWith('fwTreeNode', false);
                    $query->andFilterWhere(['=', FwDomain::realTableName() . '.status', $status]);
                    $query->andFilterWhere(['=', FwTreeNode::realTableName() . '.status', $status]);

                    if ($shareFlag != null) {
                        $query->andFilterWhere(['=', FwDomain::realTableName() . '.share_flag', $shareFlag]);
                    }

                    if ($selected_keys != null)
                        $query->andFilterWhere(['in', FwDomain::realTableName() . '.kid', $selected_keys]);

                    if ($companyIds != null)
                        $query->andFilterWhere(['in', FwDomain::realTableName() . '.company_id', $companyIds]);


                    if (!$needReturnAll) {
                        if ($parentNodeId != null)
                            $query->andFilterWhere(['=', FwTreeNode::realTableName() . '.parent_node_id', $parentNodeId]);
                        else {
                            $query->andWhere(BaseActiveRecord::getQuoteColumnName("parent_node_id") . ' is null');
                        }
                    }

                    $query
                        ->addOrderBy(['tree_level' => SORT_ASC])
                        ->addOrderBy(['parent_node_id' => SORT_ASC])
                        ->addOrderBy(['display_number' => SORT_ASC])
                        ->addOrderBy(['sequence_number' => SORT_ASC]);

                    $result = $query->asArray()->all();

                    if ($withCache) {
                        Yii::$app->cache->add($cacheKey, $result, 3600);
                    }

                    return $result;
                }
            }
        } else {
            return null;
        }
    }


    /**
     * 判断用户是否对这个域有管理权限
     * @param $userId
     * @param $domainId
     * @return bool
     */
    public function isUserManagedDomain($userId, $domainId)
    {
        $rbacService = new RbacService();
     
        $isSysManager = $rbacService->isSysManager($userId);
        $isDomainManager = $rbacService->isDomainManager($userId);
        $isSpecialUser = $rbacService->isSpecialUser($userId);

        if ($isSpecialUser) {
            return true;//超级管理员对所有域有管理权限
        } else {
            $domainModel = FwDomain::findOne($domainId);
            if (!empty($domainModel) && $domainModel->status == FwDomain::STATUS_FLAG_NORMAL) {
                $userModel = FwUser::findOne($userId);
                $companyId = $domainModel->company_id;
                if (($isSysManager || $isDomainManager) && $userModel->company_id == $companyId) {
                    //系统管理员和域管理员对本企业所有域有管理权限
                    return true;
                } else {
                    //普通人员只对可管理的域、自己所在域，有管理权限

                    //自己所在域
                    if ($userModel != null && $userModel->domain_id != null && $userModel->domain_id == $domainId) {
                        return true;
                    } else {
                        //可管理的域
                        $cntManageModel = new FwCntManageRef();
                        $cntManageModel->subject_id = $userId;
                        $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                        $cntManageModel->content_id = $domainId;;
                        $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_DOMAIN;
                        $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                        $cntManageRefService = new CntManageRefService();

                        $domainList = $cntManageRefService->getContentList($cntManageModel);

                        if (count($domainList) > 0) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }
            } else {
                return false;
            }
        }
    }


    /**
     * 判断用户是否对这个域有查询权限
     * @param $userId
     * @param $domainId
     * @return bool
     */
    public function isUserSearchedDomain($userId, $domainId)
    {
        $rbacService = new RbacService();
        $isSysManager = $rbacService->isSysManager($userId);
        $isDomainManager = $rbacService->isDomainManager($userId);
        $isSpecialUser = $rbacService->isSpecialUser($userId);

        if ($isSpecialUser) {
            return true;//超级管理员对所有域有查询权限
        } else {
            $domainModel = FwDomain::findOne($domainId);
            if (!empty($domainModel) && $domainModel->status == FwDomain::STATUS_FLAG_NORMAL) {
                $userModel = FwUser::findOne($userId);
                $companyId = $domainModel->company_id;
                if (($isSysManager || $isDomainManager) && $userModel->company_id == $companyId) {
                    //系统管理员和域管理员对本企业所有域有查询权限
                    return true;
                } else {
                    //普通人员只对管理的域、是当前层级的共享域，自己所在域，有查询权限
                    if ($userModel->domain_id != null) {
                        $domainModel = FwDomain::findOne($userModel->domain_id);
                        $parentDomainId = $domainModel->parent_domain_id;
                    } else {
                        $parentDomainId = null;
                    }

                    $domainService = new DomainService();
                    if ($domainService->isSameLevelSharedDomain($userModel->company_id, $parentDomainId, $domainId)) {
                        return true;
                    } else {
                        //自己所在域
                        if ($userModel != null && $userModel->domain_id != null && $userModel->domain_id == $domainId) {
                            return true;
                        } else {
                            //管理的域
                            $cntManageModel = new FwCntManageRef();
                            $cntManageModel->subject_id = $userId;
                            $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                            $cntManageModel->content_id = $domainId;;
                            $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_DOMAIN;
                            $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                            $cntManageRefService = new CntManageRefService();

                            $domainList = $cntManageRefService->getContentList($cntManageModel);

                            if (count($domainList) > 0) {
                                return true;
                            } else {
                                return false;
                            }
                        }
                    }
                }
            } else {
                return false;
            }
        }
    }

    public function getTreeNodeIdListByDomainId($domainIds) {
        if (!empty($domainIds)) {
            $result = FwDomain::find(false)
                ->andFilterWhere(['in', 'kid', $domainIds])
                ->all();

            return $result;
        }
        else {
            return null;
        }
    }

    /**
     * 获取用户管理的域列表
     * @param $userId
     * @return array
     */
    public function getUserManagedDomainList($userId, $withSession = true, $needReturnAll = true, $parentNodeId = null,$includeSubNode = "0", $nodeIdPath = null)
    {
        $rbacService = new RbacService();
        $domainList = null;
        $isSysManager = $rbacService->isSysManager($userId);
        $isDomainManager = $rbacService->isDomainManager($userId);
        $isSpecialUser = $rbacService->isSpecialUser($userId);

        $userModel = FwUser::findOne($userId);
        if (!$isSysManager && !$isDomainManager && !$isSpecialUser) {
            if ($userModel != null && $userModel->domain_id != null) {
                $cntManageModel = new FwCntManageRef();
                $cntManageModel->subject_id = $userId;
                $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
                $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_DOMAIN;
                $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

                $cntManageRefService = new CntManageRefService();

                $domainList = $cntManageRefService->getContentList($cntManageModel);
                
                if (!array_key_exists($userModel->domain_id, $domainList)) {
                    array_push($domainList, $userModel->domain_id);//当前域
                }
            }
        } else {
            

        }

        return $domainList;
    }


    /**
     * 获取用户查询的域列表
     * @param $userId
     * @return array
     */
    private function getUserSearchedDomainList($userId, $withSession = true, $needReturnAll = true, $parentNodeId = null,$includeSubNode = "0", $nodeIdPath = null)
    {
        $rbacService = new RbacService();
        $domainList = null;
        $isSysManager = $rbacService->isSysManager($userId);
        $isDomainManager = $rbacService->isDomainManager($userId);
        $isSpecialUser = $rbacService->isSpecialUser($userId);
        $domainService = new DomainService();

        $userModel = FwUser::findOne($userId);
        if (!$isSysManager && !$isDomainManager && !$isSpecialUser) {
            $cntManageModel = new FwCntManageRef();
            $cntManageModel->subject_id = $userId;
            $cntManageModel->subject_type = FwCntManageRef::SUBJECT_TYPE_USER;
            $cntManageModel->content_type = FwCntManageRef::CONTENT_TYPE_DOMAIN;
            $cntManageModel->reference_type = FwCntManageRef::REFERENCE_TYPE_MANGER;

            $cntManageRefService = new CntManageRefService();

            $domainList = $cntManageRefService->getContentList($cntManageModel);
            
            //非系统管理员
            if ($userModel != null && $userModel->domain_id != null) {
                if (!array_key_exists($userModel->domain_id, $domainList)) {
                    array_push($domainList, $userModel->domain_id);//当前域
                }
            }

            if ($userModel->domain_id != null) {
                $domainModel = FwDomain::findOne($userModel->domain_id);
                $parentDomainId = $domainModel->parent_domain_id;
            } else {
                $parentDomainId = null;
            }

            $selectedResult = $domainService->getSharedDomainListByCompanyId($userModel->company_id, $parentDomainId);//共享域

            $selectedList = ArrayHelper::map($selectedResult, 'kid', 'kid');

            $selected_keys = array_keys($selectedList);

            $domainList = array_unique(array_merge($domainList, $selected_keys));

        } else {
//
//            $companyId = $userModel->company_id;
//
//            if ($companyId != null) {
//                $companyModel = FwCompany::findOne($companyId);
//                if ($companyModel != null && $companyModel->status == FwCompany::STATUS_FLAG_NORMAL) {
//
//                    $selectedResult = $domainService->getAllDomainListByCompanyId($companyId, $needReturnAll, $parentNodeId,$includeSubNode, $nodeIdPath);
//
//                    $selectedList = ArrayHelper::map($selectedResult, 'kid', 'kid');
//
//                    $selected_keys = array_keys($selectedList);
//
//                    $domainList = array_unique(array_merge($domainList, $selected_keys));
//
//                }
//            }
        }

        return $domainList;
    }

    /**
     * 获取当前节点打开状态
     * @param $kid
     * @param $nodeId
     * @return boolean
     */
    public function getOpenedStatus($kid, $nodeId)
    {
        if ($nodeId == '-1')
            return true;
        else {
            return false;
        }
    }


    /**
     * 获取同级共享域
     * @param string $company_id 公司id
     * @param string $parent_domain_id 父域ID
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getShareDomain($company_id, $parent_domain_id)
    {
        $query = FwDomain::find(false);
        $query->innerJoinWith('fwTreeNode');
        $query->andFilterWhere(['=', FwDomain::realTableName() . '.status', FwDomain::STATUS_FLAG_NORMAL]);
        $query->andFilterWhere(['=', FwTreeNode::realTableName() . '.status', FwTreeNode::STATUS_FLAG_NORMAL]);

        if ($parent_domain_id != null) {
            $query->andFilterWhere(['=', 'parent_domain_id', $parent_domain_id]);
        } else {
            $query->andWhere(BaseActiveRecord::getQuoteColumnName("parent_domain_id") . ' is null');
        }
        $query->andFilterWhere(['=', 'company_id', $company_id]);
        $query->andFilterWhere(['=', 'share_flag', FwDomain::SHARE_FLAG_SHARE]);

        $query
            ->addOrderBy(['tree_level' => SORT_ASC])
            ->addOrderBy(['parent_node_id' => SORT_ASC])
            ->addOrderBy(['display_number' => SORT_ASC])
            ->addOrderBy(['sequence_number' => SORT_ASC]);

        return $query->all();
    }

    /**
     * 获取同级独享域
     * @param string $company_id 公司id
     * @param string $parent_domain_id 父域ID
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getUniqueDomain($company_id, $parent_domain_id)
    {
        $query = FwDomain::find(false);
        $query->innerJoinWith('fwTreeNode');
        $query->andFilterWhere(['=', FwDomain::realTableName() . '.status', FwDomain::STATUS_FLAG_NORMAL]);
        $query->andFilterWhere(['=', FwTreeNode::realTableName() . '.status', FwTreeNode::STATUS_FLAG_NORMAL]);

        if ($parent_domain_id != null) {
            $query->andFilterWhere(['=', 'parent_domain_id', $parent_domain_id]);
        } else {
            $query->andWhere(BaseActiveRecord::getQuoteColumnName("parent_domain_id") . ' is null');
        }
        $query->andFilterWhere(['=', 'company_id', $company_id]);
        $query->andFilterWhere(['=', 'share_flag', FwDomain::SHARE_FLAG_EXCLUSIVE]);

        $query
            ->addOrderBy(['tree_level' => SORT_ASC])
            ->addOrderBy(['parent_node_id' => SORT_ASC])
            ->addOrderBy(['display_number' => SORT_ASC])
            ->addOrderBy(['sequence_number' => SORT_ASC]);

        return $query->all();
    }
}
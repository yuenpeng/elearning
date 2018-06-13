<?php
/**
 * User: GROOT (pzyme@outlook.com)
 * Date: 2016/4/28
 * Time: 12:56
 */

namespace common\services\api;

use Yii;
use common\models\framework\FwDomain;
use common\traits\ResponseTrait;
use common\traits\ParserTrait;
use common\traits\ValidatorTrait;
use common\services\framework\ExternalSystemService;
use common\models\treemanager\FwTreeNode;
use common\services\framework\TreeNodeService;
use common\services\framework\DomainService as CommonDomainService;

class DomainService extends FwDomain{

    use ResponseTrait,ParserTrait,ValidatorTrait;

    public $systemKey;
    public function __construct($system_key,array $config = [])
    {
        $this->systemKey = $system_key;
        parent::__construct($config);
    }

    /**
     * 通过企业ID获取相关域列表记录数信息
     * @param $companyId
     * @return Integer
     */
    public function getDomainListCountByCompanyId($companyId) {
        $domainModel = new FwDomain();
        $result = $domainModel->find(false)
            ->andFilterWhere(['=','company_id', $companyId])
            //->andFilterWhere(['=','status', FwDomain::STATUS_FLAG_NORMAL])
            ->count(1);

        return $result;
    }

    /**
     * 通过企业ID获取相关域列表信息
     * @param $companyId
     * @return array|FwDomain[]
     */
    public function getDomainListByCompanyId($companyId,$limit = 1,$offset = 0) {
        $domainModel = new FwDomain();
        $result = $domainModel->find(false)
            ->andFilterWhere(['=','company_id', $companyId])
            ->andFilterWhere(['=','status', FwDomain::STATUS_FLAG_NORMAL])
            ->limit($limit)
            ->offset($offset)
            ->all();

        return $result;
    }

    /**
     * 获取域信息
     * @param $domain_key
     * @param $key_type
     * @return array
     */
    public function detail($domain_key,$key_type) {
        $externalSystemService = new ExternalSystemService();
        if(!in_array($key_type,[1,2])) {
            return $this->exception(['code' => 'common','param' => 'key_type','number' => '001']);
        }
        $domainId = $key_type == 1 ? $externalSystemService->getDomainIdByDomainKey($this->systemKey,$domain_key) : $domain_key;
        $externalDomainKey = $key_type == 1 ? $domain_key : $externalSystemService->getDomainKeyByDomainId($this->systemKey, $domainId);

        $domainModel = FwDomain::findOne($domainId);
        if(empty($domainModel)) {
            return $this->exception([
                'code' => 'common',
                'number' => '006',
                'name' => Yii::t('common', 'DataNotExist')
            ]);
        }
        $domainResult["domain_key"] = $externalDomainKey;
        $domainResult["domain_id"] = $domainModel->kid;
        $domainResult["company_id"] = $domainModel->company_id;
        $domainResult["domain_code"] = $domainModel->domain_code;
        $domainResult["domain_name"] = $domainModel->domain_name;
        $domainResult["parent_domain_name"] = null;
        $domainResult["share_flag"] = $domainModel->share_flag;

        if (!empty($domainModel->parent_domain_id)) {
            $domainResult["parent_domain_id"] = $domainModel->parent_domain_id;
            $domainResult["parent_domain_key"] = $externalSystemService->getDomainKeyByDomainId($this->systemKey, $domainModel->parent_domain_id);
            $parentDomainModel = FwDomain::findOne($domainModel->parent_domain_id);
            $domainResult["parent_domain_name"] = $parentDomainModel->domain_name;
        }
        else {
            $domainResult["parent_domain_id"] = null;
            $domainResult["parent_domain_key"] = null;
        }

        $domainResult["status"] = $domainModel->status;
        $domainResult["description"] = empty($domainModel->description) ? null: $domainModel->description;

        return $this->response([
            'code' => 'OK',
            'data' => [
                'domain' => $domainResult
            ]
        ]);
    }

    /**
     * 修改域信息
     * @param array $domains
     * @param string $codeName
     * @return array
     */
    public function modify($domain_id) {

        $commonDomainService = new CommonDomainService();
        $DomainId = $commonDomainService->getDomainIdByTreeNodeId($domain_id);
        $domainModel = FwDomain::findOne($DomainId);
        if(empty($domainModel)) {
            return $this->exception(['message' => 'model not exists']);
        }
        $treeNodeModel = FwTreeNode::findOne($domainModel->tree_node_id);
        if ($treeNodeModel != null) {
            $domainModel->domain_name = $treeNodeModel->tree_node_name;
            $domainModel->domain_code = $treeNodeModel->tree_node_code;
        }

        if ($domainModel->save()) {
            return $this->response(['data' => 'success']);
        }else {
            return $this->exception(['message' => 'failure']);
        }
    }

    /**
     * 删除域信息
     * @param $domain_key
     * @param $key_type
     * @return array
     */
    public function remove($domain_key,$key_type) {
        if(!in_array($key_type,[1,2])) {
            return $this->exception(['code' => 'common','param' => 'key_type','number' => '001']);
        }

        $externalSystemService = new ExternalSystemService();
        $domainId = $key_type == 1 ? $externalSystemService->getDomainIdByDomainKey($this->systemKey,$domain_key) : $domain_key;
        $model = FwDomain::findOne($domainId);
        if(empty($model)) {
            return $this->exception(['name' => Yii::t('common', 'DataNotExist'),'number' => '006']);
        }

        $externalSystemService->deleteDomainInfoByDomainId($domainId);
        $model->systemKey = $this->systemKey;
        if( !$model->delete()) {
            return $this->exception(['number' => '007','name' => Yii::t('common', 'Operation_Confirm_Warning_Failure')]);
        }
        return $this->response(['code' => 'OK','data' => ['delete_result' => true]]);
    }
}
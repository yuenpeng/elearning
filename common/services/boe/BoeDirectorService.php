<?php
namespace common\services\boe;
use common\base\BoeBase;
use common\services\boe\BoeBaseService;
use yii\db\Expression;
use yii\db\Query;
use Yii;
use common\models\boe\BoeDirectorUser;
use common\base\BaseActiveRecord;
use common\helpers\TLoggerHelper;
use yii\data\ActiveDataProvider;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use common\models\treemanager\FwTreeNode;
use common\services\framework\RbacService;
use common\services\framework\UserOrgnizationService;
use common\models\framework\FwOrgnization;
use common\models\framework\FwUser;
use backend\services\UserService;



 
 


 
/**
 * Desc: 新任总监特训营相关服务
 * Frame:  
 * User: songsang
 * Date: 2017/9/1
 */
class BoeDirectorService extends FwUser{
    public  $cacheTime = 43200;  //缓存12小时
    public  $currentLog = array();
    public  $cacheNameFix = 'boe_';
    
    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    protected  function isNoCacheMode() {
        return Yii::$app->request->get('no_cache') == 1 ? true : false;
    }

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    protected  function isDebugMode() {
        return Yii::$app->request->get('debug_mode') == 1 ? true : false;
    }

     /**
     * 读取缓存的封装
     * @param type $cache_name
     * @param type $debug
     * @return type
     */
    protected  function getCache($cache_name) {
        if (self::isNoCacheMode()) {
            return NULL;
        }
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        $sult = Yii::$app->cache->get($new_cache_name);
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From Cache,Cache Name={$new_cache_name}\n";
            if ($sult) {
                print_r($sult);
            } else {
                print_r("Cache Not Hit");
            }
            echo "\n</pre>";
        }
        return $sult;
    }

    /**
     * 修改缓存的封装
     * @param type $cache_name
     * @param type $data
     * @param type $time
     * @param type $debug
     */
    protected  function setCache($cache_name, $data = NULL) {
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        Yii::$app->cache->set($new_cache_name, $data, self::$cacheTime); // 设置缓存
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }

    //导入数据
    public function saveImport($data, $file, $fileMd5)
    {
        $errColum = 'G';  
        if (!file_exists($file)) {
            return false;
        }

        $reader = \PHPExcel_IOFactory::createReaderForFile($file);

        $objPHPExcel = $reader->load($file);

        $sheet = $objPHPExcel->setActiveSheetIndex(0);
        $sheet->getColumnDimension($errColum)->setAutoSize(true);
        $sheet->setCellValue($errColum.'1', 'result');
            
        $dataFrom = 'DirectorUserImport_' . $fileMd5;
        $user_no =[];

        //操作数组
        $saveList = [];
        $changeList = [];
        $deleteList = [];

        TLoggerHelper::Error("Import User");

        foreach ($data as $index => $item) {
            if ($item['op'] === 'A') {
                //工号唯一检查
                $model = new BoeDirectorUser();
                $query = $model->find(false)
                ->andFilterWhere(['=', 'user_no', $item['user_no']]);
                $count = $query->count(1);
                if ($count|| in_array($item['user_no'], $user_no)) {
                    $sheet->setCellValue($errColum . ($index + 1), '工号 already exists');
                    continue;
                }
                //字段检查
                if ($item['year']=='') {
                    $sheet->setCellValue($errColum . ($index + 1), '年度  is incorrect');
                    continue;
                }
                if ($item['user_no']=='') {
                    $sheet->setCellValue($errColum . ($index + 1), '工号 is incorrect');
                    continue;
                }
                if ($item['id_number']=='') {
                    $sheet->setCellValue($errColum . ($index + 1), '身份证  is incorrect');
                    continue;
                }
                if ($item['real_name']=='') {
                    $sheet->setCellValue($errColum . ($index + 1), '姓名  is incorrect');
                    continue;
                }
              
                $user_no[] = $item['user_no'];


                //用户信息检查
                $where  = array('and',
                    array('=', 'is_deleted', '0'),
                    array('=', 'user_no',$item['user_no']),
                );
                $user = FwUser::find(false)->select('orgnization_id,id_number,user_name')->where($where)->asArray()->one();
                if(empty($user)){
                    $sheet->setCellValue($errColum . ($index + 1), '用户不存在');
                    continue;
                }elseif($user['id_number']&&$user['id_number']!=$item['id_number']){
                    $sheet->setCellValue($errColum . ($index + 1), '用户身份证号码错误');
                    continue;
                }elseif($user['real_name']!=$item['user_name']){
                    $sheet->setCellValue($errColum . ($index + 1), '用户姓名错误');
                    continue;
                }elseif(!$user['orgnization_id']){
                    $sheet->setCellValue($errColum . ($index + 1), '用户未指定组织部门（内部）');
                    continue;
                }else{
                    $model->orgnization_id = $user['orgnization_id'];
                    // $sheet->setCellValue('K' . ($index + 1), $orgnization['orgnization_id']);
                }       

                //组织者检查
                $model->year = $item['year'];
                $model->user_no = $item['user_no'];
                $model->id_number = $item['id_number'];
                $model->real_name = $item['real_name'];
                $model->organizer = $item['organizer'];
                $sheet->setCellValue($errColum . ($index + 1), 'success');
                $saveList[] = $model;
            } elseif ($item['op'] === 'U') {
                $model = BoeDirectorUser::findOne(['user_no' => $item['user_no']]);
                if ($model) {
                    $model->year = $item['year'];
                    $model->id_number = $item['id_number'];
                    $model->real_name = $item['real_name'];
                    $model->organizer = $item['organizer'];
           
                    $sheet->setCellValue('I' . ($index + 1), 'success');
                    $changeList[] = $model;
                } else {
                    $sheet->setCellValue('I' . ($index + 1), 'user not found');
                }
            } elseif ($item['op'] === 'D') {
                $model = BoeDirectorUser::findOne(['user_no' => $item['user_no']]);
                if ($model) {
                    $sheet->setCellValue('I' . ($index + 1), 'success');
                    $deleteList[] = $model;
                } else {
                    $sheet->setCellValue('I' . ($index + 1), 'user not found');
                }
            }
        }

        //生成导入结果集
        $class = get_class($reader);
        $class = explode('_', $class);
        $writerType = end($class);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $writerType);
        $objWriter->save($file);

        $errMsg = '';

        //数据入库处理开始
        if (count($saveList) > 0) {
            $ret = BaseActiveRecord::batchInsertSqlArray($saveList, $errMsg);
            if (!$ret) {
                return $errMsg;
            }
             
        }
        if (count($changeList) > 0) {
            $ret = BaseActiveRecord::batchUpdateNormalMode($changeList, $errMsg);
            if (!$ret) {
                return $errMsg;
            }
        }
        if (count($deleteList) > 0) {
            $ret = BaseActiveRecord::batchDeleteNormalMode($deleteList, $errMsg);
            if (!$ret) {
                return $errMsg;
            }
        }
        return true;
    }
    //search 
    /**
     * 搜索用户数据
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params, $managerFlag, $parentNodeId, $includeSubNode)
    {

        $query = BoeDirectorUser::find(false);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);
    
 

        if (!$this->validate()) {
            // uncomment the following line if you do not want to any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query
            ->leftJoin(FwOrgnization::realTableName(),
                BoeDirectorUser::tableName() . "." . BaseActiveRecord::getQuoteColumnName("orgnization_id") . " = " . FwOrgnization::tableName() . "." . BaseActiveRecord::getQuoteColumnName("kid"))
            ->leftJoin(FwTreeNode::realTableName(),
                FwOrgnization::tableName() . "." . BaseActiveRecord::getQuoteColumnName("tree_node_id") . " = " . FwTreeNode::tableName() . "." . BaseActiveRecord::getQuoteColumnName("kid"))
//            ->innerJoinWith('fwOrgnization.fwTreeNode')
//            ->innerJoinWith('orgnization.treeNode')
            ->andFilterWhere(['like', 'real_name', trim(urldecode($this->real_name))])
            ->andFilterWhere(['like', 'user_no', trim(urldecode($this->user_no))]);
           
//            ->andFilterWhere(['=', FwTreeNode::realTableName(). '.is_deleted', FwTreeNode::DELETE_FLAG_NO ])
//            ->andFilterWhere(['=', FwOrgnization::realTableName(). '.is_deleted', FwOrgnization::DELETE_FLAG_NO ])
           

//         if ($includeSubNode == '1') {
//             if ($parentNodeId != '') {
//                 $treeNodeModel = FwTreeNode::findOne($parentNodeId);
//                 $nodeIdPath = $treeNodeModel->node_id_path . $parentNodeId . "/%";

//                 $condition = ['or',
//                     BaseActiveRecord::getQuoteColumnName("node_id_path") . " like '" . $nodeIdPath . "'",
//                     ['=', FwOrgnization::realTableName() . '.tree_node_id', $parentNodeId]];
//                 $query->andFilterWhere($condition);
// //                $query->andWhere(BaseActiveRecord::getQuoteColumnName("node_id_path") . " like '" . $nodeIdPath . "'");
//             } else {
//                 $condition = ['or',
//                     BaseActiveRecord::getQuoteColumnName("node_id_path") . " like '/%'",
//                     BaseActiveRecord::getQuoteColumnName("orgnization_id") . ' is null'];
//                 $query->andFilterWhere($condition);
//             }

// //            $treeNodeService = new TreeNodeService();
// //            $treeTypeId = $treeNodeService->getTreeTypeId('orgnization');
// //            $query->andFilterWhere(['=','tree_type_id',$treeTypeId]);

//         } else {
//             if ($parentNodeId == '') {
//                 $query->andWhere(BaseActiveRecord::getQuoteColumnName("orgnization_id") . ' is null');
//             } else {
//                 $query->andFilterWhere(['=', FwOrgnization::realTableName() . '.tree_node_id', $parentNodeId]);
//             }
//         }


//        if (is_array($treeNodeIdList)) {
//            if (in_array('', $treeNodeIdList)) {
////                $condition = ;
//
//                $condition = ['or',
//                    [ 'in',FwOrgnization::realTableName() . '.tree_node_id',$treeNodeIdList],
//                    BaseActiveRecord::getQuoteColumnName("orgnization_id") . ' is null'
//                    ];
//                //$condition[] = ['in', Orgnization::tableName() . '.tree_node_id', $treeNodeIdList];
//                $query->andFilterWhere($condition);
//            }
//            else
//            {
//                $condition = [ 'in',FwOrgnization::realTableName() . '.tree_node_id',$treeNodeIdList];
//                //$condition[] = ['in', Orgnization::tableName() . '.tree_node_id', $treeNodeIdList];
//                $query->andFilterWhere($condition);
//            }
//        }
//        else {
//            if ($treeNodeIdList == '') {
//                $query->andWhere(BaseActiveRecord::getQuoteColumnName("orgnization_id") . ' is null');
//            } else {
//                $query->andFilterWhere(['=', FwOrgnization::realTableName() . '.tree_node_id', $treeNodeIdList]);
//            }
//        }

        // if (!Yii::$app->user->getIsGuest()) {
        //     $userId = Yii::$app->user->getId();
        //     $companyId = Yii::$app->user->identity->company_id;

        //     $rbacService = new RbacService();
        //     $isSpecialUser = $rbacService->isSpecialUser($userId);
        //     $isSysManager = $rbacService->isSysManager($userId);

        //     $selectedResult = null;
        //     //如果是特殊用户，则取所有数据
        //     if (!$isSpecialUser) {
        //         //如果不是特殊用户（超级管理员)，则根据授权范围取，（系统管理员默认是空，所以要特殊过滤成当前企业所有数据）
        //         $userOrgnizationService = new UserOrgnizationService();
        //         $userOrgnizations = $userOrgnizationService->getUserManagedOrgnizationList($userId, false);
        //         if (!empty($userOrgnizations)) {
        //             $selectedResult = $userOrgnizationService->getTreeNodeIdListByOrgnizationId($userOrgnizations);
        //         } else {
        //             if ($isSysManager) {
        //                 $query->andFilterWhere(['=', BoeDirectorUser::realTableName() . '.company_id', $companyId]);
        //             }
        //         }
        //     }

        //     if (isset($selectedResult) && $selectedResult != null) {
        //         $selectedList = ArrayHelper::map($selectedResult, 'kid', 'kid');

        //         $orgnizationIdList = array_keys($selectedList);

        //         $query->andFilterWhere(['in', BoeDirectorUser::realTableName() . '.orgnization_id', $orgnizationIdList]);
        //     } else {
        //         if (!$isSpecialUser && !$isSysManager) {
        //             $query->andWhere(BaseActiveRecord::getQuoteColumnName("kid") . ' is null');
        //         }
        //     }

        // }


//            ->andFilterWhere(['like', 'limitation', $this->limitation])
//            ->andFilterWhere(['like', 'code_gen_way', $this->code_gen_way])
//            ->andFilterWhere(['like', 'code_prefix', $this->code_prefix]);
//        $sort->attributes=['LPT_NAME'=> [
//            'asc' => ['LPT_NAME' => SORT_ASC],
//            'desc' => ['LPT_NAME' => SORT_DESC]]];
        $dataProvider->setSort(false);

//        $query->addOrderBy([FwTreeNode::tableName() . '.tree_level' => SORT_ASC]);
//        $query->addOrderBy([FwTreeNode::tableName() . '.parent_node_id' => SORT_ASC]);
//        $query->addOrderBy([FwTreeNode::tableName() . '.sequence_number' => SORT_ASC]);
//        $query->addOrderBy([FwUser::realTableName() .'.user_name' => SORT_ASC]);
 
        return $dataProvider;
    }

    //获取岗位信息
    public function getPositionName($user_no){
        $user = FwUser::findOne(['user_no'=>$user_no]);
        if(!$user){
            return '';
        }
        $query = (new Query())->from('`eln_fw_user_position`');
        $sult = $query
            ->select(['position_name'])
            ->leftJoin('`eln_fw_position`', '`eln_fw_user_position`.position_id = `eln_fw_position`.kid')
            ->where("is_master=1 and `eln_fw_user_position`.user_id= '".$user->kid."'")
            ->orderby('is_master desc')
            ->limit(1)
            ->all();
          //  echo $query->createCommand()->getRawSql();
        return $sult[0]['position_name'];
    }
}

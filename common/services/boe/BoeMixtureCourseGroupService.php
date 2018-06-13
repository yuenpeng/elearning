<?php
/**
 * Created by PhpStorm.
 * User: Vendoryep
 * Date: 2018/5/16
 * Time: 16:47
 */

namespace common\services\boe;


use common\models\boe\BoeMixtureCourseGroup;
use common\models\boe\BoeMixtureCourseGroupTeacher;
use common\models\boe\BoeMixtureCourseGroupCourse;
use common\models\boe\BoeMixtureCourseGroupCategory;
use common\models\boe\BoeMixtureCourseTemp;
use common\models\boe\BoeMixtureGroupTemp;
use common\models\boe\BoeMixtureTaskCourse;
use common\models\boe\BoeMixtureTaskCourseTemp;
use common\models\learning\LnCourse;
use Yii;
use common\services\framework\UserCompanyService;
use components\widgets\TPagination;
use common\base\BaseActiveRecord;
use yii\helpers\ArrayHelper;

class BoeMixtureCourseGroupService extends  BoeMixtureCourseGroup
{
    public $is_deleted = 0;
    const RELATE_TYPE_YES = "1";
    const RELATE_TYPE_NO = "0";


    /**
     * 课程组列表
     * @param $params
     * @return array
     */
    public function getCourseGroupList($params, $isCourseCount = true){
        $model = BoeMixtureCourseGroup::find(false);
        if (!empty($params['keyword'])){
            $keyword = htmlspecialchars($params['keyword']);
            $model->andFilterWhere(['like', 'group_name', $keyword]);
        }
        $model->andFilterWhere(['owner_id'=>$params['ownerId']]);
        if (isset($params['status']) && $params['status'] != ""){
            $model->andFilterWhere(['=', 'status', $params['status']]);
        }
        if (!empty($params['TreeNodeKid']) && $params['TreeNodeKid'] != '-1'){
            $categoryId = $this->getTreeNodeIdToCategoryId($params['TreeNodeKid']);
            if (!empty($categoryId)){
                $model->andFilterWhere(['=', 'category_id', $categoryId]);
            }
        }
        $count = $model->count('kid');
        if ($count > 0) {
            if ($isCourseCount) {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy("created_at DESC")->asArray()->all();

                foreach ($result as $key => $item) {
                    $memberCount = BoeMixtureCourseGroupCourse::find(false)->andFilterWhere(['=', 'course_group_id', $item['kid']])->count('kid');
                    $result[$key]['course_count'] = $memberCount;
                }
            }else{
                $page = null;
                $result = $model->orderBy("created_at DESC")->all();
            }
            return ['data' => $result, 'page' => $page];
        }else{
            return ['data' => null, 'page' => null];
        }
    }
    /**
     * tree_node_id 转 category_id
     * @param $tree_node_id
     * @return array|null|string
     **/
    public function getTreeNodeIdToCategoryId($tree_node_id){
        if (empty($tree_node_id)) return null;
        $categories = BoeMixtureCourseGroupCategory::findAll(['tree_node_id'=>$tree_node_id],false);
        if (is_array($tree_node_id)){
            $result = array();
            foreach ($categories as $value){
                $result[] = $value->kid;
            }
            return $result;
        }else{
            return $categories ? $categories[0]->kid : '';
        }
    }

    /**
     * 刷新页面清空数据
     * @param $userId
     * @param $companyId
     */
    public function deleteCourseTempAll($userId){
        $condition = [
            ':owner_id' => $userId,
            ':course_type'=>BoeMixtureCourseTemp::COURSE_IS_GROUP,
        ];
        BoeMixtureCourseTemp::physicalDeleteAll("'owner_id'=':owner_id' and 'course_type'=':course_type'", $condition);
    }
    /**
     * @param $params
     * @return array
     */
    public function getCourseList($params){
        $userId = Yii::$app->user->getId();
        $companyService = new UserCompanyService();
        $manageCompanyList = $companyService->getUserManagedCompanyList($userId);
        $model = LnCourse::find(false)
            ->andFilterWhere(['in', 'company_id', $manageCompanyList])
            ->andFilterWhere(['=', 'status', LnCourse::COURSE_START])
            ->andFilterWhere(['=','is_deleted',$this->is_deleted]);
        $ids = array();
        if(isset($params['id']) && !empty($params['id'])){
            $task_id = $params['id'];
            $course_ids = BoeMixtureTaskCourse::find(false)->andFilterWhere(['=','task_id',$task_id])->select('object_id')->asArray()->all();
            $ids = ArrayHelper::getColumn($course_ids,'object_id');

        }
        if(isset($params['relate_type']) && in_array($params['relate_type'],[self::RELATE_TYPE_NO,self::RELATE_TYPE_YES])){
            if($params['relate_type'] == self::RELATE_TYPE_YES){
                $model->andWhere(['in','kid',$ids]);
            }else{
                $model->andWhere(['not in','kid',$ids]);
            }
        }
        if (!empty($params['keyword'])) {
            $keyword = htmlspecialchars($params['keyword']);
            $model->andFilterWhere(['like', 'course_name', $keyword]);
        }
        $count = $model->count('kid');
        if ($count) {
            if (!empty($params['format'])){
                $result = $model->all();
                return ['data' => $result];
            }else {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy('created_at DESC,kid')->all();
                return ['data' => $result, 'page' => $page];
            }
        }else{
            return ['data' => null, 'page' => null];
        }

    }

    /**
     * 获取课程组列表
     */

    public function getGroupList($params){
        $task_id = $params['id'];
        $model = BoeMixtureCourseGroup::find(false)
            ->andFilterWhere(['=', 'status', BoeMixtureCourseGroup::GROUP_STATUS_USED])
            ->andFilterWhere(['=','is_deleted',BoeMixtureCourseGroup::DELETE_FLAG_NO]);
        $ids = array();
        if(isset($params['id']) && !empty($params['id'])){
            $course_ids = BoeMixtureTaskCourse::find(false)->andFilterWhere(['=','task_id',$task_id])->select('object_id')->asArray()->all();
            $ids = ArrayHelper::getColumn($course_ids,'object_id');

        }
        if(isset($params['relate_type']) && in_array($params['relate_type'],[self::RELATE_TYPE_NO,self::RELATE_TYPE_YES])){
            if($params['relate_type'] == self::RELATE_TYPE_NO){
                $model->andWhere(['not in','kid',$ids]);
            }else{
                $model->andWhere(['in','kid',$ids]);
            }
        }
        if (!empty($params['keyword'])) {
            $keyword = htmlspecialchars($params['keyword']);
            $model->andFilterWhere(['like', 'group_name', $keyword]);
        }

        $count = $model->count('kid');
        if ($count) {
            if (!empty($params['format'])){
                $result = $model->all();
                return ['data' => $result];
            }else {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy('created_at DESC,kid')->all();
                return ['data' => $result, 'page' => $page];
            }
        }else{
            return ['data' => null, 'page' => null];
        }

    }
    /**
     * @param $groupID
     * @param $course_batch
     * @return array|string
     */
    public function createCourseTemp($group_id, $course_batch){
        if (empty($group_id)){
            return  ['result' => 'fail'];
        }
        $data = BoeMixtureCourseGroup::findOne($group_id);
        if (empty($data)){
            return  ['result' => 'fail'];
        }
        $dataMemberAll = BoeMixtureCourseGroupCourse::findAll(['course_group_id' => $data->kid]);
        if (empty($dataMemberAll)){
            return  ['result' => 'fail'];
        }
        $batchModel = array();
        foreach ($dataMemberAll as $item){
            $course_info = LnCourse::findOne($item->course_id);
            $model = new BoeMixtureCourseTemp();
            $model->course_batch = $course_batch;
            $model->owner_id = $data->owner_id;
            $model->user_id = $course_info->created_by;
            $model->course_id = $course_info->kid;
            $model->course_name = $course_info->course_name;
            $model->course_credit = $course_info->default_credit;
            $model->course_created_time = $course_info->created_at;
            $model->course_open_status = $course_info->open_status;
            array_push($batchModel, $model);

        }
        $errMsg = "";
        if (!empty($batchModel)) {
            BaseActiveRecord::batchInsertSqlArray($batchModel, $errmsg);
        }
        return $errMsg;
    }

    /**
     * 批量插入课程临时数据
     * @param $params
     * @return bool|string
     */
    public function betchInsertCourseTemp($params){
        $course = $params['course'];
        BoeMixtureCourseTemp::updateAll(['is_deleted' => 0], 'course_batch=:course_batch', [':course_batch' =>$params['course_batch']]);
        if (empty($course)){
            return false;
        }
        if (!empty($course)){
            $ownerId = Yii::$app->user->getId();
            $batchModel = array();
            $errMsg = "";
            foreach ($course as $v){
                $item = LnCourse::findOne($v);
                $hasData = BoeMixtureCourseTemp::findOne(['course_id'=>$v,'course_batch'=>$params['course_batch'],'owner_id' => $ownerId,'user_id'=>$item->created_by]);
                if (empty($hasData)) {
                    $model = new BoeMixtureCourseTemp();
                    $model->course_batch = $params['course_batch'];
                    $model->owner_id = $ownerId;
                    $model->course_type = $params['course_type'];
                    $model->user_id = $item->created_by;
                    $model->course_id = $item->kid;
                    $model->course_name = $item->course_name;
                    $model->course_credit = $item->default_credit;
                    $model->course_created_time = $item->created_at;
                    $model->course_open_status = $item->open_status;
                    array_push($batchModel, $model);
                }else{
                    $errMsg = $hasData->getErrors();
                }
            }
            if (!empty($batchModel)) {
                BaseActiveRecord::batchInsertSqlArray($batchModel, $errmsg);
            }
            return $errMsg;
        }else{
            return false;
        }
    }

    /**
     * 查询临时列表
     * @param $params
     * @return array
     */
    public function getCourseTemp($params){
        $model = BoeMixtureCourseTemp::find(false);
        if ($params['ownerId']) {
            $model->andFilterWhere(['=', 'owner_id', $params['ownerId']]);
        }

        if (!empty($params['course_batch'])){
            $model->andFilterWhere(['=', 'course_batch', $params['course_batch']]);
        }

        if (!empty($params['keyword'])) {
            $keyword = htmlspecialchars($params['keyword']);
            $model->andFilterWhere(['like', 'course_name', $keyword]);
        }

        $count = $model->count('kid');
        if ($count) {
            if (!empty($params['format'])){
                $result = $model->orderBy('created_at desc')->all();
                return ['data' => $result];
            }else {
                $page = new TPagination(['defaultPageSize' => $params['defaultPageSize'], 'totalCount' => $count]);
                $result = $model->offset($page->offset)->limit($page->limit)->orderBy('created_at desc,user_id')->all();
                return ['data' => $result, 'page' => $page];
            }
        }else{
            return ['data' => null, 'page' => null];
        }
    }

    /**
     * @param $params
     */
    public function setCourseBatchCourseName($params){
        $sessionAudienceBatchUserName = "mixture_course_batch_course_name".$params['course_batch'];
        $owner_id = Yii::$app->user->getId();
        $model = BoeMixtureCourseTemp::find(false);
        $result = $model->andFilterWhere(['=', 'course_batch', $params['course_batch']])
            ->andFilterWhere(['=', 'owner_id', $owner_id])
            ->select('course_name')
            ->asArray()
            ->all();
        if (!empty($result)){
            $result = ArrayHelper::map($result, 'course_name', 'course_name');
            $result = array_keys($result);
        }
        Yii::$app->session->set($sessionAudienceBatchUserName, $result);
    }

    /**
     * @param $kid
     */
    public function deleteCourseTemp($kid){
        $kid = explode(',', $kid);
        BoeMixtureCourseTemp::physicalDeleteAllByKid($kid);
    }
    /**
     * @param $kid
     */
    public function deleteGroupTemp($kid){
        $kid = explode(',', $kid);
        BoeMixtureGroupTemp::physicalDeleteAllByKid($kid);
    }

    /**
     * 保存临时数据表
     * @param $params
     * @return array
     */
    public function saveCourseTemp($params){
        if (empty($params['group_name'])){
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'group_name_not_empty')];
        }
        $course_batch = $params['course_batch'];
        if (empty($course_batch)) {
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'loading_fail')];
        }
        $ownerId = $params['owner_id'];
        if(empty($params['groupId'])){
            $res = $this->isExistsGroupName($params['group_name']);
            if ($res){
                return ['result' => 'fail', 'errmsg' => Yii::t('common', 'having_group_name')];
            }
        }
        $boeGroupCourseTempModel = BoeMixtureCourseTemp::find(false);
        $boeGroupCourseTempModel->andFilterWhere(['=', 'course_batch', $course_batch])
            ->andFilterWhere(['=', 'owner_id', $ownerId]);
        $course_count = $boeGroupCourseTempModel->count('kid');
        $course_credit = $boeGroupCourseTempModel->sum('course_credit');
        $course_ids = $boeGroupCourseTempModel->select('course_id')->orderBy('created_at desc')->asArray()->all();

        if (empty($course_ids)){
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'course_not_empty')];
        }

        $GroupId = !empty($params['groupId']) ? $params['groupId'] : "";
        $pass_credit = $params['pass_credit'];
        $course_credit = $this->getCourseCredit($ownerId,$course_batch);
        if($pass_credit > $course_credit){
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'credit_error')];
        }
        $treeNodeId = $params['TreeNodeId'];
        $categoryId = $this->getTreeNodeIdToCategoryId($treeNodeId);
        $model = empty($GroupId) ? new BoeMixtureCourseGroup() : BoeMixtureCourseGroup::findOne($GroupId);
        $model->group_name = $params['group_name'];
        $model->description = $params['desc'];
        $model->complete_rule = $pass_credit;
        $model->course_number = $course_count;
        $model->status = $params['status'];
        $model->owner_id = $ownerId;
        $model->group_score = $course_credit;
        $model->course_batch = $course_batch;
        $model->saveEncode = true;
        if (empty($GroupId)) {
            $model->category_id = $categoryId;
            $model->needReturnKey = true;
            $result = $model->save();
        }else{
            $result = $model->update();
        }
        if ($result !== false){
            $group_id = $model->kid;
            //更新讲师信息
            if(!empty($params['teacher_ids'])){
                $this->updateTeacher($group_id,$params['teacher_ids']);
            }
            $modelArray = array();
            if (!empty($GroupId)) {
                //更新
                $updateArray = array();
                foreach ($course_ids as $item) {
                    $modelTemp = BoeMixtureCourseGroupCourse::findOne(['course_group_id' => $group_id, 'course_id' => $item['course_id']]);
                    if (empty($modelTemp)){
                        $modelTemp = new BoeMixtureCourseGroupCourse();
                        $modelTemp->course_group_id = $group_id;
                        $modelTemp->course_id = $item['course_id'];
                        array_push($modelArray, $modelTemp);
                    }else{
                        array_push($updateArray, $modelTemp);
                    }
                }
                $insertArray = array();
                $errMsg = "";
                $resultId = array();
                if (!empty($modelArray)) {
                    BaseActiveRecord::batchInsertNormalMode($modelArray, $errmsg, true, $resultId);
                    $insertArray = $resultId;
                }
                $errMsg = "";
                $updateResult = array();
                if (!empty($updateArray)) {
                    BaseActiveRecord::batchUpdateNormalMode($updateArray, $errmsg, true, $updateResult);
                    if (!empty($insertArray)) {
                        $insertArray = array_merge($insertArray, $updateResult);
                    }else{
                        $insertArray = $updateResult;
                    }
                }
                $resultIdArray = array_filter($insertArray);
                $resultIdArray = array_unique($resultIdArray);
                if (!empty($resultIdArray)) {
                    $resultIdArraySql = "'".join("','", $resultIdArray)."'";
                    BoeMixtureCourseGroupCourse::updateAll(
                        [
                            'is_deleted' => 1
                        ],
                        'kid not in ('.$resultIdArraySql.') and course_group_id=:course_group_id',
                        [':course_group_id' => $group_id]
                    );
                }
                //return ['result' => 'success'];
            }else{
                //新添加课程组课程
                foreach ($course_ids as $item) {
                    $modelTemp = new BoeMixtureCourseGroupCourse();
                    $modelTemp->course_group_id = $group_id;
                    $modelTemp->course_id = $item['course_id'];
                    array_push($modelArray, $modelTemp);
                }
                $errMsg = "";
                if (!empty($modelArray)) {
                    BaseActiveRecord::batchInsertNormalMode($modelArray, $errmsg, true, $resultId);
                }
            }
            $this->deleteCourseTempAll($ownerId);
            return ['result' => 'success'];
        }else{
            return ['result' => 'fail', 'errmsg' => Yii::t('common', 'SaveFail')];
        }
    }

    /**
     * 更新讲师信息
     */
    public function updateTeacher($group_id,$teachers){
        $condition = [
            ':course_group_id' => $group_id,
        ];
        BoeMixtureCourseGroupTeacher::deleteAll("course_group_id=:course_group_id", $condition);
        $teacherArray = [];
        foreach($teachers as $teacher)
        {
            $groupTeacherModel = new BoeMixtureCourseGroupTeacher();
            $groupTeacherModel->title = $teacher['title'];
            $groupTeacherModel->course_group_id = $group_id;
            $groupTeacherModel->teacher_id = $teacher['kid'];
            array_push($teacherArray,$groupTeacherModel);
        }
        if (!empty($teacherArray)) {
            $result = BaseActiveRecord::batchInsertNormalMode($teacherArray, $errmsg, true, $resultId);
        }

    }

    /**
     * 检查是否存在相同的课程组名称
     * @param $ownerId
     * @param $companyId
     * @param $group_name
     * @return bool
     */
    public function isExistsGroupName($group_name){
        $model = BoeMixtureCourseGroup::find(false)
            ->andFilterWhere(['=', 'group_name', $group_name]);
        $result = $model->one();

        if (!empty($result)){
            return true;
        }else{
            return false;
        }

    }

    /**
     * 获取临时表中课程的总学分
     */

    public function getCourseCredit($userId,$course_batch){
        $model = BoeMixtureCourseTemp::find(false);
        $model->andFilterWhere(['=','owner_id',$userId])
            ->andFilterWhere(['=','course_batch',$course_batch]);
        $credit = $model->sum('course_credit');
        return $credit;
    }


    /**
     * 发布
     * @param $group_id
     * @return array
     * @throws \Exception
     */
    public function publishCourseGroup($group_id){
        if (empty($group_id)){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        $data = BoeMixtureCourseGroup::findOne($group_id);
        if (empty($data)){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        if ($data['status'] == BoeMixtureCourseGroup::STATUS_FLAG_NORMAL){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        $data->status = BoeMixtureCourseGroup::STATUS_FLAG_NORMAL;
        if ($data->update() !== false){
            return  ['result' => 'success', 'errmsg' => ''];
        }else{
            return  ['result' => 'fail', 'errmsg' => ''];
        }
    }

    /**
     * 启用
     * @param $group_id
     * @return array
     * @throws \Exception
     */
    public function startCourseGroup($group_id){
        if (empty($group_id)){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        $data = BoeMixtureCourseGroup::findOne($group_id);
        if (empty($data)){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        if ($data['status'] != BoeMixtureCourseGroup::STATUS_FLAG_STOP){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        $data->status = BoeMixtureCourseGroup::STATUS_FLAG_NORMAL;
        if ($data->update() !== false){
            return  ['result' => 'success', 'errmsg' => ''];
        }else{
            return  ['result' => 'fail', 'errmsg' => ''];
        }
    }

    /**
     * 停用
     * @param $group_id
     * @return array
     * @throws \Exception
     */
    public function stopCourseGroup($group_id){
        if (empty($group_id)){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        $data = BoeMixtureCourseGroup::findOne($group_id);
        if (empty($data)){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        if ($data['status'] != BoeMixtureCourseGroup::STATUS_FLAG_NORMAL){
            return  ['result' => 'fail', 'errmsg' => ''];
        }
        $data->status = BoeMixtureCourseGroup::STATUS_FLAG_STOP;
        if ($data->update() !== false){
            return  ['result' => 'success', 'errmsg' => ''];
        }else{
            return  ['result' => 'fail', 'errmsg' => ''];
        }
    }

    /**
     * 删除课程组
     * @param $group_id
     * @param $companyId
     * @return array
     */
    public function deletedCourseGroup($group_id) {
        if (empty($group_id)){
            return  ['result' => 'fail'];
        }
        $find = BoeMixtureCourseGroup::findOne(['kid' => $group_id]);
        if (empty($find)){
            return  ['result' => 'fail'];
        }
        $find->delete();
        BoeMixtureCourseGroupCourse::deleteAll("course_group_id=:group_id", [':group_id'=>$group_id]);
        BoeMixtureCourseGroupTeacher::deleteAll("course_group_id=:group_id", [':group_id'=>$group_id]);
        return ['result' => 'success'];
    }
}
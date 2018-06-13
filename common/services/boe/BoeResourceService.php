<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\models\boe\BoeResource;
use common\models\boe\BoeResourceCategory;
use common\models\boe\BoeResourceFileModel; //文件信息模型
use common\models\boe\BoeResourceShareUser; //用户共享信息模型
use common\models\boe\BoeResourceReport; //举报信息模型
use common\models\social\SoAudience;
use common\models\social\SoAudienceMember;
use common\base\BoeResourceBase;
use common\base\BoeBase;
use Yii;

/**
 * Description of ResourceService
 *
 * @author xinpeng
 */
class BoeResourceService {

    private static $loadedObject = array();
	
    public static function getAudienceMembers($audience_id = array()) {
	   $sult	= array();
	   if(is_array($audience_id)&&$audience_id)
	   {
		   $where 		= array('and');
           $where[] 	= array('is_deleted' => '0');
           $where[] 	= array('status' => '1');
		   if(count($audience_id) == 1)
		   {
			   $where[] 	= array('audience_id' => $audience_id[0]);
		   }else{
			   $where[] 	= array('in', 'audience_id', $audience_id);
		   }
		   $sult			= SoAudienceMember::find(false)->select('user_id')->where($where)->indexBy('user_id')->asArray()->all();
		   $sult			= array_keys($sult);
		   return $sult;   
	   }
	   return $sult;    
    }
	
    public static function getResourceList($condition = array()) {
        $params = array(
            'condition' => array(),
            'orderBy' => BoeBase::array_key_is_nulls($condition, array('orderBy', 'order_by', 'order by'), 'created_at desc'),
            'indexby' => 'kid',
            'returnTotalCount' => 1,
            'cacheTime' => BoeBase::array_key_is_numbers($condition, array('cache_time', 'cacheTime'), 0),
            'limit' => BoeBase::array_key_is_numbers($condition, array('limit', 'limitNum', 'limit_num'), 10),
        );

        $cate_id = BoeBase::array_key_is_nulls($condition, array('category_id', 'categoryId', 'categoryID', 'cateID', 'cate_id', 'cateId'), '');
        $keyword = BoeBase::array_key_is_nulls($condition, array('keyword', 'search_keyword', 'searchKeyword'), '');
        $exclude_id = BoeBase::array_key_is_nulls($condition, array('exclude_id', 'exclude_kid', 'excludeKid', 'excludeId', 'excludeID'), '');

        $getAtString = BoeBase::array_key_is_numbers($condition, array('get_at_string', 'getAtString'));
        $getUserName = BoeBase::array_key_is_numbers($condition, array('get_user_name', 'getUserName'));
        $returnList = BoeBase::array_key_is_numbers($condition, array('return_list', 'returnList'));

        $file_class = BoeBase::array_key_is_numbers($condition, array('file_class', 'fileClass', 'file_type', 'fileType'), -1);
        if ($exclude_id) {//排除的ID
            $params['condition'][] = array('not in', 'kid', $exclude_id);
        }
       //  BoeBase::debug(__METHOD__);
       //  BoeBase::debug($condition);
        if ($cate_id) {//分类搜索
            if (empty(self::$loadedObject['resource_category'])) {
                self::$loadedObject['resource_category'] = new BoeResourceCategory();
            }
            $tmp_arr = self::$loadedObject['resource_category']->getSubId($cate_id, 1); //找出分类对应子子孙孙的ID
           
//            BoeBase::debug($tmp_arr,1);
            if ($tmp_arr) {
                $params['condition'][] = array('in', 'category_id', $tmp_arr);
            }
            $tmp_arr = NULL;
        }
        if ($file_class !== -1) {//按类型搜索
            $params['condition'][] = array('=', 'file_class', $file_class);
        }
        if ($keyword) {//关键词搜索时
            if (empty(self::$loadedObject['resourceModel'])) {
                self::$loadedObject['resourceModel'] = new BoeResource();
            }
            $tmp_arr = self::$loadedObject['resourceModel']->parseKeywordCondition($keyword);
            if ($tmp_arr) {
                $params['condition'][] = $tmp_arr;
            }
            $tmp_arr = NULL;
        }
        if (empty(self::$loadedObject['resourceModel'])) {
            self::$loadedObject['resourceModel'] = new BoeResource();
        }
        $data = self::$loadedObject['resourceModel']->getList($params);
        //BoeBase::debug(__METHOD__.var_export($params,true)."\n Data:".var_export($data,true));
        if ($data['totalCount']) {
            $data['list'] = BoeResourceService::parseResourceList($data['list'], $getAtString, $getUserName);
        }
        return ($returnList) ? $data['list'] : $data;
    }

    /**
     * 整理获取到的视频列表
     * @param type $data
     */
    static public function parseResourceList($data, $getAtString = 0, $getUserName = 0) {
        $sult = array();
        if ($data && is_array($data)) {
            $resource_id = array();
            $user_id = array();
            foreach ($data as $a_info) {
                $resource_id[] = $a_info['kid'];
                $user_id[$a_info['user_id']] = $a_info['user_id'];
                $sult[$a_info['kid']] = $a_info;
            }
            $data = NULL;
            if ($getAtString) {
                $user_at_string_arr = self::getMoreResourceShareUserAtString($resource_id);
                //BoeBase::debug(__METHOD__);
//                BoeBase::debug('$user_at_string_arr;');
//                BoeBase::debug($user_at_string_arr,1);
            }
            if ($getUserName) {
                $user_name_arr = self::getMoreUserName($user_id);
//                BoeBase::debug('$user_name_arr;');
//                BoeBase::debug($user_name_arr);
            }

            if ($getAtString || $getUserName) {

                foreach ($sult as $key => $a_info) {
                    if ($getAtString) {
                        $sult[$key]['share_at_string'] = BoeBase::array_key_is_nulls($user_at_string_arr, $key);
                    }
                    if ($getUserName) {
                        $sult[$key]['user_name'] = BoeBase::array_key_is_nulls($user_name_arr, $a_info['user_id']);
                    }
					$get_url_p = array(
						Yii::$app->controller->id . '/get-ks3-url',
						'redirect' => 1,
						'source_mode' => 1,
					);
					$get_url_p['kid'] 			= $a_info['file_id'];
					$sult[$key]['down_url'] 	= Yii::$app->urlManager->createUrl($get_url_p);	
                }
            }
        }
//        BoeBase::debug($sult, 1);
        return $sult;
    }

    /**
     * 读取多个视频对应的共享用户信息的@字符串
     * @param type $resource_id
     * @return type
   */
    static function getMoreResourceShareUserAtString($resource_id) {
        $get_share_user_info_where = array(
            'and',
            ['in', 'resource_id', $resource_id],
            ['is_deleted' => 0]
        );
        $share_info = BoeResourceShareUser::find(false)->select('user_id,resource_id')->where($get_share_user_info_where)->asArray()->all();
        $sult = array();
        if ($share_info) {//对应的视频有相关的分享记录时S
            $user_id = array();
            $doc_share = array();
            foreach ($share_info as $a_info) {//找出用户ID
                $user_id[$a_info['user_id']] = $a_info['user_id'];
            }
            $where = array('and');
            $where[] = array('is_deleted' => 0);
            $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
            $where[] = array('in', 'kid', array_keys($user_id));
            $user_model = FwUser::find(false)->select('real_name,nick_name,user_name,kid,user_no,email');
            $user_info = $user_model->where($where)->indexby('kid')->asArray()->all();
          //  BoeBase::debug(__METHOD__);
            
            if ($user_info && is_array($user_info)) {//有结果的时候S
                foreach ($share_info as $key => $a_info) {//找出用户名称S
                    $tmp_name = '';
                    if (isset($user_info[$a_info['user_id']])) {
//                        BoeBase::debug($a_info['user_id']);
//                        BoeBase::debug($user_info);
                        $tmp_user_info = BoeBase::parseUserListName($user_info[$a_info['user_id']]);
                        $tmp_name = $tmp_user_info['name_text'];
                    }
                    if (!isset($doc_share[$a_info['resource_id']])) {
                        $doc_share[$a_info['resource_id']] = array();
                    }
                    $doc_share[$a_info['resource_id']][$a_info['user_id']] = $tmp_name;
                }//找出用户名称E
                $user_info = $user_model = NULL;
                foreach ($doc_share as $key => $a_info) {//拼接结果S
                    $sult[$key] = implode(' ', $a_info);
                }//拼接结果E
            }//有结果的时候E
        }//对应的视频有相关的分享记录时E  
        return $sult;
    }

    /**
     * 读取多个视频对应的共享用户信息 
     * @param type $resource_id
     * @return type
     */
    static function getMoreUserName($user_id = NULL) {
        if (!$user_id) {
            return array();
        }
        $where = array('and');
        $where[] = array('is_deleted' => 0);
        $where[] = array('<>', 'status', FwUser::STATUS_FLAG_STOP);
        $where[] = array(is_array($user_id) ? 'in' : '=', 'kid', $user_id);
        $user_model = FwUser::find(false)->select('real_name,nick_name,user_name,kid');
        $user_info = $user_model->where($where)->indexby('kid')->asArray()->all();
        $sult = array();
        if ($user_info && is_array($user_info)) {//有结果的时候S
            foreach ($user_info as $key => $a_info) {//找出用户名称S
                $tmp_name = trim($a_info['nick_name']);
                if (!$tmp_name) {
                    $tmp_name = trim($a_info['real_name']);
                }
                if (!$tmp_name) {
                    $tmp_name = trim($a_info['user_name']);
                }
                $sult[$key] = $tmp_name;
            }  //找出用户名称E
        }//有结果的时候E  
        return $sult;
    }

    /**
     *  根据传递的$resource_info视频信息，$share_user_info权限分享信息，判断对应的$user_id用户是否有权限查看
     * @param type $resource_info 视频信息的ID或是数组
     * @param type $share_user_info 权限分享信息的多维数组,如果为NULL，会从数据库中读取
     * @param type $user_id 用户ID
     * @return boolean 
     */
    static function checkUserViewResourceAuth($resource_info = array(), $share_user_info = NULL, $user_id = '') {
        if (!is_array($resource_info)) {
            if (empty(self::$loadedObject['resourceModel'])) {
                self::$loadedObject['resourceModel'] = new BoeResource();
            }
            $resource_info = self::$loadedObject['resourceModel']->getInfo($resource_info);
            if (!$resource_info) {
                return false;
            }
        }

        if ($resource_info['user_id'] == $user_id) {
            return true;
        }
        $public_user_domain = self::getUserDomainInfo($resource_info['user_id']); //发布者所在域
        $view_user_domain = self::getUserDomainInfo($user_id); //查看者所在域 
        if (array_intersect_assoc($public_user_domain, $view_user_domain)) {//如果两者域是有交集的,此时能看与否取决于$share_user_info
            if ($resource_info['is_private'] == 0) {//公开的权限
                return true;
            }
            //对于非公开的视频的校验开始S
            if ($share_user_info === NULL) {//如果未指定共享配置信息时，从数据库读取视频的共享配置S
                if (empty(self::$loadedObject['resource_share_user'])) {
                    self::$loadedObject['resource_share_user'] = new BoeResourceShareUser();
                }
                $share_user_info = self::$loadedObject['resource_share_user']->getResourceShareUserInfo($resource_info['kid']);
            }  //如果未指定共享配置信息时，从数据库读取视频的共享配置E
            if (!$share_user_info || !is_array($share_user_info)) {
                return false;
            }

            if (isset($share_user_info[$user_id])) {
                return true;
            }
            foreach ($share_user_info as $a_info) {
                if ($a_info['user_id'] == $user_id) {
                    return true;
                    break;
                }
            }
            //对于非公开的视频的校验开始E
        }
        return false;
    }

    /**
     * 添加视频的下载数量
     * @param type $resource_id
     * @param type $user_id
     * @return boolean
     */
    static function addResourceDownNum($resource_id, $user_id) {
        if ($resource_id && $user_id) {
            if (self::checkNeedUpadteDownNum($resource_id, $user_id)) {//检测用户下载文件时是否可以添加视频的下载记录
                if (empty(self::$loadedObject['resourceModel'])) {
                    self::$loadedObject['resourceModel'] = new BoeResource();
                }
                self::$loadedObject['resourceModel']->updateDownNum($resource_id);
                self::updateUserResourceDownLog($resource_id, $user_id);
            }
        }
        return false;
    }

    /**
     * 检测用户下载文件时是否可以添加视频的下载记录
     * @param type $resource_id
     * @param type $user_id
     */
    private static function checkNeedUpadteDownNum($resource_id, $user_id) {
        $cache_name = "doc_down_{$resource_id}_{$user_id}";
        $sult = Yii::$app->cache->get($cache_name);
        if ($sult) {
            return false;
        }
        return true;
    }

    /**
     * 更新某一个对某个视频的下载记录
     * @param type $resource_id
     * @param type $user_id
     */
    private static function updateUserResourceDownLog($resource_id, $user_id) {
        $cache_name = "doc_down_{$resource_id}_{$user_id}";
        $cache_time = BoeResourceBase::getBoeDocFileConfig('DocDownUpdateCountTime');
        Yii::$app->cache->set($cache_name, var_export($_SERVER, true) . "\nDate:" . date('Y-m-d H:i:s'), $cache_time);
    }

    /**
     * 添加视频的下载数量
     * @param type $resource_id
     * @param type $user_id
     * @return boolean
     */
    static function addResourceVisitNum($resource_id, $user_id) {
        if ($resource_id && $user_id) {
            if (self::checkNeedUpadteVisitNum($resource_id, $user_id)) {
                if (empty(self::$loadedObject['resourceModel'])) {
                    self::$loadedObject['resourceModel'] = new BoeResource();
                }
                self::$loadedObject['resourceModel']->updateVisitNum($resource_id);
                self::updateUserResourceVisitLog($resource_id, $user_id);
            }
        }
        return false;
    }

    /**
     * 检测用户查看文件时是否可以添加视频的查看记录
     * @param type $resource_id
     * @param type $user_id
     */
    private static function checkNeedUpadteVisitNum($resource_id, $user_id) {
        $cache_name = "doc_visit_{$resource_id}_{$user_id}";
        $sult = Yii::$app->cache->get($cache_name);
        if ($sult) {
            return false;
        }
        return true;
    }

    /**
     * 更新某一个对某个视频的查看记录
     * @param type $resource_id
     * @param type $user_id
     */
    private static function updateUserResourceVisitLog($resource_id, $user_id) {
        $cache_name = "resource_visit_{$resource_id}_{$user_id}";
        $cache_time = BoeResourceBase::getBoeDocFileConfig('DocVisitUpdateCountTime');
        Yii::$app->cache->set($cache_name, var_export($_SERVER, true) . "\nDate:" . date('Y-m-d H:i:s'), $cache_time);
    }

    /**
     * 添加视频的下载数量
     * @param type $resource_id
     * @param type $user_id
     * @return boolean
     */
    static function addResourceReportNum($resource_id, $user_id) {
        if ($resource_id && $user_id) {
            if (self::checkNeedUpadteReportNum($resource_id, $user_id)) {
                if (empty(self::$loadedObject['resourceModel'])) {
                    self::$loadedObject['resourceModel'] = new BoeResource();
                }
                self::$loadedObject['resourceModel']->updateReportNum($resource_id);
                self::updateUserDocReportLog($resource_id, $user_id);
            }
        }
        return false;
    }

    /**
     * 检测用户下载文件时是否可以添加视频的举报记录
     * @param type $resource_id
     * @param type $user_id
     */
    static function checkNeedUpadteReportNum($resource_id, $user_id) {
        $cache_name = "doc_report_{$resource_id}_{$user_id}";
        $sult = Yii::$app->cache->get($cache_name);
        if ($sult) {
            return false;
        }
        return true;
    }

    /**
     * 更新某一个对某个视频的举报记录
     * @param type $resource_id
     * @param type $user_id
     */
    private static function updateUserResourceReportLog($resource_id, $user_id) {
        $cache_name = "resource_report_{$resource_id}_{$user_id}";
        $cache_time = BoeResourceBase::getBoeDocFileConfig('DocReportUpdateCountTime');
        Yii::$app->cache->set($cache_name, var_export($_SERVER, true) . "\nDate:" . date('Y-m-d H:i:s'), $cache_time);
    }

    /**
     * 读取出用户所能查询的域信息
     * @param type $user_id
     * @return array
     */
    static function getUserDomainInfo($user_id = '') {
        $rightInterface_obj = new RightInterface();
//读取出用户所有的域信息
        $domain_obj = $rightInterface_obj->getSearchDomainListByUserId($user_id);
        $domain_info = array();
        foreach ($domain_obj as $a_info) {
            $domain_info[$a_info->kid] = $a_info->kid;
        }
        return $domain_info;
    }

    /**
     * 删除视频
     * @param type $resource_id 视频的ID，可以是一个或是多个，多个的时候可用数组或是带分号的字符串表达
     * @param type $user_id 是否在删除前判断视频的上传者是否为指定的$user_id,如果传递了相关的参数，那么就表示只有视频的上传者和user_id的值一致时都会被删除
     * @param type $physicalDelete 是否进行物理删除,如果是，那么相应的文件，金山云上面的文件也都会删除
     * @return int
     */
    static function deleteResource($resource_id, $user_id = 0, $physicalDelete = 0) {
        if (!$resource_id) {
            return 0;
        }
        $resource_obj = null;
        $resource_id_arr = array();
        $deleteValue = 1;
        $tmp_arr = array(';', '、', '；', ',');
        $tmp_resource_id_arr = is_array($resource_id) ? $resource_id : explode(',', str_replace($tmp_arr, ',', $resource_id));

        if ($user_id) {//指定了用户ID时,需要校验删除者是不是对应的用户
            if (empty(self::$loadedObject['resourceModel'])) {
                self::$loadedObject['resourceModel'] = new BoeResource();
            }

            foreach ($tmp_resource_id_arr as $a_id) {
                $tmp_resource_info = self::$loadedObject['resourceModel']->getInfo($a_id);
                if ($tmp_resource_info['user_id'] == $user_id) {
                    $resource_id_arr[] = $a_id;
                }
            }
            $tmp_resource_info = NULL;
        } else {
            $resource_id_arr = $tmp_resource_id_arr;
        }

        if ($resource_id_arr) {
            if (empty(self::$loadedObject['resourceModel'])) {
                self::$loadedObject['resourceModel'] = new BoeResource();
            }
            // BoeBase::debug(__METHOD__.var_export($resource_id_arr,true),1);
            self::deleteResourceFile($resource_id_arr, $user_id, $physicalDelete); //删除视频对应的文件，包括金山云
            $deleteValue = self::$loadedObject['resourceModel']->deleteInfo($resource_id_arr, $user_id, $physicalDelete);
            if (empty(self::$loadedObject['resource_share_user'])) {
                self::$loadedObject['resource_share_user'] = new BoeResourceShareUser();
            }
            foreach ($resource_id_arr as $a_id) {
                self::$loadedObject['resource_share_user']->getResourceShareUserInfo($a_id, false, 2); //删除分享权限信息的缓存
            }
        }
        return $deleteValue;
    }

    /**
     * 删除resource_id对应的文件，包括金山云、本地文件和数据库的信息
     * @param type $resource_id 视频的ID，可以是一个或是多个，多个的时候可用数组
     * @param type $user_id 是否在删除前判断视频的上传者是否为指定的$user_id,如果传递了相关的参数，那么就表示只有视频的上传者和user_id的值一致时都会被删除
     * @param type $physicalDelete 是否进行物理删除
     * @return boolean
     */
    static function deleteResourceFile($resource_id, $user_id = 0, $physicalDelete = 0) {
        $get_file_id_where = array('and');
        $get_file_id_where[] = array(is_array($resource_id)?'in':'=', 'kid', $resource_id);
        if ($user_id) {
            $get_file_id_where[] = array('=', 'user_id', $user_id);
        }
        $model = BoeResource::find()->select('kid,file_id')->andFilterWhere($get_file_id_where);
        $resource_info = $model->asArray()->all();
        //   BoeBase::debug($model->createCommand()->getRawSql(),1);
        if ($resource_info && is_array($resource_info)) {
            $file_id = array();
            foreach ($resource_info as $a_info) {
                $file_id[] = $a_info['file_id'];
            }
            self::deleteFileFromId($file_id, $user_id, $physicalDelete);
        }
        return true;
    }

    /**
     * 根据$file_id_info删除对应的文件，包括金山云、本地文件和数据库的信息
     * @param type $file_id_info
     * @param type $user_id 是否在删除前判断视频的上传者是否为指定的$user_id,如果传递了相关的参数，那么就表示只有视频的上传者和user_id的值一致时都会被删除
     * @param type $physicalDelete 是否进行物理删除
     * @return boolean
     */
    static function deleteFileFromId($file_id_info, $user_id = 0, $physicalDelete = 0) {
        $tmp_arr = array(';', '、', '；', ',');
        $id_array = is_array($file_id_info) ? $file_id_info : explode(',', str_replace($tmp_arr, ',', $file_id_info));
        $file_id = array();
        $local_file_info = array();
        $ks3_file_info = array();
        if (empty(self::$loadedObject['resource_file'])) {
            self::$loadedObject['resource_file'] = new BoeResourceFileModel();
        }

        // BoeBase::debug(__METHOD__.var_export($file_id_info,true),1);
        foreach ($id_array as $a_file_id) {//拼凑出要删除的文件和云盘上要删除的KEY开始
            $tmp_file_info = self::$loadedObject['resource_file']->getInfo($a_file_id);
            $tmp_check = ($tmp_file_info) ? true : false;
            if ($tmp_check && $user_id) {
                $tmp_check = $tmp_file_info['user_id'] == $user_id;
            }
            if ($tmp_check) {//文件检测通过S
                $file_id[] = $a_file_id;
                if ($physicalDelete) {//是否进行物理删除S
                    $file_ext = BoeBase::array_key_is_nulls($tmp_file_info, 'file_ext');
                    $file_path = BoeBase::array_key_is_nulls($tmp_file_info, 'file_full_path');
                    $pdf_status = BoeBase::array_key_is_nulls($tmp_file_info, 'pdf_status');
                    $file_key = BoeBase::array_key_is_nulls($tmp_file_info, 'file_relative_path'); //文件的相对路径
                    $cloud_status = BoeBase::array_key_is_nulls($tmp_file_info, 'cloud_status');
                    $local_file_info[] = $file_path; //文件存放在本地的绝对路径
                    $bucket = BoeResourceBase::getFileClassConfig($file_ext, 'ks3_bucket'); //存放在金山云的空间名称
                    $need_pdf = BoeResourceBase::checkFileCanConvertPdf($file_ext);
                    $need_mp4 = BoeResourceBase::checkFileCanConvertMp4($file_ext);
                    $need_mp3 = BoeResourceBase::checkFileCanConvertMp3($file_ext);
                    if ($cloud_status == 3) {//已经上传到云了S
                        if (!isset($ks3_file_info[$bucket])) {
                            $ks3_file_info[$bucket] = array();
                        }
                        $ks3_file_info[$bucket][] = $file_key;
                        if ($need_pdf) {
                            $ks3_file_info[$bucket][] = str_replace(".{$file_ext}", '.pdf', $file_key);
                        }
                        if ($need_mp4) {
                            $ks3_file_info[$bucket][] = str_replace(".{$file_ext}", '.mp4', $file_key);
                        }
                        if ($need_mp3) {
                            $ks3_file_info[$bucket][] = str_replace(".{$file_ext}", '.mp3', $file_key);
                        }
                    }//已经上传到云了E

                    if ($pdf_status == 3) {//需要删除本地的PDF文件
                        $local_file_info[] = str_replace(".{$file_ext}", '.pdf', $file_path);
                    }
                }//是否进行物理删除E
            }//文件检测通过E
        }//拼凑出要删除的文件和云盘上要删除的KEY 结束

        if ($ks3_file_info) {//先删除金山云的文件
            foreach ($ks3_file_info as $key => $a_info) {
                $ks3_file_info[$key]['sult'] = BoeResourceBase::deleteKs3File($key, $a_info);
            }
//                BoeBase::debug(__METHOD__);
//                BoeBase::debug($ks3_file_info,1);
        }
        if ($local_file_info) {//删除本机的文件
            foreach ($local_file_info as $key => $a_info) {
                @unlink($a_info);
            }
        }
//         BoeBase::debug(__METHOD__.var_export($file_id,true),1);
        self::$loadedObject['resource_file']->deleteInfo($file_id, $user_id, $physicalDelete); //先删除对应的文件在数据库的信息和缓存
    }

    /**
     * 获取文件的访问的URL地址
     * @param type $key
     * @param type $source_mode 是否获取源文件信息，仅针对要转换成Pdf,mp4,mp3的文件有效
     * @return type
     */
    static function getFileKs3Url($key, $source_mode = 0) {
        if (!$key) {
            return -100;
        }
        $file_db_obj = $file_info = $file_url = NUll;
        if (empty(self::$loadedObject['resource_file'])) {
            self::$loadedObject['resource_file'] = new BoeResourceFileModel();
        }
        $file_info = self::$loadedObject['resource_file']->getInfo($key, '*'); //第1参数表示ID，第2个参数表示获取全部字段，第3个参数表示优先从缓存读取，第4个参数表示忽略当前线程的记录
        if (!$file_info) {//文件信息不存在时
            return -99;
        }
        $file_path = BoeBase::array_key_is_nulls($file_info, 'file_full_path');
        $cloud_status = BoeBase::array_key_is_nulls($file_info, 'cloud_status'); //上传到云空间的状态0=未上传,1=正在上传,2=上传失败,3=上传成功 
        switch ($cloud_status) {//Switch Start
            case 0: case 1: case 2: //未上传或正在上传的或上传失败的时候
                return -98;
                break;
            case 3://上传成功 S
                $file_ext = BoeBase::array_key_is_nulls($file_info, 'file_ext');
                $file_key = BoeBase::array_key_is_nulls($file_info, 'file_relative_path');
                $bucket = BoeResourceBase::getFileClassConfig($file_ext, 'ks3_bucket');
                $last_file_type = '';
                $get_adp = false;
                if (!$source_mode) {//不是获取原始文件的状态,根据扩展名获取相应的转码后的文件S
                    if (BoeResourceBase::checkFileCanConvertPdf($file_ext)) {
                        $last_file_type = 'pdf';
                    } elseif (BoeResourceBase::checkFileCanConvertMp4($file_ext)) {
                        $last_file_type = 'mp4';
                        $get_adp = true; //需要获取转码状态
                    } elseif (BoeResourceBase::checkFileCanConvertMp3($file_ext)) {
                        $last_file_type = 'mp3';
                        $get_adp = true; //需要获取转码状态
                    } else {
                        
                    }
                }//不是获取原始文件的状态,根据扩展名获取相应的转码后的文件E
                else {
                    $options = array('response-content-type' => 'application/octet-stream');
                }
				
                if ($get_adp) {//对于那些个需要获取转换后的文件代码S
                    $cloud_adp = BoeBase::array_key_is_nulls($file_info, 'cloud_adp');
                    if ($cloud_adp) {
                        $cloud_adp = @json_decode($cloud_adp, true);
                        //  $client->getAdp(array("TaskID" => $taskid));
                    }
                    if (!$cloud_adp || !is_array($cloud_adp)) {
                        return -97;
                    }
                    $task_id = BoeBase::array_key_is_nulls($cloud_adp, 'task_id');
                    $convert_status = BoeBase::array_key_is_numbers($cloud_adp, 'convert_status');
                    if ($convert_status == 1) {//转码完成了，并且回调已经响应了S
                        $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                    } else {
                        $process_status = 0;
                        if ($task_id) {//根据任务ID，获取异步任务的状态
                            $process_status = BoeResourceBase::getKs3AdpTask($task_id, 'processstatus'); //获取任务的处理状态
                        }
                        if ($process_status != 3) {//转码未完成
                            return -96;
                        } else { //已经转码完成了，更新相关的状态值
                            $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                            self::$loadedObject['resource_file']->updateCloudAdpStatus($key);
                        }
                    }
                }//对于那些个需要获取转换后的文件代码E
                else {
                    if ($last_file_type) {
                        $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                    }
                }
				$file_key = $file_ext =='mp4'?str_replace(".mp4", "_new.mp4", $file_key):$file_key;
                $file_url = BoeResourceBase::getKs3Url($bucket, $file_key, $options);
                if (!$file_url) {
                    return -95;
                }
                return $file_url ? $file_url : -94;
                break; //上传成功 E
        }//Switch End
    }

    static function redirectFile($file_info = array(), $file_url = NULL, $is_down_mode = 0) {
        if (!is_array($file_info)) {
            if (empty(self::$loadedObject['resource_file'])) {
                self::$loadedObject['resource_file'] = new BoeResourceFileModel();
            }
            $file_info = self::$loadedObject['resource_file']->getInfo($file_info, '*'); //第1参数表示ID，第2个参数表示获取全部字段 
        }
        if (!$file_url) {
            $file_url = self::getFileKs3Url($file_info['kid'], $is_down_mode);
        }
        if (!is_numeric($file_url)) {
            $broswer = BoeBase::get_broswer();
            header("Pragma: no-cache");
            header("Expires: 0");
            header("Cache-Component: must-revalidate, post-check=0, pre-check=0");
            if ($is_down_mode) {
                header('Content-type: application/octet-stream;charset=utf-8');
            } else {
                header('Content-type: ' . $file_info['file_type'] . ';charset=utf-8');
            }
            header("Accept-Ranges: bytes");
            header("Accept-Length:" . $file_info['file_size']);

            switch ($broswer) {
                case 'IE6':
                    $file_name = iconv('utf-8', 'gbk', $file_info['old_file_name']);
                    header('Content-Disposition: attachment; filename="' . $file_name . '"');
                    break;
                case 'Firefox':
                    header("Content-Disposition: attachment; filename*=\"utf8''{$file_info['old_file_name']}\"");
                    break;
                default:
                    header('Content-Disposition: attachment; filename="' . $file_info['old_file_name'] . '"');
                    break;
            }
            header("Location:{$file_url}");
            exit();
        } else {
            $error = self::getFileKs3UrlErrorText($file_url);
            exit("<script>alert(\"{$error}\");</script>");
        }
    }

    /**
     * 获取文件在预览时的类型
     * @param type $file_ext
     * @return String
     */
    static function getFilePreviewType($file_info = '') {
        if ($file_info['file_ext'] == 'pdf' || BoeResourceBase::checkFileCanConvertPdf($file_info['file_ext'])) {
            return 'pdf';
        } elseif ($file_info['file_ext'] == 'mp4' || BoeResourceBase::checkFileCanConvertMp4($file_info['file_ext'])) {
            return 'vedio';
        } elseif ($file_info['file_ext'] == 'mp3' || BoeResourceBase::checkFileCanConvertMp3($file_info['file_ext'])) {
            return 'audio';
        } elseif ($file_info['file_ext'] == 'swf') {
            return 'flash';
        } elseif ($file_info['file_ext'] != 'swf' && !empty($file_info['pic_width']) && !empty($file_info['pic_height'])) {
            return 'image';
        } else {
            return 'other';
        }
    }

    /**
     * 根据getFileKs3Url方法返回的错误代码返回相应的文字
     * @param type $error_code
     * @return type
     */
    static function getFileKs3UrlErrorText($error_code) {
        switch ($error_code) {
            case -100:
                return Yii::t('boe', 'no_assgin_info');
                break;
            case -99:
                return Yii::t('boe', 'file_info_loss');
                break;
            case -98:
                return Yii::t('boe', 'file_no_complete_uploading');
                break;
            case -97:
                return Yii::t('boe', 'file_adp_error');
                break;
            case -96:
                return Yii::t('boe', 'file_converting');
                break;
            case -95:
                return Yii::t('boe', 'file_ks3_return_error');
                break;
            default:
                return 'Unknow Error:' . $error_code;
                break;
        }
    }

    /**
     * 检测是否处于后台管理登录状态
     * @return boolean
     */
    static function checkBackendAdminLogin() {
        return false;
    }

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isNoCacheMode() {
        return Yii::$app->request->get('no_cache') == 1 ? true : false;
    }

}

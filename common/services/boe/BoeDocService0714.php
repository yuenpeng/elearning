<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\models\boe\BoeDoc;
use common\models\boe\BoeDocCategory;
use common\models\boe\BoeDocFileModel; //文件信息模型
use common\models\boe\BoeDocShareUser; //用户共享信息模型
use common\models\boe\BoeDocReport; //举报信息模型
use common\base\BoeDocFile;
use common\base\BoeBase;
use Yii;

/**
 * Description of DocService
 *
 * @author Administrator
 */
class BoeDocService {

    private static $loadedObject = array();

    public static function getDocList($condition = array()) {
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
            if (empty(self::$loadedObject['doc_category'])) {
                self::$loadedObject['doc_category'] = new BoeDocCategory();
            }
            $tmp_arr = self::$loadedObject['doc_category']->getSubId($cate_id, 1); //找出分类对应子子孙孙的ID
           
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
            if (empty(self::$loadedObject['docModel'])) {
                self::$loadedObject['docModel'] = new BoeDoc();
            }
            $tmp_arr = self::$loadedObject['docModel']->parseKeywordCondition($keyword);
            if ($tmp_arr) {
                $params['condition'][] = $tmp_arr;
            }
            $tmp_arr = NULL;
        }
        if (empty(self::$loadedObject['docModel'])) {
            self::$loadedObject['docModel'] = new BoeDoc();
        }
        $data = self::$loadedObject['docModel']->getList($params);
        //BoeBase::debug(__METHOD__.var_export($params,true)."\n Data:".var_export($data,true));
        if ($data['totalCount']) {
            $data['list'] = BoeDocService::parseDocList($data['list'], $getAtString, $getUserName);
        }
        return ($returnList) ? $data['list'] : $data;
    }

    /**
     * 整理获取到的文档列表
     * @param type $data
     */
    static public function parseDocList($data, $getAtString = 0, $getUserName = 0) {
        $sult = array();
        if ($data && is_array($data)) {
            $doc_id = array();
            $user_id = array();
            foreach ($data as $a_info) {
                $doc_id[] = $a_info['kid'];
                $user_id[$a_info['user_id']] = $a_info['user_id'];
                $sult[$a_info['kid']] = $a_info;
            }
            $data = NULL;
            if ($getAtString) {
                $user_at_string_arr = self::getMoreDocShareUserAtString($doc_id);
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
                }
            }
        }
//        BoeBase::debug($sult, 1);
        return $sult;
    }

    /**
     * 读取多个文档对应的共享用户信息的@字符串
     * @param type $doc_id
     * @return type
   */
    static function getMoreDocShareUserAtString($doc_id) {
        $get_share_user_info_where = array(
            'and',
            ['in', 'doc_id', $doc_id],
            ['is_deleted' => 0]
        );
        $share_info = BoeDocShareUser::find(false)->select('user_id,doc_id')->where($get_share_user_info_where)->asArray()->all();
        $sult = array();
        if ($share_info) {//对应的文档有相关的分享记录时S
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
                    if (!isset($doc_share[$a_info['doc_id']])) {
                        $doc_share[$a_info['doc_id']] = array();
                    }
                    $doc_share[$a_info['doc_id']][$a_info['user_id']] = $tmp_name;
                }//找出用户名称E
                $user_info = $user_model = NULL;
                foreach ($doc_share as $key => $a_info) {//拼接结果S
                    $sult[$key] = implode(' ', $a_info);
                }//拼接结果E
            }//有结果的时候E
        }//对应的文档有相关的分享记录时E  
        return $sult;
    }

    /**
     * 读取多个文档对应的共享用户信息 
     * @param type $doc_id
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
     *  根据传递的$doc_info文档信息，$share_user_info权限分享信息，判断对应的$user_id用户是否有权限查看
     * @param type $doc_info 文档信息的ID或是数组
     * @param type $share_user_info 权限分享信息的多维数组,如果为NULL，会从数据库中读取
     * @param type $user_id 用户ID
     * @return boolean 
     */
    static function checkUserViewDocAuth($doc_info = array(), $share_user_info = NULL, $user_id = '') {
        if (!is_array($doc_info)) {
            if (empty(self::$loadedObject['docModel'])) {
                self::$loadedObject['docModel'] = new BoeDoc();
            }
            $doc_info = self::$loadedObject['docModel']->getInfo($doc_info);
            if (!$doc_info) {
                return false;
            }
        }

        if ($doc_info['user_id'] == $user_id) {
            return true;
        }
        $public_user_domain = self::getUserDomainInfo($doc_info['user_id']); //发布者所在域
        $view_user_domain = self::getUserDomainInfo($user_id); //查看者所在域 
        if (array_intersect_assoc($public_user_domain, $view_user_domain)) {//如果两者域是有交集的,此时能看与否取决于$share_user_info
            if ($doc_info['is_private'] == 0) {//公开的权限
                return true;
            }
            //对于非公开的文档的校验开始S
            if ($share_user_info === NULL) {//如果未指定共享配置信息时，从数据库读取文档的共享配置S
                if (empty(self::$loadedObject['doc_share_user'])) {
                    self::$loadedObject['doc_share_user'] = new BoeDocShareUser();
                }
                $share_user_info = self::$loadedObject['doc_share_user']->getDocShareUserInfo($doc_info['kid']);
            }  //如果未指定共享配置信息时，从数据库读取文档的共享配置E
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
            //对于非公开的文档的校验开始E
        }
        return false;
    }

    /**
     * 添加文档的下载数量
     * @param type $doc_id
     * @param type $user_id
     * @return boolean
     */
    static function addDocDownNum($doc_id, $user_id) {
        if ($doc_id && $user_id) {
            if (self::checkNeedUpadteDownNum($doc_id, $user_id)) {//检测用户下载文件时是否可以添加文档的下载记录
                if (empty(self::$loadedObject['docModel'])) {
                    self::$loadedObject['docModel'] = new BoeDoc();
                }
                self::$loadedObject['docModel']->updateDownNum($doc_id);
                self::updateUserDocDownLog($doc_id, $user_id);
            }
        }
        return false;
    }

    /**
     * 检测用户下载文件时是否可以添加文档的下载记录
     * @param type $doc_id
     * @param type $user_id
     */
    private static function checkNeedUpadteDownNum($doc_id, $user_id) {
        $cache_name = "doc_down_{$doc_id}_{$user_id}";
        $sult = Yii::$app->cache->get($cache_name);
        if ($sult) {
            return false;
        }
        return true;
    }

    /**
     * 更新某一个对某个文档的下载记录
     * @param type $doc_id
     * @param type $user_id
     */
    private static function updateUserDocDownLog($doc_id, $user_id) {
        $cache_name = "doc_down_{$doc_id}_{$user_id}";
        $cache_time = BoeDocFile::getBoeDocFileConfig('DocDownUpdateCountTime');
        Yii::$app->cache->set($cache_name, var_export($_SERVER, true) . "\nDate:" . date('Y-m-d H:i:s'), $cache_time);
    }

    /**
     * 添加文档的下载数量
     * @param type $doc_id
     * @param type $user_id
     * @return boolean
     */
    static function addDocVisitNum($doc_id, $user_id) {
        if ($doc_id && $user_id) {
            if (self::checkNeedUpadteVisitNum($doc_id, $user_id)) {
                if (empty(self::$loadedObject['docModel'])) {
                    self::$loadedObject['docModel'] = new BoeDoc();
                }
                self::$loadedObject['docModel']->updateVisitNum($doc_id);
                self::updateUserDocVisitLog($doc_id, $user_id);
            }
        }
        return false;
    }

    /**
     * 检测用户查看文件时是否可以添加文档的查看记录
     * @param type $doc_id
     * @param type $user_id
     */
    private static function checkNeedUpadteVisitNum($doc_id, $user_id) {
        $cache_name = "doc_visit_{$doc_id}_{$user_id}";
        $sult = Yii::$app->cache->get($cache_name);
        if ($sult) {
            return false;
        }
        return true;
    }

    /**
     * 更新某一个对某个文档的查看记录
     * @param type $doc_id
     * @param type $user_id
     */
    private static function updateUserDocVisitLog($doc_id, $user_id) {
        $cache_name = "doc_visit_{$doc_id}_{$user_id}";
        $cache_time = BoeDocFile::getBoeDocFileConfig('DocVisitUpdateCountTime');
        Yii::$app->cache->set($cache_name, var_export($_SERVER, true) . "\nDate:" . date('Y-m-d H:i:s'), $cache_time);
    }

    /**
     * 添加文档的下载数量
     * @param type $doc_id
     * @param type $user_id
     * @return boolean
     */
    static function addDocReportNum($doc_id, $user_id) {
        if ($doc_id && $user_id) {
            if (self::checkNeedUpadteReportNum($doc_id, $user_id)) {
                if (empty(self::$loadedObject['docModel'])) {
                    self::$loadedObject['docModel'] = new BoeDoc();
                }
                self::$loadedObject['docModel']->updateReportNum($doc_id);
                self::updateUserDocReportLog($doc_id, $user_id);
            }
        }
        return false;
    }

    /**
     * 检测用户下载文件时是否可以添加文档的举报记录
     * @param type $doc_id
     * @param type $user_id
     */
    static function checkNeedUpadteReportNum($doc_id, $user_id) {
        $cache_name = "doc_report_{$doc_id}_{$user_id}";
        $sult = Yii::$app->cache->get($cache_name);
        if ($sult) {
            return false;
        }
        return true;
    }

    /**
     * 更新某一个对某个文档的举报记录
     * @param type $doc_id
     * @param type $user_id
     */
    private static function updateUserDocReportLog($doc_id, $user_id) {
        $cache_name = "doc_report_{$doc_id}_{$user_id}";
        $cache_time = BoeDocFile::getBoeDocFileConfig('DocReportUpdateCountTime');
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
     * 删除文档
     * @param type $doc_id 文档的ID，可以是一个或是多个，多个的时候可用数组或是带分号的字符串表达
     * @param type $user_id 是否在删除前判断文档的上传者是否为指定的$user_id,如果传递了相关的参数，那么就表示只有文档的上传者和user_id的值一致时都会被删除
     * @param type $physicalDelete 是否进行物理删除,如果是，那么相应的文件，金山云上面的文件也都会删除
     * @return int
     */
    static function deleteDoc($doc_id, $user_id = 0, $physicalDelete = 0) {
        if (!$doc_id) {
            return 0;
        }
        $doc_obj = null;
        $doc_id_arr = array();
        $deleteValue = 1;
        $tmp_arr = array(';', '、', '；', ',');
        $tmp_doc_id_arr = is_array($doc_id) ? $doc_id : explode(',', str_replace($tmp_arr, ',', $doc_id));

        if ($user_id) {//指定了用户ID时,需要校验删除者是不是对应的用户
            if (empty(self::$loadedObject['docModel'])) {
                self::$loadedObject['docModel'] = new BoeDoc();
            }

            foreach ($tmp_doc_id_arr as $a_id) {
                $tmp_doc_info = self::$loadedObject['docModel']->getInfo($a_id);
                if ($tmp_doc_info['user_id'] == $user_id) {
                    $doc_id_arr[] = $a_id;
                }
            }
            $tmp_doc_info = NULL;
        } else {
            $doc_id_arr = $tmp_doc_id_arr;
        }

        if ($doc_id_arr) {
            if (empty(self::$loadedObject['docModel'])) {
                self::$loadedObject['docModel'] = new BoeDoc();
            }
            // BoeBase::debug(__METHOD__.var_export($doc_id_arr,true),1);
            self::deleteDocFile($doc_id_arr, $user_id, $physicalDelete); //删除文档对应的文件，包括金山云
            $deleteValue = self::$loadedObject['docModel']->deleteInfo($doc_id_arr, $user_id, $physicalDelete);
            if (empty(self::$loadedObject['doc_share_user'])) {
                self::$loadedObject['doc_share_user'] = new BoeDocShareUser();
            }
            foreach ($doc_id_arr as $a_id) {
                self::$loadedObject['doc_share_user']->getDocShareUserInfo($a_id, false, 2); //删除分享权限信息的缓存
            }
        }
        return $deleteValue;
    }

    /**
     * 删除doc_id对应的文件，包括金山云、本地文件和数据库的信息
     * @param type $doc_id 文档的ID，可以是一个或是多个，多个的时候可用数组
     * @param type $user_id 是否在删除前判断文档的上传者是否为指定的$user_id,如果传递了相关的参数，那么就表示只有文档的上传者和user_id的值一致时都会被删除
     * @param type $physicalDelete 是否进行物理删除
     * @return boolean
     */
    static function deleteDocFile($doc_id, $user_id = 0, $physicalDelete = 0) {
        $get_file_id_where = array('and');
        $get_file_id_where[] = array(is_array($doc_id)?'in':'=', 'kid', $doc_id);
        if ($user_id) {
            $get_file_id_where[] = array('=', 'user_id', $user_id);
        }
        $model = BoeDoc::find()->select('kid,file_id')->andFilterWhere($get_file_id_where);
        $doc_info = $model->asArray()->all();
        //   BoeBase::debug($model->createCommand()->getRawSql(),1);
        if ($doc_info && is_array($doc_info)) {
            $file_id = array();
            foreach ($doc_info as $a_info) {
                $file_id[] = $a_info['file_id'];
            }
            self::deleteFileFromId($file_id, $user_id, $physicalDelete);
        }
        return true;
    }

    /**
     * 根据$file_id_info删除对应的文件，包括金山云、本地文件和数据库的信息
     * @param type $file_id_info
     * @param type $user_id 是否在删除前判断文档的上传者是否为指定的$user_id,如果传递了相关的参数，那么就表示只有文档的上传者和user_id的值一致时都会被删除
     * @param type $physicalDelete 是否进行物理删除
     * @return boolean
     */
    static function deleteFileFromId($file_id_info, $user_id = 0, $physicalDelete = 0) {
        $tmp_arr = array(';', '、', '；', ',');
        $id_array = is_array($file_id_info) ? $file_id_info : explode(',', str_replace($tmp_arr, ',', $file_id_info));
        $file_id = array();
        $local_file_info = array();
        $ks3_file_info = array();
        if (empty(self::$loadedObject['doc_file'])) {
            self::$loadedObject['doc_file'] = new BoeDocFileModel();
        }

        // BoeBase::debug(__METHOD__.var_export($file_id_info,true),1);
        foreach ($id_array as $a_file_id) {//拼凑出要删除的文件和云盘上要删除的KEY开始
            $tmp_file_info = self::$loadedObject['doc_file']->getInfo($a_file_id);
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
                    $bucket = BoeDocFile::getFileClassConfig($file_ext, 'ks3_bucket'); //存放在金山云的空间名称
                    $need_pdf = BoeDocFile::checkFileCanConvertPdf($file_ext);
                    $need_mp4 = BoeDocFile::checkFileCanConvertMp4($file_ext);
                    $need_mp3 = BoeDocFile::checkFileCanConvertMp3($file_ext);
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
                $ks3_file_info[$key]['sult'] = BoeDocFile::deleteKs3File($key, $a_info);
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
        self::$loadedObject['doc_file']->deleteInfo($file_id, $user_id, $physicalDelete); //先删除对应的文件在数据库的信息和缓存
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
        if (empty(self::$loadedObject['doc_file'])) {
            self::$loadedObject['doc_file'] = new BoeDocFileModel();
        }
        $file_info = self::$loadedObject['doc_file']->getInfo($key, '*'); //第1参数表示ID，第2个参数表示获取全部字段，第3个参数表示优先从缓存读取，第4个参数表示忽略当前线程的记录
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
                $bucket = BoeDocFile::getFileClassConfig($file_ext, 'ks3_bucket');
                $last_file_type = '';
                $get_adp = false;
                if (!$source_mode) {//不是获取原始文件的状态,根据扩展名获取相应的转码后的文件S
                    if (BoeDocFile::checkFileCanConvertPdf($file_ext)) {
                        $last_file_type = 'pdf';
                    } elseif (BoeDocFile::checkFileCanConvertMp4($file_ext)) {
                        $last_file_type = 'mp4';
                        $get_adp = true; //需要获取转码状态
                    } elseif (BoeDocFile::checkFileCanConvertMp3($file_ext)) {
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
                            $process_status = BoeDocFile::getKs3AdpTask($task_id, 'processstatus'); //获取任务的处理状态
                        }
                        if ($process_status != 3) {//转码未完成
                            return -96;
                        } else { //已经转码完成了，更新相关的状态值
                            $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                            self::$loadedObject['doc_file']->updateCloudAdpStatus($key);
                        }
                    }
                }//对于那些个需要获取转换后的文件代码E
                else {
                    if ($last_file_type) {
                        $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                    }
                }
                $file_url = BoeDocFile::getKs3Url($bucket, $file_key, $options);
                if (!$file_url) {
                    return -95;
                }
                return $file_url ? $file_url : -94;
                break; //上传成功 E
        }//Switch End
    }

    static function redirectFile($file_info = array(), $file_url = NULL, $is_down_mode = 0) {
        if (!is_array($file_info)) {
            if (empty(self::$loadedObject['doc_file'])) {
                self::$loadedObject['doc_file'] = new BoeDocFileModel();
            }
            $file_info = self::$loadedObject['doc_file']->getInfo($file_info, '*'); //第1参数表示ID，第2个参数表示获取全部字段 
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
        if ($file_info['file_ext'] == 'pdf' || BoeDocFile::checkFileCanConvertPdf($file_info['file_ext'])) {
            return 'pdf';
        } elseif ($file_info['file_ext'] == 'mp4' || BoeDocFile::checkFileCanConvertMp4($file_info['file_ext'])) {
            return 'vedio';
        } elseif ($file_info['file_ext'] == 'mp3' || BoeDocFile::checkFileCanConvertMp3($file_info['file_ext'])) {
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

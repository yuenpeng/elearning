<?php

namespace common\base;

use common\base\BoeBase;
use common\base\BoeCurl;
use yii;

/**
 * Description of BoeDocFile
 * @Boe有关文档操作的中间件，这里包括了上传到金山云，Office文件转换
 * @date 2016-04-25
 * @author Administrator
 */
// Boe文档处理开始------------------------------
defined('BoeDocFileRootDir') or define('BoeDocFileRootDir', dirname(dirname(__DIR__)));
if (!class_exists('Ks3Client')) {
    $save_path = BoeDocFileRootDir . '/components/ks3/';
    $file_1 = $save_path . 'Ks3Client.class.php';
    $file_2 = $save_path . 'Ks3EncryptionClient.class.php';
    $file_3 = $save_path . 'core/Utils.class.php';
//                require_once $file_1;
//                require_once $file_2;
//                require_once $file_3;
    Yii::$classMap['Ks3Client'] = $file_1;
    Yii::$classMap['Ks3EncryptionClient'] = $file_2;
    Yii::$classMap['Utils'] = $file_3;
}

class BoeDocFile {

    private static $curlObj = NULL;
    private static $ks3Client = NULL;
    private static $ks3InternalClient = NULL;
    private static $ks3Config = NULL;
    private static $ks3AccessKey = ''; //开发者ID
    private static $ks3SecretKey = ''; //安全密钥
    private static $ks3Region = ''; //KS3的区域
    private static $ks3InternalRegion = ''; //KS3的内网区域
    private static $ks3FileUrlExpiresTime = 300; //KS3的文件URL地址的有效期
    private static $ks3DefaultBucket = 'boe'; //默认的空间名称
    private static $boeDocFileConfig = null;
    private static $boePdfConvertCacheName = 'boe_doc_pdf_converting';
    private static $developmentMode = false;
    private static $mp4ConvertLastString = '_new';

    /**
     * 初始化金山云对象
     */
    static function initKs3($InternalStatu = 0) {
        if ((!$InternalStatu && !self::$ks3Client) || ($InternalStatu && !self::$ks3InternalClient)) {
            if (!class_exists('Ks3Client')) {
                $save_path = dirname(dirname(__DIR__)) . '/components/ks3/';
                $file_1 = $save_path . 'Ks3Client.class.php';
                $file_2 = $save_path . 'Ks3EncryptionClient.class.php';
                $file_3 = $save_path . 'core/Utils.class.php';
//                require_once $file_1;
//                require_once $file_2;
//                require_once $file_3;
                Yii::$classMap['Ks3Client'] = $file_1;
                Yii::$classMap['Ks3EncryptionClient'] = $file_2;
                Yii::$classMap['Utils'] = $file_3;
            }
            self::$ks3Config = Yii::$app->params['boeKs3Config'];
            self::$ks3AccessKey = BoeBase::array_key_is_nulls(self::$ks3Config, array('access_key', 'AccessKey', 'accessKey'), '');
            self::$ks3SecretKey = BoeBase::array_key_is_nulls(self::$ks3Config, array('secret_key', 'SecretKey', 'secretKey'), '');
            self::$ks3Region = BoeBase::array_key_is_nulls(self::$ks3Config, array('region', 'Region'), 'ks3-cn-beijing.ksyun.com');
            self::$ks3InternalRegion = BoeBase::array_key_is_nulls(self::$ks3Config, array('internal_region', 'InternalRegion'), 'ks3-cn-beijing-internal.ksyun.com');
            self::$ks3FileUrlExpiresTime = BoeBase::array_key_is_nulls(self::$ks3Config, array('file_url_expires_time', 'FileUrlExpiresTime'), self::$ks3FileUrlExpiresTime);
            if ($InternalStatu) {//内网状态
                self::$ks3InternalClient = new \Ks3Client(self::$ks3AccessKey, self::$ks3SecretKey, self::$ks3InternalRegion);
            } else {
                self::$ks3Client = new \Ks3Client(self::$ks3AccessKey, self::$ks3SecretKey, self::$ks3Region);
            }
        }
    }

    /**
     * 删除一个或多个在金山云的文件
     * @param type $bucket
     * @param type $file_key
     * @return boolean
     */
    static function deleteKs3File($bucket = '', $file_key = '') {
        if (!$file_key) {
            return true;
        }
        self::initKs3(); //初始化KS3对象
        if (is_array($file_key)) {
            foreach ($file_key as $key => $a_file) {
                if (\Utils::chk_chinese($a_file)) {
                    $file_key[$key] = iconv('utf-8', 'gbk', $a_file);
                }
            }
            // $file_key = implode(',', $file_key);
        } else {
            if (\Utils::chk_chinese($file_key)) {
                $file_key = iconv('utf-8', 'gbk', $file_key);
            }
            $file_key = array($file_key);
        }
        if (!$bucket) {
            $bucket = self::$ks3DefaultBucket;
        }
        $args = array(
            "Bucket" => $bucket,
            "DeleteKeys" => $file_key,
        );
        //  BoeBase::debug(__METHOD__ . '$args:' . var_export($args, true), 1);
        return self::$ks3Client->deleteObjects($args);
    }

    /**
     * 获取文件在金山云的绝对地址
     * @param type $bucket
     * @param type $file_key
     * @return type
     */
    static function getKs3Url($bucket = '', $file_key = '', $options = array()) {
        if (!$file_key) {
            return '';
        }
        self::initKs3(); //初始化KS3对象
        if (\Utils::chk_chinese($file_key)) {
            $file_key = iconv('utf-8', 'gbk', $file_key);
        }
        if (!$bucket) {
            $bucket = self::$ks3DefaultBucket;
        }
        $args = array(
            "Bucket" => $bucket,
            "Key" => $file_key,
            'Options' => array('Expires' => self::$ks3FileUrlExpiresTime),
        );
        if (self::$ks3Client->objectExists($args)) {
            if ($options && is_array($options)) {
                $args["Options"] += $options;
            }
            $sult = self::$ks3Client->generatePresignedUrl($args);
//BoeBase::debug($sult, 1);
            return $sult;
        }
        return '';
    }
	
	/**
     * 将文件从金山云复制到本地
     * @param type $bucket
     * @param type $file_key
     * @return type
     */
    static function putKs3FileToLocal($bucket = '', $file_key = '', $save_path = '') {
        if (!$file_key) {
            return '';
        }
        self::initKs3(); //初始化KS3对象
        if (\Utils::chk_chinese($file_key)) {
            $file_key = iconv('utf-8', 'gbk', $file_key);
        }
        if (!$bucket) {
            $bucket = self::$ks3DefaultBucket;
        }
        $args = array(
            "Bucket" => $bucket,
            "Key" => $file_key,
            'Options' => array('Expires' => self::$ks3FileUrlExpiresTime),
        );
        if (self::$ks3Client->objectExists($args)) {
			$args = array(
				"Bucket"	=>$bucket,
				"Key"		=>$file_key,
				"WriteTo"	=>$save_path
			); 
			$sult = self::$ks3Client->getObject($args);
			return $sult;
        }
        return '';
    }
	

    /**
     * 将文件上传到金山云
     * @param type $file_path
     * @param type $bucket
     * @param type $file_key
     * @return type
     */
    static function putFileToKs3($file_path = '', $bucket = '', $file_key = '', $overlay = 0) {
        set_time_limit(1800);
        if (!file_exists($file_path)) {
            return -100;
        }
        if (!$file_key) {
            return -99;
        }
        self::initKs3(1); //以内网的方式初始化KS3对象
        $old_file_path = $file_path;
        if (\Utils::chk_chinese($file_path)) {
            $file_path = iconv('utf-8', 'gbk', $file_path);
        }
        if (!$bucket) {
            $bucket = self::$ks3DefaultBucket;
        }

        $args = array(
            "Bucket" => $bucket,
            "Key" => $file_key,
        );
        $need_upload = true;
        if (!$overlay) {//不是覆盖的时候
            $need_upload = self::$ks3InternalClient->objectExists($args) ? false : true;
        }
        if ($need_upload) {
            $args += array(
                "ACL" => "private", //根据8月3号的电话沟通会议，修改成这样的结果后，可以防止恶意不加后续安全字符串的访问的情况
                //   "ACL" => "public-read",
                "Content" => array(
                    "content" => $file_path,
                    "seek_position" => 0
                ),
            );
            $file_ext = substr(strrchr($file_path, "."), 1); //上传文件的扩展名
            $convert_params = NULL;
            if (self::checkFileCanConvertMp3($file_ext)) {//要转换成音频的文件
                $convert_params = self::ks3ConvertAudio($file_key, $bucket, md5($old_file_path), 0);
            } else {
				if (self::checkFileCanConvertMp4($file_ext) || $file_ext == 'mp4') {//要转换成视频的文件,对于Mp4进行额外的处理
                    $convert_params = self::ks3ConvertVideo($file_key, $bucket, md5($old_file_path), 0);
                }
            }
            if (is_array($convert_params)) {
                $args+=$convert_params;
            }
            $ks3_sult = self::$ks3InternalClient->putObjectByFile($args);
            //BoeBase::debug('$args:' . "\n" . var_export($args, true));
            //BoeBase::debug('$ks3_sult:' . "\n" . var_export($ks3_sult, true), 1);
            return !empty($ks3_sult['ETag']) ? $ks3_sult : -98;
        } else {
            return array('ETag' => true);
        }
    }

    /**
     * 将存在金山云上的视频文件转码，该过程是个异步操作
     * 正确的时候返回一个数组,里面包括一个TaskID
     */
    static function ks3ConvertVideo($file_key, $bucket = '', $file_id = '', $execute_mode = 1) {
        $args = self::parseKs3ConvertAdpParams($file_key, $bucket, $file_id, 'vedio');
        if ($args && is_array($args)) {
            if (!$execute_mode) {//上传时同时执行异步操作的模式
                unset($args['Bucket'], $args['Key']);
                return $args;
            } else {//执行模式
                self::initKs3(); //初始化KS3对象
                return self::$ks3Client->putAdp($args);
            }
        }
        return NULL;
    }

    /**
     * 将存在金山云上的音频文件转码，该过程是个异步操作
     * 正确的时候返回一个数组,里面包括一个TaskID
     */
    static function ks3ConvertAudio($file_key, $bucket = '', $file_id = '', $execute_mode = 1) {
        $args = self::parseKs3ConvertAdpParams($file_key, $bucket, $file_id, 'audio');
        if ($args && is_array($args)) {
            if (!$execute_mode) {//上传时同时执行异步操作的模式
                unset($args['Bucket'], $args['Key']);
                return $args;
            } else {//执行模式
                self::initKs3(); //初始化KS3对象
                return self::$ks3Client->putAdp($args);
            }
        }
        return NULL;
    }

    /**
     * 拼接出Ks3音视频的转换的参数
     * @param type $file_key
     * @param type $bucket
     * @param type $file_id
     * @param type $type
     * @return boolean|string
     */
    private static function parseKs3ConvertAdpParams($file_key, $bucket = '', $file_id = '', $type = 'vedio') {
        if (!$file_key) {
            return false;
        }
        if (!$file_id) {
            return '';
        }
        if (!$bucket) {
            return NULL;
        }

        $file_ext_name = substr(strrchr($file_key, "."), 1); //上传文件的扩展名
        $last_ext_name = $type == 'vedio' ? '.mp4' : '.mp3';
        $notify_type = $type == 'vedio' ? 'convertVedio' : 'convertAudio';
        $ks3_convert_cmd = $type == 'vedio' ? 'Ks3ConvertVedioCmd' : 'Ks3ConvertAudioCmd';
        if ($file_ext_name == 'mp4') {
            $new_file_key = str_replace('.' . $file_ext_name, self::getMp4ConvertLastString() . $last_ext_name, $file_key);
        } else {
            $new_file_key = str_replace('.' . $file_ext_name, $last_ext_name, $file_key);
        }
        $args = array(
            "Bucket" => $bucket,
            "Key" => $file_key,
            "Adp" => array(
                "NotifyURL" => self::parseKs3NotifyURL(array('type' => $notify_type, 'key' => $file_id)),
                "Adps" => array(
                    array(
                        "Command" => self::getBoeDocFileConfig($ks3_convert_cmd),
                        "Key" => $new_file_key,
                    )
                )
            )
        );
        return $args;
    }

    /**
     * 获取MP4再转码成MP4为避免重复的文件后缀
     * @return type
     */
    static function getMp4ConvertLastString() {
        return self::$mp4ConvertLastString;
    }

    /**
     * 获取异步任务的值
     * @param type $task_id
     * @param type $key
     */
    static function getKs3AdpTask($task_id = '', $key = '*') {
        if (!$task_id) {
            return NULL;
        }
        self::initKs3(); //初始化KS3对象
        $sult = self::$ks3Client->getAdp(array("TaskID" => $task_id));
        $xml = new \SimpleXMLElement($sult);
        $sult = self::obj2Aarray($xml);
        if ($key != "*" && $key != '') {//返回某一个字段的值，比如名称
            return isset($sult[$key]) ? $sult[$key] : false;
        } else {
            return $sult;
        }
    }

    private static function obj2Aarray($array) {
        if (is_object($array)) {
            $array = (array) $array;
        } if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = call_user_func(__METHOD__, $value);
//self::obj2Aarray($value);
            }
        }
        return $array;
    }

    /**
     * 获取金山云转换视频的命令行
     * @return type
     */
    static function getKs3ConvertVedioCmd() {
        return self::getBoeDocFileConfig('Ks3ConvertVedioCmd');
    }

    /**
     * 根据参数拼接些绝对的回调地址
     * @param type $params
     * @return type
     */
    static function parseKs3NotifyURL($params = array()) {
        $url = self::parseBoeUrlFromConfig(array('NotifyURLPrefix', 'notify_url_prefix'), $params);
        $host_info = Yii::$app->request->hostInfo;
        if ($host_info == 'http://localhost') {
            $url = str_replace('localhost', "u.boe.com", $url);
        }
        return $url;
    }

    /**
     * 根据文件扩展名和boeDocFileConfig的配置信息,获取相应的某项配置
     * @param type $file_ext 文件的扩展名
     * @param type $return_field 要返回的字段，详细如下：
     * 可以多个或是一个，用String 或是array，
     * 如果$return_field是个字符串，
     *          当等于all 或是*的时候，返回全部以数组形式的数据
     *          当$return_field含有，逗号时，表示返回多个字段，以数组的形式返回
     *          当$return_field不含有逗号时，只返回某个字段值，只返回对应字段值，如果指定的字段值不存在，返回NULL
     */
    static function getFileClassConfig($file_ext = '', $return_field = 'all', $debug = 0) {
        if (!$file_ext) {
            return NULL;
        }
        $file_class_config = self::getBoeDocFileConfig('FileClassConfig');
        if (!$file_class_config || !is_array($file_class_config)) {
            return NULL;
        }
        $upload_config = Yii::$app->params['boeFileUploadConfig']; //文件上传信息的配置
        $file_ext = strtolower(';' . $file_ext . ';');
        $patch_info = NULL;
        foreach ($file_class_config as $key => $a_config) {//读取配置信息的循环For Start
            if (isset($upload_config[$key])) {
                $tmp_allow_file_type = $upload_config[$key]['allow_file_type'];
//                BoeBase::debug($upload_config[$key]);
                $tmp_allow_file_type = str_replace(array('*', '.', ','), ';', $tmp_allow_file_type);
                $tmp_allow_file_type = strtolower(';' . str_replace(';;', ';', $tmp_allow_file_type) . ';');
//                BoeBase::debug($tmp_allow_file_type);
                if (strpos($tmp_allow_file_type, $file_ext) !== false) {
                    $a_config['text'] = Yii::t('boe', $a_config['lang_key']);
                    $patch_info = $a_config + $upload_config[$key];
                    break;
                }
            }
        }//读取配置信息的循环For End

        $sult = NULL;
        if ($patch_info) {//匹配到数据时S
            if ($return_field == 'all' || $return_field == '*') {//返回全部
                $sult = $patch_info;
            } else {//返回特定的字段时S
                $return_field = is_scalar($return_field) && strpos($return_field, ',') !== false ? explode(',', $return_field) : $return_field;
                if (is_array($return_field)) {//返回多个字段时S
                    $sult = array();
                    foreach ($return_field as $key) {
                        if (isset($patch_info[$key])) {
                            $sult[$key] = $patch_info[$key];
                        }
                    }
                }//返回多个字段时E
                else {//只返回单个字段S
                    $sult = isset($patch_info[$return_field]) ? $patch_info[$return_field] : NULL;
                }//只返回单个字段E
            }//返回特定的字段时E
        }//匹配到数据时E
        if ($debug) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug('$file_ext:' . $file_ext);
            BoeBase::debug('$path_info:' . var_export($patch_info, true));
            BoeBase::debug('$return_field:' . var_export($return_field, true));
            BoeBase::debug('$sult:' . var_export($sult, true), 1);
        }
        return $sult;
    }

    /**
     * 获取全部的文件分组配置信息
     * @return type
     */
    static function getALLFileClassConfig($save_field = array(), $class_value = NULL, $debug = 0) {
        $file_class_config = self::getBoeDocFileConfig('FileClassConfig');
        if (!$file_class_config || !is_array($file_class_config)) {
            return NULL;
        }
        $upload_config = Yii::$app->params['boeFileUploadConfig']; //文件上传信息的配置
        $current_user_id = Yii::$app->user->getId();
        if (!$current_user_id) {
            $current_user_id = 0;
        }
        $patch_info = array();
        $save_field = is_array($save_field) ? $save_field : explode(',', str_replace(array(';', '；', '、'), ',', $save_field));
        foreach ($file_class_config as $key => $a_config) {//读取配置信息的循环For Start
            $match = false;
            if (isset($upload_config[$key])) {
                if ($class_value !== NULL) {
                    $match = $a_config['class_value'] == $class_value;
                } else {
                    $match = true;
                }
            }
            if ($match) {
                $a_config['text'] = Yii::t('boe', $a_config['lang_key']);
                $a_config['client_url'] = Yii::$app->urlManager->createUrl(['boe/doc/upload', 'key' => $key, 'uid' => $current_user_id, 'is_swfupload' => 1]);
                $a_config +=$upload_config[$key];
                if ($save_field) {
                    $patch_info[$key] = array();
                    foreach ($save_field as $a_field) {
                        $patch_info[$key][$a_field] = BoeBase::array_key_is_nulls($a_config, $a_field, NULL);
                    }
                } else {
                    $patch_info[$key] = $a_config;
                }
            }
        }
        if ($debug) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug('$path_info:' . var_export($patch_info, true), 1);
        }
        return $patch_info;
    }

    /**
     * 将Word、XLS、PPT文件转成 PDF
     * @param type $s_file 源文件
     * @param string $pdf_dir pdf文件保存目录
     * @return type int 
     * 1=文件转换成功
     * -100=未定义源文件时
     * -99=源文件不需要转换
     * -98=源文件不存在时
     * -97=未配置转换命令时
     * -96=pdf文件最终的保存目录不存在
     * -95=转换文件失败
     */
    static function doc2pdf($s_file, $pdf_dir = '', $callback_url = '', $debug = 0) {
        set_time_limit(120);
        if (!$s_file) {
            if ($debug) {
                BoeBase::debug('未指定源文件', 1);
            }
            return -100;
        }
        $originalSourceFileName = $s_file;
        $file_ext = substr(strrchr($s_file, "."), 1); //上传文件的扩展名
        if (!self::checkFileCanConvertPdf($file_ext)) {//检测文件类型需不需要转换
            return -99;
        }
        if (DIRECTORY_SEPARATOR == '\\' && preg_match('/[\x80-\xff]./', $s_file)) {//Windows下含有中文时
            $s_file = iconv('utf-8', 'gbk', $s_file);
        }
        if (!file_exists($s_file)) {
            if ($debug) {
                BoeBase::debug($s_file . '不存在!', 1);
            }
            return -98;
        }
        $parse_file_info = pathinfo($s_file);
        $pdf_dir = $pdf_dir ? $pdf_dir : $parse_file_info['dirname'] . '/';
        $pdf_dir = BoeBase::fix_path($pdf_dir);
        $pdf_file = $pdf_dir . basename($parse_file_info['basename'], '.' . $file_ext) . '.pdf'; //最终的PDF
        clearstatcache();
        if (file_exists($pdf_file)) {
            if ($debug) {
                if (DIRECTORY_SEPARATOR == '\\' && preg_match('/[\x80-\xff]./', $originalSourceFileName)) {//Windows下含有中文时
                    $tmp_pdf_file = iconv('gbk', 'utf-8', $pdf_file);
                }
                BoeBase::debug('结果文件' . $tmp_pdf_file . '已存在!', 1);
            }
            return 1;
        }

        $convert_cmd = self::getPd2ConvertCmd();
        $convert_wait_time = self::getPd2ConvertMaxWaitTime();
        $convert_fail_retry_count = self::getPd2ConvertFailRetryCount();
        if (!$convert_cmd) {//未配置转换命令时
            if ($debug) {
                BoeBase::debug('未配置转换命令时!', 1);
            }
            return -97;
        }
        $tmp_pdf_dir = $pdf_dir;
        $pdf_dir = BoeBase::mkdir($pdf_dir);
        if (!$pdf_dir) {//pdf文件最终的保存目录不存在
            if ($debug) {
                BoeBase::debug($tmp_pdf_dir . '不存在且无法创建!', 1);
            }
            return -96;
        }
        $wait_time = 0;
        if ($convert_wait_time < 10) {
            $convert_wait_time = 10;
        }
        if ($convert_fail_retry_count < 1) {
            $convert_wait_time = 1;
        }
        $LocalHostConvertMode = !preg_match('/(http|https):\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $convert_cmd) ? true : false;
        $cmd = $curl_params = NULL;
        $convert_count = 0;
        $convert_sult = false;
        $convert_fail_retry_count = 1;
        clearstatcache();
        while ($convert_sult == false && $convert_count < $convert_fail_retry_count) {//进行多次转换操作While开始
            if ($LocalHostConvertMode) {//用本机的命令行转换时S
                $other_converting = Yii::$app->cache->get(self::$boePdfConvertCacheName); //别的地方正在转换格式与否 
                while ($other_converting && $wait_time < $convert_wait_time) {//如果别的地方正在转换，等待一会
                    $other_converting = Yii::$app->cache->get(self::$boePdfConvertCacheName);
                    if (!$other_converting) {
                        break;
                    } else {
                        $wait_time++;
                        sleep(1);
                    }
                }
                if (!$cmd) {
                    $cmd = str_replace(array('{source_file}', '{pdf_dir}', '{pdf_file}'), array($s_file, $pdf_dir, $pdf_file), $convert_cmd);
                }
                Yii::$app->cache->set(self::$boePdfConvertCacheName, 1, $convert_wait_time); //添加锁标记
                exec($cmd); //执行转换命令
                Yii::$app->cache->set(self::$boePdfConvertCacheName, 0, $convert_wait_time); //解锁
                if ($debug) {
                    BoeBase::debug("第" . ($convert_count + 1) . "次本机的命令行转换:\n" . $cmd);
                } else {
                    self::curlWriteLog("第" . ($convert_count + 1) . "次本机的命令行转换:\n" . $cmd);
                }
                clearstatcache();
                if (!file_exists($pdf_file)) {//如果转换PDF失败S
                    sleep(1);
                    $convert_sult = false;
                    $convert_count++;
                } else {
                    $convert_sult = true;
                    break;
                }
            } //用本机的命令行转换时E
            else {//用WebService接口的方式执行时转换命令时S
                if (!$cmd) {
                    $cmd = str_replace(array('{source_file}', '{pdf_dir}', '{pdf_file}'), array(urlencode($originalSourceFileName), urlencode($pdf_dir), urlencode($pdf_file)), $convert_cmd);
                    $curl_params = array(
                        'url' => $cmd,
                        'post' => array('call_back' => $callback_url)
                    );
                }

                if (self::$curlObj === NULL) {
                    self::$curlObj = new BoeCurl();
                }
                $curl_sult = self::$curlObj->start($curl_params);

                if ($debug) {
                    BoeBase::debug("第" . ($convert_count + 1) . "次WebService接口的方式转换:\n" . var_export($curl_sult, true));
                } else {
                    self::curlWriteLog("第" . ($convert_count + 1) . "次WebService接口的方式转换:\n" . var_export($curl_sult, true));
                }
                if (!empty($curl_sult['content']) && stripos($curl_sult['content'], 'Success') !== false) {
                    $convert_sult = true;
                    break;
                } else {
                    sleep(1);
                    $convert_sult = false;
                    $convert_count++;
                }
            }//用WebService接口的方式执行时转换命令时E
        }//进行多次转换操作While结束
        clearstatcache();
        if (!$convert_sult) {//如果转换PDF失败S
            if ($debug) {
                BoeBase::debug($pdf_file . '转换失败!', 1);
            } else {
                self::curlWriteLog(__METHOD__ . "方法对文件" . $originalSourceFileName . '进行PDF转换成' . $pdf_file . '失败!');
            }
            return -95;
        }//如果换成PDF失败E
        else {
            self::curlWriteLog(__METHOD__ . "方法对文件" . $originalSourceFileName . '进行PDF转换成' . $pdf_file . '成功!');
            return 1;
        }
    }

    /**
     * 获取PDF文件转换的最大等待时间 
     * @return type
     */
    static function getPd2ConvertMaxWaitTime() {
        return self::getBoeDocFileConfig('Doc2PdfWaitTime');
    }

    /**
     * 获取PDF文件转换失败时重试的次数
     * @return type
     */
    static function getPd2ConvertFailRetryCount() {
        $sult = intval(self::getBoeDocFileConfig('Doc2PdfFailRetryCount'));
        return $sult < 1 ? 1 : $sult;
    }

    /**
     * 获取PDF文件转换的命令行
     * @return type
     */
    static function getPd2ConvertCmd() {
        return self::getBoeDocFileConfig('Doc2PdfCmd');
    }

    /**
     * 根据文件扩展名和配置信息，检测文件是否符合配置中的某一类文件
     * @param type $file_ext
     * @param type $config_name
     * @return boolean
     */
    static function checkFileExtInFromConfig($file_ext, $config_name = '') {
        if (!$file_ext || !$config_name) {
            return false;
        }
        $convert_file_type = self::getBoeDocFileConfig($config_name);
        if (!$convert_file_type || !is_array($convert_file_type)) {
            return false;
        }
        $convert_file_type_str = implode(';', $convert_file_type);
        $convert_file_type_str = str_replace(array('*', '.', ','), '', $convert_file_type_str);
        $convert_file_type_str = strtolower(';' . str_replace(';;', ';', $convert_file_type_str) . ';');
        $file_ext = strtolower(';' . $file_ext . ';');
        return strpos($convert_file_type_str, $file_ext) !== false ? true : false;
    }

    /**
     * 获取上传配置相关信息
     * @return type
     */
    static function getBoeDocFileConfig($key = '') {
        if (self::$boeDocFileConfig === NULL) {
            self::$boeDocFileConfig = Yii::$app->params['boeDocFileConfig'];
        }
        return $key ? BoeBase::array_key_is_nulls(self::$boeDocFileConfig, $key) : self::$boeDocFileConfig;
    }

    /**
     * 获取将文件上传到金山云最大的上传等待时间
     * @return type
     */
    static function getPutFileToKs3MaxWaitTime() {
        return self::getBoeDocFileConfig('PutKs3WaitTime');
    }

    /**
     * 检测文件是否能够转换成PDF文件
     * @param type $file_ext
     * @return boolean
     */
    static function checkFileCanConvertPdf($file_ext = '') {
        return self::checkFileExtInFromConfig($file_ext, 'ConvertPdfFileType');
    }

    /**
     * 检测文件是否能够转换成Mp4文件
     * @param type $file_ext
     * @return boolean
     */
    static function checkFileCanConvertMp4($file_ext = '') {
        return self::checkFileExtInFromConfig($file_ext, 'ConvertMp4FileType');
    }

    /**
     * 检测文件是否能够转换成Mp3文件
     * @param type $file_ext
     * @return boolean
     */
    static function checkFileCanConvertMp3($file_ext = '') {
        return self::checkFileExtInFromConfig($file_ext, 'ConvertMp3FileType');
    }

    /**
     * 根据配置文件的数组索引和URL传递参数，拼接出相应的URL地址
     * @param type $config_name
     * @param type $params
     * @return type
     */
    static function parseBoeUrlFromConfig($config_name, $params = array()) {
        $url_mb = self::getBoeDocFileConfig($config_name);
        $url_key = "url:";
        $params = !is_array($params) ? array('key' => $params) : $params;
        if (BoeBase::startWith($url_mb, $url_key)) {//用Yii框架根据Controller和Action拼接出来的动态URL地址
            $server_name = Yii::$app->request->get('server_name');
            $path_info = substr($url_mb, strlen($url_key));
            $tmp_arr = array($path_info) + $params;
            $host_info = Yii::$app->request->hostInfo;
//            if ($server_name && $host_info == $server_name && $server_name != "http://localhost") {
//                $host_info = "http://localhost";
//            }
            return $host_info . Yii::$app->urlManager->createUrl($tmp_arr);
        } else {//已经是个绝对地址了S
            if (strpos($url_mb, '?') !== false) {//URL地址中已经含有问号了
                $tmp_url = $url_mb;
            } else {
                $tmp_url = $url_mb . '?';
            }
            foreach ($params as $key => $a_value) {
                $tmp_url.="&{$key}={$a_value}";
            }
            $tmp_url = str_replace('?&', '?', $tmp_url);
            return $tmp_url;
        }//已经是个绝对地址了E
    }

    /**
     * 根据扩展名，拼接出相应的缩略图的URL地址
     * @param type $ext
     * @return type
     */
    static function getFileIcoImageUrl($ext) {
        $template = self::getBoeDocFileConfig('FileIcoImageTemplate');
        return str_replace('{ext}', $ext, $template);
    }

//********************************和利用CURL库执行异步操作有关的代码开始*****************************************
    static function curlWriteLog($text) {
        $debug = self::$developmentMode;
        if (!$debug) {
            $debug = Yii::$app->request->get('development_mode') == 1 ? true : false;
        }
        if (!$debug) {
            $debug = YII_DEBUG ? true : false;
        }
        if ($debug) {
            $log_dir = BoeDocFileRootDir . '/boe_doc_log';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir);
            }
            $log_file = $log_dir . '/' . date("YmdH") . '.log';
            $log_content = "\n==========================" . date("Y-m-d H:i:s");
            $log_content.="==================================\n";
            $log_content.=(!is_scalar($text) ? var_export($text, true) : $text);
            $log_content.="\n=========================================================\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
        }
    }

    /**
     * 根据文件ID，异步执行转换成PDF文件
     * @param type $key
     * @return boolean
     */
    static function CurlAsynConvertPdf($key = '') {
        if (self::$curlObj === NULL) {
            self::$curlObj = new BoeCurl();
        }
        if (!$key) {
            return false;
        }
        $curl_params = array(
            'url' => self::parseBoeUrlFromConfig('AsynConvertPdfURLPrefix', array('key' => $key, 'server_name' => Yii::$app->request->hostInfo)),
            'curl_m_time_out' => 1000, //异步执行的关键
        );
        self::curlWriteLog(__METHOD__ . '在调用之前的日志：' . var_export($curl_params, true));
        $sult = self::$curlObj->start($curl_params);
        self::curlWriteLog(__METHOD__ . '在调用之后的日志：' . var_export($sult, true));
        return $sult;
    }

    /**
     * 根据文件ID，异步执行上传文件到KS3
     * @param type $key
     * @return boolean
     */
    static function CurlAsynSaveToKs3($key = '', $pdf_mode = 0) {

        if (!$key) {
            return false;
        }
        $curl_params = array(
            'url' => self::parseBoeUrlFromConfig('AsynSaveKs3URLPrefix', array('key' => $key)),
            'curl_m_time_out' => 1000, //异步执行的关键
        );
        if (self::$curlObj === NULL) {
            self::$curlObj = new BoeCurl();
        }
        $sult = self::$curlObj->start($curl_params);
        self::curlWriteLog($sult);
        return $sult;
    }

//------------------Boe文档处理结束-------------
}

<?php

/**
 * User: Zhenglk 
 */

namespace common\base;

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use Yii;

class BoeBase {

    static $boeFileUploadConfig = NULL;
    static $boeFileUpload = NULL;
    static $boeUrlTemplate = NULL;

    /**
     * getLinkInfo 一段强化分页结果的代码，为分页添加跳转输入框和页面数量下拉框
     * @param \common\base\yii\data\Pagination $pages
     * @param type $maxButtonCount
     * @param type $PageSize
     * @return string
     */
    public static function getLinkInfo($pages, $maxButtonCount = 5, $PageSize = array()) {
        if (gettype($pages) != 'object' && $pages instanceof yii\data\Pagination) {
            return '';
        }
        $sult = LinkPager::widget([
                    'pagination' => $pages,
                    'maxButtonCount' => $maxButtonCount,
                    'nextPageLabel' => '&gt',
                    'prevPageLabel' => '&lt',
        ]);
        if (!$sult) {
            $sult = "<ul class=\"pagination\"></ul>";
        }
        if (!$PageSize || !is_array($PageSize)) {
            $PageSize = array(5, 10, 20, 30, 50, 80, 100, 150, 200);
        }
        $c_pagesize = $pages->pageSize;
        $c_page = $pages->getPage() + 1;
        $c_page_size_param = $pages->pageSizeParam;
        $c_page_param = $pages->pageParam;
        $c_page_count = $pages->getPageCount();
        $all_count = $pages->totalCount;
        if (!in_array($c_pagesize, $PageSize)) {
            $PageSize[] = $c_pagesize;
        }
        $total_str = $input_str = $select_text = '';
        $total_str = str_ireplace(array('{page_count}', '{data_count}'), array($c_page_count, $all_count), Yii::t('boe', 'total_info'));
        if ($c_page_count > $maxButtonCount) {
            $input_title = Yii::t('boe', 'input_title');
            $input_str = "\n<input id=\"pageNumber\" "
                    . " title=\"{$input_title}\""
                    . " class=\"pageNumber\""
                    . " name=\"pageNumber\""
                    . " style=\"text-align:center;\""
                    . " value=\"{$c_page}\" "
                    . " onkeyup=\"this.value=this.value.replace(/\D/g,'')\" "
                    . " onafterpaste=\"this.value=this.value.replace(/\D/g,'')\" "
                    . " onkeydown=\"javascript:if(event.charCode==13||event.keyCode==13){changePageParamValue(this.value,self.location.href,'{$c_page_param}={$c_page}','{$c_page_param}');return false;}\"  type=\"text\">";
        }
        $select_text = "\n<select id='pageSizeSelect' "
                . " class='pageSizeSelect'"
                . " name='pageSizeSelect'"
                . " onchange=\"changePageParamValue(this.options[this.selectedIndex].value,self.location.href,'{$c_page_size_param}={$c_pagesize}','{$c_page_size_param}')\">\n"
                . "{options}\n</select>";
        $options_text = '';
        $mb = Yii::t('boe', 'page_size_text') . "%s" . Yii::t('boe', 'page_size_unit');
        foreach ($PageSize as $a_info) {
            $tmp_str = sprintf($mb, $a_info);
            if ($a_info == $c_pagesize) {
                $options_text.= "\n<option value='{$a_info}' selected>{$tmp_str}</option>";
            } else {
                $options_text.= "\n<option value='{$a_info}'>{$tmp_str}</option>";
            }
        }
        $select_text = str_replace('{options}', $options_text, $select_text);
        $sult = str_replace("</ul>", $total_str . $input_str . $select_text . '</ul>', $sult);

        //  self::debug($sult, 1);
        return $sult;
    }

    /**
     * 输出一段含有Js的 alert,在指定了Url的情况下，跳转到URL的<script>代码块
     * @param type $mess
     * @param type $url
     * @param type $dialog
     * @return string
     */
    public static function alert($mess, $url = '', $dialog = 0) {
        $txt = "<script>\n";
        if ($dialog) {//使用artDialog
            if ($url) {
                $txt.="alertCallBack=function(){location.href='{$url}'};\n";
            }
            $txt.="newAlert('{$mess}');\n";
        } else {
            $txt.="alert('{$mess}');\n";
            if ($url) {
                $txt.="location.href='{$url}';\n";
            }
        }
        $txt.="\n</script>";
        return $txt;
    }

    /**
     * 输出一段含有Js的 alert,在指定了$js的情况下，执行特定的JS的<script>代码块
     * @param type $mess
     * @param type $url
     * @param type $dialog
     * @return string
     */
    public static function alertAndGo($mess, $js = '', $dialog = 0) {
        $txt = "<script>\n";
        if ($dialog) {//使用artDialog
            if ($js) {
                $txt.="alertCallBack=function(){{$js}};\n";
            }
            $txt.="newAlert('{$mess}');\n";
        } else {
            $txt.="alert('{$mess}');\n";
            if ($js) {
                $txt.=$js;
            }
        }
        $txt.="\n</script>";
        return $txt;
    }

    /**
     * getUploadConfig
     * 获取上传的配置信息
     * @param type $type
     * @param type $value_key
     * @return type
     */
    public static function getUploadConfig($type = '', $value_key = '') {
        if (!self::$boeFileUploadConfig) {
            self::$boeFileUploadConfig = Yii::$app->params['boeFileUploadConfig'];
        }
        if (!isset(self::$boeFileUploadConfig[$type])) {
            return NULL;
        }
        if ($value_key) {
            return self::array_key_is_nulls(self::$boeFileUploadConfig[$type], $value_key, NULL);
        }
        return self::$boeFileUploadConfig[$type];
    }

    /**
     * 判断str是否以$needle开头
     * @param type $str
     * @param type $needle
     * @return type
     */
    public static function startWith($str, $needle) {
        return strpos($str, $needle) === 0;
    }

    /**
     * getFileUrl根据传递的相对地址和类型参数，拼凑出详细的URL地址
     * @param type $url
     * @param type $type
     * @return type
     */
    public static function getFileUrl($url = '', $type = 'news') {
        $url_prefix = self::getUploadConfig($type, 'url_prefix');
        $url_key = "url:";
        if (is_array($url_prefix)) {
            $tmp_url_prefix = self::array_key_is_nulls($url_prefix, 'url');
            $tmp_key_function = self::array_key_is_nulls($url_prefix, 'key_function', 'urlencode');
            $key_value = call_user_func($tmp_key_function, $url);
            if (self::startWith($tmp_url_prefix, $url_key)) {//需要返回的时个动态URL地址时
                $path_info = substr($tmp_url_prefix, strlen($url_key));
                return Yii::$app->urlManager->createUrl([$path_info, 'key' => $key_value]);
            } else {
                return $tmp_url_prefix . $key_value;
            }
        } else {
            if (self::startWith($url_prefix, $url_key)) {//需要返回的时个动态URL地址时
                $path_info = substr($url_prefix, strlen($url_key));
                return Yii::$app->urlManager->createUrl([$path_info, 'key' => urlencode($url)]);
            }
            return $url_prefix . $url;
        }
    }

    /**
     * 编辑器上传文件,因为前后台都会用到，所以直接这里显示
     * editorUpload
     * @param type $config_key //上传的配置数组键值
     * @param type $dir //额外的目录 
     * @param type $url_field //用来拼接访问URL的用到的数组键值，一般情况下用默认值file_relative_path就行
     * @return type
     */
    public static function editorUpload($config_key = '', $dir = '', $url_field = 'file_relative_path') {
        $file_url = "";
        $msg = "";
        $error = 0;
        if (!$error && !$dir) {
            $msg = Yii::t('boe', 'editor_params_error');
            $error = 1;
        }
        if (!$config_key) {
            $msg = Yii::t('boe', 'no_assgin_upload_config_key');
            $error = 1;
        }
        $uploadParams = NULL;
        if (!$error) {
            $uploadParams = self::getUploadConfig($config_key);
            if (!$uploadParams || !is_array($uploadParams)) {
                $msg = Yii::t('boe', 'upload_config_key_error');
                $error = 1;
            }
        }
        if (!$error) {//没有错误的时候S
            $boeFileUpload = new BoeUpload();
            $uploadParams['form_name'] = 'imgFile';
            $uploadSult = $boeFileUpload->uploadFile($uploadParams);
            if (isset($uploadSult['file_relative_path'])) {//文件上传成功
                $file_url = self::getFileUrl($uploadSult[$url_field], $config_key);
                $error = 0;
            } else {
                if (is_string($uploadSult)) {//出错了
                    $error = 1;
                    $msg = $uploadSult;
                } else {
                    $error = 1;
                    $msg = Yii::t('boe', 'upload_unknow_error');
                }
            }
        }//没有错误的时候E 
        $sult = array('error' => $error, 'url' => $file_url, 'message' => $msg);
        return $sult;
    }

    /**
     * 用ajax上传文件,因为前后台都会用到，所以直接这里显示
     * editorUpload
     * @param type $config_key //上传的配置数组键值
     * @param type $return_field //返回的字段追加文件上传结果数组的字段信息，可以是个数组例如array('file_size','pic_width')，也可以是String值*或是all, 
     * @param type $url_field //用来拼接访问URL的用到的数组键值，一般情况下用默认值file_relative_path就行
     * @return type
     */
    public static function ajaxUpload($config_key = '', $return_field = NULL, $url_field = 'file_relative_path') {
        $file_url = "";
        $file_relative_path = "";
        $msg = "";
        $error = 0;
        if (!$config_key) {
            $msg = Yii::t('boe', 'no_assgin_upload_config_key');
            $error = 1;
        }
        $uploadParams = NULL;
        if (!$error) {
            $uploadParams = self::getUploadConfig($config_key);
            if (!$uploadParams || !is_array($uploadParams)) {
                $msg = Yii::t('boe', 'upload_config_key_error');
                $error = 1;
            }
        }
        $uploadSult = array();
        if (!$error) {//没有错误的时候S
            $boeFileUpload = new BoeUpload();
            $tmpKeyArr = array_keys($_FILES);
            $uploadParams['form_name'] = current($tmpKeyArr);
            $uploadSult = $boeFileUpload->uploadFile($uploadParams);
            if (isset($uploadSult['file_relative_path'])) {//文件上传成功
                $file_relative_path = $uploadSult['file_relative_path'];
                $file_url = self::getFileUrl($uploadSult[$url_field], $config_key);
                $error = 0;
            } else {
                if (is_string($uploadSult)) {//出错了
                    $error = 1;
                    $msg = $uploadSult;
                } else {
                    $error = 1;
                    $msg = Yii::t('boe', 'upload_unknow_error');
                }
            }
        }//没有错误的时候E 

        $sult = array(
            'error' => $error,
            'url' => $file_url,
            'message' => $msg,
            'relative_path' => $file_relative_path
        );
        if (is_array($uploadSult) && $return_field) {
            if (is_array($return_field)) {
                foreach ($uploadSult as $key => $a_value) {
                    if (in_array($key, $return_field)) {
                        $sult[$key] = $a_value;
                    }
                }
            } else {
                if ($return_field == 'all' || $return_field == "*") {
                    $sult+=$uploadSult;
                }
            }
        }
        return $sult;
    }

    /**
     * 表单上传文件
     * formUpload
     * @param type $config_key
     * @param type $form_name
     * @return type array()
     */
    public static function formUpload($config_key = '', $form_name = '') {
        $sult = array();
        $msg = "";
        $error = 0;
        if (!$error && !$form_name) {
            $msg = Yii::t('boe', 'form_upload_params_error');
            $error = 1;
        }
        if (!$config_key) {
            $msg = Yii::t('boe', 'no_assgin_upload_config_key');
            $error = 1;
        }
        $uploadParams = NULL;
        if (!$error) {
            $uploadParams = self::getUploadConfig($config_key);
            if (!$uploadParams || !is_array($uploadParams)) {
                $msg = Yii::t('boe', 'upload_config_key_error');
                $error = 1;
            }
        }
        if (!$error) {//没有错误的时候S
            $boeFileUpload = new BoeUpload();
            $uploadParams['form_name'] = $form_name;
            $uploadSult = $boeFileUpload->uploadFile($uploadParams);
            if (isset($uploadSult['file_relative_path'])) {//文件上传成功 
                $sult = $uploadSult;
                $error = 0;
            } else {
                if (is_string($uploadSult)) {//出错了
                    $error = 1;
                    $msg = $uploadSult;
                } else {
                    $error = 1;
                    $msg = Yii::t('boe', 'upload_unknow_error');
                }
            }
        }//没有错误的时候E 
        $sult['error'] = $error;
        $sult['message'] = $msg;
        return $sult;
    }

    /**
     * getBoeUrl根据传递的参数的URL地址配置，获取BOE专用的URL信息
     * @param type $url
     * @param type $type
     * @return type
     */
    public static function getBoeUrl($url_params = array(), $type = '') {
        if (!self::$boeUrlTemplate) {
            self::$boeUrlTemplate = Yii::$app->params['boeUrlTemplate'];
        }
        if (!isset(self::$boeUrlTemplate[$type])) {
            return 'boeUrlTemplate [$type=' . $type . '] Not Config!';
        }
        $url = self::$boeUrlTemplate[$type];
        if (is_array($url_params)) {
            foreach ($url_params as $key => $a_params) {
                $url = str_replace('{' . $key . '}', $a_params, $url);
            }
        } else {
            $url = str_replace('{kid}', $url_params, $url);
        }
        return $url;
    }

    // --------------------------------------------1大零常用的功能性函数--------------------------------------------
    /**
     * 将多维数组合用Join_str合并成字符串
     * @param type $join_str
     * @param type $arr
     * @return string
     */
    public static function implodeAdv($join_str = "\n", $arr = NULL) {
        if (!$arr) {
            return '';
        }
        $tmp_arr = self::scatterArray($arr);
        return implode($join_str, $tmp_arr);
    }

    /**
     * 将多维数组打散成单维数组
     */
    public static function scatterArray($arr = array()) {
        if (!$arr || !is_array($arr)) {
            return array();
        }
        $tmp_arr = array();
        foreach ($arr as $a_info) {
            if (is_scalar($a_info)) {
                $tmp_arr[] = $a_info;
            } else {
                if (is_array($a_info)) {
                    $tmp_arr = array_merge($tmp_arr, call_user_func(__METHOD__, $a_info));
                }
            }
        }
        return $tmp_arr;
    }

    public static function format_size($size_number, $lang = array()) {//字节代码转换
        $s = self::is_numbers($size_number);
        $dw_array = $lang ? $lang : array("B", "KB", "MB", "GB", "TB");
        $sult = $s . $dw_array[0];
        if ($s > 0) {
            $dw_len = count($dw_array);
            for ($i = $dw_len - 1; $i >= 0; $i--) {
                $m = 1.024 * pow(1000, $i);
                if ($s >= $m) {
                    $tmp_str = number_format($s / $m, 2, ".", "") . $dw_array[$i];
                    $sult = ($s > $m) ? $tmp_str : '1' . $dw_array[$i];
                    break;
                }
            }
        } else {
            $sult = '0' . $dw_array[0];
        }
        return $sult;
    }

    public static function array_key_is_nulls($arr = NULL, $key = 0, $default = NULL) {
        if (is_array($arr)) {
            if (is_array($key)) {
                if (count($key) > 1) {//这种情况时:array_key_is_nulls($arr,array('a','b'),'')
                    $tmp_value = NULL;
                    foreach ($key as $a_key) {
                        $tmp_value = isset($arr[$a_key]) ? self::is_nulls($arr[$a_key], NULL) : NULL;
                        if ($tmp_value !== NULL) {
                            return $tmp_value;
                        }
                    }
                } else {//这种情况时:array_key_is_nulls($arr,array('a'=>'b'),'')
                    $sub1_key = key($key);
                    $sub2_key = $key[$sub1_key];
                    if (isset($arr[$sub1_key]) && is_array($arr[$sub1_key])) {
                        return self::array_key_is_nulls($arr[$sub1_key], $sub2_key, $default);
                    }
                }
            } else {//这种情况时:array_key_is_nulls($arr,'a','')
                if (isset($arr[$key])) {
                    return self::is_nulls($arr[$key], $default);
                }
            }
            return $default;
        } else {
            return self::is_nulls($arr, $default);
        }
    }

    /**
     * 字符串按数量分解成数组
     * @param type $str
     * @param type $split_len
     * @return type
     */
    public static function str2Array($str, $split_len = 1) {
        $array = array();
        $strlen = strlen($str);
        while ($strlen) {
            $tmp_str = self::left($str, $split_len);
            $array[] = $tmp_str;
            $str = substr($str, strlen($tmp_str));
            $strlen = strlen($str);
        }
        return $array;
    }

    /**
     * str2Line将字符串根据每行的字数和行数，转成带<br/>的文本
     * @param type $string
     * @param type $line_count //每行字数
     * @param type $line_num //行数
     * @param type $join_str//行行与之间的换行符
     * @return type $string
     */
    public static function str2Line($string, $line_count = 80, $line_num = 2, $join_str = '<br />') {
        $string = strip_tags($string);
        $string = str_replace(array("\r\n", "\n", " "), "", $string);
        $string = self::left($string, $line_count * $line_num);
        $t_arr = self::str2Array($string, $line_count);
        //self::debug($t_arr,1);
        return implode($t_arr, $join_str);
    }

    /**
     * left 按字节数截取中文占3个字符
     * @param type $str
     * @param type $len
     * @param type $add
     * @return string
     */
    public static function left($string = '', $length = 0, $add_str = '', $start = 0) {
        $chars = $string;
        $old_length = mb_strlen($chars);
        $i = $m = $n = $l = $k = 0;
        if ($chars != '' && $length > 0) {
            $t_s = '';
            do {
                $t_s = substr($chars, $i, 1); //echo ($t_s); 
                if (preg_match("/[^(\x80-\xFF)]|[\)]|[\(]/", $t_s)) {
                    $m++;
                } else {
                    $n++;
                }
                $k = $n / 3 + $m / 2;
                $l = $n / 3 + $m;
                $i++;
            } while ($k < $length);
            $str1 = mb_substr($string, $start, $l);
            ($add_str != "" && $l < $old_length) && $str1.=$add_str;
            return $str1;
        }
        return '';
    }

    public static function array_key_is_numbers($arr = NULL, $key = 0, $error_num = 0, $debug = 0) {
        if (is_array($arr)) {
            if (is_array($key)) {
                if (count($key) > 1) {//这种情况时:array_key_is_numbers($arr,array('a','b'),0)
                    $tmp_value = NULL;
                    foreach ($key as $a_key) {
                        $tmp_value = isset($arr[$a_key]) ? self::is_numbers($arr[$a_key], NULL) : NULL;
                        if ($tmp_value !== NULL) {
                            return $tmp_value;
                        }
                    }
                } else {//这种情况时:array_key_is_numbers($arr,array('a'=>'b'),0)
                    $sub1_key = key($key);
                    $sub2_key = $key[$sub1_key];
                    if (isset($arr[$sub1_key]) && is_array($arr[$sub1_key])) {
                        return call_user_func(__METHOD__, array($arr[$sub1_key], $sub2_key, $error_num));
                    }
                }
            } else {
                if (isset($arr[$key])) {
                    return self::is_numbers($arr[$key], $error_num);
                }
            }
            return $error_num;
        } else {
            return self::is_numbers($arr, $error_num);
        }
    }

    public static function is_nulls($v, $default = NULL) {
        if (isset($v)) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '') {
                    return $default;
                }
            }
            return $v;
        } else {
            return (empty($v)) ? $default : $v;
        }
    }

    public static function is_numbers($v, $error_num = 0) {
        if (is_numeric($v)) {
            if (is_string($v)) {
                $tmp_int_value = intval($v);
                $tmp_float_value = floatval($v);
                if ($tmp_float_value == $v || $tmp_int_value == $v) {
                    return strpos($v, '.') !== false ? floatval($v) : intval($v);
                }
            } else {
                return strpos(strval($v), '.') !== false ? floatval($v) : intval($v);
            }
        }
        return $error_num;
    }

    public static function debug($str, $exit = 0) {
        print_r("<pre>\n");
        print_r($str);
        print_r("\n</pre>\n");
        if ($exit) {
            exit();
        }
    }

    public static function only_one_null($str = '', $s = '') {//将$str中连续的多个$s只保留一个
        $tmp_null_str = trim($str);
        if (isset($tmp_null_str[0]) && isset($s[0])) {
            while (stristr($tmp_null_str, $s . $s)) {
                $tmp_null_str = str_ireplace($s . $s, $s, $tmp_null_str);
            }
        }
        return $tmp_null_str;
    }

    public static function md5_16($str) {
        return substr(md5($str), 8, -8);
    }

    /**
     * 获取当前用户的浏览器信息
     * @return string
     */
    static function get_broswer() {
        if (strpos($_SERVER["HTTP_USER_AGENT"], "MSIE 9.0")) {
            return "IE9";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "MSIE 8.0")) {
            return "IE8";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "MSIE 7.0")) {
            return "IE7";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "MSIE 6.0")) {
            return "IE6";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "Firefox")) {
            return "Firefox";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "Chrome")) {
            return "Chrome";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "Safari")) {
            return "Safari";
        } if (strpos($_SERVER["HTTP_USER_AGENT"], "Opera")) {
            return "Opera";
        } return "Unkonw";
    }

    /**
     * 将可能是Windows的文件地址转换成Linux的文件地址形式
     * @param type $path
     * @param type $is_file
     * @return type
     */
    public static function fix_path($path, $is_file = 0) {
        if (!$path) {
            return '';
        }
        if ($path[0] == '\\' && $file_full_pathpath[1] == '\\') {
            $path = '?upload?' . substr($path, 1);
        }
        $path = str_replace('\\', '/', $path);
        $path = BoeBase::only_one_null($path, '/');
        if (strpos($path, '?upload?') !== false) {
            $path = '\\\\' . substr($path, 1);
        }
        $path = rtrim($path, '/') . ($is_file ? '' : '/');
        return $path;
    }

    /**
     * @zhenglk  创建文件夹
     * @param String $path  路径
     * @param int    $chmod 文件夹权限
     * @note  $chmod 参数不能是字符串(加引号)，否则linux会出现权限问题
     */
    public static function mkdir($dir, $mode = 0777) {
        if (!$dir) {
            return false;
        }
        $t_p = DIRECTORY_SEPARATOR;
        $dir = str_replace("\\", '/', $dir);
        $mdir = '';
        if ($t_p == "\\") { //对于服务器是Windows的操作系统,如果当前页面的编码不是GBK或是gb2312之类的编码
            $dir = mb_convert_encoding($dir, "gbk", 'utf-8');
        }
        clearstatcache();
        $dir = self::fix_path($dir);
        if (!file_exists($dir) || !is_dir($dir)) {
            $dir_array = explode('/', $dir);
            $tmp_dir = implode('/', $dir_array);
            foreach ($dir_array as $val) {
                $t_val = trim($val);
                $t_val = trim($t_val, '/');
                $mdir.=$t_val . '/';
                if ($t_val == '..' || $t_val == '.' || $t_val == '') {
                    continue;
                } else {
                    if (!file_exists($mdir) || !is_dir($mdir)) {
                        @mkdir(rtrim($mdir, '/'), $mode);
                        clearstatcache();
                        if (!file_exists($mdir)) {
                            return false;
                        }
                    }
                }
            }
            return rtrim($tmp_dir, '/') . '/';
        } else {
            return rtrim($dir, '/') . '/';
        }
    }

    /**
     * 判断当前浏览器是否支持
     * @return boolean
     */
    static public function isSupported() {
        $notSupportedBrowserList = ['msie 9.0', 'msie 8.0', 'msie 7.0', 'msie 6.0', 'msie 5.0'];
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        foreach ($notSupportedBrowserList as $v) {
            if (strpos($userAgent, $v) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * 在某些特定的场合下，将传递到数据库操作的kid语句，进行安全上的解析，防止注入
     * 例如：kid参数是'8A4AAB97-0D7A-B0FB-E0E9-BF2F70A03389','F05B9E4D-F922-923A-C354-053F4FEAD793'
     *  select * from table_name where kid in('8A4AAB97-0D7A-B0FB-E0E9-BF2F70A03389','F05B9E4D-F922-923A-C354-053F4FEAD793') 这个状态下系统是安全的
     * 但如果恶意传递的kid参数是 '8A4AAB97-0D7A-B0FB-E0E9-BF2F70A03389') or (1=1
     *  此时执行的SQL语句是： select * from table_name where kid in('8A4AAB97-0D7A-B0FB-E0E9-BF2F70A03389') or (1=1) 这个状态下就有风险了
     * @param type $str
     */
    static public function parseSafeKidString($str = '') {
        $str = str_ireplace(array(' ', '(', ')', '=', ':'), '', $str);
        return $str;
    }

    static public function parseUserListName($a_info, $expand_email = 1) {
        $debug = self::array_key_is_numbers($_GET, array('debug_mode', 'debugMode'), 0);
        if (empty($a_info['real_name'])) {
            $a_info['real_name'] = NULL;
        }
        if (empty($a_info['nick_name'])) {
            $a_info['nick_name'] = NULL;
        }
        if (empty($a_info['user_name'])) {
            $a_info['user_name'] = NULL;
        }
        if (empty($a_info['user_no'])) {
            $a_info['user_no'] = NULL;
        }
        if (empty($a_info['email'])) {
            $a_info['email'] = NULL;
        }
        $a_info['fix_name'] = $a_info['real_name'] ? $a_info['real_name'] : (
                $a_info['nick_name'] ? $a_info['nick_name'] : (
                        $a_info['user_name'] ? $a_info['user_name'] : $a_info['user_no']
                        )
                );

        if ($expand_email) {
            $email = $a_info['email'] ? $a_info['email'] : ($a_info['user_no'] ? $a_info['user_no'] : $a_info['user_name']);
        } else {
            $email = $a_info['user_no'] ? $a_info['user_no'] : $a_info['user_name'];
        }
        $a_info['name_text'] = $a_info['fix_name'] . '(' . $email . ')';

        if ($debug) {
            self::debug(__METHOD__);
            self::debug('expand_email:' . $expand_email);
            self::debug($a_info);
        }
        return $a_info;
    }

    /**
     * 对于不支持的浏览器进行跳转到浏览器下载页面
     * @return boolean
     */
    static public function jumpBrowserSupported() {
        $notSupportedBrowserList = array('msie 9.0', 'msie 8.0', 'msie 7.0', 'msie 6.0', 'msie 5.0');
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $current_controller = Yii::$app->controller->id;
        $current_action = Yii::$app->controller->action->id;
       
        if ($current_controller == 'boe/common' && $current_action == 'upgrade-browser') {
            return false;
        } else {
           // self::debug($userAgent, 1);
            foreach ($notSupportedBrowserList as $v) {
                if (strpos($userAgent, $v) !== false) {//浏览器不支持时s
                    $url = Yii::$app->urlManager->createUrl('boe/common/upgrade-browser');
                    header("location:{$url}");
                    exit("<script>top.location='{$url}'</script>");
                    return false;
                }//浏览器不支持时E
            }
        }
        return false;
    }
	
	/**
	 * 浏览器友好的变量输出
	 * @param mixed $var 变量
	 * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
	 * @param string $label 标签 默认为空
	 * @param boolean $strict 是否严谨 默认为true
	 * @return void|string
	 */
	function dump($var, $echo=true, $label=null, $strict=true) {
		$label = ($label === null) ? '' : rtrim($label) . ' ';
		if (!$strict) {
			if (ini_get('html_errors')) {
				$output = print_r($var, true);
				$output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
			} else {
				$output = $label . print_r($var, true);
			}
		} else {
			ob_start();
			var_dump($var);
			$output = ob_get_clean();
			if (!extension_loaded('xdebug')) {
				$output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
				$output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
			}
		}
		if ($echo) {
			echo($output);
			return null;
		}else
			return $output;
	}
	
	/**
	 * 数字判断输出
	 */
	function numJudge($num =0) {
		if(is_numeric($num)&&$num >=10000)
		{
			return round($num/10000,2)."万";
		}
		return $num;	
	}
	

}

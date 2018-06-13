<?php

namespace common\base;

use common\base\BoeBase;

/**
 * @author Zhenglk
 */
class BoeUpload {

    private $errorReturnMode = 1; //显示为1时表示出错时直接返回文本，0表示出错时返回错误值 
    private $removeFailTryCount = 10; //错误重试的次数
    private $imgExtArray = array("jpg", "jpge", 'jpe', 'jpeg', "png", "bmp", "gif", "swf");
    private $thumbImgExtArray = array("jpg", "jpge", 'jpe', 'jpeg', "png", "bmp", "gif");
    private $fileNameMode = 1; //保存文件的命名规则，默认了以当前时间 20141109110215,  1=以时间戳,  2=以之前的名称,3=以之前名称Md5后的值32位,4=以之前名称Md5后的值16位
    private $interlace = 1; //缩略图隔行扫描
    private $errorText = array(
        'no_file_info' => "上传信息未指定!",
        'no_upload_info' => "无法获取上传文件的分类信息。",
        'no_save_dir' => "没有可以保存的文件夹,指定的文件夹都不存在且无法创建或是没有写的权限。",
        'no_save' => "没有保存任何文件，原因待查。",
        'out_size' => "当前上传文件的尺寸{file_size}超出了允许最大的{max_size}的设定。",
        'allow_file_type' => "扩展名为({file_ext_name})的文件类型不是当前配置允许上传的文件类型!当前规定只允许上传扩展名是({ext_name})的文件。",
        'pic_width' => "当前上传图片的宽度({pic_width})超过了规定的最大宽度({max_width})。",
        'pic_height' => "当前上传图片的高度({pic_height})超过了规定的最大宽度({max_height})。",
        'form_error' => array(
            1 => "上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值。",
            2 => "上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值。",
            3 => "文件只有部分被上传。",
            4 => "没有文件被上传。",
            5 => "服务器临时文件夹丢失。",
            6 => "没有找到临时文件目录。",
            7 => "文件写入失败。",
        ),
    );
    private $errorNumber = array(
        'no_file_info' => -500,
        'no_upload_info' => -499,
        'no_save_dir' => -498,
        'no_save' => -497,
        'out_size' => -496,
        'allow_file_type' => -495,
        'pic_width' => -494,
        'pic_height' => -493,
        'form_error' => array(
            1 => -492,
            2 => -491,
            3 => -490,
            4 => -489,
            5 => -488,
            6 => -487,
            7 => -486,
        ),
    );
    private $imageSteamLoaded = array();
    public $debug = false;

    function __construct($file_name_mode = NULL, $interlace = NULL, $errorReturnMode = NULL, $errorText = NULL) {
        if ($file_name_mode !== NULL) {
            $this->fileNameMode = $file_name_mode;
        }
        if ($interlace !== NULL) {
            $this->interlace = $interlace;
        }
        if ($errorReturnMode !== NULL) {
            $this->errorReturnMode = $errorReturnMode;
        }
        if ($errorText !== NULL && is_array($errorText)) {
            $this->setErrorText($errorText);
        }
    }

    public function setErrorReturnMode($errorReturnMode) {
        $this->errorReturnMode = $errorReturnMode;
    }

    /**
     * 配置语言包
     * @param type $errorText
     */
    public function setErrorText($errorText) {
        if (is_array($errorText)) {
            foreach ($this->errorText as $key => $a_info) {
                if ($key !== 'form_error') {
                    if (isset($errorText[$key])) {
                        $this->errorText[$key] = $errorText[$key];
                    }
                }
            }
        }
        if (isset($errorText['form_error']) && is_array($errorText)) {
            foreach ($this->errorText['form_error'] as $key => $a_info) {
                if ($key !== 'form_error') {
                    if (isset($errorText['form_error'][$key])) {
                        $this->errorText['form_error'][$key] = $errorText['form_error'][$key];
                    }
                }
            }
        }
    }

    /**
     * 返回错误信息
     * @param type $error_key
     * @return type
     */
    private function getError($error_info) {
        if (is_array($error_info)) {
            $error_key = BoeBase::array_key_is_nulls($error_info, 'key');
            $error_num = $this->errorNumber[$error_key];
            $error_text = BoeBase::array_key_is_nulls($error_info, 'error_text', $this->errorText[$error_key]);
        } else {
            $error_num = $this->errorNumber[$error_info];
            $error_text = $this->errorText[$error_info];
        }
        switch ($this->errorReturnMode) {
            case 2://返回错误数值和错误文字
                return array(
                    'error_num' => $error_num,
                    'error_text' => $error_text,
                );
                break;
            case 0://返回错误数值
                return $error_num;
                break;
            default: case 1://返回错误文字
                return $error_text;
                break;
        }
    }

    /**
     * 将文件上传到服务器指定的文件夹中,成功后返回一个array(文件大小,文件宽度,文件高度,文件名称) 
     * @param type $save_info
     * @return type
     */
    function uploadFile($save_info = array()) {
        if (!is_array($save_info)) {
            return $this->getError('no_upload_info');
        }
        $direct_file_mode = BoeBase::array_key_is_numbers($save_info, 'direct_file_mode', 0);
        if (!$direct_file_mode) {//如果不是直接传递文件的方式时,从$_FILES获取文件信息
            $form_name = BoeBase::array_key_is_nulls($save_info, 'form_name', '');
            $upload_file = NULL;
            if ($form_name) {
                $upload_file = BoeBase::array_key_is_nulls($_FILES, $form_name, NULL);
            }
            if (!$upload_file) {
                return $this->getError('no_file_info');
            }
            if ($upload_file['error']) {
                return $this->getError(array('key' => 'form_error', 'error_text' => $upload_file['error']));
            }
        } else {
            $upload_file = BoeBase::array_key_is_nulls($save_info, 'file_path', '');
            $tmp_info = pathinfo($upload_file);
            $tmp_ext = BoeBase::array_key_is_nulls($tmp_info, array('extension', 'ext'), '');
            $tmp_type = BoeBase::array_key_is_nulls($tmp_info, array('mime_type', 'mime_type', 'type'), $tmp_ext);
            $upload_file = array(
                'name' => basename($upload_file, '.' . $tmp_ext),
                'type' => $tmp_type,
                'ext' => $tmp_ext,
                'tmp_name' => $upload_file,
                'size' => filesize($upload_file),
                'error' => '',
            );
            //	BoeBase::debug($upload_file,1);
        }
        $file_save_folder = BoeBase::array_key_is_nulls($save_info, 'save_folder', '');  //文件保存的目录
        $max_size = BoeBase::array_key_is_numbers($save_info, 'max_size') * 1024;  //文件的最大上传容量
        $max_width = BoeBase::array_key_is_numbers($save_info, 'pic_width');   //图片文件的最大的宽度
        $max_height = BoeBase::array_key_is_numbers($save_info, 'pic_height');   //图片文件的最大的高度
        $ext_name = BoeBase::array_key_is_nulls($save_info, 'allow_file_type', ''); //允许上传的文件扩展名
        $thumb_config = BoeBase::array_key_is_nulls($save_info, 'thumb_config');   //是否生成缩略图,该参数必须是个数组
        $sub_folder_mode = BoeBase::array_key_is_numbers($save_info, 'sub_folder_mode');   //子目录规则
        $sub_folder_level = BoeBase::array_key_is_numbers($save_info, 'sub_folder_level');   //子目录深度 
//==================================================对文件1系列的校验，包括字节、文件类型、图片宽度、高度开始=======================================================
        $file_name = $upload_file['name'];
        $file_type = $upload_file['type'];
        $file_tmp_name = $upload_file['tmp_name'];
        $file_size = $upload_file['size'];        //上传文件的大小
        /**
         * 对文件类型进行校验
         */
        $file_ext_name = strtolower(BoeBase::array_key_is_nulls($upload_file, 'ext', substr(strrchr($file_name, "."), 1))); //上传文件的扩展名
        if (!$this->checkFileExtension($file_ext_name, $ext_name)) {     //检测上传文件的类型是不是允许
            $error_text = str_ireplace(array('{file_ext_name}', '{ext_name}'), array($file_ext_name, $ext_name), $this->errorText['allow_file_type']);
            return $this->getError(array('key' => 'allow_file_type', 'error_text' => $error_text));
        }

        /**
         * 对文件字节进行校验
         */
        if ($file_size > $max_size && $max_size > 0) {
            $error_text = str_ireplace(array('{file_size}', '{max_size}'), array(BoeBase::format_size($file_size), BoeBase::format_size($max_size)), $this->errorText['out_size']);
            return $this->getError(array('key' => 'allow_file_type', 'error_text' => $error_text));
        }
        /**
         * 针对图片文件校验
         */
        if (in_array($file_ext_name, $this->imgExtArray)) {//如果上传的是图片文件时
            $tmp_pic_info = getimagesize($file_tmp_name);
            $tmp_pic_width = $tmp_pic_info[0];
            $tmp_pic_height = $tmp_pic_info[1];
            if ($max_width && $tmp_pic_width > $max_width) {
                $error_text = str_ireplace(array('{pic_width}', '{max_width}'), array($tmp_pic_width, $max_width), $this->errorText['pic_width']);
                return $this->getError(array('key' => 'pic_width', 'error_text' => $error_text));
            }
            if ($max_height && $tmp_pic_height > $max_height) {
                $error_text = str_ireplace(array('{pic_height}', '{max_height}'), array($tmp_pic_height, $max_height), $this->errorText['pic_height']);
                return $this->getError(array('key' => 'pic_height', 'error_text' => $error_text));
            }
            $sult['pic_width'] = $tmp_pic_width;
            $sult['pic_height'] = $tmp_pic_height;
            unset($tmp_pic_info, $tmp_pic_width, $tmp_pic_height);
            if (!in_array($file_ext_name, $this->thumbImgExtArray)) {//如果上传的图片格式是不能生成缩略图的
                $thumb_config = 0;
            }
        } else {
            $thumb_config = 0; //如果上传的不是图片文件,忽略生成缩略图的配置
        }
//==================================================对文件1系列的校验，包括字节、文件类型、图片宽度、高度结束=======================================================   
        $file_base_name = BoeBase::array_key_is_nulls($save_info, 'file_name', ''); //保存的文件名,如果未指定的，将会按一定的规则重新生成
        if (!$file_base_name) {
            $file_base_name = $this->createFileBaseName($upload_file['name']);
        }
        $sub_folder = $this->createSubFileName($sub_folder_mode, $sub_folder_level, $file_base_name);
        $file_full_path = BoeBase::fix_path($file_save_folder . '/' . $sub_folder . '/') . $file_base_name . '.' . $file_ext_name; //文件的绝对路径
        //判断文件是否已经存在
        $exits_retry_num = 0;
        while (file_exists($file_full_path) && $exits_retry_num < 10) {//文件已经存在了,重新生成文件名 while 开始
            $file_base_name = $this->createFileBaseName($file_base_name, 1); //重新生成文件的保存名称
            $sub_folder = $this->createSubFileName($sub_folder_mode, $sub_folder_level, $file_base_name); //重新生成子目录
            $file_full_path = BoeBase::fix_path($file_save_folder . '/' . $sub_folder . '/') . $file_base_name . '.' . $file_ext_name; //文件的绝对路径
            if (!file_exists($file_full_path)) {
                break;
            } else {
                $exits_retry_num++;
                usleep(200);
            }
        }//文件已经存在了,重新生成文件名 while 结束 
        $file_save_folder = dirname($file_full_path);

//===============================生成保存的多级文件夹=====================================================================================

        $file_save_folder = BoeBase::mkdir($file_save_folder);
        if (!$file_save_folder) {//如果没有可以保存的目录时 
            return $this->getError(array('key' => 'no_save_dir', 'error_text' => $this->errorText['no_save_dir'] . $file_save_folder));
        }
//=============================== 处理文件====================================================
        $tmp_is_moved = 0;
        $move_i=0;
        while (!$tmp_is_moved && $move_i < $this->removeFailTryCount) {
            if ($direct_file_mode) {//直接传递文件的方式
                $tmp_is_moved = @copy($file_tmp_name, $file_full_path);
            } else {
                if (@move_uploaded_file($file_tmp_name, $file_full_path)) {
                    $tmp_is_moved = 1;
                }
            }
            if($tmp_is_moved){
                break;
            }else{
                $move_i++;
                sleep(1);
            }
        }
        if (!$tmp_is_moved) {//移动文件失败
            return $this->getError('no_save');
        }
//=============================== 处理文件结果====================================================
        $sult = array();
        $sult['file_size'] = $file_size;
        $sult['pic_width'] = 0;
        $sult['pic_height'] = 0;
        $sult['old_file_name'] = $file_name;
        $sult['file_type'] = $file_type;
        $sult['file_name'] = $file_base_name . '.' . $file_ext_name;
        $sult['file_full_path'] = $file_full_path; //文件的绝对路径
        $sult['file_relative_path'] = BoeBase::fix_path($sub_folder . '/') . $sult['file_name']; //文件的相对路径
        $sult['file_save_folder'] = $file_save_folder; //文件的保存的目录
        $sult['file_ext'] = $file_ext_name;


        if (in_array($file_ext_name, $this->imgExtArray)) {//如果上传的图片格式,补充图片的长宽信息S
            $tmp_pic_info = getimagesize($file_full_path);
            if (!$sult['pic_width']) {
                $sult['pic_width'] = $tmp_pic_info[0];
            }
            if (!$sult['pic_height']) {
                $sult['pic_height'] = $tmp_pic_info[1];
            }
        }//如果上传的图片格式,补充图片的长宽信息E
        if ($this->debug) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug('最终的$file_full_path:' . $file_full_path);
            BoeBase::debug('最终的$file_save_folder:' . $file_save_folder);
            BoeBase::debug('最终的$sult:' . var_export($sult, true));
        }
        return $sult;
    }

    /**
     * 根据命名规则生成文件的基本名称
     * @param type $old_file_name
     * @return type
     */
    private function createFileBaseName($old_file_name, $addRandNum = 0) {
        switch ($this->fileNameMode) {
            case 1://以时间戳
                $tmp_file_name = time() + ($addRandNum ? mt_rand(100000, 1000000) : 0);
                break;
            case 2://以之前的名称
                $tmp_file_name = $old_file_name . ($addRandNum ? mt_rand(100000, 1000000) : '');
                break;
            case 3://以之前名称Md5后的值32位
                $tmp_file_name = md5($old_file_name . ($addRandNum ? mt_rand(100000, 1000000) : ''));
                break;
            case 4://以之前名称Md5后的值16位
                $tmp_file_name = BoeBase::md5_16($old_file_name . ($addRandNum ? mt_rand(100000, 1000000) : ''));
                break;
            default:
                $tmp_file_name = date('YmdHis') . ($addRandNum ? mt_rand(100000, 1000000) : '');
                break;
        }
        if ($this->debug) {
            Boebase::debug(__METHOD__ . '$this->fileNameMode:' . $this->fileNameMode . '---file_name:' . $tmp_file_name);
        }
        return $tmp_file_name;
    }

    /**
     * 根据命名规则生成子目录
     * @param type $sub_folder_mode
     * @param type $sub_folder_level
     * @param type $fileBaseName
     * @return type String
     */
    private function createSubFileName($sub_folder_mode = 0, $sub_folder_level = 0, $fileBaseName = '') {

        $sult = '';
        switch ($sub_folder_mode) {
            case 1://按年月日时分秒
                $date_tag = 'YmdHis';
                $date_tag_len = strlen($date_tag);
                $sub_folder_level = $sub_folder_level > $date_tag_len ? $date_tag_len : $sub_folder_level;
                for ($i = 0; $i < $sub_folder_level; $i++) {
                    $sult.=$date_tag[$i] . '/';
                }
                $sult = date($sult);
                break;
            case 2://根据文件名称Md5后的值取几位得到
                $sub_folder = md5($fileBaseName ? $fileBaseName : mt_rand(100000, 1000000));
                $sub_folder = $sub_folder_level ? substr($sub_folder, 0, $sub_folder_level) : $sub_folder;
                for ($i = 0; $i < strlen($sub_folder); $i++) {
                    $sult.=$sub_folder[$i] . '/';
                }
                break;
            default:
                break;
        }
        if ($this->debug) {
            BoeBase::debug(__METHOD__);
            BoeBase::debug('$sub_folder_mode:' . $sub_folder_mode);
            BoeBase::debug('$sub_folder_level:' . $sub_folder_level);
            BoeBase::debug('$fileBaseName:' . $fileBaseName);
            BoeBase::debug('$sult:' . $sult);
        }
        return $sult;
    }

    /**
     *  检测一个扩展名是不是符合条件的
     * @param type $current_ext
     * @param type $ext_str
     * @return boolean
     */
    private function checkFileExtension($current_ext = '', $ext_str = '*.*') {
        if ($ext_str == 'alls' || !isset($current_ext) || !isset($ext_str) || $ext_str == '*.*' || $ext_str == '*') {
            return true;
        } else {
            $ext_array = array();
            $current_ext = trim(strtolower($current_ext));
            if (is_string($ext_str)) {
                $ext_str = str_replace('*.', '', strtolower($ext_str));
                (strpos($ext_str, ';')) && $ext_array = explode(';', $ext_str);
                (strpos($ext_str, ',')) && $ext_array = explode(',', $ext_str);
            } else {
                (is_array($ext_str)) && $ext_array = $ext_str;
            }
            return in_array($current_ext, $ext_array);
        }
    }

    /**
     * 根据配置信息和文件信息生成缩略图
     * @param type $thumb_config
     * @param type $file_info array Or file_path
     * @param type $clear_im
     * @return type
     */
    public function createThumb($thumb_config = NULL, $file_info = NULL, $clear_im = 1) {
        if (!is_array($thumb_config)) {
            return -100;
        }
        if (!$thumb_config) {
            return -99;
        }
        if (!$file_info) {
            return -99;
        }
        if (is_array($file_info)) {//如果传递是个文件信息数组
            $file_save_folder = BoeBase::array_key_is_nulls($file_info, 'save_folder', ''); //保存路径 
            $file_ext_name = BoeBase::array_key_is_nulls($file_info, 'ext_name', ''); //扩展名
            $file_base_name = BoeBase::array_key_is_nulls($file_info, 'base_name', ''); //基本名称

            $file_pic_width = BoeBase::array_key_is_numbers($file_info, 'pic_width', 0);  //原图的宽度
            $file_pic_height = BoeBase::array_key_is_numbers($file_info, 'pic_height', 0); //原图的高度
        } else {//是个字符串 
            $tmp_info = pathinfo($file_info);
            $file_save_folder = BoeBase::fix_path(BoeBase::array_key_is_nulls($tmp_info, 'dirname', '')); //保存路径 
            $file_ext_name = BoeBase::array_key_is_nulls($tmp_info, 'extension', ''); //扩展名
            $file_base_name = basename(BoeBase::array_key_is_nulls($tmp_info, 'basename', ''), '.' . $file_ext_name); //基本名称
            $file_pic_width = 0; //原图的宽度
            $file_pic_height = 0; //原图的高度
        }

        if (!$file_save_folder) {
            return -97;
        }
        if (!$file_base_name) {
            return -96;
        }
        if (!$file_ext_name) {
            return -95;
        }
        if (!in_array($file_ext_name, $this->thumbImgExtArray)) {//如果上传的图片格式是不能生成缩略图的
            return -94;
        }
        $file_full_path = $file_save_folder . $file_base_name . '.' . $file_ext_name;
        if (!file_exists($file_full_path)) {//原文件不存在
            return -93;
        }

        if ($file_pic_width == 0 || $file_pic_height == 0) {//没有指定原图的宽度或是高度时，自动从文件中读取
            $tmp_pic_info = getimagesize($file_full_path);
            if (!$file_pic_width) {
                $file_pic_width = $tmp_pic_info[0];
            }
            if (!$file_pic_height) {
                $file_pic_height = $tmp_pic_info[1];
            }
        }
        $file_fulle_path_md5 = 'x_' . md5($file_full_path);
        $sult = array();
        $p = array(
            'image_type' => $file_ext_name,
        );
        foreach ($thumb_config as $a_config) {
            if (!is_array($a_config) || !$a_config) {
                continue;
            } else {
                $p['pic_width'] = BoeBase::array_key_is_numbers($a_config, 'width');  //生成缩略图的宽度
                $p['pic_height'] = BoeBase::array_key_is_numbers($a_config, 'height');  //生成缩略图的高度
                $p['save_folder'] = BoeBase::array_key_is_nulls($a_config, 'save_folder', $file_save_folder);
                $tmp_key = BoeBase::array_key_is_nulls($a_config, 'name_last', $p['pic_width'] . '_' . $p['pic_height']);
                $p['file_name'] = BoeBase::array_key_is_nulls($a_config, 'file_name', $file_base_name . $tmp_key); //缩略图的文件名称
                if (!$p['pic_width'] || !$p['pic_height']) {//未指定缩略图的大小
                    continue;
                } else {//指定缩略图的大小 Else start
                    if ($p['pic_width'] >= $file_pic_width && $p['pic_height'] >= $file_pic_height) {
                        /**
                          如果定义的缩略图片大小已经大于实际图片的大小了,此时就不需要用图片流的方式去生成缩略了,直接用原图就可以了
                         */
                        $sult[$tmp_key] = array(
                            'save_folder' => $file_save_folder,
                            'file_name' => $file_base_name . '.' . $file_ext_name,
                            'width' => $file_pic_width,
                            'height' => $file_pic_height,
                        );
                    } else {//以图片流的方式去生成缩略图
                        if (!isset($sult[$tmp_key])) {
                            if (!isset($this->imageSteamLoaded[$file_fulle_path_md5])) {
                                $this->imageSteamLoaded[$file_fulle_path_md5] = $this->createImSteam($file_full_path, $file_ext_name);
                            }
                            $sult[$tmp_key] = $this->createThumbFromImageSteam($p, $file_fulle_path_md5);
                        } else {
                            continue;
                        }
                    }
                }//指定缩略图的大小 Else end
            }
        }//For END
        if ($clear_im && isset($this->imageSteamLoaded[$file_fulle_path_md5])) {
            $this->imageSteamLoaded[$file_fulle_path_md5] = NULL;
        }
        return $sult;
    }

    /**
     *  生成图片的文件流
     * @param type $file_name
     * @param type $image_type
     * @return type
     */
    private function createImSteam($file_name, $image_type) {
        switch ($image_type) {
            case "gif":
                return (function_exists('imagecreatefromgif')) ? @imagecreatefromgif($file_name) : NULL;
                break;
            case "png":
                return (function_exists('imagecreatefrompng')) ? @imagecreatefrompng($file_name) : NULL;
                break;
            default:
                return (function_exists('imagecreatefromjpeg')) ? @imagecreatefromjpeg($file_name) : NULL;
                break;
        }
        return NULL;
    }

    /**
     *  根据图片流,生成缩略图
     * @param type $p
     * @param type $steam_key
     * @return int
     */
    private function createThumbFromImageSteam($p = NULL, $steam_key = '') {
        if (!is_array($p)) {
            return -1;
        }
        if (!isset($this->imageSteamLoaded[$steam_key]) || !$this->imageSteamLoaded[$steam_key]) {
            return -2;
        } else {
            $im = &$this->imageSteamLoaded[$steam_key];
        }
        $new_im = NULL;
        $thumb_width = BoeBase::array_key_is_nulls($p, 'pic_width', 0);
        $thumb_height = BoeBase::array_key_is_nulls($p, 'pic_height', 0);
        $save_path = BoeBase::array_key_is_nulls($p, 'save_folder', '');
        $image_type = strtolower(BoeBase::array_key_is_nulls($p, 'image_type', 'jpg'));
        $save_name = BoeBase::array_key_is_nulls($p, 'file_name', date('YmdHis'));
        $save_path = BoeBase::fix_path($save_path);
        $save_path_sult = BoeBase::mkdir($save_path); //生成文件夹目录
        $function_array = array('gif' => 'imagegif', 'png' => 'imagepng', 'jpg' => 'imagejpeg', 'jpge' => 'imagejpeg', 'jpe' => 'imagejpeg', 'jpeg' => 'imagejpeg');
        if (!$save_path_sult) {
            return -3; //生成保存缩略图的文件夹失败
        }
        if (!function_exists($function_array[$image_type])) {
            return -4; //没有安装函数库
        }
        $sult_file = $save_path . $save_name . '.' . $image_type;
        $f_exists_check = 0;
        while (file_exists($sult_file) && $f_exists_check < 10) {//检查下目标文件是否存在,如果存在直接删除
            @unlink($sult_file);
            clearstatcache();
            if (!file_exists($sult_file)) {
                break;
            } else {
                $f_exists_check++;
                sleep(1);
            }
        }

        if (file_exists($sult_file)) {//目标文件是否存在
            return -5; //出现这个值的表明文件系统就要挂了
        }

        $source_pic_width = imagesx($im);
        $source_pic_height = imagesy($im);
        $new_thumb_width = $source_pic_width;
        $new_thumb_height = $source_pic_height;
        $resize_width = $resize_height = false;

        if (($thumb_width && $source_pic_width > $thumb_width) || ($thumb_height && $source_pic_height > $thumb_height)) {//有必要生成缩略图时
            if ($thumb_width && $source_pic_width > $thumb_width) {
                $source_pic_width_ratio = $thumb_width / $source_pic_width;
                $resize_width = true;
            }
            if ($thumb_height && $source_pic_height > $thumb_height) {
                $source_pic_height_ratio = $thumb_height / $source_pic_height;
                $resize_height = true;
            }
            if ($resize_width && $resize_height) {
                $ratio = ($source_pic_width_ratio < $source_pic_height_ratio) ? $source_pic_width_ratio : $source_pic_height_ratio;
            } elseif ($resize_width) {
                $ratio = $source_pic_width_ratio;
            } elseif ($resize_height) {
                $ratio = $source_pic_height_ratio;
            }
            $new_thumb_width = $source_pic_width * $ratio;
            $new_thumb_height = $source_pic_height * $ratio;

            if ($image_type == 'png') {
                imagesavealpha($im, true);
            }
            if (function_exists('imagecreatetruecolor')) {
                $new_im = imagecreatetruecolor($new_thumb_width, $new_thumb_height);
            } else {
                $new_im = imagecreate($new_thumb_width, $new_thumb_width);
            }
            switch ($image_type) {
                case 'gif':
                    $background_color = imagecolorallocate($new_im, 255, 255, 255);  //  指派一个绿色
                    imagecolortransparent($new_im, $background_color); // 设置为透明色，若注释掉该行则输出黑色背景的图
                    break;
                case 'png':
                    imagealphablending($new_im, false);
                    imagesavealpha($new_im, true);
                    break;
                default:
                    imageinterlace($new_im, $this->interlace);
                    break;
            }

            if (function_exists('imagecopyresampled')) {
                imagecopyresampled($new_im, $im, 0, 0, 0, 0, $new_thumb_width, $new_thumb_height, $source_pic_width, $source_pic_height);
            } else {
                imagecopyresized($new_im, $im, 0, 0, 0, 0, $new_thumb_width, $new_thumb_height, $source_pic_width, $source_pic_height);
            }

            if (($image_type == 'jpg') || ($image_type == 'jpeg')) {
                call_user_func_array($function_array[$image_type], array($new_im, $sult_file, 100));
            } else {
                call_user_func_array($function_array[$image_type], array($new_im, $sult_file));
            }
            imagedestroy($new_im);
            $new_im = NULL;
            return array(
                'save_folder' => $save_path,
                'file_name' => $save_name . '.' . $image_type,
                'width' => $new_thumb_width,
                'height' => $new_thumb_height,
            );
        }
        return 1;
    }

//根据图片流,生成缩略图  结束 
}

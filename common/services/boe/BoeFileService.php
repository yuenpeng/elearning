<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\models\learning\LnFiles;
use common\models\learning\LnCourseware;
use common\base\BoeDocFile;
use common\base\BoeBase;
use Yii;

/**
 * H3C视频转换
 * @author xinpeng
 */
class BoeFileService {
	
	static $dir			= "/home/www/elearning";
	
	/*
	 * 获取课件视频信息的当前状态
	 */
	static function getCourseVideoStatus($file_id = NULL)
	{
		if(!$file_id)
		{
			return NULL;
		}
		$component_id	= array('00000000-0000-0000-0000-000000000003','00000000-0000-0000-0000-000000000009');
		$f_where  		= array(
			'and',
			array('=', 'is_deleted', '0'),
			array('=', 'status', '1'),
			array('=', 'kid', $file_id)
		);
		$file_db_obj 	= new LnFiles();
		$file_info 		= $file_db_obj->find(false)->where($f_where)->asArray()->one();
		if(!$file_info)
		{
			return NULL;
		}
		$cloud_status	= array();
		if(in_array($file_info['component_id'],$component_id))
		{
			$status_array	= Yii::t('boe', 'course_video_status');
			$cloud_status	= $status_array[$file_info['cloud_status']];
			$cloud_status['cloud_status'] = $file_info['cloud_status'];
			return $cloud_status;
		}
		return NULL;	
	}
	
	/*
	 * 保存视频文件到金山云并转换
	*/
	static function saveToKs3($id = NULL){
		if(!$id)
		{
			return -100;
		}
		$file_db_obj 	= new LnFiles();
		$f_where		= array('kid'=>$id,'is_deleted'=>'0');
        $file_info 		= $file_db_obj->find(false)->where($f_where)->asArray()->one();
		if (!$file_info) {//文件信息不存在时
            return array('error' => "-99");
        }
		$kid 				= BoeBase::array_key_is_nulls($file_info, 'kid');
        $file_ext 			= BoeBase::array_key_is_nulls($file_info, 'file_extension'); //文件的扩展名
        $file_save_path 	= BoeBase::array_key_is_nulls($file_info, 'file_path');//文件的相对路径
		$file_path			= self::$dir.$file_save_path;
        $file_key 			= $file_save_path; //文件的相对路径
        $bucket 			= BoeDocFile::getFileClassConfig($file_ext, 'ks3_bucket'); //存放在金山云的空间名称
		$model  			= LnFiles::findOne(array('kid'=>$kid),false);
		if(!file_exists($file_path))
		{
			//本地文件不存在
			$model->cloud_status 		= 4;
			$model->cloud_time 			= time();
			$model->update();
			//$file_db_obj->updateAll(array('cloud_status'=>'4','cloud_time'=>time()), " kid = '{$kid}'");
			return array('error' => "-94");
		}
		$file_size		= file_exists($file_path)?filesize($file_path):-1;
		if($file_size<=0)
		{
			//本地文件存在但是文件大小为零
			$model->cloud_status 		= 5;
			$model->cloud_time 			= time();
			$model->update();
			//$file_db_obj->updateAll(array('cloud_status'=>'5','cloud_time'=>time()), " kid = '{$kid}'");
			return array('error' => "-93");
		}
		$file_info['file_exist']	= file_exists($file_path)?1:0;
		$file_info['file_url'] 		= self::getFileKs3Url($kid); //获取文件的访问URL
		//return $file_info;
		$cloud_status = 0;
        if (!$debug) {
            $cloud_status 	= BoeBase::array_key_is_nulls($file_info, 'cloud_status'); //上传到云空间的状态0=未上传,1=正在上传,2=上传失败,3=上传成功,4=文件不存在
        }
		$need_upload = false;
		//boeBase::dump($file_info);
		//return $file_info;
        switch ($cloud_status) {
            case 0://未上传
                $need_upload = true;
                break;
            case 1://正在上传，
                $need_upload = true;
                break;
            case 2://上传失败
                $need_upload = true;
                break;
            case 3://上传成功 
                exit(json_encode(array('error' => 0)));
                break;
			case 4://未上传
                $need_upload = true;
                break;
        }
        if ($need_upload) {//可以进行上传时S 
			//将文件的上传状态设定为正在上传
			$file_db_obj->updateAll(array('cloud_status'=>'1','cloud_time'=>time()), " kid = '{$kid}'");
            $upload_sult = BoeDocFile::putFileToKs3($file_path, $bucket, $file_key, $debug);
			//return $upload_sult;
            if (!is_array($upload_sult)) {//上传出错了S
                switch ($upload_sult) {
                    case -98://文件上传失败
                       $model->cloud_status 		= 2;
					   $model->cloud_time 			= time();
					   $model->update();
					 //$file_db_obj->updateAll(array('cloud_status'=>'2','cloud_time'=>time()), " kid = '{$kid}'");
                       break;
                }
            } else {
                $task_id 	= BoeBase::array_key_is_nulls($upload_sult, 'TaskID');
				$cloud_adp 	= json_encode(array('task_id' => $task_id));
				$model->cloud_status 		= 3;
				$model->cloud_adp 			= $cloud_adp;
				$model->cloud_time 			= time();
				$model->update();
                //$file_db_obj->updateAll(array('cloud_status'=>'3','cloud_adp'=>$cloud_adp,'cloud_time'=>time()), " kid = '{$kid}'");
            }//转换出错了E
			$upload_sult['kid']	= $kid;	
            return array('error' => 0, 'upload_sult' => $upload_sult);
			//exit(json_encode(array('error' => 0, 'upload_sult' => $upload_sult)));
        }//可以进行转换时E
        return array('error' => "-97");
		//exit(json_encode(array('error' => "-97")));
	}
    
	
	
	/**
     * 获取文件的访问的URL地址(视频)
     * @param type $key
     * @param type $source_mode 是否获取源文件信息
     * @return type
     */
    static function getFileKs3Url($id = NULL) {
        if (!$id) {
            return -100;
        }
        $file_db_obj 	= new LnFiles();
		$f_where		= array('kid'=>$id,'is_deleted'=>'0');
        $file_info 		= $file_db_obj->find(false)->where($f_where)->asArray()->one();
        if (!$file_info) {//文件信息不存在时
            return -99;
        }
		$kid 			= BoeBase::array_key_is_nulls($file_info, 'kid');
        $file_save_path = BoeBase::array_key_is_nulls($file_info, 'file_path');
		$file_path		= self::$dir.$file_save_path;
        $cloud_status   = BoeBase::array_key_is_nulls($file_info, 'cloud_status'); //上传到云空间的状态0=未上传,1=正在上传,2=上传失败,3=上传成功
        switch ($cloud_status) {//Switch Start
            case 0: case 1: case 2: //未上传或正在上传的或上传失败的时候
                return -98;
                break;
            case 3://上传成功 S
                $file_ext 	= BoeBase::array_key_is_nulls($file_info, 'file_extension');
                $file_key 	= $file_save_path;
                $bucket 	= BoeDocFile::getFileClassConfig($file_ext, 'ks3_bucket');
                $last_file_type = '';
                $get_adp = false;
				if (BoeDocFile::checkFileCanConvertMp4($file_ext)) {
					$last_file_type = 'mp4';
					$get_adp = true; //需要获取转码状态
				}
                if ($get_adp) {//对于那些个需要获取转换后的文件代码S
                    $cloud_adp = BoeBase::array_key_is_nulls($file_info, 'cloud_adp');
                    if ($cloud_adp) {
                        $cloud_adp = @json_decode($cloud_adp, true);
                    }
                    if (!$cloud_adp || !is_array($cloud_adp)) {
                        return -97;
                    }
                    $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
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
                return $file_url ? $file_url : -92;
                break; //上传成功 E
        }//Switch End
    }
	
	/**
     * 下载转换好的文件(视频)
     * @param type $key
     * @return type
     */
    static function putKs3FileToLocal($id = NULL) {
        if (!$id) {
            return -100;
        }
        $file_db_obj 	= new LnFiles();
		$f_where		= array('kid'=>$id,'is_deleted'=>'0');
        $file_info 		= $file_db_obj->find(false)->where($f_where)->asArray()->one();
        if (!$file_info) {//文件信息不存在时
            return -99;
        }
		$kid 			= BoeBase::array_key_is_nulls($file_info, 'kid');
        $file_save_path = BoeBase::array_key_is_nulls($file_info, 'file_path');
		$file_extension = BoeBase::array_key_is_nulls($file_info, 'file_extension');
		$file_dir 		= BoeBase::array_key_is_nulls($file_info, 'file_dir');
		$file_path		= self::$dir.$file_save_path;
        $cloud_status   = BoeBase::array_key_is_nulls($file_info, 'cloud_status'); //上传到云空间的状态0=未上传,1=正在上传,2=上传失败,3=上传成功
        switch ($cloud_status) {//Switch Start
            case 0: case 1: case 2: //未上传或正在上传的或上传失败的时候
                return -98;
                break;
            case 3://上传成功 S
                $file_ext 	= BoeBase::array_key_is_nulls($file_info, 'file_extension');
                $file_key 	= $file_save_path;
                $bucket 	= BoeDocFile::getFileClassConfig($file_ext, 'ks3_bucket');
                $last_file_type = '';
                $get_adp = false;
				if (BoeDocFile::checkFileCanConvertMp4($file_ext)) {
					$last_file_type = 'mp4';
					$get_adp = true; //需要获取转码状态
				}
                if ($get_adp) {//对于那些个需要获取转换后的文件代码S
                    $cloud_adp = BoeBase::array_key_is_nulls($file_info, 'cloud_adp');
                    if ($cloud_adp) {
                        $cloud_adp = @json_decode($cloud_adp, true);
                    }
                    if (!$cloud_adp || !is_array($cloud_adp)) {
                        //return -97;
                    }
                    $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                }//对于那些个需要获取转换后的文件代码E
                else {
                    if ($last_file_type) {
                        $file_key = str_replace(".{$file_ext}", ".{$last_file_type}", $file_key);
                    }
                }
				if($file_extension <> 'mp4')
				{
					$file_extension2	= ".".$file_extension;
					if(!strpos($file_dir,'video'))
					{
						//$end_dir	= end(array_filter(explode('/',$file_dir)));
						//$file_dir	= '/upload/video/originfile/'.$end_dir.'/';
						$file_dir	= str_replace('/upload/originfile/',"/upload/video/originfile/",$file_dir);
						if(!is_dir(self::$dir.$file_dir))
						{
							mkdir(self::$dir.$file_dir,0777,true);
						}
						$file_save_path	= str_replace('/upload/originfile/',"/upload/video/originfile/",$file_save_path);
					}
					$file_save_path		= str_replace($file_extension2,".mp4",$file_save_path);
					$file_path			= self::$dir.$file_save_path;
					//str_replace("world","Shanghai","Hello world!");
				}
				//return self::$dir.$file_dir;
                $sult = BoeDocFile::putKs3FileToLocal($bucket, $file_key, $file_path);
                if (!$sult) {
                    return -91;
                }
				if($file_extension <> 'mp4')
				{
					$model  = LnFiles::findOne(array('kid'=>$kid),false);
					$model->cloud_status 		= 6;
					$model->file_path 			= $file_save_path;
					$model->file_dir 			= $file_dir;
					$model->cloud_time 			= time();
					$model->mime_type 			= 'video/mp4';
					$model->file_extension 		= 'mp4';
					//boeBase::dump($model);
					$ss = $model->update();
					//$file_db_obj->updateAll(array('cloud_status'=>'6','file_path'=>$file_save_path,'file_dir'=>$file_dir,'mime_type'=>'video/mp4','file_extension'=>'mp4','cloud_time'=>time()), " kid = '{$kid}'");	
				}else{
					$model  = LnFiles::findOne(array('kid'=>$kid),false);
					$model->cloud_status 		= 6;
					$model->cloud_time 			= time();
					//boeBase::dump($model);
					$model->update();
					//$file_db_obj->updateAll(array('cloud_status'=>'6','cloud_time'=>time()), " kid = '{$kid}'");
				}
                return $sult;
                break; 
        }//Switch End
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
			case -94:
                return Yii::t('boe', 'file_not_exist');
                break;
			case -93:
                return Yii::t('boe', 'file_no_size');
                break;
			case -92:
                return Yii::t('boe', 'file_no_url');
                break;
            default:
                return 'Unknow Error:' . $error_code;
                break;
        }
    }

}

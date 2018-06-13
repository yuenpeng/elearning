<?php
namespace common\helpers\boe;

use backend\services\CompanyService;
use common\models\framework\FwDictionary;
use common\models\framework\FwUser;
use common\models\learning\LnComponent;
use common\models\learning\LnCourse;
use common\models\learning\LnFiles;
use common\models\learning\LnHomeworkFile;
use common\services\framework\DictionaryService;
use components\widgets\TFlowplayer;
use components\widgets\TH5player;
use common\services\learning\ComponentService;
use common\services\learning\FileService;
use Exception;
use stdClass;
use Yii;
use yii\helpers\Html;
use common\services\framework\ExternalSystemService;


class TFileModelHelper{

    public $ExtractPath = '/upload/filedir/';//文件上传相对路径，解压后目录
    public $TempPath = '/upload/temp/';//文件上传相对路径，临时文件目录
    public $OriginPath = '/upload/originfile/';//文件上传相对路径，原文件目录
    public $BackupFilePath = '/upload/backupfile/';//文件上传相对路径，备份文件目录
    public $HomeworkPath = '/upload/homework/';//作业文件上传相对路径，作业文件目录
    public $HomeworkExtractPath = '/upload/homework/filedir/';//作业文件上传相对路径，作业文件目录
    public $ScormOriginPath = '/upload/scorm/originfile/';//scorm文件上传相对路径，原文件目录
    public $ScormBackupFilePath = '/upload/scorm/backupfile/';//scorm文件上传相对路径，备份文件目录
    public $ScormExtractPath = '/upload/scorm/filedir/';//scorm解压后目录
    public $AiccOriginPath = '/upload/aicc/originfile/';//aicc文件上传相对路径，原文件目录
    public $AiccBackupFilePath = '/upload/aicc/backupfile/';//aicc文件上传相对路径，备份文件目录
    public $AiccExtractPath = '/upload/aicc/filedir/';//scorm解压后目录
    public $AudioOriginPath = '/upload/audio/originfile/';//audio文件上传相对路径，原文件目录
    public $AudioBackupFilePath = '/upload/audio/backupfile/';//audio文件上传相对路径，备份文件目录
    public $VideoOriginPath = '/upload/video/originfile/';//video文件上传相对路径，原文件目录
    public $VideoBackupFilePath = '/upload/video/backupfile/';//video文件上传相对路径，备份文件目录

    public $ExtractPhysicalPath = '@upload/filedir/';//文件上传绝对路径，解压后目录
    public $OriginPhysicalPath = '@upload/originfile/';//文件上传绝对路径，原文件目录
    public $BackupFilePhysicalPath = '@upload/backupfile/';//文件上传绝对路径，备份文件目录
    public $HomeworkPhysicalPath = '@upload/homework/';//作业文件上传绝对路径，作业文件目录
    public $HomeworkExtractAbsolutePath = '@upload/homework/filedir/';//作业文件上传绝对路径，作业文件目录
    public $ExaminationQuestionPhysicalPath = '@upload/examination-question/';//作业文件上传绝对路径，作业文件目录
    public $AudiencePhysicalPath = '@upload/audience/';//作业文件上传绝对路径，作业文件目录
    public $ScormExtractAbsolutePath = '@upload/scorm/filedir/';//scorm文件上传绝对路径，原文件目录
    public $ScormOriginPhysicalPath = '@upload/scorm/originfile/';//scorm文件上传绝对路径，原文件目录
    public $ScormBackupFilePhysicalPath = '@upload/scorm/backupfile/';//scorm文件上传绝对路径，备份文件目录
    public $AiccExtractAbsolutePath = '@upload/aicc/filedir/';//aicc文件上传绝对路径，原文件目录
    public $AiccOriginPhysicalPath = '@upload/aicc/originfile/';//aicc文件上传绝对路径，原文件目录
    public $AiccBackupFilePhysicalPath = '@upload/aicc/backupfile/';//aicc文件上传绝对路径，备份文件目录
    public $AudioOriginPhysicalPath = '@upload/audio/originfile/';//audio文件上传绝对路径，原文件目录
    public $AudioBackupFilePhysicalPath = '@upload/audio/backupfile/';//audio文件上传绝对路径，备份文件目录
    public $VideoOriginPhysicalPath = '@upload/video/originfile/';//video文件上传绝对路径，原文件目录
    public $VideoBackupFilePhysicalPath = '@upload/video/backupfile/';//video文件上传绝对路径，备份文件目录

    public $mediatype = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'PlayOffice',
        'application/msword'=>'PlayOffice',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'PlayOffice',
        'application/vnd.ms-excel'=>'PlayOffice',
        'application/x-excel'=>'PlayOffice',
        'application/vnd.ms-powerpoint'=>'PlayOffice',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'PlayOffice',
        'application/x-shockwave-flash'=>'PlayVideo',
        'video/mp4'=>'PlayVideo',
        'video/x-flv'=>'PlayVideo',
        'video/avi'=>'PlayVideo',
        'video/x-ms-wmv'=>'PlayVideo',
        'audio/mp3'=>'PlayAudio',
        'audio/mpeg'=>'PlayAudio',
        'application/pdf'=>'PlayPdf',
        'application/octet-stream' => 'PlayScorm',
        'application/x-zip-compressed' => 'PlayScorm',
    ];


       /**
     * 压缩下载文件
     * @param $files
     * @param bool $download
     */
    public function  HomeworkCompressDownLoad($files,$filename){
        $zip = new \ZipArchive(); 
        $ziped_ok = 1; //zip压缩完成
        $downloadFileName =$_SERVER['DOCUMENT_ROOT'].'/upload/homework/'.$filename.'.zip';
        if($zip->open($downloadFileName,\ZipArchive::CREATE)===TRUE&&!empty($files)){
            foreach ($files as $user_no => $files_array) {
                foreach ($files_array as $key => $file) {
                    $fullPath = $_SERVER['DOCUMENT_ROOT'].$file;
                    if(file_exists($fullPath)){
                        $localname = $user_no.'_'.$filename.($key+1).'.'.substr(strrchr($fullPath, '.'), 1);
                        $result = $zip->addFile($fullPath,$localname);   
                    }else{
                        $ziped_ok = 0;
                        continue;
                    }
                }
            }
            if($ziped_ok){
                $zip->close();
                // //清空（擦除）缓冲区并关闭输出缓冲  
                ob_end_clean();  
                //下载建好的.zip压缩包  
                header("Content-Type: application/force-download");//告诉浏览器强制下载  
                header("Content-Transfer-Encoding: binary");//声明一个下载的文件  
                header('Content-Type: application/zip');//设置文件内容类型为zip  
                header('Content-Disposition: attachment; filename='.$filename.'.zip');//声明文件名  
                header('Content-Length: '.filesize($downloadFileName));//声明文件大小  
                error_reporting(0);  
                //将欲下载的zip文件写入到输出缓冲  
                readfile($downloadFileName);  
                //将缓冲区的内容立即发送到浏览器，输出  
                flush(); 
                unlink($downloadFileName); 
                exit; 
            }
        }else{
            exit();
        }
    }
}
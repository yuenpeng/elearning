<?php
/**
 * Created by PhpStorm.
 * User: Alex Liu
 * Date: 2016/9/19
 * Time: 12:07
 */

namespace common\helpers;


use common\crpty\AES;
use common\crpty\CryptErrorCode;
use Yii;

class TEncryptHelper
{
    /**
     * session绑定签名加密
     * @param $sessionId
     * @param $exp
     * @return bool|string
     */
    public static function BindSignEncrypt($sessionId, $exp)
    {
        if (empty($sessionId)) {
            return false;
        }

        $secretKey = 'session_secret_key';
        $sign = $exp . '|' . $sessionId;
        $aes = new AES();
        $aes->setSecretKey($secretKey);
        $result = $aes->encrypt($sign);
        if ($result[0] === CryptErrorCode::OK) {
            return $result[1];
        } else {
            return false;
        }
    }

    /**
     * 读取文件参数加密
     * @param $sessionId
     * @param $filePath
     * @return bool
     */
    public static function ReadFileParamEncrypt($sessionId, $filePath)
    {
        if (empty($sessionId) || empty($filePath)) {
            return false;
        }

        $secretKey = 'session_secret_key';
        $str = $sessionId . '|' . $filePath;
        $aes = new AES();
        $aes->setSecretKey($secretKey);
        $result = $aes->encrypt($str);
        if ($result[0] === CryptErrorCode::OK) {
            return $result[1];
        } else {
            return false;
        }
    }

    /**
     * 参数加密
     * @param string $param
     * @return bool
     */
    public static function ParamEncrypt($param)
    {
        if (empty($param)) {
            return false;
        }

        $secretKey = 'param_secret_key';
        $aes = new AES();
        $aes->setSecretKey($secretKey);
        $result = $aes->encrypt($param);
        if ($result[0] === CryptErrorCode::OK) {
            return $result[1];
        } else {
            return false;
        }
    }

}
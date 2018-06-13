<?php
namespace common\services\boe;
use common\base\BoeBase;
use common\services\boe\BoeBaseService;
use Yii;
use yii\db\Query;
use common\models\boe\BoeAcountRelated;
use common\models\framework\FwUser;
use common\helpers\TBaseHelper;
use common\services\framework\DictionaryService;
use common\base\BaseActiveRecord;


/**
 * Desc: 多账号关联
 * User: songsang
 * Date: 25/85/18
 */
 
class BoeAcountRelatedService {

    private static $cacheTime = 60; //缓存12小时
    private static $currentLog = array();
    private static $cacheNameFix = 'boe_';

      /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isNoCacheMode() {
        return Yii::$app->request->get('no_cache') == 1 ? true : false;
    }

    /**
     * isNoCacheMode当前是否处于重建缓存的状态
     * @return type
     */
    private static function isDebugMode() {
        return Yii::$app->request->get('debug_mode') == 1 ? true : false;
    }

     /**
     * 读取缓存的封装
     * @param type $cache_name
     * @param type $debug
     * @return type
     */
    private static function getCache($cache_name) {
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
    private static function setCache($cache_name, $data = NULL) {
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        Yii::$app->cache->set($new_cache_name, $data, self::$cacheTime); // 设置缓存
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }


    public static function accountRelatedUrl($acount_default){
        if($acount_default==1){//工号
            $url = TBaseHelper::getHomePage();
        }elseif($acount_default==2){//身份证  
            $user_infos = Yii::$app->user->getIdentity();
            $service = new DictionaryService();
            $dictionary = $service->getDictionariesByCategory('txy-domain');
            foreach ($dictionary as $value) {
                if($user_infos->domain_id==$value->dictionary_value){
                    $url = $value->description;
                    break;
                }
            }
        }elseif($acount_default==3){
            $url = TBaseHelper::getHomePage();
        }elseif($acount_default==4){
            $url = TBaseHelper::getHomePage();
        }
        return $url;
    }

    public function accountRelatedInfo($user_no){
        if($user_no){
            $account_related = BoeAcountRelated::find(false)->where(array('user_no'=>$user_no,'is_deleted'=>'0'))->asArray()->one();
            return $account_related;
        }
    }

    public function accountOtherIdNumber(){
        $result   = array();
        $user_infos = Yii::$app->user->getIdentity();
        if(!$user_infos->id_number){//不存在身份证异常情况
            return $result;
        }
        //获取缓存数据
        $cache_name  = 'account_other_id_number_'.$user_infos->id_number;
        $result = self::getCache($cache_name);
        if(!$result){  
            $userByIdNumber = FwUser::find(false)->select('kid,domain_id')->where(array('user_name'=>$user_infos->id_number,'is_deleted'=>'0'))->asArray()->one();  
            $service = new DictionaryService();
            $dictionary = $service->getDictionariesByCategory('txy-domain');
            $result = array();
            foreach ($dictionary as $value) {
                if($userByIdNumber['domain_id']==$value->dictionary_value){
                    $url = '/';
                    $url  .= strpos($value->description,'html')?$value->description:$value->description.'.html';
                    $result = array('acount_type'=>2,'dictionary_name'=>$value->dictionary_name,'description'=>$url);
                    break;
                }
            }
            self::setCache($cache_name, $result);
        }
        return $result;
    }
    
    public function UpdateAcountRelated($user_infos,$acount_default){
        $code = 0;
        if($user_infos&&$acount_default){
            $data['user_no'] = $user_infos->user_no;
            $data['acount_default'] = $acount_default;
            $data['id_number'] = '';
            $data['mobile_no'] = '';
            $data['email'] = '';
            if($acount_default==2){
                $data['id_number'] = $user_infos->id_number;
            }elseif($acount_default==3){
                $data['mobile_no'] = $user_infos->mobile_no;
            }elseif($acount_default==4){
                $data['email'] = $user_infos->email;
            }
            $boeAcountRelated = new BoeAcountRelated();
            $info = $boeAcountRelated->find(false)->select('kid,acount_default')->where(array('user_no'=>$user_infos->user_no,'is_deleted'=>'0'))->asArray()->one();  
            if($info['kid']){
                $data['kid'] = $info['kid'];
            }
            $result = $boeAcountRelated->saveInfo($data);
            if(!empty($result)){//操作成功
                $code = 1;
            }
        }
        return $code;
    }
}
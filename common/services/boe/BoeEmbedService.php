<?php
namespace common\services\boe;
use common\base\BoeBase;
use common\services\boe\BoeBaseService;
use yii\db\Expression;
use yii\db\Query;
use Yii;
use common\models\boe\BoeEmbed;
use common\models\boe\BoeEmbedCourse;
use common\models\boe\BoeEmbedOption;
use common\models\boe\BoeEmbedUserResult;
use common\models\framework\FwUser;
use common\models\framework\FwOrgnization;
use common\services\boe\BoeEnrollService;
use common\services\boe\FlowService;
/**
 * Desc: 前置任务相关服务，采用YII2原生+缓存
 * Frame: 1.配置参数基础操作 2.数据层业务模块 3.
 * User: songsang
 * Date: 2016/6/21
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeEmbedService {
    private static $cacheTime = 43200; //缓存12小时
    private static $currentLog = array();
    private static $cacheNameFix = 'boe_';
    private static $tableConfig = array(//
        'FwEmbedWork' => array(
            'real_table'=>'eln_boe_embed_work',
            'order_by' => 'create_at asc',
            'primary_key' => 'kid',
            'parent_key_name' => '',
            'field' => 'kid,title,configure'
        )
    );


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


    static function getEmberCourseNums($kid){
        $base_where = array('and',array('=','is_deleted',0));
        if($kid){
            $base_where[] = array('=','embed_id',$kid);
        }
        $sult = BoeEmbedCourse::find()->where($base_where)->count();
        return $sult;
    }

    static function deleteEmbed($kid){
        if($kid){
            $boeEmbed = new BoeEmbed();
            $sult = $boeEmbed->deleteInfo($kid);
        }
        return $sult;
    }

    static function getBoeEmbedOption(){
        $log_key_name = __METHOD__ . '' . 'embed';
        if (!isset(self::$currentLog[$log_key_name])){
            $boeEmbedOption = new BoeEmbedOption();
            $data = $boeEmbedOption->getAll();
            $sult = array();
            if(!empty($data)){
                foreach ($data as $key => $value) {
                    $sult[$key] = array(
                        'kid'=>$value['kid'],
                        'option_name'=>$value['option_name'],
                        'is_read'=>$value['is_read'],
                        'is_edit'=>$value['is_edit'],
                        'is_required'=>$value['is_required']
                    );
                }
            }
            self::$currentLog[$log_key_name] = $sult;
        }
        return self::$currentLog[$log_key_name];
    }
    static function getPayrollPlaceTxt($user_id){
        $log_key_name = __METHOD__ . '_' . $user_id;
        if (!isset(self::$currentLog[$log_key_name])){
            //工作地代码
            $dictionary_code = (new \yii\db\Query())->from('eln_fw_user_attribute')->where(array('=','userId',$user_id))->select(['payrollPlace'])->one();

            //分类代码
            $category_id = (new \yii\db\Query())->from('eln_fw_dictionary_category')->where(array('=','cate_code','payroll_place'))->select(['kid'])->one();

            //发薪地
            $where = array('and',);
            $where[] = array('=','dictionary_code',$dictionary_code['payrollPlace']);
            $where[] = array('=','dictionary_category_id',$category_id['kid']);
            $query = (new \yii\db\Query())->from('eln_fw_dictionary')->where($where)->select(['dictionary_name']);
            $sult = $query->one();
            $sult = $sult['dictionary_name']?$sult['dictionary_name']:'';
            self::$currentLog[$log_key_name] = $sult;
        }
        return self::$currentLog[$log_key_name];
    }
    function isCreditNo($vStr){
        $vCity = array(
            '11','12','13','14','15','21','22',
            '23','31','32','33','34','35','36',
            '37','41','42','43','44','45','46',
            '50','51','52','53','54','61','62',
            '63','64','65','71','81','82','91'
        );
        if (!preg_match('/^([\d]{17}[xX\d]|[\d]{15})$/', $vStr)) return false;
        if (!in_array(substr($vStr, 0, 2), $vCity)) return false;
        $vStr = preg_replace('/[xX]$/i', 'a', $vStr);
        $vLength = strlen($vStr);
        if ($vLength == 18) {
             $vBirthday = substr($vStr, 6, 4) . '-' . substr($vStr, 10, 2) . '-' . substr($vStr, 12, 2);
        } else {
             $vBirthday = '19' . substr($vStr, 6, 2) . '-' . substr($vStr, 8, 2) . '-' . substr($vStr, 10, 2);
        }
        if (date('Y-m-d', strtotime($vBirthday)) != $vBirthday) return false;
        if ($vLength == 18) {
            $vSum = 0;
            for ($i = 17 ; $i >= 0 ; $i--) {
                $vSubStr = substr($vStr, 17 - $i, 1);
                $vSum += (pow(2, $i) % 11) * (($vSubStr == 'a') ? 10 : intval($vSubStr , 11));
            }
            if($vSum % 11 != 1) return false;
        }
        return true;
    }
    /*
     * 获取真实的组织全路径[单个用户]
     * $user_id 用户ID
     * $org_id 组织ID
     * $user_no 员工工号
     * @author xinpeng
    */
    public static function getOrgPath($user_id = NULL,$org_id = NULL,$user_no = NULL){
        
        if($user_id)
        {
            $user_info          = FwUser::findOne(array('kid'=>$user_id,'is_deleted'=>'0'));
            if(!isset($user_info['kid']))
            {
                return -93;
            }
            $orgnization_id     = $user_info['orgnization_id'];
        }
        elseif($user_no)
        {
            $user_info          = FwUser::findOne(array('user_no'=>$user_no,'is_deleted'=>'0'));
            if(!isset($user_info['kid']))
            {
                return -92;
            }
            $orgnization_id     = $user_info['orgnization_id'];
        }elseif($org_id)
        {
            $orgnization_id     = $org_id;
        }else
        {
            return -91;;
        }
        $orgnization_info       = FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
		if(!isset($orgnization_info['kid']))
        {
            return -90;
        }
        $parent_orgnization_id  =$orgnization_info['parent_orgnization_id'];
        $org_data               = array();
        $org_data['id'][0]      = $orgnization_id;
        $org_data['name'][0]    = $orgnization_info['orgnization_name'];
        $i                  = 1;
        while ($parent_orgnization_id){
                $orgnization_id         = $parent_orgnization_id;
                $orgnization_info       = FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
                if(!isset($orgnization_info['kid']))
                {
                    return -89;
                }
                $orgnization_level = $orgnization_info['orgnization_level'];
                $org_data['id'][$orgnization_level]     = $orgnization_id;
                $org_data['name'][$orgnization_level]   = $orgnization_info['orgnization_name'];
                $parent_orgnization_id  =$orgnization_info['parent_orgnization_id'];
                ;
        }
        $org_id_path    = $org_data['id'];
        $org_name_path  = $org_data['name'];
        krsort($org_id_path);
        krsort($org_name_path);
        $org_id_path    = implode("/",$org_id_path);
        $org_name_path  = implode("/",$org_name_path);
        $org_data['org_id_path']    = $org_id_path;
        $org_data['org_name_path']  = $org_name_path;
		//print_r($org_data);
        return  $org_data;
    }
	
	/*
     * 学习管理员编辑-保存前置任务信
     * @author xinpeng 2018-03-28 
    */
	public static function editEmbedResult($params = array()){
		$post = $params;
		if(!empty($post['kid']))
		{
			$data['kid'] = $post['kid'];
		}
		$data['embed_id'] = $post['embed_id'];
		$course_id = $data['course_id'] = $post['course_id'];
		$data['result'] = json_encode($post['arr']);
		$user_id = $data['user_id'] =  $post['user_id'];

		//全局配置信息
        $embedOptionAll  =  self::getBoeEmbedOption();
        //局部配置信息
        $embed = new BoeEmbed();
        $embedOption  = $embed->getInfo($data['embed_id'],'configure');
        $embedOption = json_decode($embedOption,true);

		//用户信息
		$user_info 	  =	 FwUser::find(false)->select('*')->where(array('kid' => $user_id))->asArray()->one();
		$u = array();
		$reporting_manager_change = 0;
		//数据清洗
		foreach ($post['arr'] as $key => $value) {
			if($embedOption[$key]){
				//必填数据验证
				if($embedOption[$key]['is_required']){ //必填数据
					if(trim($value)==''){
						$sult = $embedOptionAll[$key]['option_name'].'不可为空';
						return $sult;
						//exit(json_encode($sult));
						//break;
					}
					//数据正确性验证
					if($embedOptionAll[$key]['option_name']=='手机号码'){
						$u['mobile_no2'] = $value;
						$u['mobile_no'] = $value;
						$u['to_mobile_no'] = 1;
						if(!preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#',$value)){
							$sult = '手机号码不合法';
							return $sult;
							//exit(json_encode($sult));
						}

					}
					if($embedOptionAll[$key]['option_name']=='邮箱'){
						$u['email2'] = $value;
						$u['email'] = $value;
						$u['to_email'] =1;
						$pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
						if(!preg_match($pattern,trim($value))){
							echo "1111";
							$sult = '邮箱不合法';
							return $sult;
							//exit(json_encode($sult));
						}

					}
					if($embedOptionAll[$key]['option_name']=='身份证'){
						$u['id_number'] = $value;
						$ck = self::isCreditNo($value);
						if(!$ck){
							$sult = '身份证不合法';
							return $sult;
							//exit(json_encode($sult));
						}
					}
				}
				if ($embedOptionAll[$key]['option_name']=='费用结算地') {
						$data['pay_place'] = $value;
					}
				if ($embedOptionAll[$key]['option_name']=='直线经理') {
					if($user_info['reporting_manager_id'] != $value){
						$reporting_manager_change = 1;
						$reporting_manager_id  = $value;
					}
				}
			}
		}
		//exit(json_encode($reporting_manager_change));
		//更新结果集
		$boeEmbedUserResult = new BoeEmbedUserResult();
		$result  = $boeEmbedUserResult->saveInfo($data);
      
		//exit(json_encode($result));
		if(is_array($result)){
			foreach ($result as $key => $value) {
				$sult = $value[0];
			}
		}else{
			$sult = 1;
			//更新用户报名确认信息
			$r4	= BoeEnrollService::editEnrollUser($course_id,$user_id);
			//更新直线经理
			if($reporting_manager_change){
				$data = array('approver_id'=>$reporting_manager_id,'user_id'=>$user_id);
				$r2 = FlowService::saveUserLeader($data);
			}
			//更新用户表信息
			$fwUser = new FwUser();
			$u['kid'] = $user_id;
			$r3 = $fwUser->saveInfo($u);
		}
		return $sult;	
	}
	
	
	
	
	
	
	
	
	
}

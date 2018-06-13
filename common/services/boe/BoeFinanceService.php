<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\services\learning\ResourceDomainService;
use common\models\learning\LnResourceDomain;
use common\base\BoeBase;
use common\models\boe\BoeEnrollUser;
use common\models\boe\BoeCharge;
use common\models\boe\BoeChargeDetail;
use common\models\boe\BoeInvoiceCompany;
use common\models\framework\FwCompany;
use common\models\framework\FwDomain;
use common\models\framework\FwUser;
use common\models\framework\FwRole;
use common\models\framework\FwUserRole;
use common\models\framework\FwUserPosition;
use common\models\framework\FwRolePermission;
use common\models\framework\FwPermission;
use common\models\boe\BoeEnterprise;
use common\services\boe\BoeEnterpriseService;
use common\services\boe\BoeBaseService;
use common\services\boe\BoeOrgnizationService;
use common\services\message\PushMessageService;
use yii\db\Query;
use Yii;

/**
 * 专题闯关相关
 * @author xinpeng
 */
class BoeFinanceService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'boe_finance_';

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
    private static function setCache($cache_name, $data = NULL, $time = 0) {
        $new_cache_name = self::$cacheNameFix . (!is_scalar($cache_name) ? md5(serialize($cache_name)) : $cache_name);
        $time = $time ? $time : self::$cacheTime;
        Yii::$app->cache->set($new_cache_name, $data, $time); // 设置缓存 
        $debug = self::isDebugMode();
        if ($debug) {
            echo "<pre>\nRead Info From DataBase,Cache Name={$new_cache_name}\n";
            print_r($data);
            echo "\n</pre>";
        }
    }
	
	/*
	 * 财务模块--根据权限角色获取对应的人员并取得最新的人员担当
	*/
	public static function getRoleUserId($role_code = NULL) {
		if($role_code)
		{
			$find_role = FwRole::findOne(array('role_code'=>$role_code,'status'=>'1','is_deleted'=>'0'));
			if(isset($find_role['kid'])&&$find_role['kid'])
			{
				$find_user = FwUserRole::find(false)->where(array('role_id'=>$find_role['kid'],'status'=>'1','is_deleted'=>'0'))->orderBy('created_at desc')->asArray()->one();
				//boeBase::dump($find_user);
				if(isset($find_user['user_id'])&&$find_user['user_id'])
				{
					//return 	$find_user;
					return 	$find_user['user_id'];
				}
			}
		}
		return NULL;	
	}
	
	/************************************定时任务S*********************************************/
	/*
	 * 报名信息汇总
	*/
	public static function chargeCollect($uid = NULL) {
		//获取当前的报销地分类（存在有效报名待收费信息的）
		$invoice_info = BoeEnrollUser::find(false)->select('distinct(invoice_id),invoice_place')
			->where(array(
				'enroll_status_code'=>50,
				'charge_status'=>'0',
				'is_charge'	=>'1',
				'is_deleted'=>'0'
			  ))
			->asArray()->all();
		boeBase::dump($invoice_info);
		//exit();
		/*查询当前月是否汇总过相应的信息*/
		$collect_date = date('Y年m月');
		$find_date	= BoeCharge::find(false)
			->where(array('charge_date'=>$collect_date,'is_deleted'=>'0'))
			->orderBy('serial desc')
			->asArray()->one();
		$serial = 0;
		if(!empty($find_date))
		{
			$serial = $find_date['serial'];
		}
		foreach($invoice_info as $i_key=>$i_value )
		{
			$serial			= $serial+$i_key+1;
			$apply_serial 	= sprintf("%04d",$serial);//生成4位数，不足前面补0
			$apply_code 	= "F".date("Ymd").$apply_serial;
			//boeBase::dump($apply_code);
			//获取当前所有的已报名待收费信息
			$getInfo = BoeEnrollUser::find(false)
				->where(array(
					'enroll_status_code'=>50,
					'invoice_id'=>$i_value['invoice_id'],
					'charge_status'=>'0',
					'is_charge'	=>'1',
					'is_deleted'=>'0'
				  ))
				->asArray()->all();
			//boeBase::dump($getInfo);
			//exit();
			$charge_data = 	array(
				'serial'		=>$serial,
				'apply_code' 	=>$apply_code,
				'charge_date'	=>date('Y年m月'),
				'invoice_id'	=>$i_value['invoice_id'],
				'invoice_place'	=>$i_value['invoice_place'],
				'charge_amount'	=>'',
				'charge_status_code'=>'F10'
			);
			$charge_obj 	= new BoeCharge();
			$charge_result 	= $charge_obj->saveInfo($charge_data);
			unset($charge_obj);
			boeBase::dump($charge_result);
			//exit();
			foreach($getInfo as $g_key=>$g_value )
			{
				$detail_data = array(
					'charge_id' 		=>$charge_result,
					'charge_apply_code'	=>$apply_code,
					'charge_apply_num'	=>$g_value['charge_apply_num']+1,
					'course_id'			=>$g_value['course_id'],
					'course_name'		=>$g_value['course_name'],
					'user_id'			=>$g_value['user_id'],
					'user_no'			=>$g_value['user_no'],
					'real_name'			=>$g_value['real_name'],
					'organization_name'	=>$g_value['organization_name'],
					'position_name'		=>$g_value['position_name'],
					'invoice_id'		=>$g_value['invoice_id'],
					'invoice_place'		=>$g_value['invoice_place'],
					'expense'			=>$g_value['expense'],
					'enroll_time'		=>$g_value['enroll_time']
				);
				/*
				 * 信息过滤-过滤掉京东方企业和京东方域的数据信息S
				*/
				$course_id 	= $g_value['course_id'];
				$user_id	= $g_value['user_id'];
				$company_array 	= Yii::t('boe', 'charge_company');
				$domain_array	= Yii::t('boe', 'charge_domain');
				//限制必须是京东方企业
				$user_domain_key = $user_company_key = $course_domain_key = $course_company_key	 = false;
				$right_obj 		= new RightInterface();
				$user_domain_obj 	= $right_obj->getSearchDomainListByUserId($user_id);
				foreach ($user_domain_obj as $a_info) {
					if(in_array($a_info['domain_name'],$domain_array))
					{
						$user_domain_key = true;
					}
				}
				//限制必须是京东方域
				$user_company_obj 	= $right_obj->getSearchCompanyListByUserId($user_id);
				foreach ($user_company_obj as $a_info) {
					if(in_array($a_info['company_name'],$company_array))
					{
						$user_company_key = true;
					}
				}
				//限制课程所属课程的企业必须是京东方企业,域必须是京东方域
				$resourceDomain = new ResourceDomainService();
                $resourceDomain->resource_id = $course_id;
                $resourceDomain->resource_type = LnResourceDomain::RESOURCE_TYPE_COURSE;
                $course_domain_obj = $resourceDomain->getContentList($resourceDomain);
				$domain_where	= array(
					'and',
					array('in','domain_name',$domain_array),
					array('=','is_deleted','0'),
				);
				$domain_info 	= FwDomain::find(false)->select('kid')->where($domain_where)->indexBy('kid')->asArray()->all();
				$domain_id_array = array_keys($domain_info);
				foreach ($course_domain_obj as $a_id) {
					if(in_array($a_id,$domain_id_array))
					{
						$course_domain_key = true;
					}
				}
				/*
				 * 信息过滤-过滤掉京东方企业和京东方域的数据信息E
				*/
				//boeBase::dump($company_key);
				if($user_domain_key&&$user_company_key&&$course_domain_key)
				{
					$datail_obj 	= new BoeChargeDetail();
					$detail_result 	= $datail_obj->saveInfo($detail_data);
					if($detail_result)
					{
						$user_data = array(
							'kid'				=>$g_value['kid'],
							'charge_id'			=>$charge_result,
							'detail_id'			=>$detail_result,
							'charge_apply_code'	=>$apply_code,
							'charge_status'		=>'1',
							'charge_apply_num'	=>$g_value['charge_apply_num']+1
						);
						$user_obj 	= new BoeEnrollUser();
						$user_result 	= $user_obj->saveInfo($user_data);
						unset($user_data,$user_obj);
					}
					unset($datail_obj);
					boeBase::dump($detail_result);	
				}
			}
			//针对HRBP-每个-获取对应的HRBP
			$current_hrbp = BoeEnterprise::findOne(array('enterprise_name'=>$g_value['invoice_place'],'is_deleted'=>'0'));
			if(!empty($current_hrbp['hrbp_no']))
			{
				$bp_info = FwUser::findOne(array('user_name'=>$current_hrbp['hrbp_no'],'is_deleted'=>'0'));
				if(!empty($bp_info['kid'])&&!empty($getInfo[0]['user_id']))
				{
					$pushService = new PushMessageService();
					$pushService->sendMailByChargeCollect(date('Y年m月'), $getInfo[0]['created_by'],$bp_info['kid']);
				}
			}
			//boeBase::dump($charge_data);	
			//boeBase::dump($getInfo);	
		}
		exit();	
	}

}

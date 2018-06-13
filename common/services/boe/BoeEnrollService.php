<?php
namespace common\services\boe;
use common\base\BoeBase;
use common\services\boe\BoeBaseService;
use common\services\interfaces\service\RightInterface;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Url;
use common\helpers\TNetworkHelper;
use Yii;
use common\models\boe\BoeEmbed;
use common\models\boe\BoeEmbedCourse;
use common\models\boe\BoeEmbedOption;
use common\models\boe\BoeEmbedUserResult;
use common\models\boe\BoeEnroll;
use common\models\boe\BoeEnrollUser;
use common\models\boe\BoeEnrollFlow;
use common\models\boe\BoeInvoiceCompany;
use common\models\boe\BoeFlowLog;
use common\models\boe\BoeEnterprise;
use common\models\learning\LnCourse;
use common\models\learning\LnCourseReg;
use common\models\learning\LnCourseEnroll;
use common\models\social\SoAudience;
use common\models\social\SoAudienceMember;
use common\models\framework\FwUser;
use common\models\framework\FwUserDisplayInfo;
use common\models\framework\FwOrgnization;
use common\services\boe\BoeEmbedService;
use common\services\learning\CourseService;
use common\services\learning\RecordService;
use common\services\message\MessageService;
use common\services\message\PushMessageService;
use common\services\message\TimelineService;
use common\services\framework\PointRuleService;
use components\widgets\TPagination;
/**
 * Desc: 新报名流程信息处理
 * User: xinpeng
 * Date: 2017/12/21
 */
defined('BoeWebRootDir') or define('BoeWebRootDir', dirname(dirname(dirname(__DIR__))));

class BoeEnrollService{
    private static $cacheTime 		= 43200; //缓存12小时
    private static $currentLog 		= array();
    private static $cacheNameFix 	= 'boe_enroll_';
	
	/*
	 * 报名收费上线专用受众测试
	*/
	public static function ChargeEnrollAudience($uid = NULL) {
		if(!$uid)
		{
			return flase;
		}
		$su_info 	= SoAudience::findOne(array('audience_name' => '报名收费上线','is_deleted'=>'0'));
		if(empty($su_info['kid']))
		{
			return false;
		}
		$mem_info 	= SoAudienceMember::find(false)->select('user_id')
					->where(array('audience_id'=>$su_info['kid'],'is_deleted'=>'0'))
					->indexBy('user_id')->asArray()->all();
		$mem_array	= array_keys($mem_info);
		//boeBase::dump($uid);
		//boeBase::dump($mem_array);
		if($mem_array&&in_array($uid,$mem_array))
		{
			return true;
		}
		return false;	
	}
	
	/*
     * 课程时长处理
    */
    public static function boeTime($time_num = 0, $time_unit = 0) {
        //1=>'分钟',2=>'小时',3=>'天'
        //时：分 00:00
        $hour = $min = 0;
        switch($time_unit)
        {
            case 1://时长是分钟
                if($time_num>=60)
                {
                    $hour   =  floor($time_num / 60) ;
                    $min    =  $time_num % 60;
                }else{
                    $min    =  $time_num;
                }
                break;
            case 2://时长是小时
                $hour   = $time_num;
                break;
            case 3://时长是天
                $hour   = $time_num * 24;
                break;  
        }
        $min    = $min==0?"00":($min>0&&$min<10?"0".$min:$min);
        $time   = $hour>0?($hour.":".$min):"0:".$min;
        return $time;   
    }
	
	/*
	 * 更加OA审批返回的数据信息更新报名流程中的OA信息
	*/
	public static function updateFlowOa($data = NULL) {
		if(empty($data['request_id']) || !isset($data['result']))
		{
			return -100;
		}
		$enroll_id 	= $data['request_id'];
		$data_en 	= BoeEnroll::findOne(array('kid'=>$enroll_id,'is_deleted'=>'0'));
		if(empty($data_en['kid']))
		{
			return -82;
		}
		if(isset($data_en['enroll_status_code'])&&$data_en['enroll_status_code']>30)
		{
			return -84;
		}
		if(!empty($data['handled_by']))
		{
			$data_by 	= FwUserDisplayInfo::findOne(array('user_no'=>$data['handled_by'],'is_deleted'=>'0'));
		}
		$oa_confirm = $data['result'] =='true'?1:2;
		$code		= $oa_confirm == 1?40:51;
		$oa_data 	= array(
			'code'			=>$code,
			'enroll_id' 	=>$enroll_id,
			'course_id'		=>$data_en['event_id'],
			'user_id'		=>$data_en['applier_id'],
			'oa_confirm'	=>$oa_confirm,
			'oa_mark'		=>json_encode($data),
			'oa_by'			=>!empty($data_by['user_id'])?$data_by['user_id']:NULL,
			'oa_at'			=>!empty($data['handled_at'])?$data['handled_at']:time()
		);
		$result 	=  self::updateEnroll($oa_data);
		return $result;
	}
	
	/*
	 * 向OA推送发起OA审批
	*/
	public static function pushOaFlow($oa_array = array()) {
		$time_unit	= array('1'=>'分钟','2'=>'小时','3'=>'天');
		$course_id 	= $oa_array['course_id'];
		$user_id	= $oa_array['user_id'];
		$enroll_id	= $oa_array['enroll_id'];
		$data_user 	= FwUser::findOne(array('kid'=>$user_id,'is_deleted'=>'0'));
		if(empty($data_user['kid']))
		{
			return -81;
		}
		$data_course = LnCourse::findOne(array('kid'=>$course_id,'is_deleted'=>'0'));
		if(empty($data_course['kid']))
		{
			return -80;
		}
		$data_embed  = self::getConfirmedEmbed($course_id,$user_id);
		if(empty($data_course['kid']))
		{
			return -79;
		}
		$oa_data	= array(
			'user_no' 		=>$data_user['user_no'],
			'request_type'	=>'boeu_training',
			'request_id'	=>$enroll_id,
			'request_name'	=>$data_course['course_name'],
			'training_info' =>array(
				'name' 	=>$data_course['course_name'],
				'url'	=>"http://u.boe.com".Url::toRoute(array('/resource/course/view', 'id' => $course_id)),
				'training_desc'=>$data_course['course_desc'],
				'type'	=>$data_course['course_type']=='1'?'面授':'在线',
				//course表示课程，project表示项目
				'object_type'=>$data_course['is_course_project'] =='1'?'项目':'课程',
				//针对收费课程分类学习（1、必修课 2、选修课）
				'is_obligated'=>$data_course['study_type'] == 1?'true':'false',
				'duration'	=>$data_course['course_period'].$time_unit[$data_course['course_period_unit']],
				'level'		=>$data_course->getDictionaryText('course_level',$data_course['course_level']),
				'price'		=>"￥".$data_course['course_price'],
				'paid_by'	=>$data_embed['code']['account_place'],//费用承担单位
				'enroll_time_start'=>$data_course['enroll_start_time']?date("Y年m月d日",$data_course['enroll_start_time']):"",
				'enroll_time_end'=>$data_course['enroll_end_time']?date("Y年m月d日",$data_course['enroll_end_time']):"",
				'training_time_start'=>$data_course['open_start_time']?date("Y年m月d日",$data_course['open_start_time']):"",
				'training_time_end'=>$data_course['open_end_time']?date("Y年m月d日",$data_course['open_end_time']):"",
				'location'	=>$data_course['training_address'],
				'score'		=>$data_course['default_credit']
			),
			'inform'			=>json_decode("{}"),//办结知会
			'default_inform'	=>json_decode("{}"),//默认知会
			'attachment'		=>json_decode("{}"),//附件
			'time'				=>time(),//请求时间戳
		);
		//return $oa_data;
		//return -78;//发送OA审批还在调试中
		//推送信息地址
		$flow_url	= Yii::t('api', 'java_push_oa_url');
		$response 	= TNetworkHelper::HttpPost($flow_url, $oa_data);
		$data 		= json_decode($response['content'],true);
		if(isset($data['code'])&& $data['code']==0)
		{
			//return 1;
		}else
		{
			//记录日志B-01
			$log_data = "pushMsg_".json_encode($oa_data)."_returnMsg_".json_encode($data)."_time_".time();
			Yii::info($log_data,"flow");
			unset($log_data);
			//记录日志E-01
			//return -78;
		}
		return $data;
	}
	
	/*
	 * 获取管理员角色-列表
	*/
	public static function getRolesCode($user_id = NULL,$role = NULL) {
		$rightInterface = new RightInterface();
		$roles			= $rightInterface->getRoleListByUserId($user_id);
		$roles_code 	= array();
		foreach($roles as $r_key=>$r_value)
		{
			$roles_code[]	= $r_value->role_code;
		}
		if($role&&in_array($role,$roles_code))
		{
			return 1;
		}
		return $roles_code;
	}
	
	
	/*
	 * 管理员特权
	 * 任何状态下通过该功能将用户由当前状态修改为参训确认状态
	*/
	public function superBoeEnrollCourse($enrollId,$enrollNewId,$userId)
	{
		if(!$enrollId || !$enrollNewId || !$userId)
		{
			return false;
		}
		$data_en 	= BoeEnroll::findOne(array('kid'=>$enrollNewId,'is_deleted'=>'0'));//新报名信息
		$data_flow 	= BoeEnrollFlow::findOne(array('enroll_id'=>$enrollNewId,'is_deleted'=>'0'));//新报名流程信息
		$data_co 	= LnCourse::findOne(array('kid'=>$data_en['event_id'],'is_deleted'=>'0'));//课程信息
		/* 
		 * 新报名流程处理
		*/
		$flow_params = array();
		if (!empty($data_en['kid'])) {
			if (empty($data_flow['kid'])) {
				//资质审核前
				$user_params				= array(
					'voucher_confirm'	=>'1',
					'voucher_mark'		=>Yii::t('boe', 'flow_approved_accept'),
					'expense'			=>$data_co['course_price'],
					'enroll_time'		=>$data_en['applier_at'],
					'course_id'			=>$data_co['kid'],
					'course_name'		=>$data_co['course_name'],
					'user_id'			=>$data_en['applier_id'],
					'enroll_id'			=>$enrollNewId,
					'uid'				=>$userId,
					'code'				=>40
				);
				$user_result = self::addEnrollUser($user_params);
				$flow_params = array(
					'enroll_id' 		=>$enrollNewId,
					'base_confirm'		=>'1',
					'voucher_confirm'	=>'1',
					'approval_confirm'	=>'0',
					'approved_mark'		=>NULL,
					'approved_by'		=>NULL,
					'approved_at'		=>NULL,
					'created_from'		=>'super'
				);		
			}else{
					$flow_params = array(
						'kid' 				=>$data_flow['kid'],
						'base_confirm'		=>'1',
						'voucher_confirm'	=>'1',
						'approval_confirm'	=>'0',
						'approved_mark'		=>NULL,
						'approved_by'		=>NULL,
						'approved_at'		=>NULL,
						'created_from'		=>'super'
					);
			}
			//改变主表状态
			$en_params	= array(
				'enroll_id' 	=>$enrollNewId,
				'created_from'	=>'super'
			);
			$en_result 	= self::updateEnrollStatus($en_params,40);
			//改变流程表状态
			if($flow_params)
			{
				$flow_obj = new BoeEnrollFlow();
				$flow_result = $flow_obj->saveInfo($flow_params);
			}
			//添加操作日志
			$enroll_status_code = Yii::t('boe', 'enroll_status_code');//报名状态编号
			$log_params		= array(
				'event_type' 			=>'1',
				'event_id' 				=>$params['enroll_id'],
				'event_status_code' 	=>40,
				'mark' 					=>$enroll_status_code[40]['mtxt'],
				'created_from'			=>'super'
			);
			$log_result		= self::addFlowLog($log_params);
		}
		return 1;
	}
	
	
	/*
	 * 删除课程报名信息
	*/
	public function delBoeEnrollCourse($enrollId,$enrollNewId){
		if($enrollNewId)
		{	
			$res_flow = BoeEnrollFlow::findOne(array('enroll_id'=>$enrollNewId,'is_deleted'=>'0'));
			if(!empty($res_flow))
			{
				$flow_obj = new BoeEnrollFlow();
				$flow_obj->deleteInfo($res_flow['kid']);	
			}
			$res_user = BoeEnrollUser::findOne(array('enroll_id'=>$enrollNewId,'is_deleted'=>'0'));
			if(!empty($res_user))
			{
				$user_obj = new BoeEnrollUser();
				$user_obj->deleteInfo($res_user['kid']);
			}
			$res_en = BoeEnroll::findOne(array('kid'=>$enrollNewId,'is_deleted'=>'0'));
			if(!empty($res_en))
			{
				/*
				 * 删除用户前置任务信息
				*/
				$res_em = BoeEmbedUserResult::findOne(array(
					'course_id'		=>$res_en['event_id'],
					'user_id'		=>$res_en['applier_id'],
					'is_deleted'	=>'0'
				));
				if(!empty($res_em))
				{
					$em_obj = new BoeEmbedUserResult();
					$em_obj->deleteInfo($res_em['kid']);
				}
				$en_obj = new BoeEnroll();
				$en_obj->deleteInfo($res_en['kid']);
			}
		}
		$courseService = new CourseService();
        $result = $courseService->delEnrollCourse($enrollId);
		return $result;	
	}
	
	/*
	 * 获取前置任务选项信息
	*/
	public function getEmbedOption(){ 
		$op_db 				= new BoeEmbedOption();
		$all_option 		= $op_db->getAll();
		$option_array		= Yii::t('boe', 'embed_option');
		$option_data		= array();
		foreach($all_option as $a_key=>$a_value){
			$key		= $option_array[$a_value['option_name']];
			$option_data[$a_key]['kid']  = $a_value['kid'];
			$option_data[$a_key]['name'] = $a_value['option_name'];
			$option_data[$a_key]['code'] = $option_array[$a_value['option_name']];	
		}
		return $option_data;
	}
	/**
     * 获取资质确认信息（前置任务的信息）
	 * @param $params
     */
	public function getConfirmedEmbed($course_id,$user_id){ 
		$all_option = self::getEmbedOption();
		$user_data 	= array();
		if($course_id&&$user_id)
		{
			$em_user = BoeEmbedUserResult::findOne(array(
				'course_id'	=>$course_id,
				'user_id'	=>$user_id,
				'is_deleted'=>'0'
			));
			$user_data['course_id'] 	= $course_id;
			$user_data['user_id'] 		= $user_id;
			if(isset($em_user['result'])&&$em_user['result'])
			{
				$user_info = json_decode($em_user['result']);
				$user_info = (array)$user_info;
				foreach($user_info as $u_key=>$u_value)
				{
					$code_key = $all_option[$u_key]['code'];
					$name_key = $all_option[$u_key]['name'];
					$user_data['code'][$code_key] = $u_value;
					$user_data['name'][$name_key] = $u_value;
					if($u_value){
					$user_data['html'].= '<div class="col-sm-2 embed_1">'.$name_key.'</div>'.'<div class="col-sm-4 embed_1">'.$u_value.'</div>';
					}
					unset($code_key,$name_key);
				}
			}	
		}
		return $user_data;
	}
	
	/**
     * 添加用户报名信息
	 * @param $params
     */
	public function addEnroll($params = NULL){ 
		if(!isset($params['course_id']) || !$params['course_id'])
		{
			return -88;
		}
		if(!isset($params['user_id']) || !$params['user_id'])
		{
			return -87;
		}
		//判断报名信息
		$enroll_info = BoeEnroll::findOne(array(
			'event_type'=>'1',
			'event_id'	=>$params['course_id'],
			'applier_id'=>$params['user_id'],
			'is_deleted'=>'0'
		));
		if(isset($enroll_info['kid'])&&$enroll_info['kid'])
		{
			return -86;
		}
		//添加报名信息
		$lce_info = LnCourseEnroll::findOne(array(
			'course_id'	=>$params['course_id'],
			'user_id'	=>$params['user_id'],
			'is_deleted'=>'0'
		));
		if(!isset($lce_info['kid'])|| !$lce_info['kid'])
		{
			return -85;
		}
		$enroll_status_code = Yii::t('boe', 'enroll_status_code');//报名状态编号
		$en_data	= array(
			'event_type'		=>'1',
			'event_id'			=>$params['course_id'],
			'lce_id'			=>$lce_info['kid'],
			'applier_id'		=>$params['user_id'],
			'applier_at'		=>time(),
			'is_oa'				=>'-1',//待确认状态
			'enroll_status_code'=>'10',
			'enroll_mark'		=>$enroll_status_code[10]['mtxt']
		);
		if(isset($params['is_agent'])&&$params['is_agent'])
		{
			$en_data['is_agent'] = $params['is_agent'];
		}
		if(isset($params['agent_id'])&&$params['agent_id'])
		{
			$en_data['agent_id'] = $params['agent_id'];
		}
		$en_obj = new BoeEnroll();
		$en_res = $en_obj->saveInfo($en_data);
		//添加流程日志
		$log_data	= array(
			'event_type' 		=>'1',
			'event_id'			=>$en_res,
			'event_status_code'	=>'10',
			'mark'				=>$enroll_status_code[10]['mtxt']
		);
		$log_res = self::addFlowLog($log_data);
	}
	
	/**
     * 更新用户报名信息
	 * @param $params
     */
	public function updateEnroll($params = NULL){ 
		if(isset($params['code'])&&$params['code'])
		{
			if(isset($params['enroll_id'])&&$params['enroll_id'])
			{
				$flow_info	= BoeEnrollFlow::findOne(array('enroll_id'=>$params['enroll_id'],'is_deleted'=>'0'));
				$enroll_info= BoeEnroll::findOne(array('kid'=>$params['enroll_id'],'is_deleted'=>'0'));
				//资质审核
				if(isset($params['base_confirm'])&&$params['base_confirm'])
				{
					if(isset($flow_info['kid'])  || !isset($enroll_info['kid']))
					{
						return -98;	
					}
					//添加入流程表
					$flow_data = array(
						'enroll_id' 	=>$params['enroll_id'],
						'base_confirm'	=>$params['base_confirm'],
						'base_mark'		=>$params['base_mark'],
						'base_by'		=>$params['uid'],
						'base_at'		=>time()
					);
					//资质审核确认-同意
					if($params['base_confirm'] ==1)
					{
						//获取该用户的前置任务信息
						$data_embed  = self::getConfirmedEmbed($enroll_info['event_id'],$enroll_info['applier_id']);
						if(empty($data_embed['code']['account_place']))
						{
							return -77;
						}
						$enterprise = BoeEnterprise::findOne(array('enterprise_name'=>$data_embed['code']['account_place'],'is_deleted'=>'0'));
						if(!isset($enterprise['is_oa']))
						{
							return -76;
						}
						//如果不是OA审批-需要上传报名凭证
						if($enterprise['is_oa'] == '0')
						{
							$params['code'] = 31;
						}
						//需要OA审批
						if($enterprise['is_oa'] == '1')
						{
							//发起OA测试返回信息S
							$oa_array =array(
								'enroll_id'		=>$params['enroll_id'],
								'course_id'		=>$params['course_id'],
								'user_id'		=>$params['user_id'],
							);
							$oa_result = self::pushOaFlow($oa_array);
							$error_array 	= Yii::t('api', 'err_flow');
							if($oa_result < 0)
							{
								return $error_array[$oa_result]['msg'];
							}
							else{
								if(isset($oa_result['code'])&&$oa_result['code'] == 0)
								{
									if(!empty($oa_result['data']['ApprovalNumber']))
									{
										$flow_data['oa_no'] = $oa_result['data']['ApprovalNumber'];
									}		
								}else{
									if(isset($oa_result['code'])&&$oa_result['code'] == 1005)
									{
										return $error_array[-102]['msg'];
									}
									return $error_array[-101]['msg'];
								}
							}
							//发起OA测试返回信息E	
						}
						
					}
				}
				//OA审核
				if(isset($params['oa_confirm'])&&$params['oa_confirm'])
				{
					if(!isset($flow_info['kid'])&&!$flow_info['kid'])
					{
						return -83;
					}
					//添加入流程表
					$flow_data = array(
						'kid'			=>$flow_info['kid'],
						'enroll_id' 	=>$params['enroll_id'],
						'oa_confirm'	=>$params['oa_confirm'],
						'oa_mark'		=>$params['oa_mark'],
						'oa_by'			=>$params['oa_by'],
						'oa_at'			=>$params['oa_at']
					);
					//如果OA审核同意
					if($params['oa_confirm']==1)
					{
						//添加确认表中
						$user_result = self::addEnrollUser($params);
					}		
				}
				//材料审核
				if(isset($params['voucher_confirm'])&&$params['voucher_confirm'])
				{
					if(!isset($flow_info['kid'])&&!$flow_info['kid'])
					{
						return -97;	
					}
					//添加入流程表
					$flow_data = array(
						'kid'				=>$flow_info['kid'],
						'enroll_id' 		=>$params['enroll_id'],
						'voucher_confirm'	=>$params['voucher_confirm'],
						'voucher_mark'		=>$params['voucher_mark'],
						'voucher_by'		=>$params['uid'],
						'voucher_at'		=>time()
					);
					//如果材料确认同意
					if($params['voucher_confirm']==1)
					{
						//添加确认表中
						$user_result = self::addEnrollUser($params);
					}
				}
				//参训确认
				if(isset($params['approval_confirm'])&&$params['approval_confirm'])
				{
					if(!isset($flow_info['kid'])&&!$flow_info['kid'])
					{
						return -96;	
					}
					//添加入流程表
					$flow_data = array(
						'kid'				=>$flow_info['kid'],
						'enroll_id' 		=>$params['enroll_id'],
						'approval_confirm'	=>$params['approval_confirm'],
						'approved_mark'		=>$params['approved_mark'],
						'approved_by'		=>$params['uid'],
						'approved_at'		=>time()
					);
					//更新确认用户表中
					$user_result = self::updateEnrollUser($params);
				}
				$flow_db 		= new BoeEnrollFlow();
				$flow_result 	= $flow_db->saveInfo($flow_data);
				//改变主表状态
				$status_data	= array(
					'enroll_id' =>$params['enroll_id'],
				);
				if(isset($params['is_charge']))
				{
					$status_data['is_charge'] = $params['is_charge'];
				}
				if(isset($params['voucher_confirm'])&&$params['voucher_confirm']==2)
				{
					$status_data['enroll_mark'] = Yii::t('boe', 'voucher_no_tip');
				}
				if(isset($enterprise['is_oa']))
				{
					$status_data['is_oa'] = $enterprise['is_oa'];
				}
				$status_result 	= self::updateEnrollStatus($status_data,$params['code']);
				//return $status_result;
				//添加操作日志信息
				$enroll_status_code = Yii::t('boe', 'enroll_status_code');//报名状态编号
				$log_data		= array(
					'event_type' 			=>'1',
					'event_id' 				=>$params['enroll_id'],
					'event_status_code' 	=>$params['code'],
					'mark' 					=>$enroll_status_code[$params['code']]['mtxt']
				);
				if(isset($params['voucher_confirm'])&&$params['voucher_confirm']==2)
				{
					$log_data['mark'] = Yii::t('boe', 'voucher_no_tip');
				}
				$log_result		= self::addFlowLog($log_data);
				return 1;
			}
			return -99;	
		}
		return 	-100;
	}
	
	/**
     * 学习管理员编辑用户前置信息后需要同步更新报名确认用户信息（eln_boe_enroll_user）
	 * @param $params
	 * $course_id 课程ID $user_id 用户ID
     */
	public function editEnrollUser($course_id = NULL,$user_id = NULL){
		if(empty($course_id) || empty($user_id))
		{
			return -100;
		}
		//看下是否需要更新报名状态信息（如果处于报名完善信息状态-需要更新）
		$find_enroll = BoeEnroll::findOne(array(
			'event_type' 	=>'1',
			'event_id' 		=>$course_id,
			'applier_id'	=>$user_id,
			'is_deleted'=>'0'
		));
		if(!empty($find_enroll['enroll_status_code'])&&$find_enroll['enroll_status_code']==10)
		{
			//更改报名状态、更新报名日志B xinpeng 20171226
			$enroll_data = array(
				'course_id' =>$course_id,
				'user_id' 	=>$user_id
			);
			$res_en = self::updateEnrollStatus($enroll_data,20);
			//boeBase::dump($res_en);
			//添加流程日志
			$enroll_status_code = Yii::t('boe', 'enroll_status_code');//报名状态编号
			$log_data	= array(
				'event_type' 		=>'1',
				'event_id'			=>$res_en,
				'event_status_code'	=>'20',
				'mark'				=>$enroll_status_code[20]['mtxt']
			);
			$log_res = self::addFlowLog($log_data);
			//boeBase::dump($log_res);
			//更改报名状态、更新报名日志E	
		}
		//更新报名状态信息结束
		$find_user  = BoeEnrollUser::findOne(array(
			'course_id' =>$course_id,
			'user_id'	=>$user_id,
			'is_deleted'=>'0'
		));
		if(empty($find_user['kid']))
		{
			return 1;	
		}
		$user_where = array('user_id'=>$user_id,'is_deleted'=>'0');
		$user_info 	= FwUserDisplayInfo::find(false)->where($user_where)->asArray()->one();
		$embed_info = self::getConfirmedEmbed($course_id,$user_id);
		$user_array = array_merge($user_info,$embed_info['code']);
		//费用结算地-缴费地-必填
		if(!empty($user_array['account_place']))
		{
			$invoice_id = self::getInvoiceId($user_array['account_place']);
			if(!$invoice_id)
			{
				return -75;
			}
			//return $invoice_id;
		}else{
			return -74;
		}
		//HRBP-必填
		if(!empty($user_array['hrbp_name']))
		{
			//$hrbp_info = array();
			$hrbp_user_no = explode(")",$user_array['hrbp_name']);
			$hrbp_user_no = explode("(",$hrbp_user_no[0]);
			if(empty($hrbp_user_no[1]))
			{
				return -73;
			}
			$hrbp_info	= FwUser::findOne(array('user_no'=>$hrbp_user_no[1],'status'=>'1','is_deleted'=>'0'));
			if(empty($hrbp_info['kid']))
			{
				return -72;
			}
		}else{
			return -71;
		}
		$user_data	= array(
			'kid'					=>$find_user['kid'],
			//'user_id'				=>$user_id,
			'user_no'				=>$user_array['user_no'],
			'real_name'				=>$user_info['real_name'],
			'organization_name'		=>$user_array['orgnization_name'],
			'organization_name_path'=>$user_array['orgnization_name_path'],
			'organization_tx'		=>$user_array['orgnization_tx'],
			'organization_zz'		=>$user_array['orgnization_zz'],
			'position_name'			=>$user_array['position_name'],
			'email'					=>$user_array['email'],
			'mobile_no'				=>$user_array['mobile_no'],
			'invoice_id'			=>$invoice_id,
			'invoice_place'			=>$user_array['account_place'],
			'hrbp_user_id'			=>$hrbp_info['kid'],
			'hrbp_user_no'			=>$hrbp_info['user_no'],
			'hrbp_user_name'		=>$hrbp_info['real_name'],
			'hrbp_email'			=>$hrbp_info['email']?$hrbp_info['email']:$hrbp_info['email2'],
		);
		$eu_obj	= new BoeEnrollUser();
		$result = $eu_obj->saveInfo($user_data);
		return $result;	
	} 
		
	/**
     * 材料审核|OA审核同意后添加报名确认用户信息
	 * @param $params
	 * params['course_id'] 课程ID params['user_id'] 用户ID
	 * params['enroll_id'] 报名KID
     */
	public function addEnrollUser($params = NULL){
		if(!isset($params['enroll_id']) || !$params['enroll_id'])
		{
			return -95;
		}
		if(!isset($params['course_id']) || !$params['course_id'])
		{
			return -94;
		}
		if(!isset($params['user_id']) || !$params['user_id'])
		{
			return -93;
		}
		/*if(!isset($params['voucher_confirm']) || $params['voucher_confirm']!=1)
		{
			return -92;
		}*/
		$find_user  = BoeEnrollUser::findOne(array(
			'enroll_id' =>$params['enroll_id'],
			'user_id'	=>$params['user_id'],
			'is_deleted'=>'0'
		));
		if(isset($find_user['kid'])&&$find_user['kid'])
		{
			return -84;	
		}
		$user_where = array('user_id'=>$params['user_id'],'is_deleted'=>'0');
		$user_info 	= FwUserDisplayInfo::find(false)->where($user_where)->asArray()->one();
		$embed_info = self::getConfirmedEmbed($params['course_id'],$params['user_id']);
		$user_array = array_merge($user_info,$embed_info['code']);
		//费用结算地-缴费地-必填
		if(!empty($user_array['account_place']))
		{
			$invoice_id = self::getInvoiceId($user_array['account_place']);
			if(!$invoice_id)
			{
				return -75;
			}
			//return $invoice_id;
		}else{
			return -74;
		}
		//HRBP-必填
		if(!empty($user_array['hrbp_name']))
		{
			//$hrbp_info = array();
			$hrbp_user_no = explode(")",$user_array['hrbp_name']);
			$hrbp_user_no = explode("(",$hrbp_user_no[0]);
			if(empty($hrbp_user_no[1]))
			{
				return -73;
			}
			$hrbp_info	= FwUser::findOne(array('user_no'=>$hrbp_user_no[1],'status'=>'1','is_deleted'=>'0'));
			if(empty($hrbp_info['kid']))
			{
				return -72;
			}	
		}else{
			return -71;
		}
		$user_data	= array(
			'enroll_id'				=>$params['enroll_id'],
			'enroll_status_code'	=>$params['code'],
			'course_id'				=>$params['course_id'],
			'course_name'			=>$params['course_name'],
			'user_id'				=>$params['user_id'],
			'user_no'				=>$user_array['user_no'],
			'real_name'				=>$user_info['real_name'],
			'organization_name'		=>$user_array['orgnization_name'],
			'organization_name_path'=>$user_array['orgnization_name_path'],
			'organization_tx'		=>$user_array['orgnization_tx'],
			'organization_zz'		=>$user_array['orgnization_zz'],
			'position_name'			=>$user_array['position_name'],
			'email'					=>$user_array['email'],
			'mobile_no'				=>$user_array['mobile_no'],
			'invoice_id'			=>$invoice_id,
			'invoice_place'			=>$user_array['account_place'],
			'hrbp_user_id'			=>$hrbp_info['kid'],
			'hrbp_user_no'			=>$hrbp_info['user_no'],
			'hrbp_user_name'		=>$hrbp_info['real_name'],
			'hrbp_email'			=>$hrbp_info['email']?$hrbp_info['email']:$hrbp_info['email2'],
			'expense'				=>$params['expense'],//报名费用
			'is_charge'				=>isset($params['is_charge'])?$params['is_charge']:1,
			'enroll_time'			=>$params['enroll_time'],//报名时间
		);
		//return $user_data;
		$eu_obj	= new BoeEnrollUser();
		$result = $eu_obj->saveInfo($user_data);
		return $result;	 
	}
	
	/**
     * 参训确认同意后更新报名确认用户信息
	 * @param $params
	 * params['course_id'] 课程ID params['user_id'] 用户ID
	 * params['enroll_id'] 报名KID
     */
	public function updateEnrollUser($params = NULL){
		if(!isset($params['enroll_id'])&&!$params['enroll_id'])
		{
			return -91;
		}
		if(!isset($params['user_id'])&&!$params['user_id'])
		{
			return -90;
		}
		$find_user = BoeEnrollUser::findOne(array(
			'enroll_id'=>$params['enroll_id'],
			'user_id'=>$params['user_id'],
			'is_deleted'=>'0'
			));
		if(!isset($find_user['kid'])&&!$find_user['kid'])
		{
			return -89;
		}
		$user_data = array();
		$user_data['kid'] = $find_user['kid'];
		//修改是否收费
		if(isset($params['is_charge'])&&$params['is_charge'])
		{
			$user_data['is_charge'] = $params['is_charge'];
		}
		//修改报名状态
		if(isset($params['code'])&&$params['code'])
		{
			$user_data['enroll_status_code'] = $params['code'];
		}
		//修改收费状态
		if(isset($params['charge_status'])&&$params['charge_status'])
		{
			$user_data['charge_status'] = $params['charge_status'];
		}
		//修改收费申请编号
		if(isset($params['charge_apply_num'])&&$params['charge_apply_num'])
		{
			$user_data['charge_apply_num'] = $params['charge_apply_num'];
		}
		$eu_obj	= new BoeEnrollUser();
		$result = $eu_obj->saveInfo($user_data);
		return $result;	
	}
	
	
	/**
     * 获取发票报销地信息ID
     */
	public function getInvoiceId($invoice_place = NULL){ 
		if($invoice_place)
		{
			$invoice_info = BoeInvoiceCompany::findOne(array('short_title'=>$invoice_place,'is_deleted'=>'0'));
			if(!empty($invoice_info['enterprise_id']))
			{
				return $invoice_info['enterprise_id'];	
			}
			$enterprise = BoeEnterprise::findOne(array('enterprise_name'=>$invoice_place,'is_deleted'=>'0'));
			if(empty($enterprise['kid']))
			{
				return NULL;
			}
			$in_obj		= new BoeInvoiceCompany();
			$in_data 	= array(
				'enterprise_id'	=>$enterprise['kid'],
				'short_title'	=>$invoice_place
			);
			if(!empty($invoice_info['kid']))
			{
				$in_data['kid'] = $invoice_info['kid'];
			}
			$result 	= $in_obj->saveInfo($in_data);
			return $enterprise['kid'];
		}
		return NULL;	
	}
	
	/**
     * 更新报名状态
	 * @param $params
	 * params['course_id'] 课程ID params['user_id'] 用户ID
	 * params['enroll_id'] 报名KID
     */
	public function updateEnrollStatus($params = NULL,$status_code){ 
		$enroll_id = NULL;
		if(isset($params['enroll_id'])&&$params['enroll_id'])
		{
			$enroll_id = $params['enroll_id'];
		}
		if(isset($params['course_id'])&&$params['course_id']&&isset($params['user_id'])&&$params['user_id'])
		{
			$enroll_info	= BoeEnroll::findOne(array('event_id'=>$params['course_id'],'event_type'=>'1','applier_id'=>$params['user_id'],'is_deleted'=>'0'));
			if(isset($enroll_info['kid'])&&$enroll_info['kid'])
			{
				$enroll_id = $enroll_info['kid'];
			}
		}
		if($enroll_id)
		{
			$en_obj 	= new BoeEnroll();
			$enroll_status_code = Yii::t('boe', 'enroll_status_code');//报名状态编号
			$en_data	= array(
				'kid'				=>$enroll_id,
				'enroll_status_code'=>$status_code,
				'enroll_mark'		=>isset($params['enroll_mark'])?$params['enroll_mark']:$enroll_status_code[$status_code]['mtxt']	
			);
			//return $en_data;
			if(isset($params['is_charge']))
			{
				$en_data['is_charge'] = $params['is_charge'];
			}
			if(isset($params['created_from']))
			{
				$en_data['created_from'] = $params['created_from'];
			}
			if(isset($params['is_oa']))
			{
				$en_data['is_oa'] = $params['is_oa'];
			}
			$en_res = $en_obj->saveInfo($en_data);
			return $en_res;
		}
		return NULL;
	}
	/**
     * 更改
	 * @param $params
	 * params['event_type'] 事件类型（1-审批；2-收费；3-发票）
	 * params['event_id'] 所属事件对应的ID
	 * params['event_status_code'] 所属事件现在所对应的状态码
	 * params['mark'] 所属日志详细信息
     */
	public function addFlowLog($params = NULL){ 
		$data	= array();
		if(isset($params['event_type'])&&$params['event_type']&&isset($params['event_id'])&&$params['event_id']&&isset($params['event_status_code'])&&$params['event_status_code']&&isset($params['mark'])&&$params['mark'])
		{
			$data = array(
				'event_type' =>$params['event_type'],
				'event_id' =>$params['event_id'],
				'event_status_code' =>$params['event_status_code'],
				'mark' =>$params['mark']
			);
		}
		if($data)
		{
			if(isset($params['created_from']))
			{
				$data['created_from'] = $params['created_from'];
			}
			$fl_obj 	= new BoeFlowLog();
			$fl_res 	= $fl_obj->saveInfo($params);
			return $fl_res;
		}
		return NULL;
	}
	/**
     * 获取面授课件报名数据
     * @param $courseId
     * @param $params
     * @return array
     */
    public function searchCourseEnroll($courseId, $params = NULL, $justReturnCount = false){
        $enrollModel = new Query();
        $enrollModel->from(BoeEnroll::tableName() . ' as len')
            ->leftJoin(FwUserDisplayInfo::tableName() . ' as t1', 't1.user_id = len.applier_id')
            ->distinct()
            ->select('t1.real_name,t1.orgnization_name,t1.orgnization_name_path,t1.user_no,t1.location,t1.position_name,t1.email,
t1.mobile_no,t1.payroll_place,t1.payroll_place_txt,
len.kid,len.lce_id,len.applier_id,len.applier_at,len.is_agent,len.agent_id,len.is_charge,
len.is_oa,enroll_status_code,
enroll_mark,t1.position_mgr_level_txt');
        $enrollModel->andWhere("len.is_deleted='0'")
            ->andWhere("t1.status='1' and t1.is_deleted='0'");
        if (!empty($params['keyword'])) {
            $params['keyword'] = trim($params['keyword']);
            $enrollModel->where("t1.real_name like '%{$params['keyword']}%' or t1.user_no like '%{$params['keyword']}%' or t1.orgnization_name like '%{$params['keyword']}%' or t1.position_name like '%{$params['keyword']}%'");
        }
        $enrollModel->andFilterWhere(['=', 'len.event_id', $courseId])
            ->andFilterWhere(['=', 'len.is_deleted', '0']);
		
		if (isset($params['filter']) && $params['filter']>1) {
			$enrollModel->andFilterWhere(['=', 'enroll_status_code', $params['filter']]);
		}
        $count = $enrollModel->count();
        if ($justReturnCount) {
            return $count;
        }

        if (isset($params['showAll']) && $params['showAll'] === 'True') {
            $pages = new TPagination(['defaultPageSize' => $count, 'totalCount' => $count]);
            $data = $enrollModel->all();
        } else {
            $pages = new TPagination(['defaultPageSize' => 12, 'totalCount' => $count]);
            $data = $enrollModel->offset($pages->offset)->limit($pages->limit)->all();
        }
        $result = array(
            'count' => $count,
            'pages' => $pages,
            'data' => $data,
        );
        return $result;
    }
	

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
}

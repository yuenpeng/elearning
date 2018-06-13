<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\models\framework\FwUserDisplayInfo;
use common\models\framework\FwOrgnization;
use common\models\framework\FwDictionary;
use common\models\framework\FwDictionaryCategory;
use common\models\learning\LnCourse;
use common\models\message\MsPushMsg;
use common\models\message\MsPushMsgObject;
use common\models\message\MsPushMsgResult;
use common\models\boe\BoeBp;
use common\base\BoeBase;
use common\services\boe\BoeBaseService;
use yii\db\Query;
use Yii;

/**
 * 审批系统API
 * @author xinpeng
 */
class BoeApprovalService {
    private static $cacheTime = 0;
    private static $cacheNameFix = 'boe_approval_';
	private static $course_allow_to_user = '课程 《{courseName}》 报名已通过审批';
	private static $course_disallow_to_user = '课程 《{courseName}》 报名未通过审批';
	private static $user_list = 
	'<?xml version="1.0" encoding="UTF-8"?>
<java version="1.7.0_121" class="java.beans.XMLDecoder">
 <object class="com.hp.appfw.service.pushmsg.Reciptions">
  <void property="email">
   <string>{user_email_list}</string>
  </void>
 </object>
</java>';
	
	
	/*
	 * 获取系统的邮件模板配置信息
	 * eln_fw_dictionary_category(数据表) cate_code(字段) email_template(值)
	 * eln_fw_dictionary(数据表) dictionary_code(字段) ） 
	 * @author xinpeng
	*/
	public static function getEmailTemplateConfig()
	{
		$template_config	=  array();
		$category_info		=  FwDictionaryCategory::findOne(array('cate_code'=>'email_template','is_deleted'=>'0'));
		if(isset($category_info['kid'])&&$category_info['kid'])
		{
			$config_info = FwDictionary::find()->where(array(
			'dictionary_category_id'=>$category_info['kid'],
			'is_deleted'=>'0',
			'`status`'	=>'1'
			))->orderBy('sequence_number asc')->all();
			if($config_info)
			{
				$config_array	= array();
				foreach($config_info as $c_key=>$c_value)
				{
					$config_array[$c_value['dictionary_code']] = array(
						'dictionary_code'	=> $c_value['dictionary_code'],
						'dictionary_value'	=> $c_value['dictionary_value'],
						'dictionary_name'	=> $c_value['dictionary_name'],
					);
				}
				$template_config = $config_array;
			}
		}
		return $template_config;
	}
	
	/*
	 * 获取系统的邮件抄送配置信息
	 * eln_fw_dictionary_category(数据表) cate_code(字段) email_cc(值)
	 * eln_fw_dictionary(数据表) dictionary_code(字段) 
	   is_cc_self 抄送自己
	   is_cc_manager 抄送学员领导
	   is_cc_hrbp 抄送HRBP（培训担当） 
	 * @author xinpeng
	*/
	public static function getEmailCCConfig()
	{
		$cc_config	= array(
			'is_cc_self' 	=>0,
			'is_cc_manager'	=>0,
			'is_cc_hrbp'	=>0
		);
		$category_info	=  FwDictionaryCategory::findOne(array('cate_code'=>'email_cc','is_deleted'=>'0'));
		if(isset($category_info['kid'])&&$category_info['kid'])
		{
			$config_info = FwDictionary::findAll(array(
			'dictionary_category_id'=>$category_info['kid'],
			'is_deleted'=>'0',
			'`status`'	=>'1'
			));
			if($config_info)
			{
				$config_array	= array();
				foreach($config_info as $c_key=>$c_value)
				{
					$config_array[$c_value['dictionary_code']] = $c_value['dictionary_value'];
				}
				$cc_config = array_merge($cc_config,$config_array);
			}
		}
		return $cc_config;
	}
	/*
	 * 根据课程ID和用户ID来查询对应的消息推送信息（邮件）
	 * $course_id 课程ID
	 * $user_id 学员ID
	 * $data_type 数据类别 1是审核通过 3是审核拒绝
	 * @author xinpeng
	*/
	public static function getMsPushMsgInfoFromCourse($course_id,$user_id,$data_type)
	{
		if(!$course_id || !$user_id || !$data_type)
		{
			return -86;
		}
		$course_info = LnCourse::findOne(array('kid'=>$course_id,'is_deleted'=>'0'));
		if($data_type ==1)
		{
		$msg_title	 = str_replace("{courseName}",$course_info['course_name'],self::$course_allow_to_user);
		}else{
		$msg_title	 = str_replace("{courseName}",$course_info['course_name'],self::$course_disallow_to_user);	
		}
		//return $msg_title;
		$m	= MsPushMsg::realTableName();
		$o	= MsPushMsgObject::realTableName();
		$sql 	= "
			 select m.kid,m.title from {$m} m
inner join {$o} o on o.push_msg_id = m.kid
where m.title = '{$msg_title}' and o.obj_id ='{$user_id}' and o.obj_type ='4';";
		$connection		= Yii::$app->db;
		$msg_info		= $connection->createCommand($sql)->queryOne();
		return 	$msg_info;
	}
	/*
	 * 获取课程创建者信息
	 * $course_id 课程ID
	 * @author xinpeng
	*/
	public static function getCourseCreateInfo($course_id)
	{
		if(!$course_id)
		{
			return -84;
		}
		$course_info 	= LnCourse::findOne(array('kid'=>$course_id,'is_deleted'=>'0'));
		if(!isset($course_info['created_by'])|| !$course_info['created_by'])
		{
			return -83;
		}
		$create_info	= FwUser::findOne(array('kid'=>$course_info['created_by'],'is_deleted'=>'0'));
		return $create_info;
	}
	
	/*
	 * 修改消息推送信息（邮件）[批量]
	 * $params['course_id'] 课程ID
	 * $params['user_id'] 学员ID(数组)
	 * $params['data_type'] 数据类别 1是审核通过 3是审核拒绝
	 * @author xinpeng
	*/
	public static function updateBatchMsPushMsg($params)
	{
		if(!isset($params['course_id']) || !isset($params['user_id']) || !isset($params['data_type']) || 
		!$params['course_id'] || !$params['user_id'] || !$params['data_type'])
		{
			return -86;
		}
		if(!is_array($params['user_id']))
		{
			return -82;
		}
		foreach($params['user_id'] as $u_key=>$u_value )
		{
			$new_params = $params;
			$new_params['user_id'] = $u_value;
			self::updateMsPushMsg($new_params);
		}
		return 1;	
	}
	
	
	/*
	 * 修改消息推送信息（邮件）[单一]
	 * $params['course_id'] 课程ID
	 * $params['user_id'] 学员ID
	 * $params['data_type'] 数据类别 1是审核通过 3是审核拒绝
	 * @author xinpeng
	*/
	public static function updateMsPushMsg($params)
	{
		if(!isset($params['course_id']) || !isset($params['user_id']) || !isset($params['data_type']) || 
		!$params['course_id'] || !$params['user_id'] || !$params['data_type'])
		{
			return -86;
		}
		$msg_info = self::getMsPushMsgInfoFromCourse($params['course_id'],$params['user_id'],$params['data_type']);
		if(!isset($msg_info['kid']) || !$msg_info['kid'])
		{
			return -85;
		}
		//获取邮件抄送系统配置
		$cc_config 	= self::getEmailCCConfig();
		$params		= array_merge($params,$cc_config);
		
		//$model 	= MsPushMsg::findOne(array('kid'=>$msg_info['kid']));
		$user_email ="";
		$ms_data	= array();
		if(isset($params['is_cc_self'])&&$params['is_cc_self']==1)
		{
			//$model->cc_self = $params['is_cc_self'];
			$ms_data['cc_self'] = $params['is_cc_self'];	
		}
		if(isset($params['is_cc_manager'])&&$params['is_cc_manager']==1)
		{
			//$model->cc_manager = $params['is_cc_manager'];
			$ms_data['cc_manager'] = $params['is_cc_manager'];
		}
		if(isset($params['is_cc_hrbp'])&&$params['is_cc_hrbp']==1)
		{
			$hrbp = self::getUserBp($params['user_id']);
			//在推送消息对象中增加一条记录
			if(isset($hrbp[0]['user_id'])&&$hrbp[0]['user_id']&&trim($hrbp[0]['user_id'])&&isset($hrbp[0]['email'])&&$hrbp[0]['email']&&trim($hrbp[0]['email']))
			{
				$hrbp_kid	= $hrbp[0]['user_id'];
				//MsPushMsgObject
				$mpo	= new MsPushMsgObject();
				$mpo->push_msg_id		= $msg_info['kid'];
				$mpo->push_flag			= '1';//抄送
				$mpo->obj_flag			= '0';//系统对象
				$mpo->obj_type			= '4';//个人
				$mpo->obj_range			= '1';//不含子节点
				$mpo->obj_id			= $hrbp_kid;//个人KID
				$sult 					= $mpo->save();
			}		
		}
		//$model->send_method = '0';//发送方式：群发
		//$model->sender_id = NULL;//发件人修改为空
		//$model->update();
		$ms_data['send_method'] = '0';
		//$ms_data['sender_id'] = NULL;
		$ms	= new MsPushMsg();
		$ms->updateAll($ms_data, " kid = '{$msg_info['kid']}'");
		return 1;
	}

    /*
     * 基础型邮件信息推送
	 * $params 参数
	   $params['sender'] 发件人邮箱
	   $params['addressee'] 收件人邮箱
	   $params['mail_subject'] 邮件标题
	   $params['mail_content'] 邮件内容
	   $params['mail_type'] 邮件内容类型 1、文本型 2、html型
	 * @author xinpeng
     */
    public static function baseEmailSend($params) {
        //发件人信息不能为空
		if(!isset($params['sender']) || !$params['sender'])
		{
			return -99;	
		}
		//收件人信息不能为空
		if(!isset($params['addressee']) || !$params['addressee'])
		{
			return -98;
		}
		//邮件主题/标题不能为空
		if(!isset($params['mail_subject']) || !$params['mail_subject'])
		{
			return -97;
		}
		//邮件内容不能为空
		if(!isset($params['mail_content']) || !$params['mail_content'])
		{
			return -96;
		}
		//邮件内容类型
		if(!isset($params['mail_type']) || !$params['mail_type'])
		{
			return -95;
		}
		$mail	= Yii::$app->mailer->compose();
		$mail->setFrom($params['sender']); 
		$mail->setTo($params['addressee']);  
		$mail->setSubject($params['mail_subject']);
		if($params['mail_type'] ==1)
		{
			$mail->setTextBody($params['mail_content']);   //发布纯文字文本
		}else{
			$mail->setHtmlBody($params['mail_content']);    //发布可以带html标签的文本
		}
		if($mail->send())  
		{
			return 1;
		}else{
			return -94;
		}
    }
	
	 /*
     * 模板邮件信息推送
	 * $params 参数
	   $params['template_value'] 邮件模板值
	   $params['course_tag'] 课程ID/编号
	   $params['user_tag'] 用户ID/工号
	 * @author xinpeng
     */
    public static function templateEmailSend($params) {
        //邮件模板值不能为空
		if(!isset($params['template_value']) || !$params['template_value'])
		{
			return -81;
		}
		//课程ID/编号不能为空
		if(!isset($params['course_tag']) || !$params['course_tag'])
		{
			return -80;
		}
		//用户ID/工号不能为空
		if(!isset($params['user_tag']) || !$params['user_tag'])
		{
			return -79;
		}
		//邮件主题/标题不能为空
		if(!isset($params['mail_subject']) || !$params['mail_subject'])
		{
			return -97;
		}
		$w_course	= array(
			'and',
			"`is_deleted` = '0'",
			array(
				'or',
				"`kid` = '{$params['course_tag']}'",
				"`course_code` =  '{$params['course_tag']}'"
			)
		);
		$w_user	= array(
			'and',
			"`is_deleted` = '0'",
			array(
				'or',
				"`kid` = '{$params['user_tag']}'",
				"`user_no` =  '{$params['user_tag']}'"
			)
		);
		$course		= LnCourse::find()->where($w_course)->one();
		$user		= FwUser::find()->where($w_user)->one();
		$user_from	= FwUser::find()->where(array('kid'=>$course['created_by'],'is_deleted'=>'0'))->asArray()->one();
		if(!isset($course['kid']) || !isset($user['kid']))
		{
			return -77;
		}
		if(!isset($user['email']) || !trim($user['email']))
		{
			return -76;
		}
		if(!isset($user_from['email']) || !trim($user_from['email']))
		{
			return -75;
		}
		$mail		= Yii::$app->mailer->compose($params['template_value'],array('course'=>$course,'user'=>$user));
		$mail->setFrom($user_from['email']); 
		$mail->setTo($user['email']);  
		$mail->setSubject($params['mail_subject']);
		if($mail->send())  
		{
			return 1;
		}else{
			return -74;
		}
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
			$user_info			= FwUser::findOne(array('kid'=>$user_id,'is_deleted'=>'0'));
			if(!isset($user_info['kid']))
			{
				return -93;
			}
			$orgnization_id		= $user_info['orgnization_id'];
		}
		elseif($user_no)
		{
			$user_info			= FwUser::findOne(array('user_no'=>$user_no,'is_deleted'=>'0'));
			if(!isset($user_info['kid']))
			{
				return -92;
			}
			$orgnization_id		= $user_info['orgnization_id'];
		}elseif($org_id)
		{
			$orgnization_id		= $org_id;
		}else
		{
			return -91;;
		}
		$orgnization_info		= FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
		if(!isset($orgnization_info['kid']))
		{
			return -90;
		}
		$parent_orgnization_id 	=$orgnization_info['parent_orgnization_id'];
		$org_data				= array();
		$org_data['id'][0]		= $orgnization_id;
		$org_data['name'][0]	= $orgnization_info['orgnization_name'];
		$i					= 1;
		while ($parent_orgnization_id){
				$orgnization_id 		= $parent_orgnization_id;
				$orgnization_info		= FwOrgnization::findOne(array('kid'=>$orgnization_id,'is_deleted'=>'0'));
				if(!isset($orgnization_info['kid']))
				{
					return -89;
				}
				$org_data['id'][$i]		= $orgnization_id;
				$org_data['name'][$i]	= $orgnization_info['orgnization_name'];
				$parent_orgnization_id 	=$orgnization_info['parent_orgnization_id'];
				$i++;
		}
		$org_id_path	= $org_data['id'];
		$org_name_path	= $org_data['name'];
		krsort($org_id_path);
		krsort($org_name_path);
		$org_id_path	= implode("/",$org_id_path);
		$org_name_path	= implode("/",$org_name_path);
		$org_data['org_id_path']	= $org_id_path;
		$org_data['org_name_path']	= $org_name_path;
		return 	$org_data;
	}
	
	/*
	 * 获取学员对应的HRBP[单个用户]
	 * $user_id 用户ID
	 * $org_id 组织ID
	 * $user_no 员工工号
	 * @author xinpeng
	*/
	public static function getUserBp($user_id = NULL,$org_id = NULL,$user_no = NULL){
		
		$orgArray		= $user_bp	= array();
		if($user_id)
		{
			$orgArray	= self::getOrgPath($user_id);
		}
		elseif($user_no)
		{
			$orgArray	= self::getOrgPath(NULL,NULL,$user_no);
		}elseif($org_id)
		{
			$orgArray	= self::getOrgPath(NULL,$org_id,NULL);
		}else
		{
			return -88;
		}
		if(!is_array($orgArray)&&$orgArray<0)
		{
			return $orgArray;
		}
		$org_id	= $orgArray['id'];
		krsort($org_id);
		$org_id	= "'".implode("','",$org_id)."'";
		$sql_bp ="
		SELECT b.kid,b.orgnization_id,b.orgnization_name_path,b.user_id,u.user_no,u.user_name,u.real_name,u.email 
		FROM eln_boe_bp b 
		INNER JOIN eln_fw_user u ON u.kid = b.user_id AND u.is_deleted = '0'
		INNER JOIN eln_fw_orgnization o ON o.kid = b.orgnization_id AND o.is_deleted = '0'
		WHERE b.is_deleted = '0' and b.orgnization_id in ({$org_id})
		ORDER BY o.orgnization_level desc;  
		";
		$connection		= Yii::$app->db;
		$user_bp		= $connection->createCommand($sql_bp)->queryAll();
		if(!$user_bp)
		{
			return $orgArray['org_name_path'];
		}
		return $user_bp;
	}
	
	/*
	  * 获取学员对应的HRBP[多个学员信息]
	*/
	public static function getUserBpEmail($user_id = array()){
		if(!$user_id)
		{
			return -87;
		}
		$bpArray	= array();
		foreach($user_id as $u_key=>$u_value )
		{
			$bp_info	= self::getUserBp($u_value);
			//$bpArray[$u_value] = $bp_info;
			if(isset($bp_info[0]['email'])&&$bp_info[0]['email'])
			{
				$bpArray[$bp_info[0]['user_id']] = $bp_info[0]['email'];
			}	
		}
		$bpArray = array_values($bpArray);
		$bpArray = implode(";",$bpArray);
		return $bpArray;
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
}

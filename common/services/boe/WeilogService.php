<?php

namespace common\services\boe;

use common\services\interfaces\service\RightInterface;
use common\models\framework\FwUser;
use common\base\BoeBase;
use common\models\boe\BoeWeilog;
use common\models\boe\BoeWeilogGroup;
use common\models\boe\BoeWeilogUser;
use common\models\boe\BoeBadword;
use common\models\boe\BoeWeilogNotice;
use common\services\boe\BoeBaseService;
use yii\db\Query;
use Yii;

/**
 * 京东方大学微日志相关
 * @author xinpeng
 */
class WeilogService {

    static $loadedObject = array();
    static $_env = array();
    private static $cacheTime = 0;
    private static $userInfoCacheTime = 600;
    private static $cacheNameFix = 'weilog_';

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
     * 根据用户ID获取该用户页面入口信息
     */
	 public static function getUserEntry($user_id=NULL)
	 {
		  $entry		= -1;
		  if($user_id)
		  {
			  $user_group = self::getUserALLGroupInfo($user_id);
			  $group_num	= 0;
			  if(isset($user_group)&&is_array($user_group))
			  {
				  $group_num = count($user_group);
				  if($group_num > 1)
				  {
					  $entry = 4;//单一账号多个群组页面入口
				  }
				  if($group_num == 1)
				  {
					  $user_group 		=  array_values($user_group);
					  $user_group 		=  $user_group[0];
					  $user_type_num 	=  count($user_group);
					  if ($user_type_num > 1)
					  {
						  $entry = 4;//单一账号单个群组多个身份页面入口
					  }
					  if($user_type_num == 1)
					  {
						  $entry_1	= array_keys($user_group);
						  $entry = $entry_1[0];
					  }
				  }
			  }
		  }
		  return $entry;
	 }

	 /*
     * 根据用户ID获取该用户所有群组及对应的群内成员身份信息
     */
	 public static function getUserALLGroupInfo($user_id=NULL)
	 {
	 	  if(!$user_id)
		  {
			  return -101;
		  }
		  //$user_type	= array(0=>'普通成员',1=>'观察员',2=>'评论员',3=>'群主');
		  $where  = array('user_id'=>$user_id);
		  $db_obj = new BoeWeilogUser();
		  $p = array(
			  'condition' => array($where),
			  'returnTotalCount' => 1,
			  'indexby' => 'kid',
		  );
		  $sult 	= $db_obj->getList($p);
		  $sult2	= array();
		  if(isset($sult['list'])&&$sult['list'])
		  {
			  foreach($sult['list'] as  $s_key=>$s_value)
			  {
				   $new_key	= $s_value['group_id'];
				   $sult2[$new_key][$s_value['user_type']] = $s_value;
			  }
			  $sult['list'] = $sult2;
		  }
		  return $sult['list'];
	 }

	 /*
	  * 根据群组ID获取所有群组所有成员信息
	  * is_all 参数为0时只获取该群组内的普通成员和观察员，为1时获取的是群组内的所有成员（包含群主和评论员信息）
	 */
	 public static function getUsersFromGroup($group_id=NULL,$is_all=0)
	 {
		 if(!$group_id)
		 {
			 return -87;
		 }
		 if($is_all==1)
		 {
			 $where  = array('group_id'=>$group_id);
		 }
		 elseif($is_all==2){
			 $where  = array(
			 	'and',
				 array('=','group_id',$group_id),
				 array('=','user_type',0),
			 );
		 }
		 else
		 {
			 $where  = array(
			 	'and',
				 array('=','group_id',$group_id),
				 array('in','user_type',array(0,1)),
			 );
		 }
		 $db_obj = new BoeWeilogUser();
		 $p = array(
			  'condition' => array($where),
			  'returnTotalCount' => 1,
			  'indexby' => 'kid',
			  'orderby'	=>'user_type desc',
		 );
		 $sult 	= $db_obj->getList($p);
		 $user_array	= array();
		 foreach($sult['list'] as $s_key=>$s_value)
		 {
			 $user_array[$s_value['user_id']]	= $s_value['user_id'];
			 if($s_value['user_type']==0&&$s_value['comment_id'])
			 {
				 $user_array[$s_value['comment_id']]	= $s_value['comment_id'];
			 }
		 }
		 $sult['user_data'] = BoeBaseService::getMoreUserInfo($user_array, 1);
		 return $sult;
	 }

	/*
	 * 获取所有群组信息，索引为$key
	*/
	public static function getAllGroupInfoFromKey($key)
	{
		$key_array	= array('user_id','user_no','kid');
		if(!$key || !in_array($key,$key_array))
		{
			return false;
		}
		$sult		= array();
		$all_group	= self::getAllGroupInfo();
		foreach($all_group as $g_key=>$g_value)
		{
			if($key=='kid')
			{
				$sult[$g_value[$key]]	= $g_value;
			}
			else
			{
				$sult[$g_value[$key]][]	= $g_value;
			}
		}
		return $sult;
	}
	/*
	 * 获取所有群组信息
	*/
	public static function getAllGroupInfo()
	{
		 $db_obj 		= new BoeWeilogGroup();
		 $sult		 	= $db_obj->getList();
		 return $sult;
	}

	  /*
	  * 添加群组成员信息
	 */
	 public static function addGroupUser($params = array())
	 {
		 //判断传递信息是否存在
		 $user_type	= array(0,1,2,3);
		 if(!isset($params['user_no']) || !$params['user_no'])
		 {
			 return -100;
		 }
		 if(!isset($params['group_id']) || !$params['group_id'])
		 {
			 return -99;
		 }
		 if(!isset($params['user_type']) || !in_array($params['user_type'],$user_type))
		 {
			 return -98;
		 }
		 //判断该数据是否存在
		 $md		= self::mdUser($params);
		 $check		= self::checkGroupUserExist($md);
		 if($check!=1)
		 {
			 return $check;
		 }
		//需要保存的成员信息数据
		$data	= array(
			'user_no'		=>$params['user_no'],//成员工号
			'user_type'		=>$params['user_type'],//成员类型
			'group_id'		=>$params['group_id'],//所属群组ID
			'md'			=>$md,//md5
			'comment_id'	=>'',
			'comment_no'	=>'',
		);
		//根据用户工号获取该用户信息
		$user_info = self::getOneUserInfoFromUserNo($data['user_no']);
		if(!isset($user_info['kid']) || !$user_info['kid'])
		{
			return -96;//该工号不存在
		}
		$data['user_id']	= $user_info['kid'];
		$data['keyword1']	= $user_info['orgnization_path']."##".$user_info['real_name']."##".$user_info['user_no'];
		//判断当前的成员信息类型
		if($data['user_type']==0)//当前成员为普通成员时
		{
			 if(isset($params['comment_no'])&&$params['comment_no'])//当前成员对应的评论员信息存在时
			 {
				  $comment_info = self::getOneUserInfoFromUserNo($params['comment_no']);
				  if(!isset($comment_info['kid']) || !$comment_info['kid'])
				  {
					  return -96;//该工号不存在
				  }
				  $data['comment_id']	= $comment_info['kid'];
				  $data['comment_no']	= $params['comment_no'];
				  $comment_data		= array(
					  'user_id'		=>$data['comment_id'],
					  'user_no'		=>$data['comment_no'],
					  'group_id'	=>$data['group_id'],
					  'user_type'	=>2,
					  'keyword1'	=>$comment_info['orgnization_path']."##".$comment_info['real_name']."##".$comment_info['user_no']
				  );
				  //保存评论员信息
				  $comment_sult		= self::manageGroupCommentator($comment_data);
				  if($comment_sult!=1)
				  {
					   return $comment_sult;//保存评论 员信息失败
				  }
				  unset($comment_info);
			 }
		}
		$sult = self::manageGroupUserSave($data);
		return $sult;
	 }
	 /*
	  * 保存存储成员信息
	 */
	 private static function manageGroupUserSave($data = array())
	 {
		 $userObj 	= new BoeWeilogUser();
		 $db_sult 	= $userObj->saveInfo($data);
		 if (is_array($db_sult) || $db_sult === false) {
			return -94;//存储成员信息失败
		 }
		 return  1;
	 }

	 /*
	  * 管理群组评论员信息
	 */
	 public static function manageGroupCommentator($data=array())
	 {
		 $md		= self::mdUser($data);
		 $check		= self::checkGroupUserExist($md);
		 if($check==1)
		 {
		 	$data['md'] 	= self::mdUser($data);
			if($data['md']<0)
			{
				 return $data['md'];
			}
			self::manageGroupUserSave($data);
		 }
		 return 1;
	 }
	 /*
	  * 群组成员索引信息md
	 */
	 public static function mdUser($data=array())
	 {
		 if(!isset($data['user_no']) || !$data['user_no'] || !isset($data['group_id']) || !$data['group_id'] ||
		 !isset($data['user_type']))
		 {
			return -93;
		 }
		 $md		= $data['user_no']."-".$data['group_id'].'-'.$data['user_type'];
		 $md		= md5($md);
		 return $md;
	 }
	/*
	  * 检查群组成员信息是否存在
	 */
	public static function checkGroupUserExist($md=NULL,$kid=NULL)
	{
		if(!$md)
		{
			return -92;
		}
		if($kid)
		{
			$where = array(
				'and',
				array('<>', 'kid', $kid),
				array('=', 'md', $md),
				array('=', 'is_deleted', 0),
			);
		}
		else
		{
			$where = array(
				'and',
				array('=', 'md', $md),
				array('=', 'is_deleted', 0),
			);
		}
		$userObj 	= new BoeWeilogUser();
		$find 		= $userObj->find(false)->where($where)->asArray()->one();
		if(isset($find['kid'])&&$find['kid'])
		{
			return -91;//该信息已存在
		}
		return 1;
	}
	 /*
	  * 根据条件获取当前成员的群组信息
	 */
	 public static function getUserInfo($user_id = NULL ,$user_type = -1)
	 {
		 if(!$user_id)
		 {
			 return -82;
		 }
		 if(!in_array($user_type,array(-1,0,1,2,3)))
		 {
		 	 return -98;
		 }
		 $where = array(
			  'and',
			  array('=', 'user_id', $user_id),
			  array('=', 'user_type', $user_type),
			  array('=', 'is_deleted', 0),
		  );
		 $userObj 			= new BoeWeilogUser();
		 $user_info 		= $userObj->find(false)->where($where)->asArray()->one();
		 $all_group 				= self::getAllGroupInfoFromKey('kid');
		 $user_info['group_name']	= $all_group[$user_info['group_id']]['group_name'];
		 return $user_info;

	 }
	 /*
	  * 根据群组成员md获取成员的信息
	 */
	 public static function getUserInfoFromMd($md=NULL)
	 {
		 if(!$md)
		 {
			 return -92;
		 }
		 $db_obj					= new BoeWeilogUser;
		 $where 					= array('md'=>$md);
		 $user_info					= $db_obj->find(false)->where($where)->asArray()->one();
		 $all_group 				= self::getAllGroupInfoFromKey('kid');
		 $user_info['group_name']	= $all_group[$user_info['group_id']]['group_name'];
		 return $user_info;
	 }

	 /*
	  * 根据群组成员kid获取成员的信息
	 */
	 public static function getUserInfoFromKid($kid=NULL)
	 {
		 if(!$kid)
		 {
			 return -85;
		 }
		 $db_obj					= new BoeWeilogUser;
		 $where 					= array('kid'=>$kid);
		 $user_info					= $db_obj->find(false)->where($where)->asArray()->one();
		 $other_info 				= self::getOneUserInfo($user_info['user_id']);
		 $user_info['real_name']	= $other_info['real_name'];
		 $user_info['form_name']	= $other_info['real_name']."(".$user_info['user_no'].")";
		 $user_info['comment_name']	= $user_info['comment_form_name'] = NULL;
		 if($user_info['comment_id'])
		 {
			 $other_info2 			= self::getOneUserInfo($user_info['comment_id']);
			 $user_info['comment_name']			= $other_info2['real_name'];
			 $user_info['comment_form_name'] 	= $other_info2['real_name']."(".$user_info['comment_no'].")";
		 }
		 return $user_info;
	 }
	 /*
	  * 添加群组信息
	 */
	 public static function addGroup($params = array())
	 {
		 if(!isset($params['user_no']) || !$params['user_no'])
		 {
			 return -100;
		 }
		 if(!isset($params['group_name']) || !$params['group_name'])
		 {
			 return -90;
		 }
		 $where 	= array('group_name'=>$params['group_name'],'is_deleted'=>0);
		 $groupObj 	= new BoeWeilogGroup();
		 $find 		= $groupObj->find(false)->where($where)->asArray()->one();
		 if($find['kid'])
		 {
			 return -89;//该群已存在
		 }
		 $data	= array(
			  'user_no'			=>$params['user_no'],
			  'group_name'		=>$params['group_name'],
		  );
		  $user_info = self::getOneUserInfoFromUserNo($data['user_no']);
		  if(!isset($user_info['kid']) || !$user_info['kid'])
		  {
			  return -74;//该工号不存在
		  }
		  $data['user_id']	= $user_info['kid'];
		  $data['keyword1']	= $user_info['orgnization_path']."##".$user_info['real_name']."##".$user_info['user_no'];
		  unset($user_info);
		  //return $data;
		  $groupObj 	= new BoeWeilogGroup();
		  $db_sult 		= $groupObj->saveInfo($data);
		  if (is_array($db_sult) || $db_sult === false) {
				return -88;
		  }
		  //把群主信息加入到群成员信息当中
		  $user_data	= array(
			  'user_no'			=>$data['user_no'],//成员工号
			  'user_type'		=>3,//成员类型
			  'group_id'		=>$db_sult,//所属群组ID
		  );
		  $user_sult	= self::addGroupUser($user_data);
		  return $user_sult;
	 }
	 /*
	  * 编辑群组信息
	 */
	public static function updateGroup($params = array())
	{
	 	if(!isset($params['user_no']) || !$params['user_no'])
		{
			 return -100;
		}
		if(!isset($params['group_name']) || !$params['group_name'])
		{
			 return -90;
		}
		if(!isset($params['kid']) || !$params['kid'])
		{
			 return -87;
		}
		$where_g = array(
			'and',
			array('=', 'group_name', $params['group_name']),
			array('<>', 'kid', $params['kid'])
		);
		$groupObj 	= new BoeWeilogGroup();
		$find_g 	= $groupObj->find(false)->where($where_g)->asArray()->one();
		//return $find_g;
		if(isset($find_g['kid'])&&$find_g['kid'])
		{
			return -89;//该群已存在
		}
		$data	= array(
			  'kid'				=>$params['kid'],
			  'user_no'			=>$params['user_no'],
			  'group_name'		=>$params['group_name'],
		 );
		$user_info = self::getOneUserInfoFromUserNo($data['user_no']);
		if(!isset($user_info['kid']) || !$user_info['kid'])
		{
			return -96;//该工号不存在
		}
		$data['user_id']	= $user_info['kid'];
		$data['keyword1']	= $user_info['orgnization_path']."##".$user_info['real_name']."##".$user_info['user_no']."##".$user_info['email'];
		$groupObj 		= new BoeWeilogGroup();
		$db_sult 		= $groupObj->saveInfo($data);
		if (is_array($db_sult) || $db_sult === false) {
			  return -86;//更新失败
		}
		unset($user_info,$groupObj,$db_sult);
		//更新群组群主信息到群成员信息表中
		$where = array(
			'and',
			array('=', 'group_id', $params['kid']),
			array('=', 'user_type', 3)
		);
		$userObj 		= new BoeWeilogUser();
		$find 			= $userObj->find(false)->where($where)->asArray()->one();
		if(!isset($find['kid']) || !$find['kid'])
		{
			return 	-91;
		}
		$user_data	= array(
			'kid'			=>$find['kid'],
			'user_no'		=>$data['user_no'],//成员工号
			'user_type'		=>3,//成员类型
			'group_id'		=>$params['kid'],//所属群组ID
		);
		$user_sult	= self::updateGroupUser($user_data);
		return $user_sult;
	}

	/*
	 * 编辑群组成员信息
	*/
	public static function updateGroupUser($params = array())
	{
		//判断传递信息是否存在
		 $user_type	= array(0,1,2,3);
		 if(!isset($params['user_no']) || !$params['user_no'])
		 {
			 return -100;
		 }
		 if(!isset($params['group_id']) || !$params['group_id'])
		 {
			 return -99;
		 }
		 if(!isset($params['user_type']) || !in_array($params['user_type'],$user_type))
		 {
			 return -98;
		 }
		 if(!isset($params['kid']) || !$params['kid'])
		 {
			   return -85;
		 }
		 //判断该数据是否存在
		 $md		= self::mdUser($params);
		 $check		= self::checkGroupUserExist($md,$params['kid']);
		 if($check!=1)
		 {
			 return $check;
		 }
		 //检查并处理原评论员信息
		 $comment_before	= self::setBeforeGroupCommentator($params);
		 //return $comment_before;
		//需要保存的成员信息数据
		$data	= array(
			'kid'			=>$params['kid'],
			'user_no'		=>$params['user_no'],//成员工号
			'user_type'		=>$params['user_type'],//成员类型
			'group_id'		=>$params['group_id'],//所属群组ID
			'md'			=>$md,//md5
		);
		//根据用户工号获取该用户信息
		$user_info = self::getOneUserInfoFromUserNo($data['user_no']);
		if(!isset($user_info['kid']) || !$user_info['kid'])
		{
			return -97;//该工号不存在
		}
		$data['user_id']	= $user_info['kid'];
		$data['keyword1']	= $user_info['orgnization_path']."##".$user_info['real_name']."##".$user_info['user_no']."##".$user_info['email'];
		//判断当前的成员信息类型
		if($data['user_type']==0)//当前成员为普通成员时
		{
			 if(isset($params['comment_no'])&&$params['comment_no'])//当前成员对应的评论员信息存在时
			 {
				  $comment_info = self::getOneUserInfoFromUserNo($params['comment_no']);
				  if(!isset($comment_info['kid']) || !$comment_info['kid'])
				  {
					  return -96;//该工号不存在
				  }
				  $data['comment_id']	= $comment_info['kid'];
				  $data['comment_no']	= $params['comment_no'];
				  $comment_data		= array(
					  'user_id'		=>$data['comment_id'],
					  'user_no'		=>$data['comment_no'],
					  'group_id'	=>$data['group_id'],
					  'user_type'	=>2,
					  'keyword1'	=>$comment_info['orgnization_path']."##".$comment_info['real_name']."##".$comment_info['user_no']."##".$comment_info['email']
				  );
				  //保存评论员信息
				  $comment_sult		= self::manageGroupCommentator($comment_data);
				  if($comment_sult!=1)
				  {
					  return -95;//保存评论员信息失败
				  }
				  unset($comment_info);
			 }
		}
		else
		{
			$data['comment_id']	= $data['comment_no'] =	NULL;
		}
		$sult = self::manageGroupUserSave($data);
		return $sult;
	}
	/*
	 * 检查并处理原评论员信息
	*/
	public static function setBeforeGroupCommentator($params=array())
	{
		if(!isset($params['kid']) || !$params['kid'])
		{
			return -85;
		}
		$db_obj = new BoeWeilogUser();
		$find	= $db_obj->getInfo($params['kid']);
		if(!isset($find['kid'])|| !$find['kid'] )
		{
			return -84;
		}
		//判定是普通成员S
		if($find['user_type']==0&&$find['comment_no']&&$find['comment_id'])
		{
			//判定修改后也是普通成员S
			if($params['user_type']==0&&isset($params['comment_no'])&&$params['comment_no'])
			{
				//判定修改前的用户工号和修改后的工号不一致时
				if($find['comment_no']!=$params['comment_no'])
				{
					self::updateGroupCommentator($find);
				}
			}
			//判定修改后也是普通成员E
		}
		//判定是普s通成员E
		return 1;
	}
	 /*
	  * 添加评论信息
	 */
	 public static function addWeilogComment($params = array())
	 {
		 if(!isset($params['kid']) ||  !$params['kid'])
		 {
			 return -78;
		 }
		 if(!isset($params['comment_show']))
		 {
			return -77;
		 }
		 if(!isset($params['comment_info']) || !$params['comment_info'])
		 {
			return -76;
		 }
		 $db_obj	= new BoeWeilog();
		 $info		= $db_obj->getInfo($params['kid']);

		 $where		= array(
		 	 'and',
			  array('=','group_id',$info['group_id']),
			  array('=','user_id',$info['created_by']),
			  array('=','comment_id',$info['comment_by']),
			  array('=','user_type',0),
			  array('=','is_deleted',0),
		 );

		 $user_obj	= new BoeWeilogUser();
		 $user_info	= $user_obj->find(false)->where($where)->asArray()->one();
		 if(!isset($user_info['comment_id']) || !$user_info['comment_id'])
		 {
			 return -75;
		 }
		 $current_at = time();
         $systemKey = $db_obj->getSystemKey();
         $ip = Yii::$app->getRequest()->getUserIP();
		 $data		= array(
		 	 'kid'				=>$params['kid'],
			 'comment_info'		=>$params['comment_info'],
			 'comment_show'		=>$params['comment_show'],
			 'comment_at'		=>$current_at,
			 'comment_from'		=>$systemKey,
			 'comment_ip'		=>$ip
		 );
		 $db_obj	= new BoeWeilog();
		 $sult		= $db_obj->saveInfo($data);
		 if (is_array($sult) || $sult === false) {
			return -74;//存储评论信息失败
		 }
		 else
		 {
			$info					= $db_obj->getInfo($params['kid']);
			$info['comment_time']	= date("Y-m-d H:i:s", $info['comment_at']);
			return $info;
		 }
	 }


    /*
     * 前台删除日志信息
     */
    public static function deleteLog($log_id, $user_id) {
        $deleteValue = 0;
        $sult = array(
            'deleteValue' => 0,
            'errorCode' => '',
        );
        if ($log_id) {
            $logObj = new BoeWeilog();
            $where = array(
                'kid' => $log_id,
                'created_by' => $user_id
            );
            $find = $logObj->find(false)->where($where)->asArray()->one();
            if ($find) {
                 $deleteValue = $logObj->deleteInfo($log_id);
            } else {
                $deleteValue = -4;
            }
        }
        $sult['deleteValue'] = $deleteValue;
        if ($deleteValue < 1) {
            switch ($deleteValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -1:
                    $sult['errorCode'] = Yii::t('boe', 'info_loss');
                    break;
                case -2: case -3:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $deleteValue;
                    break;
                case -4:
                    $sult['errorCode'] = Yii::t('boe', 'no_power');
                    break;
            }
        }
        return $sult;
    }

	/*
	 * 删除或是修改普通成员信息时对评论员信息的校验和修正
	*/
	public static function updateGroupCommentator($user_info = array())
	{
		if(!isset($user_info['user_type']) || $user_info['user_type']!=0  || !isset($user_info['comment_id'])
		|| !$user_info['comment_id'] || !isset($user_info['group_id']) || !$user_info['group_id'])
		{
			return -79;
		}
		$where  = array(
			'comment_id'	=>$user_info['comment_id'],
			'group_id'		=>$user_info['group_id'],
			'is_deleted'	=>0
		);
		$dbObj = new BoeWeilogUser();
		$total_num = $dbObj->find(false)->where($where)->asArray()->count();
		//判定修改前对应的评论员对应的普通成员数量
		if($total_num <= 1)
		{
			$where2 = array(
				'and',
				array('=', 'group_id', $user_info['group_id']),
				array('=', 'user_id', $user_info['comment_id']),
				array('=', 'user_type', 2)
			);
			$find_c 		= $dbObj->find(false)->where($where2)->asArray()->one();
			if(isset($find_c['kid'])&&$find_c['kid'])
			{
				$db_sult = $dbObj->deleteInfo($find_c['kid']);
			}
		}
		return 1;
	}
	/*
	 * 前台删除群组成员信息
	*/
	public static function deleteUser($user_kid, $user_id) {
        $deleteValue = 0;
        $sult = array(
            'deleteValue' => 0,
            'errorCode' => '',
        );
        if ($user_kid) {
			$dbObj = new BoeWeilogUser();
			$user_info	= $dbObj->getInfo($user_kid);
            $where = array(
                'user_id' 	=> $user_id,
                'group_id' 	=> $user_info['group_id'],
				'user_type'	=> 3
            );
            $find = $dbObj->find(false)->where($where)->asArray()->one();
            if ($find) {
                 //当前会员为普通成员并且含有评论员信息时
				 if($user_info['user_type']==0&&$user_info['comment_id'])
				 {
					  self::updateGroupCommentator($user_info);
				 }
				 $deleteValue = $dbObj->deleteInfo($user_kid);
            } else {
                $deleteValue = -4;
            }
        }
        $sult['deleteValue'] = $deleteValue;
        if ($deleteValue < 1) {
            switch ($deleteValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -1:
                    $sult['errorCode'] = Yii::t('boe', 'info_loss');
                    break;
                case -2: case -3:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $deleteValue;
                    break;
                case -4:
                    $sult['errorCode'] = Yii::t('boe', 'no_power');
                    break;
            }
        }
        return $sult;
    }
	/*
	 * 前台删除群组信息
	*/
	public static function deleteGroup($group_kid, $user_id) {
        $deleteValue = 0;
        $sult = array(
            'deleteValue' => 0,
            'errorCode' => '',
        );
        if ($group_kid) {
			$userObj 	= new BoeWeilogUser();
			$dbObj 		= new BoeWeilogGroup();
			//$group_info	= $dbObj->getInfo($group_kid);
            $where = array(
                'group_id' 	=> $group_kid,
				'user_type'	=>0,
				'is_deleted'=> 0
            );
            $user_num = $userObj->find(false)->where($where)->asArray()->count();
            if ($user_num>0) {
				$deleteValue = -4;
            } else {
                $where2 = array(
					'group_id' 	=> $group_kid,
					'user_type'	=>3,
					'is_deleted'=>0
				);
				$find	=  $userObj->find(false)->where($where2)->asArray()->one();
				if(isset($find['kid'])&&$find['kid'])
				{
					$userObj->deleteInfo($find['kid']);
				}
				$deleteValue = $dbObj->deleteInfo($group_kid);
            }
        }
        $sult['deleteValue'] = $deleteValue;
        if ($deleteValue < 1) {
            switch ($deleteValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -1:
                    $sult['errorCode'] = Yii::t('boe', 'info_loss');
                    break;
                case -2: case -3:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $deleteValue;
                    break;
                case -4:
                    $sult['errorCode'] = Yii::t('boe', 'boe_welog_group_has_user');
                    break;
            }
        }
        return $sult;
    }
    /*
     * 日志点赞
     * Input:$uid String
      $uid  String  Not NULL
      操作逻辑：
      1、根据UID和log_id判断有没有当前对应的用户对于指定的log_id有没有点过赞,如果有返回1,判断依据是缓存中有无记录
      2、更新数据库;
      3、添加缓存标记;
     */

    public static function likeLog($log_id, $user_id) {
        $likeValue = 0;
        $log_data = array();
        $sult = array(
            'likeValue' => 0,
            'errorCode' => '',
            'likeNum' => 0,
        );
        $cache_name = __METHOD__ . md5('_weilog_like_' . serialize($log_id));
        $log_data = Yii::$app->cache->get($cache_name);
        if (isset($log_data[$user_id]) && $log_data[$user_id] == 1) {
            $likeValue = -5;
        } else {
            if ($log_id) {
                $where = array('kid' => $log_id);
                $logObj = new BoeWeilog();
                $find = $logObj->find(false)->where($where)->asArray()->one();
                if ($find) {
                    $data = array(
                        'kid' => $log_id,
                        'like_num' => $find['like_num'] + 1
                    );
                    $likeValue = $logObj->saveInfo($data);
                    if ($likeValue) {
                        $sult['likeNum'] = $data['like_num'];
                        $likeValue = 1;
                    } else {
                        $likeValue = -6;
                    }
                }
            }
        }
        $sult['likeValue'] = $likeValue;
        if ($likeValue < 1) {
            switch ($likeValue) {
                case 0:
                    $sult['errorCode'] = Yii::t('boe', 'no_assgin_info');
                    break;
                case -5:
                    $sult['errorCode'] = Yii::t('boe', 'like_end');
                    break;
                case -6:
                    $sult['errorCode'] = Yii::t('boe', 'db_error') . $deleteValue;
                    break;
            }
        } else {
            $log_data[$user_id] = 1;
            Yii::$app->cache->set($cache_name, $log_data); // 设置缓存
        }
        return $sult;
    }

    /**
     * 获取单个用户的基本信息
     * @param type $uid
     * @param type $create_mode
     */
    public static function getOneUserInfoFromUserNo($user_no, $create_mode = 0) {
        if (!$user_no) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_user_no_' . $user_no;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if ($sult === NULL || $sult === false) {//从数据库读取
            $tmp_sult = BoeBaseService::getMoreUserInfoFromUserNo($user_no, 1);
            $sult = BoeBase::array_key_is_nulls($tmp_sult, $user_no, array());
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }
        return $sult;
    }


	/**
     * 获取单个用户的基本信息
     * @param type $uid
     * @param type $create_mode
     */
    public static function getOneUserInfo($uid, $create_mode = 0) {
        if (!$uid) {
            return NULL;
        }
        $cache_name = __METHOD__ . '_uid_' . $uid;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if ($sult === NULL || $sult === false) {//从数据库读取
            $tmp_sult = BoeBaseService::getMoreUserInfo($uid, 1);
            $sult = BoeBase::array_key_is_nulls($tmp_sult, $uid, array());
            self::setCache($cache_name, $sult, self::$userInfoCacheTime); // 设置缓存
        }
        return $sult;
    }

	/*
	 * 筛选群组列表
	*/
	public static function getGroupList($params = array()) {
        $sult 	= array();
        $where = array(
            'and',
        );
        //用户
        if (isset($params['user_id']) && $params['user_id']) {
            $where[] = array(is_array($params['user_id']) ? 'in' : '=', 'eln_boe_weilog_group.user_id', $params['user_id']);
        }
        $db_obj = new BoeWeilogGroup();
        $p = array(
            'condition' => array($where),
            'offset' => isset($params['offset']) && $params['offset'] ? $params['offset'] : 0,
            'limit' => isset($params['limit']) && $params['limit'] ? $params['limit'] : 0,
            'returnTotalCount' => 1,
            'orderBy' => 'created_at desc',
            'indexby' => 'kid',
        );
        $sult = $db_obj->getList($p);
        //组织路径
        if (isset($params['get_orgnization_path']) && $params['get_orgnization_path'] == 1 && isset($sult['list']) && is_array($sult['list'])) {
            $user_data = $user_array = array();
            foreach ($sult['list'] as $s_key => $s_value) {
                $user_array[$s_value['user_id']] = $s_value['user_id'];
                $sult['list'][$s_key]['create_time'] = date("Y-m-d H:i:s", $s_value['created_at']);
            }
            $sult['user_data'] = BoeBaseService::getMoreUserInfo($user_array, 1);
        }
        return $sult;
    }



    /*
     * 筛选日志列表
     * 	$params['stime']取值：string 开始时间
	 * 	$params['etime']取值：string 结束时间
     *  $params['group_id'] 取值：string or array 所属群组
	 *  $params['offset'] 取值：int
     *  $params['limit'] 取值：int
     *  $params['get_orgnization_path'] 取值：int
      	如果$params['get_orgnization_path']=1,那么在返回结果中会包括用户的组织路径信息
     */

    public static function getLogList($params = array(),$is_all_user=0) {
        $sult 	= array();
        $where  = array(
            'and',
            //array('=', 'eln_boe_weilog.is_deleted', 0),
        );
		//关键词
		if (isset($params['keyword']) && $params['keyword']) {
			$where[] = array(
				'or',
				array('like', 'content', '%' . $params['keyword'] . '%', false),
				array('like', 'keyword1', '%' . $params['keyword'] . '%', false)
			);
        }
		//开始时间
        if (isset($params['s_date']) && $params['s_date']) {
			$params['s_date']	= strtotime($params['s_date']."00:00");//开始时间
			$where[] = array('>=', 'eln_boe_weilog.created_at', $params['s_date']);
        }
		//结束时间
		if (isset($params['e_date']) && $params['e_date']) {
			$params['e_date']	= strtotime($params['e_date']."00:00")+86400;//结束时间
			$where[] = array('<', 'eln_boe_weilog.created_at', $params['e_date']);
        }
        //所属群组ID
        if (isset($params['group_id']) && $params['group_id']) {
            $where[] = array(is_array($params['group_id']) ? 'in' : '=', 'eln_boe_weilog.group_id', $params['group_id']);
        }
        //用户
        if (isset($params['user_id']) && $params['user_id']) {
            $where[] = array(is_array($params['user_id']) ? 'in' : '=', 'eln_boe_weilog.created_by', $params['user_id']);
        }
		//评论员
		if(isset($params['comment_by'])&&$params['comment_by'])
		{
			$where[] = array(is_array($params['comment_by']) ? 'in' : '=', 'eln_boe_weilog.comment_by', $params['comment_by']);
		}
		//是否已点评
		if(isset($params['comment_at']))
		{
			if($params['comment_at']>0)
			{
				$where[] = array('>', 'eln_boe_weilog.comment_at', 0);
			}
			else
			{
				$where[] = array('=', 'eln_boe_weilog.comment_at', 0);
			}
		}
        $db_obj = new BoeWeilog();
        $p = array(
            'condition' => array($where),
            'offset' => isset($params['offset']) && $params['offset'] ? $params['offset'] : 0,
            'limit' => isset($params['limit']) && $params['limit'] ? $params['limit'] : 0,
            'returnTotalCount' => 1,
            'orderBy' => 'created_at desc',
            'indexby' => 'kid',
        );
		//return $p;
        $sult = $db_obj->getList($p);
		$user_all		=array();
		if($is_all_user==1)
		{
			unset($p['offset'],$p['limit'],$p['returnTotalCount']);
			$p['select']	='created_by';
			$p['indexby']	='created_by';
			$user_all	= $db_obj->getList($p);
		}
        //组织路径
        if (isset($params['get_orgnization_path']) && $params['get_orgnization_path'] == 1 && isset($sult['list']) && is_array($sult['list'])) {
            $user_data = $user_array = array();
            foreach ($sult['list'] as $s_key => $s_value) {
                $user_array[$s_value['created_by']] = $s_value['created_by'];
                $sult['list'][$s_key]['create_time'] = date("Y-m-d H:i:s", $s_value['created_at']);
				if($s_value['comment_at']>0)
				{
					$sult['list'][$s_key]['comment_time'] = date("Y-m-d H:i:s", $s_value['comment_at']);
				}
				$sult['list'][$s_key]['comment_button_show'] = 0;
				if(isset($params['current_user_id']) && $params['current_user_id'] && $params['current_user_id']==$s_value['comment_by'])
				{
					$sult['list'][$s_key]['comment_button_show'] = 1;
				}
            }
            $sult['user_data'] = BoeBaseService::getMoreUserInfo($user_array, 1);
        }
		$data						=array(
			'totalCount'	=>$sult['totalCount'],
			'list'			=>$sult['list'],
			'user_data'		=>$sult['user_data'],
			'user_all'		=>$user_all
		);
        return $data;
    }

    /* 信息过滤 */

    public static function boeTrim($str, $remove = "") {
        $search = array(" ", "　", "\n", "\r", "\t");
        $replace = array("", "", "", "", "");
        $str = str_replace($search, $replace, $str);
        if ($remove) {
            $str = str_replace($remove, "", $str);
        }
        return $str;
    }

    /*     * ************************************敏感词汇的过滤****************************************************** */
    /*
     * 获取所有敏感词汇数组信息
     */

    public static function get_all_badword() {
        $sult = array();
        $cache_name = __METHOD__ . md5('_badword_all');
        $all_badword = Yii::$app->cache->get($cache_name);
        if ($all_badword) {
            $sult = $all_badword;
        } else {
            $badObj = new BoeBadword();
            $sult = $badObj->getList();
            Yii::$app->cache->set($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /*
     * 获取所有敏感词汇的索引数组信息
     */

    public static function get_all_badword_key() {
        $sult = array();
        $cache_name = __METHOD__ . md5('_badword_all_key');
        $all_badword_key = Yii::$app->cache->get($cache_name);
        if ($all_badword_key) {
            $sult = $all_badword_key;
        } else {
            $sult_all = self::get_all_badword();
            foreach ($sult_all as $s_key => $s_value) {
                $sult[$s_value['first']] = $s_value['first'];
            }
            $sult = array_keys($sult);
            Yii::$app->cache->set($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /*
     * 敏感词汇检测
     * 返回数组包含敏感词汇和原关键词
     */

    public static function check_bad_word($keyword = '') {
        if (!$keyword) {
            return -99;
        }
        $all_key = self::get_all_badword_key();
        $all_key = implode("|", $all_key);
        if ($all_key) {
            $all_key = "/{$all_key}/is";
            preg_match_all($all_key, $keyword, $bad_key);
            if (isset($bad_key[0]) && $bad_key[0] && is_array($bad_key[0])) {
                $check_p = array(
                    'index' => $bad_key[0],
                    'search_key' => $keyword,
                );
                $sult = self::check_badword_key($check_p);
                return $sult;
            }
        }
        return '';
    }

    /*
     * 敏感词汇检测 检测索引和对应的关键词
     */

    public static function check_badword_key($p = array()) {
        $sult = '';
        if (!isset($p) || !$p) {
            return -98;
        }
        if (!isset($p['index']) || !$p['index'] || !is_array($p['index'])) {
            return -97;
        }
        if (!isset($p['search_key']) || !$p['search_key'] || !is_string($p['search_key'])) {
            return -96;
        }
        $sult_badword = self::get_badword_from_key($p['index']); //获取的是数组
        //return $sult_badword;
        foreach ($sult_badword as $b_key => $b_value) {
            if (strpos($p['search_key'], $b_value) !== false) {
                $sult.=$b_value . ";";
            }
        }
        $sult = trim($sult, ";");
        if (is_int($sult) && $sult < 0) {
            $sult = '';
        }
        return $sult;
    }

    /*
     * 根据索引获得相关敏感词汇的相关数组
     */

    public static function get_badword_from_key($p = array()) {
        if (!isset($p) || !$p) {
            return -95;
        }
        $sult = array();
        foreach ($p as $key => $value) {
            if ($value) {
                $badword_array = self::get_badword_from_a_key($value);
                $sult = array_merge($sult, $badword_array);
            }
        }
        return $sult;
    }

    /*
     * 根据单个索引获取相关的敏感词汇的数组
     */

    public static function get_badword_from_a_key($key = NULL) {
        if (!isset($key) || !$key) {
            return -94;
        }
        $sult = array();
        $cache_name = __METHOD__ . md5('_badword_one_key_' . serialize($key));
        $badword = Yii::$app->cache->get($cache_name);
        if ($badword) {
            $sult = $badword;
        } else {
            $sult_all = self::get_all_badword();
            foreach ($sult_all as $s_key => $s_value) {
                if ($s_value['first'] == $key) {
                    $sult[] = $s_value['keyword'];
                }
            }
            Yii::$app->cache->set($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

    /*
     * 日志提示信息
     */
    public static function getWeilogNotice($current_date,$create_mode = 0) {
        if (!$current_date) {
          	$current_date = date("Y-m-d");
        }
        $cache_name = __METHOD__ . $current_date;
        if ($create_mode) {
            $sult = NULL;
        } else {
            $sult = self::getCache($cache_name); // 读取缓存 ,读取不到时返回false
        }
        if ($sult === NULL || $sult === false) {//从数据库读取
           	$obj = new BoeWeilogNotice();
           	$where = array('and',);
           	$where[] = array('=','is_deleted','0');
           	$where[] = array('<=','current_date',$current_date);
            $sult = $obj->find(false)->select(['notice','current_date'])->where($where)->orderBy('current_date desc')->asArray()->one();
            self::setCache($cache_name, $sult); // 设置缓存
        }
        return $sult;
    }

}

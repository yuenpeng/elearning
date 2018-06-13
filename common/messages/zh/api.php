<?php

return array(
//area Common
//area A
//area B
//area C
//area D
//area E
    'err_followed' => '该用户已被关注过，不需重复关注!',
    'err_followed_self' => '不能关注自己',
    'err_course_sign' => '签到失败,该课程还未开课!',
    'err_course_sign_end' => '签到失败,该课程已结束!',
	'java_name'			=>'boeu-api',
  	'java_partnerId'	=>'10001',
  	'java_list_url'		=>'http://120.131.3.113:8888/student/pageList',
  	'java_view_url'		=>'http://120.131.3.113:8888/student/query',
  	'java_update_url'	=>'http://120.131.3.113:8888/student/update',
	'java_push_oa_url'	=>'http://120.131.3.113:8888/oa/sendApprovalRequest',
	'err_flow'=>array(
		-102 =>array('code'=>-102,'msg'=>'OA审批数据推送失败，请您再次提交重试一下(-102)','mark'=>'JavaException'),
		-101 =>array('code'=>-101,'msg'=>'OA审批数据接口异常,请检查数据接口(-101)','mark'=>'JavaException'),
		-100 =>array('code'=>-100,'msg'=>'获取信息失败,请检查链接及参数是否准确(-100)','mark'=>'PhpException'),
		-99  =>array('code'=>-99,'msg'=>'获取信息报错,错误编码不存在(-99)','mark'=>'PhpException'),
		-84	=>array('code'=>-84,'msg'=>'该报名信息已审批(-84)','mark'=>'PhpException'),
		-83	=>array('code'=>-83,'msg'=>'未查询到对应的报名流程信息(-83)','mark'=>'PhpException'),
		-82	=>array('code'=>-82,'msg'=>'未查询到对应的报名信息(-82)','mark'=>'PhpException'),
		-81	=>array('code'=>-81,'msg'=>'获取用户信息失败(-81)','mark'=>'PhpException'),
		-80	=>array('code'=>-80,'msg'=>'获取课程信息失败(-80)','mark'=>'PhpException'),
		-79	=>array('code'=>-79,'msg'=>'获取前置任务信息失败(-79)','mark'=>'PhpException'),
		-78	=>array('code'=>-78,'msg'=>'发起OA审批失败,请检查接口及相应错误日志(-78)','mark'=>'PhpException'),
		
		0 	=>array('code'=>0,'msg'=>'OK','mark'=>'成功'),
		100 =>array('code'=>100,'msg'=>'fail','mark'=>'失败'),
		10001 =>array('code'=>10001,'msg'=>'签名信息不匹配','mark'=>'JavaException'),
		10002 =>array('code'=>10002,'msg'=>'数据已存在','mark'=>'JavaException'),
		10003 =>array('code'=>10003,'msg'=>'数据不存在','mark'=>'JavaException'),
		10004 =>array('code'=>10004,'msg'=>'此审批已经成功发送给oa并返回审批单号','mark'=>'JavaException'),
		10005 =>array('code'=>10005,'msg'=>'Boeu-api接收数据成功但发送oa失败','mark'=>'JavaException'),
		
		10001 =>array('code'=>10001,'msg'=>'该用户已经离职','mark'=>'JavaException'),
		10002 =>array('code'=>10002,'msg'=>'该用户属于劳务派遣','mark'=>'JavaException'),
		10003 =>array('code'=>10003,'msg'=>'该用户已经退休','mark'=>'JavaException'),
		10004 =>array('code'=>10004,'msg'=>'该用户未入职','mark'=>'JavaException'),
		10005 =>array('code'=>10005,'msg'=>'该用户已经内退','mark'=>'JavaException'),
		10006 =>array('code'=>10006,'msg'=>'java.lang.NullPointerException','mark'=>'JavaException'),
		10007 =>array('code'=>10007,'msg'=>'Expected one result (or null) to be returned by selectOne(), but found: 2','mark'=>'JavaException'),
		10008 =>array('code'=>10008,'msg'=>'其他原因','mark'=>'JavaException'),
		
		20001 =>array('code'=>20001,'msg'=>'记录不存在','mark'=>'ApiException'),
		20002 =>array('code'=>20002,'msg'=>'存在相同的账户名','mark'=>'ApiException'),
		20003 =>array('code'=>20003,'msg'=>'存在相同的岗位代码','mark'=>'ApiException'),
		20004 =>array('code'=>20004,'msg'=>'存在相同的组织部门代码','mark'=>'ApiException'),
		20005 =>array('code'=>20005,'msg'=>'用户需要设定域','mark'=>'ApiException'),
		20006 =>array('code'=>20006,'msg'=>'传入参数错误','mark'=>'ApiException'),
		20007 =>array('code'=>20007,'msg'=>'数据并发','mark'=>'ApiException'),
		20008 =>array('code'=>20008,'msg'=>'接口程序损坏','mark'=>'ApiException'),
		20009 =>array('code'=>20009,'msg'=>'其他原因','mark'=>'ApiException'),
	),
//area F
//area G
//area H
//area I
//area J
//area K
//area L
//area M
//area N
//area O
//area P
//area Q
//area R
//area S
//area T
//area U
//area V
//area W
//area X
//area Y
//area Z
);

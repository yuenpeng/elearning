<?php

namespace common\helpers;


class TLadpHelper
{
	
	const SUFFIX="@CORP.JBCPPETS.COM";
	const URL="ldap://192.168.127.134:389";
	
	
	public function validatePawdbyLadp($username,$password){
		$ldap_conn = ldap_connect(TLadpHelper::URL);
		$validatePasswordResult=false;
		$bd = ldap_bind($ldap_conn,$username.TLadpHelper::SUFFIX,$password);
		if(ldap_errno($ldap_conn)!=0)
		{
			$validatePasswordResult=false;
		}
		else
		{
			$validatePasswordResult=true;
		}
		ldap_unbind($ldap_conn) or die("Can't unbind from LDAP server."); //与服务器断开连接
		return $validatePasswordResult;
	}
	
}
?>
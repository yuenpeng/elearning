<?php
/**
 * Created by PhpStorm.
 * User: tangming
 * Date: 4/14/2015
 * Time: 8:00 PM
 */
namespace common\helpers;

class TNetworkHelper
{

    public static function getClientRealIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
        {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
        {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        }
        return $ip;
    }

    public static function getClientMacAddress()
    {
        @exec("arp -a", $array); //执行arp -a命令，结果放到数组$array中

        $mac = null;
        foreach ($array as $value) {
            if ( //匹配结果放到数组$mac_array
                strpos($value, $_SERVER["REMOTE_ADDR"]) &&
                preg_match("/(:?[0-9a-f]{2}[:-]){5}[0-9a-f]{2}/i", $value, $mac_array)
            ) {
                $mac = $mac_array[0];
                break;
            }
        }
        return $mac; //输出客户端MAC
    }


    /**
     * 发送Json对象数据
     *
     * @param $url 请求url
     * @param $data 参数数组
     * @return array
     */
    public static function HttpPost($url, $data)
    {
        $jsonStr = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );

        $return_content = curl_exec($ch);
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

        curl_close($ch);

        $result = array();
        $result['content'] = $return_content;
        $result['code'] = $return_code;
        $result['time'] = $total_time;

        return $result;
    }

    /**
     * 发送Json对象数据
     *
     * @param $url 请求url
     * @param $data 参数数组
     * @return array
     */
    public static function HttpGet($url, $params)
    {
        //amended by baoxianjian 15:10 2016/1/26
        if(is_array($params) && count($params)>0 )
        {
            $paramStr = http_build_query($params);
            $paramStr = str_replace(['fq1=', 'fq2='], ['fq=', 'fq='], $paramStr);
        }
        if(defined('HIGHLIGHT_STYLE') && HIGHLIGHT_STYLE==1)
        {   
           $paramStr.='&hl.simple.pre=<font+color%3D"%23FF0000">&hl.simple.post=<%2Ffont>&hl.tag.pre=<font+color%3D"%23FF0000">&hl.tag.post=<%2Ffont>';
        }                                                                            
        
        if($paramStr)
        {
            $url = $url.'?'.$paramStr;
        }
        
        if($_GET['debug'])
        {
            echo  $url;
        }
        
        
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //added by baoxianjian 15:10 2016/1/26
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $return_content = curl_exec($ch);
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $result = array();
        $result['content'] = $return_content;
        $result['code'] = $return_code;
        $result['time'] = $total_time;

        return $result;
    }

    /**
     * HTTP Protocol defined status codes
     * HTTP协议状态码,调用函数时候只需要将$num赋予一个下表中的已知值就直接会返回状态了。
     * @param int $num
     */
    public static function returnHeaderStatus($num)
    {
        $http = array(
            100 => "HTTP/1.1 100 Continue",
            101 => "HTTP/1.1 101 Switching Protocols",
            200 => "HTTP/1.1 200 OK",
            201 => "HTTP/1.1 201 Created",
            202 => "HTTP/1.1 202 Accepted",
            203 => "HTTP/1.1 203 Non-Authoritative Information",
            204 => "HTTP/1.1 204 No Content",
            205 => "HTTP/1.1 205 Reset Content",
            206 => "HTTP/1.1 206 Partial Content",
            300 => "HTTP/1.1 300 Multiple Choices",
            301 => "HTTP/1.1 301 Moved Permanently",
            302 => "HTTP/1.1 302 Found",
            303 => "HTTP/1.1 303 See Other",
            304 => "HTTP/1.1 304 Not Modified",
            305 => "HTTP/1.1 305 Use Proxy",
            307 => "HTTP/1.1 307 Temporary Redirect",
            400 => "HTTP/1.1 400 Bad Request",
            401 => "HTTP/1.1 401 Unauthorized",
            402 => "HTTP/1.1 402 Payment Required",
            403 => "HTTP/1.1 403 Forbidden",
            404 => "HTTP/1.1 404 Not Found",
            405 => "HTTP/1.1 405 Method Not Allowed",
            406 => "HTTP/1.1 406 Not Acceptable",
            407 => "HTTP/1.1 407 Proxy Authentication Required",
            408 => "HTTP/1.1 408 Request Time-out",
            409 => "HTTP/1.1 409 Conflict",
            410 => "HTTP/1.1 410 Gone",
            411 => "HTTP/1.1 411 Length Required",
            412 => "HTTP/1.1 412 Precondition Failed",
            413 => "HTTP/1.1 413 Request Entity Too Large",
            414 => "HTTP/1.1 414 Request-URI Too Large",
            415 => "HTTP/1.1 415 Unsupported Media Type",
            416 => "HTTP/1.1 416 Requested range not satisfiable",
            417 => "HTTP/1.1 417 Expectation Failed",
            500 => "HTTP/1.1 500 Internal Server Error",
            501 => "HTTP/1.1 501 Not Implemented",
            502 => "HTTP/1.1 502 Bad Gateway",
            503 => "HTTP/1.1 503 Service Unavailable",
            504 => "HTTP/1.1 504 Gateway Time-out"
        );
        header($http[$num]);
    }
}

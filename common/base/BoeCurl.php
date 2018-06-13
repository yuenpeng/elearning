<?php

namespace common\base; 
use common\base\BoeBase;
use yii; 
/**
 * Description of BoeCurl
 * @Boe有关Curl操作的中间件 
 * @date 2016-04-25
 * @author Administrator
 */
class BoeCurl {

    private $runtime_start_time = 0;
    private $runtime_stop_time = 0;
    private $more_handle = NULL;
    private $curl_handle = array();
    private $default_user_agent = 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_7) AppleWebKit/534.16+ (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4';
    private $common_user_agent = array(
        "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)",
        "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)",
        "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)",
        "Mozilla/4.0 (compatible; MSIE 9.0; Windows NT 6.1)",
        "Mozilla/5.0 (compatible; rv:1.9.1) Gecko/20130819 Firefox/26.0.2",
        "Mozilla/5.0 (compatible; rv:1.9.2) Gecko/20130619 Firefox/25.0.2",
        "Mozilla/5.0 (compatible; rv:2.0) Gecko/20130419 Firefox/24.0.1",
        "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0.2) Gecko/20130119 Firefox/21.0.2",
        "Mozilla/5.0 (compatible) AppleWebKit/534.21 (KHTML, like Gecko) Chrome/11.0.682.0 Safari/534.21",
        "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_7) AppleWebKit/534.16+ (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4",
        "Opera/9.80 (compatible; U) Presto/2.7.39 Version/11.00",
        "Mozilla/5.0 (compatible; U) AppleWebKit/533.1 (KHTML, like Gecko) Maxthon/3.0.8.2 Safari/533.1",
        "Mozilla/5.0 (iPhone; U; CPU OS 4_2_1 like Mac OS X) AppleWebKit/532.9 (KHTML, like Gecko) Version/5.0.3 Mobile/8B5097d Safari/6531.22.7",
        "Mozilla/5.0 (iPad; U; CPU OS 4_2_1 like Mac OS X) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/4.0.2 Mobile/8C148 Safari/6533.18.5",
        "Mozilla/5.0 (Linux; U; Android 2.2) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1",
        "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
        "msnbot/1.1 (+http://search.msn.com/msnbot.htm)"
    );
    var $curl_get_info_fields = array('content_type', 'http_code', 'total_time', 'redirect_url', 'url'); //要保留的字段

    function __construct() {
        
    }

    function start($p, $debug = 0) {
        if (!is_array($p) || !$p) {
            return -1;
        }
        if (!isset($p['urls'])) {
            if (!isset($p['url'])) {
                return -2;
            }
            return $this->single_curl($p, $debug);
        }

        $url = BoeBase::array_key_is_nulls($p, 'urls', NULL);
        if (!is_array($url) || !$url) {
            return -3;
        }
        $this->get_microtime();
        $s = date("Y-m-d H:i:s");
        $sult = array('curl_sult' => array());
        $this->more_handle = curl_multi_init();
        $common_curl_p = array(
            'curl_time_out' => BoeBase::array_key_is_numbers($p, array('curl_time_out', 'time_out'), 0),
            'curl_m_time_out' => BoeBase::array_key_is_numbers($p, array('curl_m_time_out', 'm_time_out'), 0),
            'curl_return_transfer' => BoeBase::array_key_is_numbers($p, array('curl_return_transfer', 'return_transfer'), 1),
            'curl_follow_location' => BoeBase::array_key_is_numbers($p, array('curl_follow_location', 'follow_location'), 1),
            'curl_no_body' => BoeBase::array_key_is_numbers($p, array('curl_no_body', 'no_body'), 0),
            'curl_header' => BoeBase::array_key_is_numbers($p, array('curl_header', 'header'), 0),
            'curl_user_agent' => BoeBase::array_key_is_nulls($p, array('curl_user_agent', 'user_agent')),
            'curl_cookie_jar' => BoeBase::array_key_is_nulls($p, array('curl_cookie_jar', 'cookie_jar')),
            'curl_post' => BoeBase::array_key_is_nulls($p, array('curl_post', 'post')),
            'curl_post_file' => BoeBase::array_key_is_nulls($p, array('curl_post_file', 'post_file')),
            'curl_referer' => BoeBase::array_key_is_nulls($p, array('curl_referer', 'referer')),
            'curl_cookie' => BoeBase::array_key_is_nulls($p, array('curl_cookie', 'cookie')),
            'curl_http_header' => BoeBase::array_key_is_nulls($p, array('curl_http_header', 'http_header')),
            'call_back' => BoeBase::array_key_is_nulls($p, 'call_back', NULL),
        );
        $curl = array();
        $err_url = array();
        $tmp_url = NULL;
        foreach ($url as $k => $a_url) {
            if (is_array($a_url)) {
                if (!isset($a_url['url'])) {
                    $err_url[$k] = $a_url;
                    break;
                } else {
                    $curl[$k] = array(
                        'url' => $a_url['url'],
                        'curl_time_out' => BoeBase::array_key_is_numbers($a_url, array('curl_time_out', 'time_out'), $common_curl_p['curl_time_out']),
                        'curl_m_time_out' => BoeBase::array_key_is_numbers($a_url, array('curl_m_time_out', 'm_time_out'), $common_curl_p['curl_m_time_out']),
                        'curl_return_transfer' => BoeBase::array_key_is_numbers($a_url, array('curl_return_transfer', 'return_transfer'), $common_curl_p['curl_return_transfer']),
                        'curl_follow_location' => BoeBase::array_key_is_numbers($a_url, array('curl_follow_location', 'follow_location'), $common_curl_p['curl_follow_location']),
                        'curl_no_body' => BoeBase::array_key_is_numbers($a_url, array('curl_no_body', 'no_body'), $common_curl_p['curl_no_body']),
                        'curl_header' => BoeBase::array_key_is_numbers($a_url, array('curl_header', 'header'), $common_curl_p['curl_header']),
                        'curl_user_agent' => BoeBase::array_key_is_nulls($a_url, array('curl_user_agent', 'user_agent'), $common_curl_p['curl_user_agent']),
                        'curl_cookie_jar' => BoeBase::array_key_is_nulls($a_url, array('curl_cookie_jar', 'cookie_jar'), $common_curl_p['curl_cookie_jar']),
                        'curl_post' => BoeBase::array_key_is_nulls($a_url, array('curl_post', 'post'), $common_curl_p['curl_post']),
                        'curl_post_file' => BoeBase::array_key_is_nulls($a_url, array('curl_post_file', 'post_file'), $common_curl_p['curl_post_file']),
                        'curl_referer' => BoeBase::array_key_is_nulls($a_url, array('curl_referer', 'referer'), $common_curl_p['curl_referer']),
                        'curl_cookie' => BoeBase::array_key_is_nulls($a_url, array('curl_cookie', 'cookie'), $common_curl_p['curl_cookie']),
                        'curl_http_header' => BoeBase::array_key_is_nulls($a_url, array('curl_http_header', 'http_header'), $common_curl_p['curl_http_header']),
                        'call_back' => BoeBase::array_key_is_nulls($a_url, 'call_back', $common_curl_p['call_back']),
                    );
                    $this->add_handle($curl[$k], $k, 1);
                }
            } else {
                $curl[$k] = $common_curl_p;
                $curl[$k]['url'] = $a_url;
                $this->add_handle($curl[$k], $k, 1);
            }
        }
        if (!$curl) {
            curl_multi_close($this->more_handle);
            $this->more_handle = NULL;
            return -4;
        }
        $this->exec_handle();
        foreach ($curl as $k => $a_curl) {
            $sult['curl_sult'][$k] = array();
            $sult['curl_sult'][$k]+=$this->format_curl_get_info(curl_getinfo($this->curl_handle[$k]));
            if ($debug) {//调式的时候,将采集的参数也保存到结果中
                $sult['curl_sult'][$k]['parameter'] = $a_curl;
            }
            if ($debug == 0) {
                $sult['curl_sult'][$k]['function_error'] = '';
                $sult['curl_sult'][$k]['content'] = curl_multi_getcontent($this->curl_handle[$k]);
                $tmp_call_back = BoeBase::array_key_is_nulls($a_curl, 'call_back', '');
                if ($tmp_call_back) {
                    if (function_exists($tmp_call_back)) {
                        $sult['curl_sult'][$k]['content'] = call_user_func_array($tmp_call_back, array($sult['curl_sult'][$k]['content']));
                    } else {
                        $sult['curl_sult'][$k]['function_error'] = $tmp_call_back . ' is not exists!';
                    }
                }
            }
            curl_multi_remove_handle($this->more_handle, $this->curl_handle[$k]);
            $this->curl_handle[$k] = NULL;
            unset($curl[$k]);
        }
        curl_multi_close($this->more_handle);
        $this->more_handle = NULL;
        unset($curl);
        $sult['start_time'] = $s;
        $sult['end_time'] = date("Y-m-d H:i:s");
        $sult['use_time'] = $this->runtime_spent();
        return $sult;
    }

    function single_curl($p, $debug = 0) {//单个抓取
        if (!is_array($p) || !$p) {
            return -1;
        }
        if (!isset($p['url'])) {
            return -2;
        }
        $url = BoeBase::array_key_is_nulls($p, 'url');
        if (!$url) {
            return -3;
        }
        $handle_key = md5($url);
        $this->add_handle($p, $handle_key);
        $tmp_content = curl_exec($this->curl_handle[$handle_key]);
        $sult = $this->format_curl_get_info(curl_getinfo($this->curl_handle[$handle_key]));
        $sult['content'] = &$tmp_content;
        $tmp_call_back = BoeBase::array_key_is_nulls($p, 'call_back', '');
        if ($tmp_call_back) {
            $sult['function_error'] = '';
            if (function_exists($tmp_call_back)) {
                $sult['content'] = call_user_func_array($tmp_call_back, array($sult['content']));
            } else {
                $sult['function_error'] = $tmp_call_back . ' is not exists!';
            }
        }
        curl_close($this->curl_handle[$handle_key]);
        if ($debug) {
            debug($sult, 1);
        }
        return $sult;
    }

    private function format_post($post = array(), $post_file = 0) {//格式化POST信息
        if (is_array($post)) {
            $post_data = '';
            $tmp_array = array();
            foreach ($post as $key => $a_form) {
                $tmp_array[] = "{$key}={$a_form}";
            }
            if ($tmp_array) {
                $post_data = $post_file ? $tmp_array : implode('&', $tmp_array);
            }
            return $post_data;
        } else {
            if (!is_string($post)) {
                return '';
            }
            return ($post_file) ? explode('&', $post) : $post;
        }
    }

    private function format_curl_get_info($data = array()) {
        if (!is_array($data) || !$data) {
            return array();
        }
        if (!is_array($this->curl_get_info_fields) || !$this->curl_get_info_fields) {
            return $data;
        }
        $tmp_array = array();
        foreach ($data as $key => $a_info) {
            if (in_array($key, $this->curl_get_info_fields)) {
                $tmp_array[$key] = $a_info;
            }
        }
        return $tmp_array;
    }

    private function add_handle($p, $key = NULL, $is_more = 0) {
        $url = BoeBase::array_key_is_nulls($p, 'url');
        if ($key === NULL) {
            $key = md5($url);
        }
        if (!isset($this->curl_handle[$key]) || !is_object($this->curl_handle[$key])) {
            $this->curl_handle[$key] = curl_init();
        }
        $curl_time_out = BoeBase::array_key_is_numbers($p, array('curl_time_out', 'time_out'), 0);
        $curl_m_time_out = BoeBase::array_key_is_numbers($p, array('curl_m_time_out', 'm_time_out'), 0);
        $curl_return_transfer = BoeBase::array_key_is_numbers($p, array('curl_return_transfer', 'return_transfer'), 1);
        $curl_follow_location = BoeBase::array_key_is_numbers($p, array('curl_follow_location', 'follow_location'), 1);
        $curl_no_body = BoeBase::array_key_is_numbers($p, array('curl_no_body', 'no_body'), 0);
        $curl_header = BoeBase::array_key_is_numbers($p, array('curl_header', 'header'), 0);
        $curl_user_agent = BoeBase::array_key_is_nulls($p, array('curl_user_agent', 'user_agent'), $this->default_user_agent);
        if ($curl_user_agent == 'rand') {//如果是随机封装浏览器的头部时
            $curl_user_agent = $this->common_user_agent[array_rand($this->common_user_agent)];
        }
        $curl_cookie_jar = BoeBase::array_key_is_nulls($p, array('curl_cookie_jar', 'cookie_jar'));
        $curl_post = BoeBase::array_key_is_nulls($p, array('curl_post', 'post'));
        $curl_post_file = BoeBase::array_key_is_numbers($p, array('curl_post_file', 'post_file'));
        $curl_referer = BoeBase::array_key_is_nulls($p, array('curl_referer', 'referer'));
        $curl_cookie = BoeBase::array_key_is_nulls($p, array('curl_cookie', 'cookie'));
        $curl_http_header = BoeBase::array_key_is_nulls($p, array('curl_http_header', 'http_header'), NULL);

        curl_setopt($this->curl_handle[$key], CURLOPT_URL, $url);
        curl_setopt($this->curl_handle[$key], CURLOPT_HEADER, $curl_header);
        curl_setopt($this->curl_handle[$key], CURLOPT_RETURNTRANSFER, $curl_return_transfer);
        curl_setopt($this->curl_handle[$key], CURLOPT_NOBODY, $curl_no_body);
        curl_setopt($this->curl_handle[$key], CURLOPT_FOLLOWLOCATION, $curl_follow_location);
        curl_setopt($this->curl_handle[$key], CURLOPT_USERAGENT, $curl_user_agent);
        curl_setopt($this->curl_handle[$key], CURLOPT_REFERER, ($curl_referer) ? $curl_referer : $url);

        if ($curl_m_time_out) {//如果指定了毫秒级的超时处理,该设定优先于CURLOPT_TIMEOUT
            curl_setopt($this->curl_handle[$key], CURLOPT_NOSIGNAL, 1);
            curl_setopt($this->curl_handle[$key], CURLOPT_TIMEOUT_MS, $curl_m_time_out);
        } else {
            if ($curl_time_out) {
                curl_setopt($this->curl_handle[$key], CURLOPT_TIMEOUT, $curl_time_out);
            }
        }
        if ($curl_http_header && is_array($curl_http_header)) {
            curl_setopt($this->curl_handle[$key], CURLOPT_HTTPHEADER, $curl_http_header);
        }
        if ($curl_post) {
            curl_setopt($this->curl_handle[$key], CURLOPT_POST, 1);
            curl_setopt($this->curl_handle[$key], CURLOPT_POSTFIELDS, $this->format_post($curl_post, $curl_post_file));
        }
        if ($curl_cookie) {
            curl_setopt($this->curl_handle[$key], CURLOPT_COOKIE, $curl_cookie);
        }
        if ($curl_cookie_jar) {
            curl_setopt($this->curl_handle[$key], CURLOPT_COOKIEJAR, $curl_cookie_jar);
            //curl_setopt($this->curl_handle[$key],CURLOPT_COOKIEFILE,$cookie_jar);
        }
        if ($is_more) {
            curl_multi_add_handle($this->more_handle, $this->curl_handle[$key]);
        }
    }

    private function exec_handle() {
        $flag = null;
        do {
            curl_multi_exec($this->more_handle, $flag);
        } while ($flag > 0);
    }

    function get_microtime() {
        list($usec, $sec) = explode(' ', microtime());
        return ((float) $usec + (float) $sec);
    }

    function runtime_start() {
        $this->runtime_start_time = $this->get_microtime();
    }

    function runtime_stop() {
        $this->runtime_stop_time = $this->get_microtime();
    }

    function runtime_spent() {
        return round(($this->runtime_stop_time - $this->runtime_start_time) * 1000, 4);
    }

}

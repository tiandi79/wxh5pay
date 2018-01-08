<?php

/***
*  微信类
*  by tiandi 
*  2017.12.13
*/

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

class wechat {
	public $para;

	public function __construct(){
    }

	public function get_openid_by_code($appid, $secret, $code, $grant_type='authorization_code')
    {	
        $result = $this->get_access_token($appid, $secret, $code, $grant_type);
        return $result['openid'];
    }

	public function get_access_token($appid, $secret, $code, $grant_type='authorization_code')
    {
        $api_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?';
        $api_url = $api_url.'appid='.$appid;
        $api_url = $api_url.'&secret='.$secret;
        $api_url = $api_url.'&code='.$code;
        $api_url = $api_url.'&grant_type='.$grant_type;
        $response = $this->_curl_post_ssl($api_url,0);
        $result = json_decode($response, true);

        return $result;
    }

	private function _curl_post_ssl($url, $type=0, $vars=null, $second=30)
	{
		$ch = curl_init();
		//超时时间
		curl_setopt($ch,CURLOPT_TIMEOUT,$second);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
	
	 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
	 
		curl_setopt($ch,CURLOPT_POST, $type);
		if(isset($vars))
			curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
		
		$data = curl_exec($ch);
		if($data){
			curl_close($ch);
			return $data;
		}
		else { 
			$error = curl_errno($ch);
			curl_close($ch);
			return false;
		}
	}

	public function create_noncestr( $length = 24 ) 
	{  
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";  
		$str ="";  
		for ( $i = 0; $i < $length; $i++ )  {  
			$str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);   
		}  
		return $str;  
	}

	function getIPaddress()
	{
		$IPaddress='';
		if (isset($_SERVER)){
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
				$IPaddress = $_SERVER["HTTP_X_FORWARDED_FOR"];
			} else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
				$IPaddress = $_SERVER["HTTP_CLIENT_IP"];
			} else {
				$IPaddress = $_SERVER["REMOTE_ADDR"];
			}
		} else {
			if (getenv("HTTP_X_FORWARDED_FOR")){
				$IPaddress = getenv("HTTP_X_FORWARDED_FOR");
			} else if (getenv("HTTP_CLIENT_IP")) {
				$IPaddress = getenv("HTTP_CLIENT_IP");
			} else {
				$IPaddress = getenv("REMOTE_ADDR");
			}
		}
		return $IPaddress;
	}

	function create_xml($apikey,$is_h5=0){ 
		$this->set_para('sign', $this->create_sign($apikey,$is_h5));
		return $this->ArrayToXml($this->para);
	}

	function check_sign_para(){
		if($this->para["appid"] == null || 
			$this->para["device_info"] == null || 
			$this->para["mch_id"] == null || 
			$this->para["nonce_str"] == null || 
			$this->para["body"] == null || 
			$this->para["out_trade_no"] == null ||
			$this->para["total_fee"] == null || 
			$this->para["spbill_create_ip"] == null || 
			$this->para["notify_url"] == null || 
			$this->para["trade_type"] == null || 
			$this->para["openid"] == null 
			)
			{
				return false;
			}
		return true;
	}

	function check_h5_sign_para(){
		if($this->para["appid"] == null || 
			$this->para["device_info"] == null || 
			$this->para["mch_id"] == null || 
			$this->para["nonce_str"] == null || 
			$this->para["body"] == null || 
			$this->para["out_trade_no"] == null ||
			$this->para["total_fee"] == null || 
			$this->para["spbill_create_ip"] == null || 
			$this->para["notify_url"] == null || 
			$this->para["trade_type"] == null
			)
			{
				return false;
			}
		return true;
	}

	function check_query_sign_para(){
		if($this->para["appid"] == null || 
			$this->para["mch_id"] == null || 
			$this->para["nonce_str"] == null || 
			$this->para["out_trade_no"] == null
			)
			{
				return false;
			}
		return true;
	}

	function create_sign($apikey,$is_h5){
		if($is_h5 == 1) {
			if($this->check_h5_sign_para() == false) {
			echo "H5请求统一下单签名参数错误！";
			}
		}
		elseif($is_h5 == 2) {
			if($this->check_query_sign_para() == false) {
			echo "查询订单签名参数错误！";
			}
		}
		else {
			if($this->check_sign_para() == false) {
				echo "JSAPI请求统一下单签名参数错误！";
			}
		}
		
		ksort($this->para);
		$tempsign = "";
		foreach ($this->para as $k => $v){
			if (null != $v && "null" != $v && "sign" != $k) {
				$tempsign .= $k . "=" . $v . "&";
			}
		}
		$tempsign = substr($tempsign, 0, strlen($tempsign)-1); //去掉最后的&
		$tempsign .="&key=". $apikey;  //拼接APIKEY
		return strtoupper(md5($tempsign));
	}

	function set_para($key,$value){
		$this->para[$key] = $value;
	}


	function ArrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
        	 if (is_numeric($val))
        	 {
        	 	$xml.="<".$key.">".$val."</".$key.">"; 

        	 }
        	 else
        	 	$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";  
        }
        $xml.= "</xml>";
        return $xml; 
    }

	public function request_for_pre_id($postxml)
	{
		$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
		$response = $this->_curl_post_ssl($url, 1 , $postxml);
		$responseObj = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		//print_r($responseObj);
		return $responseObj;
	}

	public function getjsapisignkey($noncestr,$prepay_id,$timestamp,$appid,$apikey) {
		$tempsign = "appId=".$appid."&nonceStr=".$noncestr."&package=".$prepay_id."&signType=MD5&timeStamp=".$timestamp."&key=".$apikey;
	return strtoupper(md5($tempsign));
	}

	function check_respond_date($res,$apikey) {
		ksort($res);
		$tempsign = "";
		foreach ($res as $k => $v){
			if (null != $v && "null" != $v && "sign" != $k && "code" != $k) {
				$tempsign .= $k . "=" . $v . "&";
			}
		}
		$tempsign = substr($tempsign, 0, strlen($tempsign)-1); //去掉最后的&
		$tempsign .="&key=". $apikey;  //拼接APIKEY
		$sign = strtoupper(md5($tempsign));
		//$text= "return_sign=".$res['sign']."|real_sign=".$sign;$file = fopen("test2.txt","w");fwrite($file, $text);fclose($file);
		if($sign == $res['sign']) {
			
			return true; 
		}
		else {
			
			return false;
		}
	}

	public function queryorder($postxml)
	{
		$url = 'https://api.mch.weixin.qq.com/pay/orderquery';
		$response = $this->_curl_post_ssl($url, 1 , $postxml);
		$responseObj = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		//print_r($responseObj);
		return $responseObj;
	}
}
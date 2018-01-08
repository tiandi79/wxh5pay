<?php
/* 微信JSAPI支付        */
/* V 2.0               */
/* by tiandi           */
/* www.tiandiyoyo.com  */
/* 2017.12.13        */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/wxjsapi.php';

if (file_exists($payment_lang))
{
    global $_LANG;
    include_once($payment_lang);
}


/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'wxjsapi_desc';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'tiandi';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.tiandiyoyo.com';

    /* 版本号 */
    $modules[$i]['version'] = '1.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'wxjsapi_appid',           'type' => 'text',   'value' => ''),
        array('name' => 'wxjsapi_secret',            'type' => 'text',   'value' => ''),
		array('name' => 'wxjsapi_mchid',            'type' => 'text',   'value' => ''),
        array('name' => 'wxjsapi_apikey',           'type' => 'text',   'value' => '')
    );

    return;
}


class wxjsapi {
	var $para;
	var $openid;

	function __construct()
	{
	}

	function set_para($key,$value){
		$this->para[$key] = $value;
	}

	function get_code($order, $payment){
		$apikey = $payment['wxjsapi_apikey'];
		$appid = $payment['wxjsapi_appid'];
		$redirect_uri = $GLOBALS['ecs']->url().'wechat.php';
		$response_type='code';
		$scope='snsapi_base';
		$state='STATE';
		$body = $order['order_sn'];
		$total_fee = floor($order['order_amount']*100);
		$out_trade_no = $order['order_sn'] . 'O' . $order['log_id'];
		$redirect_uri .= "?body=".$body."&total_fee=".$total_fee."&out_trade_no=".$out_trade_no;

		$url = "https://open.weixin.qq.com/connect/oauth2/authorize?";
		$url .='appid='.$appid.'&redirect_uri='.urlencode($redirect_uri).'&response_type='.$response_type.'&scope='.$scope.'&state='.$state.'#wechat_redirect';
        $button = '<div style="text-align:center"><a href='.$url.' class="c-btn3" style="font-size:20px;"><img style="width:200px;" src="images/wxjsapi.jpg" /></a></div>';
		return $button;

		//by tiandi 无需跳转订单支付页，直接调起支付。
		//header("location:".$url);
		//exit;
	}

	function respond($postStr) {
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		$responseObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
		$json = json_encode($responseObj);
		$res = json_decode($json, true);

		$text= var_export($res,1);$file = fopen("wxjsapi.log","a");fwrite($file, $text);fclose($file);

		if($this->check_respond_date($res)) {
			$out_trade_no = $res['out_trade_no'];
			$out_trade_no = explode('O', $out_trade_no);
			$order_sn = $out_trade_no[0];//订单号
			$log_id = $out_trade_no[1];//订单号log_id

			if($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS') {	
				/* 改变订单状态 */
				order_paid($log_id, 2);
				echo "success";
			} else {
				echo "fail";
			}
		}
		else 
			echo "fail";
	}

	function check_respond_date($res) {
		$sql = "SELECT pay_config FROM " .$GLOBALS['ecs']->table('payment'). " WHERE pay_code = '".$res['code']."'";
		$result = $GLOBALS['db']->getOne($sql);
		$config = $this->unserialize_config($result);

		$apikey = $config['wxjsapi_apikey'];

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

	function unserialize_config($cfg){
		if (is_string($cfg) && ($arr = unserialize($cfg)) !== false)
		{
			$config = array();
	        foreach ($arr AS $key => $val)
		    {
			    $config[$val['name']] = $val['value'];
			}
	        return $config;
		}
		else
		{
			return false;
		}
	}

	function create_noncestr( $length = 24 ) {  
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";  
		$str ="";  
		for ( $i = 0; $i < $length; $i++ )  {  
			$str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);   
		}  
		return $str;  
	}

	function check_sign_para(){
		
		if($this->para["appid"] == null || 
//			$this->para["device_info"] == null || 
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

	function create_sign($apikey){
		if($this->check_sign_para() == false) {
			echo "签名参数错误！";
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

	function create_xml($apikey){ 
		$this->set_para('sign', $this->create_sign($apikey));
		return $this->ArrayToXml($this->para);
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

	function curl_post_ssl($url, $vars, $second=30)
	{
		$ch = curl_init();
		//超时时间
		curl_setopt($ch,CURLOPT_TIMEOUT,$second);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
	
	 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
	 
		curl_setopt($ch,CURLOPT_POST, 1);
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

function getkey($noncestr,$prepay_id,$timestamp,$appid,$apikey) {
	$tempsign = "appId=".$appid."&nonceStr=".$noncestr."&package=".$prepay_id."&signType=MD5&timeStamp=".$timestamp."&key=".$apikey;
	return strtoupper(md5($tempsign));
}
?>

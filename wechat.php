<?php
/**  微信授权处理流程  
/* V 1.0               
/* by tiandi           
/* www.tiandiyoyo.com  
/* 2017.12.13          
*/
define('IN_ECS', true);

require('includes/init.php');
require('includes/lib_wechat.php');
require('includes/lib_payment.php');

//公众号参数
$sql = "SELECT pay_config FROM " .$GLOBALS['ecs']->table('payment'). " WHERE pay_code = 'wxjsapi'";
$result = $GLOBALS['db']->getOne($sql);
$config = unserialize_config($result);
$apikey = $config['wxjsapi_apikey'];
$appid = $config['wxjsapi_appid'];
$secret = $config['wxjsapi_secret'];
$mchid = $config['wxjsapi_mchid'];

$wechat = new wechat();

//获取code
if(isset($_GET['code']) && $_GET['code'] != '') {

	$body = $_GET['body'];
	$total_fee =$_GET['total_fee'];
	$code = $_GET['code'];
	$out_trade_no = $_GET['out_trade_no'];
	$callback_url = $GLOBALS['ecs']->url() . 'wechat.php';
	
	$openid = $wechat->get_openid_by_code($appid, $secret, $code, $grant_type='authorization_code');

	$ip = $wechat->getIPaddress();
	$timestamp = time();
	$noncestr =  $wechat->create_noncestr();
	
	$wechat->set_para("nonce_str", $noncestr); //随机字符串
	$wechat->set_para("appid", $appid); //公众号
	$wechat->set_para("mch_id", $mchid); //商户号
	$wechat->set_para("device_info", 'WEB'); //终端设备号(商户的门店号或设备ID)，注意：PC网页或公众号内支付请传"WEB"
	$wechat->set_para("body", $body); //商品或支付单简要描述
	$wechat->set_para("out_trade_no", $out_trade_no); //商户订单号
	$wechat->set_para("total_fee", $total_fee); //付款金额，单位分
	$wechat->set_para("spbill_create_ip", $ip); // 终端地址
	$wechat->set_para("notify_url", $callback_url); //异步通知地址
	$wechat->set_para("trade_type", 'JSAPI'); //交易类型 JSAPI，NATIVE，APP
	$wechat->set_para("openid", $openid); //用户openid

	$postxml = $wechat->create_xml($apikey); 
	
	$prepayobj = $wechat->request_for_pre_id($postxml);
	$prepay_id = "prepay_id=".$prepayobj->prepay_id;
	$signkey = $wechat->getjsapisignkey($noncestr,$prepay_id,$timestamp,$appid,$apikey);
//	echo "signkey=".$signkey."<br>";
	$button = '<div style="text-align:center"><input type="button" onclick="onBridgeReady()" value="微信支付" class="c-btn3" /></div>';
	$js = "<script>function onBridgeReady(){".
				"WeixinJSBridge.invoke(".
				"'getBrandWCPayRequest', {".
				"'appId' :'".$appid."',".         
                "'timeStamp':'".$timestamp."',".             
				"'nonceStr' : '".$noncestr."',".   
				"'package': '".$prepay_id."',".
				"'signType' : 'MD5',".          
				"'paySign' : '".$signkey."'".
			"},".
			"function(res){    ". 
           "if(res.err_msg == 'get_brand_wcpay_request:ok' ) ".
			   "{".
		//	   "alert('支付成功！');".
			   "window.location.href='respond.php?code=wxjsapi';". 
			   "}".
		   "else {".
			   "alert(res.err_msg);".
		 //		"document.getElementById('show').innerText = JSON.stringify(res);".
		         "} });} ".
		    "if(typeof WeixinJSBridge == 'undefined'){".
				"if( document.addEventListener ){".
				"document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);".
			"}else if (document.attachEvent){".
			"document.attachEvent('WeixinJSBridgeReady', onBridgeReady);".
			"document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);".
			"}".
			"}else{".
				"onBridgeReady();".
			"}".
			" </script>";
	  $resultdiv = "<div id='show'></div>";
      echo $js.$resultdiv;	
}
else
{
	$xml = file_get_contents('php://input');
	if(!isset($xml)) {
		echo "fail";
		exit;
	}
	else {
		$text= "\n\n".date("Y-m-d h:i:sa").var_export($xml,1);$file = fopen("xml.log","a");fwrite($file, $text);fclose($file);
	}
	
	$responseObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	$json = json_encode($responseObj);
	$res = json_decode($json, true);

	$text= "\n\n".date("Y-m-d h:i:sa").var_export($res,1);$file = fopen("wxjsapi.log","a");fwrite($file, $text);fclose($file);

	if($wechat->check_respond_date($res,$apikey)) {
		$out_trade_no = $res['out_trade_no'];
		$out_trade_no = explode('O', $out_trade_no);
		$order_sn = $out_trade_no[0];//订单号
		$log_id = $out_trade_no[1];//订单号log_id
		$total_fee = $res['total_fee'];

		if (!check_money($log_id, $total_fee/100))
		{
			return false;
		}
		
		if($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS') {	
			order_paid($log_id, 2);
			echo "success";
			exit;
		} else {
			echo "fail";
		}
	}
	else 
		echo "fail";
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
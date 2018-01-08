<?php
/* 微信H5支付        */
/* V 1.0               */
/* by tiandi           */
/* www.tiandiyoyo.com  */
/* 2017.12.13          */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}
require(ROOT_PATH .'includes/lib_wechat.php');

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/wxh5.php';

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
    $modules[$i]['desc']    = 'wxh5_desc';

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
        array('name' => 'wxh5_appid',           'type' => 'text',   'value' => ''),
        array('name' => 'wxh5_secret',            'type' => 'text',   'value' => ''),
		array('name' => 'wxh5_mchid',            'type' => 'text',   'value' => ''),
        array('name' => 'wxh5_apikey',           'type' => 'text',   'value' => '')
    );

    return;
}


class wxh5 {
	var $para;
	var $openid;

	function __construct()
	{
	}

	function set_para($key,$value){
		$this->para[$key] = $value;
	}

	function get_code($order, $payment){
		$wechat = new wechat();

		$ip = $wechat->getIPaddress();
		$timestamp = time();
		$noncestr =  $wechat->create_noncestr();
		$apikey = $payment['wxh5_apikey'];
		$appid = $payment['wxh5_appid'];
		$mchid = $payment['wxh5_mchid'];
		$body = $order['order_sn'];
		$total_fee = floor($order['order_amount']*100);
		$out_trade_no = $order['order_sn'] . 'O' . $order['log_id'];
		$callback_url = $GLOBALS['ecs']->url() . 'wechat.php';
		
		$wechat->set_para("nonce_str", $noncestr); //随机字符串
		$wechat->set_para("appid", $appid); //公众号
		$wechat->set_para("mch_id", $mchid); //商户号
		$wechat->set_para("device_info", 'WEB'); //终端设备号(商户的门店号或设备ID)，注意：PC网页或公众号内支付请传"WEB"
		$wechat->set_para("body", $body); //商品或支付单简要描述
		$wechat->set_para("out_trade_no", $out_trade_no); //商户订单号
		$wechat->set_para("total_fee", $total_fee); //付款金额，单位分
		$wechat->set_para("spbill_create_ip", $ip); // 终端地址
		$wechat->set_para("notify_url", $callback_url); //异步通知地址
		$wechat->set_para("trade_type", 'MWEB'); //交易类型 JSAPI，NATIVE，APP

		$postxml = $wechat->create_xml($apikey,1); 
		$prepayobj = $wechat->request_for_pre_id($postxml);
		
		$url = $prepayobj->mweb_url;
		$redirect_uri = $GLOBALS['ecs']->url() . 'respond.php?code=wxh5&out_trade_no='.$out_trade_no;
		$redirect_uri.= "&redirect_url=".urlencode($redirect_uri);
		
		$button = '<div style="text-align:center"><a onclick="show()" href='.$url.' class="c-btn3" style="font-size:20px;"><img style="width:200px;" src="images/wxjsapi.jpg" /></a></div>';
		$div = '<div id="h5btn" style="display:none;position:fixed;_position:absolute;width:100%;height:100%;left:0;top:0;background-color: rgba(0,0,0,0.5);
z-index:90;"><div style="z-index:97;background:#fff;position: absolute;top: 40%;left: 50%;-ms-transform: translate(-50%,-50%);
        -moz-transform: translate(-50%,-50%);-o-transform: translate(-50%,-50%);transform: translate(-50%,-50%); opacity:1;-moz-opacity:1;
filter:alpha(opacity=100);padding:40px;font-size: 30px;   ">请确认支付是否已经完成？<hr style="margin:0px;height:1px;border:0px;background-color:#D5D5D5;color:#D5D5D5;margin: 40px 0;"/><div><a href='.$redirect_uri.' style="text-decoration: none;color:red;font-weight:bold;font-size:30px;">已完成支付</a></div><hr style="margin:0px;height:1px;border:0px;background-color:#D5D5D5;color:#D5D5D5;margin:40px 0;"/><div onclick="hide();" style="margin-top:30px;font-size:24px;color:#777">支付遇到问题，重新支付</div></div></div>';
		$js = '<script>
		function hide() { var h5btn = document.getElementById("h5btn");h5btn.style.display="none";}
		function show() { setTimeout(function(){var h5btn = document.getElementById("h5btn");h5btn.style.display="block";return true;},1000);}
		</script>';
		return $button.$div.$js;

		//by tiandi 无需跳转订单支付页，直接调起支付。
		//header("location:".$url);
		//exit;
	}

	function respond($postStr) {
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		$responseObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
		$json = json_encode($responseObj);
		$res = json_decode($json, true);

		$text= var_export($res,1);$file = fopen("wxh5.log","a");fwrite($file, $text);fclose($file);

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

		$apikey = $config['wxh5_apikey'];

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

function wxh5_getIPaddress()
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

function wxh5_getkey($noncestr,$prepay_id,$timestamp,$appid,$apikey) {
	$tempsign = "appId=".$appid."&nonceStr=".$noncestr."&package=".$prepay_id."&signType=MD5&timeStamp=".$timestamp."&key=".$apikey;
	return strtoupper(md5($tempsign));
}


<?php 
	if(!defined('BASEPATH')) exit('No direct script access allowed');
    
	if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST']=='localhost' || $_SERVER['HTTP_HOST']=='192.168.1.107')){
		define("SMS_API_KEY","");
	}
	else{	
		define("SMS_API_KEY","");
	}
	define("SENDER_ID","BERRYD");
	
	if(!function_exists('samedaydeliverymsg')) {
  		function samedaydeliverymsg($mobile,$name,$order_no,$time) {
    		$message="Dear $name , your order $order_no is confirmed. order will be delivered between 2 pm to 7 pm on today. Regards , BERRYDAIRY MART";
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('nextdaydeliverymsg')) {
  		function nextdaydeliverymsg($mobile,$name,$order_no) {
    		$message="Dear $name , your order $order_no is confirmed. order will be delivered between 7am to 1 pm on tomorrow. Regards , BERRYDAIRY MART";
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('loginotp')) {
  		function loginotp($mobile,$otp) {
    		$message="Dear User , your OTP for login to BERRYDAIRY MART is $otp . Valid for 30 minutes . Regards , BERRYDAIRY MART";
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('resetpassword')) {
  		function resetpassword($mobile,$name,$otp) {
    		$message="Dear $name, Your OTP for RESET PASSWORD to BERRYDAIRY MART is $otp. Valid for 30 minutes . Please do not share this OTP. regards , BERRYDAIRY MART";
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('orderdelivered')) {
  		function orderdelivered($mobile,$name,$order_no) {
    		$message="Dear $name, your order $order_no have been delivered. Thanks for shopping from BERRYDAIRY MART. For any query and complain plz contact us ( 8920257752 ). Regards BERRYDAIRY MART";
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('outfordelivery')) {
  		function outfordelivery($mobile,$name,$order_no,$delivery_code) {
            $message="Dear $name, your order $order_no is out for delivery . your delivery code is $delivery_code . Regards BERRYDAIRY MART";
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('senddeliverymessage')) {
  		function senddeliverymessage($order) {
            $mobile=$order['mobile'];
            $name=$order['name'];
            $order_no=$order['order_no'];
            if($order['added_on']>=date('Y-m-d 07:00:00') && $order['added_on']<=date('Y-m-d 13:00:00')){
                $time="2pm to 7pm on today";
            }
            elseif($order['added_on']>=date('Y-m-d 13:00:00')){
                $time="7am to 1pm on tomorrow";
            }
            else{
                $time="7am to 1pm on today";
            }
            //var_dump($order['added_on']>='13:00:00');
            $message="Dear $name , your order $order_no is confirmed. order will be delivered between $time. Regards , BERRYDAIRY MART";
            //echo PRE;print_r($order);echo $message;die;
            sendsms($mobile,$message);
		}  
	}

	if(!function_exists('sendsms')) {
  		function sendsms($mobile,$message,$dlt_id="") {
            $message=urlencode($message);
            $url="http://smsfortius.com/api/mt/SendSMS?";
            //$body="APIKey=".SMS_API_KEY."&senderid=".SENDER_ID."&channel=Trans&DCS=0";
            $body="user=globexiq&password=globexiq509&senderid=".SENDER_ID."&channel=Trans&DCS=0";
            $body.="&flashsms=0&number=91$mobile&text=$message&route=2";
            $url.=$body;
            $url=htmlToPlainText($url);
            @file_get_contents($url);
		}  
	}
    if(!function_exists('htmlToPlainText')){
        function htmlToPlainText($str){
            $str = str_replace('&nbsp;', ' ', $str);
            $str = html_entity_decode($str, ENT_QUOTES | ENT_COMPAT , 'UTF-8');
            $str = html_entity_decode($str, ENT_HTML5, 'UTF-8');
            $str = html_entity_decode($str);
            $str = htmlspecialchars_decode($str);
            $str = strip_tags($str);

            return $str;
        }
    }
    
?>

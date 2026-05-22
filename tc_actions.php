<?php
if(!function_exists("add_action")){
	die("Direct access is not allowed");
};

trait WC_Gateway_Halkbank_actions{

	public function process_void_action( $arg = null ){
		
		if(isset($_SERVER['HTTP_SEC_PURPOSE']) && $_SERVER['HTTP_SEC_PURPOSE'] === 'prefetch') {
			die;
		}elseif(isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch') {
			die;
		}
		

		$order_id = $_REQUEST["order_id"];
		$resp = np_voidRequest($order_id);
		$subtitle = "";
		$title    = "";
		$redirect = false;
		$transaction_data = array();
		$color = "#00FF00";

		$transaction_data = array();
		if(!$resp){
			$message = __("ERROR: Could not comminacate with the gateway API. Check API user username/password or if maybe api account is locked out.","halkbank");
		}else{

			if(stripos($resp["Response"],"Approved") !== false){
				$amount =  number_format(round(floatval($resp["amount"]) ,2),2, '.', '');

				$title    = __("Payment void-ed (canceled)","halkbank");
				$subtitle = __("Amount","halkbank") . ": " . $amount;


				$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
				
				if(empty($transaction_data)){
					$transaction_data = array();
				}else{
					if(is_string($transaction_data)){
						$transaction_data = json_decode($transaction_data, true);
					}
				}


				$trantype = "void";
				if(!isset($transaction_data[$trantype])){
					$html =$this->responseToHtml($resp, $title , "#000000" , $subtitle, true);

					$this->add_order_note($order_id, $html);

					if(!$this->no_capture_void_email)
						$this->transactionNotification($order_id, $title, $html);

					$redirect = " ";
				}

				$transaction_data[$trantype] = $resp;
				tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
				tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);

				try{
					tc_update_order_status(intval($order_id),$this->voided);
					
				}catch(Throwable $ex){
					@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
					//
				}

			}else{
				$subtitle = "";
				$title    = __("Payment could not be void-ed (canceled)","halkbank");
				$color = "#FF0000";
			}

			$message = $this->responseToHtml($resp, $title , $color , $subtitle, false);
		}
		global $tc_transaction_notification_error;
		global $last_curl_resp;
		
		?>
		<script type="text/javascript">
			window.parent.np_last_raw_resp = <?php echo json_encode($last_curl_resp ? $last_curl_resp : ""); ?>;
			window.parent.np_payment_log = <?php echo json_encode($transaction_data); ?>;
			window.parent.halkbank_frame_response(<?php echo json_encode(array("message" => $message, "closebtn" => __("Close","halkbank"), "redirect_after" => $redirect, "notification_error" => $tc_transaction_notification_error )); ?>);
		</script>
		<?php
		tc_commit_orders();
		
		die;
	}

	public function process_capture_action( $arg = null  ){
		if(isset($_SERVER['HTTP_SEC_PURPOSE']) && $_SERVER['HTTP_SEC_PURPOSE'] === 'prefetch') {
			die;
		}elseif(isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch') {
			die;
		}
		
		try{
			$order_id = $_REQUEST["order_id"];
			//OVDE Amount!

			$total = null;
			if(isset($_REQUEST["amount"])){
				$total = number_format(round(floatval($_REQUEST["amount"]),2),2, '.', '');
				if(!floatval($total))
					$total = null;
			}

			$resp = np_captureRequest($order_id, $total);
			$subtitle = "";
			$title    = "";
			$color = "#00FF00";
			$redirect = false;
			$transaction_data = array();

			if(!$resp){
				$message = __("ERROR: Could not comminacate with the gateway API. Check API user username/password or if maybe api account is locked out.","halkbank");
			}else{

				if(stripos($resp["Response"],"Approved") !== false){
					$amount =  number_format(round(floatval($resp["amount"]) ,2),2, '.', '');

					$title    = __("Payment captured (Post-Authorizated)","halkbank");
					$subtitle = __("Amount captured","halkbank") . ": " . $amount;

					$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
					
					if(empty($transaction_data)){
						$transaction_data = array();
					}else{
						if(is_string($transaction_data)){
							$transaction_data = json_decode($transaction_data, true);
						}
					}

					if(isset($transaction_data["preauth"])){
						if(isset($transaction_data["preauth"]["amount"])){
							$subtitle .= __(", of previously reserved: ","halkbank") . number_format(round(floatval($transaction_data["preauth"]["amount"]) ,2),2, '.', '');
						}
					}

					$trantype = "postauth";
					if(!isset($transaction_data[$trantype])){

						$html = $this->responseToHtml($resp, $title , "#000000" , $subtitle, true);

						$this->add_order_note($order_id, $html);

						if(!$this->no_capture_void_email)
							$this->transactionNotification($order_id, $title, $html);

						$redirect = " ";
					}

					$transaction_data[$trantype] = $resp;
					tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
					tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);

					try{
						tc_update_order_status(intval($order_id), $this->postauthorised);
					}catch(Throwable $ex){
						@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
						//
					}

				}else{
					$subtitle = "";
					$title    = __("Payment could not be captured (Pre-Authorizated)","halkbank");
					$color = "#FF0000";
				}

				$message = $this->responseToHtml($resp, $title , $color , $subtitle, false);
				global $tc_transaction_notification_error;
			}
			
			global $last_curl_resp;
		
		
			?>
			<script type="text/javascript">
				window.parent.np_last_raw_resp = <?php echo json_encode($last_curl_resp ? $last_curl_resp : ""); ?>;
				window.parent.np_payment_log = <?php echo json_encode($transaction_data); ?>;
				window.parent.halkbank_frame_response(<?php echo json_encode(array("message" => $message, "closebtn" => __("Close","halkbank"), "redirect_after" => $redirect, "notification_error" => $tc_transaction_notification_error )); ?>);
			</script>
			<?php
			tc_commit_orders();
		}catch(Throwable $ex){
			echo $ex->getMessage();
		}
		die;
	}

	public function process_refund_action(  $arg = null  ){
		if(isset($_SERVER['HTTP_SEC_PURPOSE']) && $_SERVER['HTTP_SEC_PURPOSE'] === 'prefetch') {
			die;
		}elseif(isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch') {
			die;
		}
		
		try{
			$order_id = $_REQUEST["order_id"];


			$total = null;
			if(isset($_REQUEST["amount"])){
				$total = number_format(round(floatval($_REQUEST["amount"]) ,2),2, '.', '');
				if(!floatval($total))
					$total = null;
			}


			$resp = np_refundRequest($order_id, $total);

			$transaction_data = array();
			$subtitle = "";
			$title    = "";
			$redirect = false;
			$color = "#00FF00";

			if(!$resp){
				$message = __("ERROR: Could not comminacate with the gateway API. Check API user username/password or if maybe api account is locked out.","halkbank");
			}else{
				if(stripos($resp["Response"],"Approved") !== false){
					$amount =  number_format(round(floatval($resp["amount"]) ,2),2, '.', '');


					$title    = __("Payment refunded","halkbank");
					$subtitle = __("Refunded amount","halkbank") . ": " . $amount;

					$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
					if(empty($transaction_data)){
						$transaction_data = array();
					}else{
						if(is_string($transaction_data)){
							$transaction_data = json_decode($transaction_data, true);
						}
					}

					if(isset($transaction_data["postauth"])){
						if(isset($transaction_data["postauth"]["amount"])){
							$subtitle .= __(", of originaly payed: ","halkbank") . number_format(round(floatval($transaction_data["postauth"]["amount"])  ,2),2, '.', '');
						}
					}else if(isset($transaction_data["auth"])){
						if(isset($transaction_data["auth"]["amount"])){
							$subtitle .= __(", of originaly payed: ","halkbank") . number_format(round(floatval($transaction_data["auth"]["amount"]) ,2),2, '.', '');
						}
					}


					$trantype = "refund";
					if(!isset($transaction_data[$trantype])){

						$html = $this->responseToHtml($resp, $title , "#000000" , $subtitle, true);

						$this->add_order_note($order_id, $html);

						$this->transactionNotification($order_id, $title, $html);

						$redirect = " ";
					}

					$transaction_data[$trantype] = $resp;
					tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
					tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);

					
				}else{
					$subtitle = "";
					$title    = __("Payment could not be refunded","halkbank");
					$color = "#FF0000";

				}
				$message = $this->responseToHtml($resp, $title , $color , $subtitle, false);
				global $tc_transaction_notification_error;
			}
			
			global $last_curl_resp;
			
			?>
			<script type="text/javascript">
				window.parent.np_last_raw_resp = <?php echo json_encode($last_curl_resp ? $last_curl_resp : ""); ?>;
				window.parent.np_payment_log = <?php echo json_encode($transaction_data); ?>;
				window.parent.halkbank_frame_response(<?php echo json_encode(array("message" => $message, "closebtn" => __("Close","halkbank"), "redirect_after" => $redirect, "notification_error" => $tc_transaction_notification_error )); ?>);
			</script>
			<?php
			tc_commit_orders();
		}catch(Throwable $ex){
			echo $ex->getMessage();
		}
		die;
	}

	public function process_query_action(  $arg = null  ){
		if(isset($_SERVER['HTTP_SEC_PURPOSE']) && $_SERVER['HTTP_SEC_PURPOSE'] === 'prefetch') {
			die;
		}elseif(isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch') {
			die;
		}
		try{
			$order_id = $_REQUEST["order_id"];
			$resp = np_queryRequest($order_id);

			$transaction_data = array();

			$message  = "";
			$title    = __("Payment status","halkbank");
			$subtitle = "";
			$redirect = false;

			if(!$resp){
				$message = __("ERROR: Could not comminacate with the gateway API. Check API user username/password or if maybe api account is locked out.","halkbank");
			}else{

				$color = "#000000";
				$trantype = "";

				$amount = "0.00";
				if(isset($resp["amount"]))
					$amount =  number_format(round(floatval($resp["amount"]) ,2),2, '.', '');

				$refundbtn  = "";
				$capturebtn = "";
				$err_query  = false;
				
				$TransId = "";

				if(isset($resp["EXTRA_TRANS_STAT"])){
					
					$TransId = $resp["TransId"];
					
					if(($resp["EXTRA_TRANS_STAT"] == "C" || $resp["EXTRA_TRANS_STAT"] == "S") && $resp["EXTRA_CHARGE_TYPE_CD"] == "C"){
						$subtitle = __("Refunded, amount","halkbank") . ": " . $amount;
						$color = "#FF0000";
						$trantype = "refund";
					}else if($resp["EXTRA_TRANS_STAT"] == "C"){
						$subtitle = __("Captured (Post-Authorizated), amount","halkbank") . ": " . $amount;
						$color = "#00FF00";
						$trantype = "postauth";
					}else if($resp["EXTRA_TRANS_STAT"] == "S"){
						$subtitle = __("Sale success, amount","halkbank") . ": " . $amount;
						$color = "#00FF00";
						$trantype = "auth";
					}else if($resp["EXTRA_TRANS_STAT"] == "V"){
						$subtitle = __("Voided (canceled), amount","halkbank") . ": " . $amount;
						$color = "#FF0000";
						$trantype = "void";
					}else if($resp["EXTRA_TRANS_STAT"] == "A"){
						$subtitle = __("Pre-Authorizated (reserved), still not captured, amount","halkbank") . ": " . $amount;
						$color = "#a82abf";
						$trantype = "preauth";
					}else if($resp["EXTRA_TRANS_STAT"] == "D"){
						$subtitle = __("Declined, amount","halkbank") . ": " . $amount;
						$color = "#FF0000";
						$trantype = "declined";
					}else
						$subtitle = json_encode($resp["EXTRA_TRANS_STAT"]) . __(", amount","halkbank") . ": " . $amount;
				}else{
					if(isset($resp["Response"]) && isset($resp["ProcReturnCode"])){
						if($resp["ProcReturnCode"] == "99"){
							$subtitle = __("Can not query","halkbank");
							$color = "#FF0000";
							$trantype = "Order id not found";
							global $tc_last_net_err;  
							if($tc_last_net_err){
								$trantype = $tc_last_net_err;
							}
							$err_query  = true;
						}
					}
				}

				if($trantype){

					$title .= " ({$trantype})";
					
					if($err_query){
						$title = "QUERY ERROR: " . $title;
					}else{

						$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
						if(empty($transaction_data)){
							$transaction_data = array();
						}else{
							if(is_string($transaction_data)){
								$transaction_data = json_decode($transaction_data, true);
							}
						}

						if($trantype == "refund"){
							if(isset($transaction_data["postauth"])){
								if(isset($transaction_data["postauth"]["amount"])){
									$subtitle .= __(", of originaly payed: ","halkbank") . number_format(round(floatval($transaction_data["postauth"]["amount"])  ,2),2, '.', '');
								}
							}else if(isset($transaction_data["auth"])){
								if(isset($transaction_data["auth"]["amount"])){
									$subtitle .= __(", of originaly payed: ","halkbank") . number_format(round(floatval($transaction_data["auth"]["amount"]) ,2),2, '.', '');
								}
							}
						}else if($trantype == "postauth"){
							if(isset($transaction_data["preauth"])){
								if(isset($transaction_data["preauth"]["amount"])){
									$subtitle .= __(", of previously reserved: ","halkbank") . number_format(round(floatval($transaction_data["preauth"]["amount"]) ,2),2, '.', '');
								}
							}
						}
						
						$same_exists = ($trantype == "postauth") && isset($transaction_data["auth"]);
						
						
						
						$found = false;
						if($TransId){
							foreach($transaction_data as $ttype => $tdata){
								if($tdata["TransId"] == $TransId){
									$found = true;
								}
							}
							if(!$found){
								$transaction_data[$trantype] = $resp;
								tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
							}
						}
						
						if(!$found){
							if(!isset($transaction_data[$trantype]) && !$same_exists){
								$html = $this->responseToHtml($resp, $title , $color , $subtitle, true);
								$this->add_order_note($order_id, $html);
								if($this->no_capture_void_email && $trantype == "postauth"){
									//
								}else if($this->no_capture_void_email && $trantype == "void"){
									//
								}else
									$this->transactionNotification($order_id, $title, $html);

								$redirect = " ";
							}
						}
						
						tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);
					}
				}

				$message = $this->responseToHtml($resp, $title, $color , $subtitle);

				if(isset($resp["ErrMsg"])){
					$message .= ("<p style='color:red'>" . $resp["ErrMsg"] . "</p>");
				}

				if(isset($_REQUEST["forwhat"])){
					if($_REQUEST["forwhat"] == "capture" && $trantype == "preauth"){

						$message = ("<h3>" . __("Capture amount (in payment currency)","halkbank") .":</h3>");
						$message .= ("<p style='text-align:center;'><input type='number' name='capture_amount' original='{$amount}' value='{$amount}' /></p>");
						$capturebtn = __("Capture / PostAuthorize","halkbank");


					}else if($_REQUEST["forwhat"] == "refund" && ($trantype == "postauth" || $trantype == "auth" )){


						$message = ("<h3>" . __("Refund amount (in payment currency)","halkbank") .":</h3>");
						$message .= ("<p style='text-align:center;' ><input type='number' name='refund_amount' original='{$amount}' value='{$amount}' /></p>");
						$refundbtn = __("Refund","halkbank");
					}
				}

			}
			
			global $tc_transaction_notification_error;
			global $last_curl_resp;
			
			
			?>
			<script type="text/javascript">
				window.parent.np_last_raw_resp = <?php echo json_encode($last_curl_resp ? $last_curl_resp : ""); ?>;
				window.parent.np_payment_log = <?php echo json_encode($transaction_data); ?>;
				window.parent.halkbank_frame_response(<?php echo json_encode(array("message" => $message, 
																				   "closebtn" => __("Close","halkbank"), 
																				   "redirect_after" => $redirect, 
																				   "refundbtn" => $refundbtn, 
																				   "capturebtn" => $capturebtn,
																				   "notification_error" => $tc_transaction_notification_error
																				   )); ?>);
			</script>
			<?php
			tc_commit_orders();
		}catch(Throwable $ex){
			echo $ex->getMessage();
		}
		die;
	}

	public function process_refund($order_id, $amount = NULL, $reason = ''){
		if(isset($_SERVER['HTTP_SEC_PURPOSE']) && $_SERVER['HTTP_SEC_PURPOSE'] === 'prefetch') {
			die;
		}elseif(isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch') {
			die;
		}
		
		try{
			global $currencies;
			halkbank_get_currencies();
			
			$currencies_arr = array();
			foreach($currencies as $code3 => $curr){
				$currencies_arr[$curr["currency_numeric_code"]] = $code3;
			}
			
			$np = WC_Gateway_Halkbank::instance();
			
			$order = tc_get_order($order_id, true);
			
			$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
			if(empty($transaction_data)){
				$transaction_data = array();
			}else{
				if(is_string($transaction_data)){
					$transaction_data = json_decode($transaction_data, true);
				}
			}
			
			$tran = null;
			foreach($transaction_data as $ttype => $tdata){
				if($ttype == "auth" || $ttype == "postauth"){
					$tran = $tdata;
				}
			}
			
			if($amount){
			
				$order_currency = tc_get_order_currency($order);
				if(!isset($currencies[$order_currency])){
					throw new Exception(__("Unrecognised order currency","halkbank")) .": " . $order_currency;
				}
				
				$payment_currency_num = $np->merchant_currency;
				$payment_currency = strtoupper($currencies_arr[$payment_currency_num]);
				
				if($order_currency != $payment_currency){
					$exc_rate = tc_get_order_meta($order_id,"exchange_rate_{$order_currency}{$payment_currency}",true);
					if($exc_rate){
						$amount = round($amount * floatval($exc_rate),2); 
						
						if($tran){
							if(isset($tran["amount"])){
								if(abs( $tran["amount"] - $amount ) < 0.199){
									$amount = $tran["amount"];
								}
							}
						}
						
					}else if($tran){
						$perc = round($amount / $order->get_total(),2);
						
						if($perc > 0.98){
							$perc = 1;
						}
						
						if($perc <= 1){
							$amount = $perc * floatval($tran["amount"]);
						}
					}
				}
			}else{
				if($tran){
					$amount = $tran["amount"];
				}
			}
			
			$amount = number_format(round(floatval($amount),2),2, '.', '');
			
			$resp = np_refundRequest($order_id, $amount ? $amount : null);
			
			if(!$resp){
				if ( wp_doing_ajax()) {
					header("Content-Type:application/json; charset=UTF-8");
					echo json_encode(array(
						"success" => false,
						"data" => array(
							"error" => __("No response from gateway check you API parameters!","halkbank") 
						)
					));
					die;
				}
				throw new Exception(__("Count not execute transaction","halkbank"));
				return false;
			}else{

				if(stripos($resp["Response"],"Approved") !== false){

					$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
					if(empty($transaction_data)){
						$transaction_data = array();
					}else{
						if(is_string($transaction_data)){
							$transaction_data = json_decode($transaction_data, true);
						}
					}

					$trantype = "refund";
					$title    = __("Payment refunded","halkbank");
					$subtitle = __("Amount","halkbank") . ": " . $amount;

					if(isset($transaction_data["postauth"])){
						if(isset($transaction_data["postauth"]["amount"])){
							$subtitle .= __(", of originaly payed: ","halkbank") . number_format(round(floatval($transaction_data["postauth"]["amount"]),2),2, '.', '');
						}
					}else if(isset($transaction_data["auth"])){
						if(isset($transaction_data["auth"]["amount"])){
							$subtitle .= __(", of originaly payed: ","halkbank") . number_format(round(floatval($transaction_data["auth"]["amount"]),2),2, '.', '');
						}
					}

					$html = $this->responseToHtml($resp, $title , "#000000" , $subtitle, true);
					$this->add_order_note($order_id, $html);
					$this->transactionNotification($order_id,$title, $html);
					
					$transaction_data[$trantype] = $resp;

					tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
					tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);
					
					tc_commit_orders();

					return true;
				}else{
					global $last_curl_resp;
					
					$err_msg = __("Refund operation did not succeed (maybe you need to run VOID instead).","halkbank") . ".";
					
					if($last_curl_resp){
						if(strpos($last_curl_resp, '<ErrMsg>') !== false){
							$err_msg .= (" " . explode('</ErrMsg>',explode('<ErrMsg>',$last_curl_resp)[1])[0]);
						}
					}
					
					if ( wp_doing_ajax()) {
						header("Content-Type:application/json; charset=UTF-8");
						echo json_encode(array(
							"success" => false,
							"data" => array(
								"error" => $err_msg
							)
						));
						die;
					}else{
						$np->add_order_note($order_id, date("Y-m-d H:i:s") . ": " . $err_msg);
					}
					return false;
				}
			}
		}catch(Throwable $ex){
			echo $ex->getMessage();
		}
		die;
	}

}

if(!defined("TC_ACTIONS_LOADED")){
	define("TC_ACTIONS_LOADED",1);
}


?>

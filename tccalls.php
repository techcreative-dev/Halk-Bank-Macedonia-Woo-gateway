<?php
if(!function_exists("add_action")){
	die("Direct access is not allowed");
};

add_action( 'wp_ajax_nopriv_np_api_test', 'np_api_test');
add_action( 'wp_ajax_np_api_test', 'np_api_test');

add_action( 'wp_ajax_nopriv_nptransaction', 'np_nptransaction_get');
add_action( 'wp_ajax_nptransaction', 'np_nptransaction_get');

function np_get_plugin_options(){
	$poptions = (object)get_option("woocommerce_".halkbank_method_id()."_settings");
	// Hardcoded URLs - these are no longer in settings
	$poptions->api_url = "https://epay.halkbank.mk/fim/api";
	$poptions->gateway_url = "https://epay.halkbank.mk/fim/est3Dgate";
	return $poptions;
}

function np_get_array_val($arr, $key, $default = null){
	if(empty($arr))
		return $default;
	if(!is_array($arr))
		return $default;
	if(!isset($arr[$key]))
		return $default;
	return $arr[$key];
}

function np_get_num_array_val($arr, $key, $default = 0){
	return intval(np_get_array_val($arr, $key, $default));
}

function np_common_curl_request($url, $post_data = null, $headers = null){
	$head_arr = array();
	$url = trim($url);
	
	global $tc_last_net_err;  
	global $tc_last_net_err_code; 
	
	if($headers){
		foreach($headers as $key => $val){
			if(is_numeric($key)){
				if(strpos($val,":") !== false){
					$key = substr($val,0, strpos($val,":")); 
					$head_arr[trim($key)] = trim(str_ireplace($key . ":","", $val));
				}
			}else{
				$head_arr[$key] =  $val;
			}
		}
	}
	$url_hash_get = null;
	
	$ts = time();
	$response = null;
	$is_post = false;
	
	if($post_data){
		$is_post = true;
		$response = wp_remote_post( $url, array(
				'method'      => 'POST',
				'timeout'     => 9,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => $head_arr,
				'body'        => $post_data,
				'sslverify' => false
			)
		);
	}else{
		$response = wp_remote_get( $url, array(
				'method'      => 'GET',
				'timeout'     => 9,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => $head_arr,
				'sslverify' => false
			)
		);
	}
	
	if ( is_wp_error( $response ) ) {
		$tc_last_net_err = $response->get_error_message();
		$tc_last_net_err_code = wp_remote_retrieve_response_code($response);
		throw new Exception($tc_last_net_err_code . ": " . $tc_last_net_err);
	} else {
		
		$resp = wp_remote_retrieve_body( $response );
		
		if(!$is_post){
			return $resp;
		}else if(strtolower(substr($url, -4)) === "/api"){
			
			if(stripos($resp,"CC5Response") === false){
				$tc_last_net_err      = "INVALID_RESPONSE Check api url";  
				$tc_last_net_err_code = 400;
				return null;
			}else if(stripos($resp ,"Insufficent permissions") !== false || stripos($resp ,"<Extra></Extra>") !== false){
				$tc_last_net_err      = "CHECK_API_USER Username/Password and 'LOCKED' status. (Reminder: DO NOT USE MAIN ACCOUNT FOR API COMMUNICATION, CREATE Api user in bank dashboard!)";  
				$tc_last_net_err_code = 403;
				return null;
			}else if(stripos($resp ,"No record found for") !== false || stripos($resp ,"<OrderId></OrderId>") !== false){
				$tc_last_net_err      = "ORDER_NOT_FOUND Maybe 3ds ASC was abandoned by customer or you are reading order_id from other environment (test/production) | other Merchant ID.";  
				
				if(stripos($resp ,"No record found for") !== false){
					$t = explode("No record found for", $resp);
					$t = $t[1];		
					$t = explode("<", $t);
					$t = trim($t[0]);		
					$tc_last_net_err .= " Order id: {$t}";
				}
				$tc_last_net_err_code = 404;
				return false;
			}else{
				update_option("halkbank_last_display_error","");
			}
		}
		
		if($url_hash_get){
			set_transient($url_hash_get, $resp, 1800);	
		}
		
		return $resp;
	}
}

function halkbank_plugin_textdomain() {
	global $halkbank_language_loaded;
	
	
	load_plugin_textdomain( 'halkbank', false, dirname( __FILE__ ) . '/languages' );
	$locale = get_locale();
	
	if(isset($_GET["lang"])){
		if(strlen($_GET["lang"]) == 5){
			$locale = $_GET["lang"]; 
		}else if(strlen($_GET["lang"]) == 2){
			if(stripos($_GET["lang"],"yu") !== false){
				$locale = "sr_YU";
			}else if(stripos($_GET["lang"],"rs") !== false || stripos($_GET["lang"],"sr") !== false){
				$locale = "sr_RS";
			}else if(stripos($_GET["lang"],"bs") !== false){
				$locale = "bs_BA";
			}else if(stripos($_GET["lang"],"hr") !== false){
				$locale = "hr_HR";
			}else if(stripos($_GET["lang"],"it") !== false){
				$locale = "it_IT";
			}else if(stripos($_GET["lang"],"us") !== false){
				$locale = "en_US";
			}else if(stripos($_GET["lang"],"uk") !== false){
				$locale = "en_UK";
			}else if(stripos($_GET["lang"],"en") !== false){
				$locale = "en_UK";
			}
		}
	}
	
	$poptions = np_get_plugin_options();
	if(isset($poptions->override_language)){
		if($poptions->override_language){
			$locale = $poptions->override_language;
		}
	}
	
	$locale = str_replace("-","_",$locale);
	
	if(isset($halkbank_language_loaded)){
		if($halkbank_language_loaded == $locale)
			return;
	}
	
	$halkbank_language_loaded = $locale;
	
	$lngdir = rtrim(WP_LANG_DIR,"\\");
	$lngdir = rtrim($lngdir,"/");
	
	if(file_exists($lngdir . DIRECTORY_SEPARATOR . "plugins" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo")){
		load_textdomain( 'halkbank', $lngdir  . DIRECTORY_SEPARATOR .  "plugins" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo" );
	}else{
		
		if(file_exists($lngdir . DIRECTORY_SEPARATOR . "loco"  . DIRECTORY_SEPARATOR .  "plugins" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo")){
			load_textdomain( 'halkbank', $lngdir .  "loco" . DIRECTORY_SEPARATOR . "plugins" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo" );
		}else{
			if(file_exists($lngdir . DIRECTORY_SEPARATOR . "woocommerce-halkbank-payment" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo"))
				load_textdomain( 'halkbank', $lngdir . DIRECTORY_SEPARATOR .  "woocommerce-halkbank-payment" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo" );
			else if(file_exists(dirname( __FILE__ ) . DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo"))
				load_textdomain( 'halkbank', dirname( __FILE__ ) . DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . "halkbank-{$locale}.mo" );
		}
	}
}

function tc_get_order_currency($order = null){
	try{
		global $currencies;
		halkbank_get_currencies();
		$currency = null;
		
		if($order){
			if(is_numeric($order)){
				$order = tc_get_order($order);
			}
			$currency = strtoupper($order->get_currency());
		}else{
			$currency = get_woocommerce_currency();
		}
		
		if(isset($currencies[$currency]))
			return $currency;
		
		$maybe = get_woocommerce_currency_symbol($currency);
		
		if(stripos($maybe,"дин") !== false || stripos($maybe,"din") !== false || stripos($maybe,"rsd") !== false || stripos($maybe,"рсд") !== false){
			return "RSD";
		}else if(stripos($maybe,"ден") !== false || stripos($maybe,"den") !== false || stripos($maybe,"mkd") !== false || stripos($maybe,"мкд") !== false ){
			return "MKD";
		}else if(stripos($maybe,"km") !== false || stripos($maybe,"км") !== false ){
			return "БАМ";
		}
		
		return substr(strtoupper($maybe),0,3);
	}catch(Throwable $ex){
		return null;
	}
}

function np_get_current_order_id(){
	global $tc_current_order_id;
	if(isset($tc_current_order_id))
		return $tc_current_order_id;
	
	if(isset($_REQUEST["oid"])){
		$tc_current_order_id = intval($_REQUEST["oid"]);
		return $tc_current_order_id;
	}
	
	if(isset($_REQUEST["order_id"])){
		$tc_current_order_id = intval($_REQUEST["order_id"]);
		return $tc_current_order_id;
	}
	
	if(isset($_REQUEST["halkbank_oid"])){
		$tc_current_order_id = intval($_REQUEST["halkbank_oid"]);
		return $tc_current_order_id;
	}
	
	if(get_the_ID()){
		if(tc_id_is_wc_order(get_the_ID())){
			$tc_current_order_id = get_the_ID(); 
			return $tc_current_order_id;
		}
	}
	return null;
}

add_shortcode('nptransaction', 'np_nptransaction_scode_func');
function np_nptransaction_scode_func($atts)
{
	if(!class_exists('WC_Gateway_Halkbank'))
		return "";
		
    $atts = shortcode_atts(array(
        'id'   => '',
		'type' => null
    ) , $atts, 'nptransaction');
	
	if(!$atts["id"]){
		$atts["id"] = np_get_current_order_id();
	}
	
	if(!$atts["type"])
		$atts["type"] = null;
	
	if(!$atts["id"]){
		return "";
	}
	
	
	$tc_self = WC_Gateway_Halkbank::instance();
	return $tc_self->getHtmlResponse(intval($atts["id"]));
}

function np_return_true_1($arg){
		return true;
}

function np_return_true_4($arg1, $arg2, $arg3, $arg4 ){
		return true;
}

function np_woocommerce_email_headers($current_header, $mailer_id, $order, $mailer){
	if(is_a($order,"WC_Order")){
		global $tc_current_order_id;
		$tc_current_order_id = $order->get_id();
	}
	return $current_header;
}

function np_woocommerce_current_order($order_id){
	global $tc_current_order_id;
	$tc_current_order_id = $order_id;
	return $order_id;
}

function np_in_thankyou($order_id){
	if(!class_exists('WC_Gateway_Halkbank'))
		return $order_id;
		
	global $tc_current_order_id;
	$tc_current_order_id = $order_id;
	return $order_id;
}

function np_in_order_view($order_id){
	if(!class_exists('WC_Gateway_Halkbank'))
		return $order_id;

	global $tc_current_order_id;
	$tc_current_order_id = $order_id;
	echo do_shortcode("[nptransaction]");
	return $order_id;
}

function np_in_order_email($order){
	if(!class_exists('WC_Gateway_Halkbank'))
		return $order;

	global $tc_current_order_id;
	$tc_current_order_id = $order->get_id();
	$tc_self = WC_Gateway_Halkbank::instance();

	if($tc_self->no_transaction_email){
		if(!$tc_self->no_transaction_data){
			echo do_shortcode("[nptransaction]");
		}
	}
	return $order;
}

function np_order_add_attachments($attachments, $email_id, $email_order){
	return $attachments;
}

function halkbank_uncaught_exception_handler($exception){
	global $tc_errors;
	if(!isset($tc_errors)){
		$tc_errors = array();
	}
	
	$response = new stdClass;
	$response->error     = "UNCAUGHT_EXCEPTION";
	$response->message   = $exception->getMessage();
	$response->trace     = $exception->getTraceAsString();
	
	$tc_errors[] = $response;
}	

function halkbank_error_handler($severity, $message, $file, $line = null, $ctx = null) {
	global $tc_errors;
	if(!isset($tc_errors)){
		$tc_errors = array();
	}
	
	$eerr = new stdClass;
	$eerr->severity = $severity;
	$eerr->error  = "ERROR";
	$eerr->message  = $message;
	$eerr->file     = $file;
	$eerr->line     = $line; 
	
	$tc_errors[] = $eerr;
}   

function np_csymbol_fix_registry($symbols) {
	$symbols["RSD"] = __("RSD","halkbank");
	$symbols["BAM"] = __("BAM","halkbank");
	return $symbols;
}

function np_csymbol_fix_symbol( $symbol, $currency ) {
	
	if(stripos($currency,"rsd") === 0 || stripos($currency,"рсд") === 0 || stripos($currency,"din") === 0 || stripos($currency,"дин") === 0)
		return __("RSD","halkbank");
	
	if(stripos($currency,"bam") === 0 || stripos($currency,"km") === 0 || stripos($currency,"km") === 0)
		return __("BAM","halkbank");
	
	return $symbol;
}

function np_redirect_status( $status, $location ) {
    if(isset($_REQUEST["np_plgresp"])){
       if($status == 302){
		   if(isset($_POST["oid"]) && isset($_GET["halkbank_oid"])){
			   $oid = $_POST["oid"];
			   tc_update_order_meta( $oid, 'halkbank_redirected_data', json_encode(array(
				 "GET"  => $_GET,
				 "POST" => $_POST
			   )));
			   tc_commit_orders();
		   }
	   }
    }
    return $status;
}

function np_settings( $links ) {
    $settings_link = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=halkbank' ).'">Settings</a>';
  	array_push( $links, $settings_link );
  	return $links;
}

function halkbank_get_currencies(){
	global $currencies;
	if($currencies === null){
		$currencies = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "currencies.json"), true);
	}
}

function halkbank_getExchangeRate($from_currency, $to_currency, $rate_adjust = 0, $order_id = null){
	
	
	$exchange_rate = null;
	if($from_currency == $to_currency){
		$exchange_rate = 1.0;
	}else{
		
		if($rate_adjust === null){
			if(class_exists("WC_Gateway_Halkbank")){
				$tcself = WC_Gateway_Halkbank::instance();
				if($tcself)
					$rate_adjust = floatval($tcself->conversion_rate_adjust);
			}
		}
		
		$fromto        = strtoupper($from_currency .'_'. $to_currency);
		$exchange_cache = json_decode(get_option("halkbank_excange_{$fromto}"));
		
		if($exchange_cache){
			if(isset($exchange_cache->ts)){
				if($exchange_cache->ts + (60 * 30) > time()){
					if(isset($exchange_cache->rate)){
						$exchange_rate = floatval($exchange_cache->rate);	
					}
				}
			}
		}
		
		if(!$exchange_rate)
			$exchange_rate = 1.0;
		
		if($rate_adjust){
			$exchange_rate = $exchange_rate * (1.0 + $rate_adjust / 100);
		}
	}
	
	try{
		$exchange_rate_filtered = apply_filters( 'halkbank_exchange_rate', $exchange_rate, $from_currency,$to_currency, $rate_adjust, $order_id);
		if(floatval($exchange_rate_filtered)){
			$exchange_rate = floatval($exchange_rate_filtered);
		}
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}	
	
	return $exchange_rate;
}

function np_xmlval($val){
	if(is_object($val) || is_array($val))
		return "";
	return $val;
}

function halkbank_check_pending_orders(){
	try{
		
		set_error_handler("debugErrorHandler");	
		
		if(isset($_REQUEST["np_plgresp"])){
			$doid = null;
			if(isset($_REQUEST["halkbank_oid"])){
				$doid = $_REQUEST["halkbank_oid"];
			}else if(isset($_POST["oid"])){
				$doid = $_POST["oid"];
			}
			
			if( $doid ){
				$np = WC_Gateway_Halkbank::instance();
				global $allow_cart_empty;
				$allow_cart_empty = true;
				WC_Gateway_Halkbank::instance()->process_payment_response( $doid, true);
				$allow_cart_empty = false;
			}
			return;
		}
		
		if(stripos(np_server_variable("REQUEST_URI",""),"/wp-admin/") !== false){
			return;
		}
		
		if (!class_exists( 'WC_Gateway_Halkbank' ) ) return;
		
		global $wpdb;
		
		if (np_server_variable('REQUEST_METHOD','') != 'GET')
			return;
		
		$last_ts = intval(get_option("_halkbank_check_pending_orders_ts",0));
		
		if($last_ts + 90 > time())
			return;
		
		update_option("_halkbank_check_pending_orders_ts",time(),true);
		
		$poptions = np_get_plugin_options();
		
		if(!$poptions){
			return;
		}
		
		if(!isset($poptions->enabled)){
			return;
		}
		
		if(!isset($poptions->merchant_id))
			return;
		
		if(!$poptions->merchant_id){
			return;
		}
		
		if(isset($poptions->postauth_after_days)){
			if(floatval($poptions->postauth_after_days)){
				$capture_to = 86400 * floatval($poptions->postauth_after_days);
				
				$from = time() - $capture_to;
				$to   = time() - $capture_to - (86400 * 3);
				$capture_order_ids = null;
				
				if(tc_wchps_enabled()){
					$capture_order_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_halkbank_preauth_ts' AND meta_value > %d AND meta_value < %d",$from,$to));
				}else{
					$capture_order_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_halkbank_preauth_ts' AND meta_value > %d AND meta_value < %d",$from,$to));
				}
				
				if(!empty($capture_order_ids)){
					foreach($capture_order_ids as $captureid){
						if(get_post_meta($captureid,"_halkbank_last_tran_type",true) == "PreAuth"){
							$amt = get_post_meta($captureid,"_halkbank_preauth_amount",true);
							if(!$amt){
								$amt = null;
							}
							try{
								np_captureRequest($captureid, $amt);
							}catch(Throwable $cex){
								@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $cex->getMessage(), FILE_APPEND);
								//
							}
						}
					}
				}
			}
		}
		
		$pending_order_ids = null;
		
		if(tc_wchps_enabled()){
			$pending_order_ids = $wpdb->get_col("   SELECT o.id 
													FROM
														{$wpdb->prefix}wc_orders as o
													WHERE 
														o.type = 'shop_order' AND o.payment_method = '" . halkbank_method_id() . "'
													AND
														o.status = 'wc-pending'
													AND
														o.date_created_gmt >= DATE_ADD(NOW(), INTERVAL - 12 HOUR)
													AND
														o.date_created_gmt <= DATE_ADD(NOW(), INTERVAL (- EXTRACT(HOUR FROM TIMEDIFF(NOW(), UTC_TIMESTAMP)) * 60 - 15) MINUTE);");
		}else{
			$pending_order_ids = $wpdb->get_col("   SELECT o.ID 
													FROM
														{$wpdb->prefix}posts as o
													LEFT JOIN
														{$wpdb->prefix}postmeta as om on om.post_id = o.ID AND om.meta_key = '_payment_method' AND om.meta_value='" . halkbank_method_id() . "'
													WHERE 
														o.post_type = 'shop_order'
													AND
														o.post_status = 'wc-pending'
													AND
														om.meta_value='" . halkbank_method_id() . "'
													AND
														o.post_date_gmt >= DATE_ADD(NOW(), INTERVAL - 12 HOUR)
													AND
														o.post_date_gmt <= DATE_ADD(NOW(), INTERVAL (- EXTRACT(HOUR FROM TIMEDIFF(NOW(), UTC_TIMESTAMP)) * 60 - 15) MINUTE);");
		}											

		if(empty($pending_order_ids))
			return;
		
		if(!empty($pending_order_ids)){	
			$missing_parms = false;
			
			if($poptions){
				
				if(!$poptions->enabled)
					return;
				
				if(!($poptions->enabled == "1" || $poptions->enabled == "yes"))
					return;
				
				if(!isset($poptions->merchant_username) || !isset($poptions->merchant_password) || !isset($poptions->merchant_id) || !isset($poptions->api_url)){
					$missing_parms = true;
				}
				
				if(!$poptions->merchant_username || !$poptions->merchant_password || !$poptions->merchant_id || !$poptions->api_url){
					$missing_parms = true;
				}
				
				if($missing_parms){
					
					update_option("halkbank_last_display_error","Missing parameters!");
					
					return;
				}
			}else
				return;
			
			if(!class_exists('WC_Gateway_Halkbank'))
				return;
			
			
			$merchant_username = $poptions->merchant_username;
			$merchant_password = $poptions->merchant_password;
			$merchant_id       = $poptions->merchant_id;
					 
			foreach($pending_order_ids as $oid){
				
				 if(tc_get_order_meta($oid, '_pay_response_received',true)){
					 continue;
				 }
				
				 $res = np_queryFromGateway($oid, true);
				 if($res){
					 if($res["ReturnOid"]){
						 $np = WC_Gateway_Halkbank::instance();
						 $np->DPDAT = $res;
						 
						 ob_start();
						 
						 $np->process_payment_response($oid,true);
						 
						 $resp_html = ob_get_clean();
					 }
				 }
			}
		}
		
		if(isset($poptions->override_back_url)){
			if(trim($poptions->override_back_url)){
				$url_path = parse_url(np_server_variable("REQUEST_URI",""), PHP_URL_PATH);
				if(stripos($poptions->override_back_url . "/" ,$url_path) !== false){
					
					$order_id = null;
					if(isset($_REQUEST["halkbank_oid"])){
						$order_id = intval($_REQUEST["halkbank_oid"]);
					}else if(isset($_REQUEST["order_id"])){
						$order_id = intval($_REQUEST["order_id"]);
					}else if(isset($_REQUEST["order"])){
						$order_id = intval($_REQUEST["order"]);
					}
					
					if($order_id){
						if(!class_exists('WC_Gateway_Halkbank'))
							return;
			
						global $tc_inject_after_content;
						$np = WC_Gateway_Halkbank::instance();
						$tc_inject_after_content = $np->thankyou_page($order_id);
					}
				}
			}
		}
		
	}catch(Throwable $pex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $pex->getMessage(), FILE_APPEND);
		//
	}
	
	try{
		restore_error_handler();
	}catch(Throwable $pex){
		//
	}
}

function np_add_to_content( $content ) {    
	global $tc_inject_after_content;
    if( isset($tc_inject_after_content) ) {
	    $content .= $tc_inject_after_content;
    }
    return $content;
}


function halkbank_exlpicit_response_output($content){
	global $halkbank_exlout_done;
	
	if ( ! class_exists( 'WC_Gateway_Halkbank' ) ){
		return $content;
	}
	
	$order_id = intval($_REQUEST["halkbank_oid"]);
	$out =  "";
	
	try{
		$np = WC_Gateway_Halkbank::instance();
		$np->process_payment_response($order_id);
		$out = $np->thankyou_page($order_id);
	}catch(Exception $ppex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ppex->getMessage(), FILE_APPEND);
	}
	$halkbank_exlout_done = true;
	return $out . "<br/>" . $content;
}

function np_halkbankrecalcform(){
	
	if(!isset($_REQUEST["order_id"])){
		echo "ERROR: NO ORDER ID";
	}else if(class_exists("WC_Gateway_Halkbank")){
		if(!class_exists('WC_Gateway_Halkbank')){
			echo "NO METHOD INSTANCE";
			die;	
		}
		$np = WC_Gateway_Halkbank::instance();
		echo $np->generate_authorize_form($_REQUEST["order_id"]);
	}else{
		echo "ERROR: CLASS NOT DEFINED";
	}
	die;
}

function np_rest_get_order($order_id){
	ob_start();
	
	//////////////////////////////////////////////////////////////////////////////////////////
	add_filter('rest_authentication_errors',"np_return_true_1",99,1);
	add_filter('woocommerce_rest_check_permissions',"np_return_true_4",99,4);

	global $sc_call_rest_server;
	$rest_req = new WP_REST_Request( 'GET', "/wc/v3/orders/{$order_id}");
	$rest_response = rest_do_request( $rest_req );

	if(!isset($sc_call_rest_server)){
		$sc_call_rest_server = rest_get_server();
	}

	if($rest_response->is_error()){
		$response = $sc_call_rest_server->response_to_data( $rest_response, false );
		$dump = ob_get_clean();
		
		return array(
			"error" => json_encode($response)
		);
	}

	$order_rest = $sc_call_rest_server->response_to_data( $rest_response, false );
	$a = remove_filter('rest_authentication_errors',"np_return_true_1",99);
	$b = remove_filter('woocommerce_rest_check_permissions',"np_return_true_4",99);
	//////////////////////////////////////////////////////////////////////////////////////////
	$dump = ob_get_clean();
	
	return $order_rest;
}


function np_actionRequest($action, $data){
	
	$poptions = np_get_plugin_options();

	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;

	$req = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
			<CC5Request>
			 <Name>$merchant_username</Name>
			 <Password>$merchant_password</Password>
			 <ClientId>$merchant_id</ClientId>
			 <OrderId>". $data["oid"] ."</OrderId>
			 "
			 .(($action == "Query") ? "
			 <Extra>
			 <ORDERSTATUS>QUERY</ORDERSTATUS>
			 </Extra>" : "<Type>{$action}</Type>") 
			 .(isset($data["total"]) ? "
			 <Total>{$data["total"]}</Total>" : "") ."
			</CC5Request>";
			
			
			
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, array("DATA" => $req));
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
	
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	if($result){
		$xresp = simplexml_load_string($result);
		if($xresp){
			$xresp = json_decode(json_encode($xresp));
			$xresp = np_ensure_xml_fileds($xresp);
			$xresp = json_decode(json_encode($xresp), true);
			
			if($xresp){
				if(isset($xresp["Extra"])){
					foreach($xresp["Extra"] as $key => $val){
						$xresp["EXTRA_" . strtoupper($key)] = $val;
					}
				}
				
				if(!isset($xresp["amount"])){
					if(isset($xresp["EXTRA_ORIG_TRANS_AMT"])){
						if(!empty($xresp["EXTRA_ORIG_TRANS_AMT"]))
							$xresp["amount"] = number_format(round(floatval($xresp["EXTRA_ORIG_TRANS_AMT"])/100 ,2),2, '.', '');
					}
				}	
				
				if(!isset($xresp["amount"])){	
					if(isset($xresp["EXTRA_CAPTURE_AMT"])){
						if(!empty($xresp["EXTRA_CAPTURE_AMT"]))
							$xresp["amount"] = number_format(round(floatval($xresp["EXTRA_CAPTURE_AMT"])/100,2),2, '.', '');
					}
				}
				
				if(isset($xresp["Response"])){
					if($xresp["Response"]){
						update_option("halkbank_last_display_error","");
						if($action == "PostAuth"){
							if(stripos($xresp["Response"],"Approved") !== false){
								tc_delete_order_meta($order_id, '_halkbank_preauth_ts');
								tc_update_order_meta($order_id, '_halkbank_last_tran_type', "PostAuth");
							}
						}
					}
				}
				tc_commit_orders();
				return $xresp;
			}
		}		
	}
	
	global $tc_last_net_err_code;
	global $tc_last_net_err;
	
	return array(
			'oid'            => $order_id,
			'TransId'        => "--",
			'Response'       => __("Request error","halkbank")  . " {$tc_last_net_err_code} {$tc_last_net_err}",
			'ProcReturnCode' => "--",
			'mdStatus'       => "--",
			'AuthCode'       => "--",
			'EXTRA_TRXDATE'  => "--",
			'instalment'     => "--",
			'ProcReturnCode' => "--"
		);
}

function np_queryRequest($order_id){
	return np_actionRequest("Query", array("oid" => $order_id));
}

function np_captureRequest($order_id, $total = null){
	$res = np_actionRequest("PostAuth", array("oid" => $order_id, "total" => $total));
	if($res){
		if(isset($res["Response"])){
			if(stripos($res["Response"],"Approved") !== false){
				
				$order = tc_get_order(intval($order_id));
				
				if($order){
					
					if(!class_exists('WC_Gateway_Halkbank'))
						return array();	
					
					if(!$order->is_paid())
						$order->payment_complete();
				}
				
				$res_q = np_queryRequest($order_id);
				if($res_q)
					return $res_q;
			}
		}
	}
	return $res;
}

function np_refundRequest($order_id, $total = null){
	$res = np_actionRequest("Credit", array("oid" => $order_id, "total" => $total));
	if($res){
		if(isset($res["Response"])){
			if(stripos($res["Response"],"Approved") !== false){
				$res_q = np_queryRequest($order_id);
				if($res_q)
					return $res_q;
			}
		}
	}
	return $res;
}

function np_voidRequest($order_id){
	$res = np_actionRequest("Void", array("oid" => $order_id));
	if($res){
		if(isset($res["Response"])){
			if(stripos($res["Response"],"Approved") !== false){
				$res_q = np_queryRequest($order_id);
				if($res_q)
					return $res_q;
			}
		}
	}
	return $res;
}

function np_simpleRequest($data, $CC_NUMBER, $CC_EXP, $CC_CVV){
	
	
		
	$poptions = np_get_plugin_options();

	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;

	$instalment = "";  
	if(isset($data["instalment"])){
		if($data["instalment"]){
			$instalment = "
			 <Instalment>".$data["instalment"]."</Instalment>";
		}
	}
	
	$req = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
			<CC5Request>
			 <Name>$merchant_username</Name>
			 <Password>$merchant_password</Password>
			 <ClientId>$merchant_id</ClientId>
			 <OrderId>". $data["oid"] ."</OrderId>
			 <Type>". $poptions->tran_type ."</Type>
			 <Number>$CC_NUMBER</Number>
			 <Currency>". $data["currency"] ."</Currency>{$instalment}
			 <Expires>$CC_EXP</Expires>
			 <Total>". $data["amount"] ."</Total>
			 <Cvv2Val>$CC_CVV</Cvv2Val>
			</CC5Request>";
			
			
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, array("DATA" => $req));
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}	
	
	global $tc_last_net_err;  
	global $tc_last_net_err_code;
	
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	
	if($result){
		
		update_option("halkbank_last_display_error","");
		
		if(stripos($result,"<OrderId>") !== false){
			
			$xresp = simplexml_load_string($result);
			$xresp = json_decode(json_encode($xresp));
			$xresp = np_ensure_xml_fileds($xresp);
			
			$DPDAT = array();

			$DPDAT["clientid"]      = $poptions->merchant_id;
			$DPDAT["oid"]           = np_xmlval($xresp->OrderId);
			$DPDAT["Response"]      = np_xmlval($xresp->Response);
			$DPDAT["TransId"]       = np_xmlval($xresp->TransId);
			$DPDAT["ProcReturnCode"]= np_xmlval($xresp->ProcReturnCode);
			$DPDAT["ReturnOid"]     = np_xmlval($xresp->OrderId);
			
			if(!isset($data["mdStatus"]))
				$data["mdStatus"] = "--";
			
			$DPDAT["mdStatus"]      = $data["mdStatus"];
			$DPDAT["instalment"]    = $data["instalment"];
			$DPDAT["AuthCode"]      = np_xmlval($xresp->AuthCode);
			$DPDAT["EXTRA_TRXDATE"] = np_xmlval($xresp->Extra->TRXDATE);
			
			global $last_curl_resp_obj;
			$last_curl_resp_obj = $xresp;
			
			if(isset($DPDAT["Response"])){
				if($DPDAT["Response"]){
					update_option("halkbank_last_display_error","");
				}
			}

			return $DPDAT;
		}
		return null;
	}else{
		update_option("halkbank_last_display_error","API_COMM: {$tc_last_net_err}"); 
		return $result;
	}
}

function np_authRequest($data){
	
	
	$poptions = np_get_plugin_options();

	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;
	
	$instalment = "";  
	if(isset($data["instalment"])){
		if($data["instalment"]){
			$instalment = "
			 <Instalment>".$data["instalment"]."</Instalment>";
		}
	}

	$req = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
			<CC5Request>
			 <Name>$merchant_username</Name>
			 <Password>$merchant_password</Password>
			 <ClientId>$merchant_id</ClientId>
			 <OrderId>". $data["oid"] ."</OrderId>
			 <Type>". $poptions->tran_type ."</Type>
			 <Number>". $data["md"] ."</Number>{$instalment}
			 <PayerTxnId>". $data["xid"] ."</PayerTxnId>
			 <PayerSecurityLevel>". $data["eci"] ."</PayerSecurityLevel>
			 <PayerAuthenticationCode>". $data["cavv"] ."</PayerAuthenticationCode>
			 <Currency>". $data["currency"] ."</Currency>
			 <Expires>".$data["Ecom_Payment_Card_ExpDate_Month"]."/20".$data["Ecom_Payment_Card_ExpDate_Year"]."</Expires>
			 <Total>". $data["amount"] ."</Total>
			 <Extra></Extra>
			</CC5Request>";
	
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, array("DATA" => $req));
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
	
	global $tc_last_net_err;  
	global $tc_last_net_err_code;
	
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	
	if($result){
		
		update_option("halkbank_last_display_error","");
		
		if(stripos($result,"<OrderId>") !== false){
			
			$xresp = simplexml_load_string($result);
			$xresp = json_decode(json_encode($xresp));
			$xresp = np_ensure_xml_fileds($xresp);
			
			$DPDAT = array();

			$DPDAT["clientid"]      = $poptions->merchant_id;
			$DPDAT["oid"]           = np_xmlval($xresp->OrderId);
			$DPDAT["Response"]      = np_xmlval($xresp->Response);
			$DPDAT["TransId"]       = np_xmlval($xresp->TransId);
			$DPDAT["ProcReturnCode"]= np_xmlval($xresp->ProcReturnCode);
			$DPDAT["ReturnOid"]     = np_xmlval($xresp->OrderId);
			if(!isset($data["mdStatus"]))
				$data["mdStatus"] = "--";
			$DPDAT["mdStatus"]      = $data["mdStatus"];
			$DPDAT["instalment"]    = $data["instalment"];
			$DPDAT["AuthCode"]      = np_xmlval($xresp->AuthCode);
			$DPDAT["EXTRA_TRXDATE"] = np_xmlval($xresp->Extra->TRXDATE);
			
			global $last_curl_resp_obj;
			$last_curl_resp_obj = $xresp;
			
			
			if(isset($DPDAT["Response"])){
				if($DPDAT["Response"]){
					update_option("halkbank_last_display_error","");
				}
			}

			return $DPDAT;
		}
		return null;
	}else{
		update_option("halkbank_last_display_error","API_COMM: {$tc_last_net_err}");
		return $result;
	}
}

function np_ensure_xml_fileds($returned_object){
	if(!$returned_object)
		$returned_object = new stdClass;
	
	$rflds = array(
		'OrderId',
		'Response',
		'TransId',
		'AuthCode',
		'ProcReturnCode'
	);
	
	$rflds_extra = array(
		'ORD_ID',
		'MDSTATUS',
		'AUTH_CODE',
		'AUTH_DTTM',
		'TRXDATE'
	);
	
	foreach($rflds as $ind => $fld){
		if(!isset($returned_object->{$fld})){
			$returned_object->{$fld} = null;
		}
	}
	
	if(!isset($returned_object->Extra)){
		$returned_object->Extra = new stdClass;
	}
	
	foreach($rflds_extra as $ind => $fld){
		if(!isset($returned_object->Extra->{$fld})){
			$returned_object->Extra->{$fld} = null;
		}
	}
	
	return $returned_object;
}

function np_nptransaction_get(){
	header("Content-Type:text/html");
	
	$tc_self = WC_Gateway_Halkbank::instance();
	
	$order_id = np_get_current_order_id();
	
	if($order_id && $tc_self && isset($tc_self->include_order_details) && $tc_self->include_order_details){
		echo $tc_self->order_details_html($order_id);
		echo "<hr/>";
	}
	
	echo do_shortcode("[nptransaction]");
	die;
}

function np_api_test(){
	
	header("Content-Type:application/json");
	
	$prev_err = get_option("halkbank_last_display_error","");
	$poptions = np_get_plugin_options();
	
	if(isset($_REQUEST["api_url"])){
		$poptions->api_url = $_REQUEST["api_url"];
	}
	
	if(isset($_REQUEST["api_username"])){
		$poptions->merchant_username = $_REQUEST["api_username"];
	}
	
	if(isset($_REQUEST["api_password"])){
		$poptions->merchant_password = $_REQUEST["api_password"];
	}
	
	if(isset($_REQUEST["merchant_id"])){
		$poptions->merchant_id = $_REQUEST["merchant_id"];
	}
	
	global $tc_last_net_err;  
	global $tc_last_net_err_code;
	np_queryFromGateway(microtime(true), null, $poptions);
	update_option("halkbank_last_display_error",$prev_err);
	
	if($tc_last_net_err_code == 400 || $tc_last_net_err_code == 403){
		wp_send_json(array("code" => $tc_last_net_err_code, "message" => "API COMM ERROR:" . $tc_last_net_err),200);
	}else{
		if($tc_last_net_err_code == 404)
			$tc_last_net_err_code = 200;//fictive order id
		wp_send_json(array("code" => $tc_last_net_err_code, "message" => "API COMM: OK"),200);
	}
	die;
}

function np_queryFromGateway($oid, $no_retry = false, $override_options = null){

	$poptions = $override_options ? $override_options : np_get_plugin_options();
	
	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;
	//encoding=\"ISO-8859-9\"
	$post_variables = array(
"DATA" => 
  "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
	 <CC5Request>
	 <Name>$merchant_username</Name>
	 <Password>$merchant_password</Password>
	 <ClientId>$merchant_id</ClientId>
	 <OrderId>$oid</OrderId>
	 <Extra>
	 <ORDERSTATUS>QUERY</ORDERSTATUS>
	 </Extra>
	</CC5Request>"
   );
   
    			
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, $post_variables);
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
	
	global $tc_last_net_err;  
	global $tc_last_net_err_code;

	
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	if($result){
		update_option("halkbank_last_display_error","");
		
		if(stripos($result,"<OrderId>") !== false){
			
			$xresp = simplexml_load_string($result);
			$xresp = json_decode(json_encode($xresp));
			$xresp = np_ensure_xml_fileds($xresp);
			
			$DPDAT = array();
			$DPDAT["clientid"]      = $poptions->merchant_id;
			$DPDAT["oid"]           = np_xmlval($xresp->OrderId);
			$DPDAT["Response"]      = np_xmlval($xresp->Response);
			$DPDAT["TransId"]       = np_xmlval($xresp->TransId);
			
			if(isset($xresp->Extra->ORD_ID)){
				$DPDAT["ReturnOid"]     = np_xmlval($xresp->Extra->ORD_ID);
				$DPDAT["mdStatus"]      = np_xmlval($xresp->Extra->MDSTATUS);
				$DPDAT["AuthCode"]      = np_xmlval($xresp->Extra->AUTH_CODE);
				$DPDAT["EXTRA_TRXDATE"] = np_xmlval($xresp->Extra->AUTH_DTTM);
				$DPDAT["ProcReturnCode"]= np_xmlval($xresp->ProcReturnCode);
			}else{
				$DPDAT["ReturnOid"]     = np_xmlval($xresp->OrderId);
				$DPDAT["mdStatus"]      = np_xmlval($xresp->Extra->MDSTATUS);
				$DPDAT["AuthCode"]      = np_xmlval($xresp->AuthCode);
				$DPDAT["EXTRA_TRXDATE"] = np_xmlval($xresp->Extra->TRXDATE);
				$DPDAT["ProcReturnCode"]= np_xmlval($xresp->ProcReturnCode);
			}
			
			if(isset($xresp->Extra->ORIG_TRANS_AMT)){
				$amt = floatval(np_xmlval($xresp->Extra->ORIG_TRANS_AMT)) / 100;
				if($amt)
					$DPDAT["amount"] = $amt;
			}
			
			if(!isset($DPDAT["mdStatus"]))
				$DPDAT["mdStatus"] = "--";
		
			if(!$DPDAT["ReturnOid"] && !$no_retry){
				sleep(1);
				return np_queryFromGateway($oid,true);
			}
			
			if(isset($DPDAT["Response"])){
				if($DPDAT["Response"]){
					update_option("halkbank_last_display_error","");
				}
			}
			return $DPDAT;
		}
		
		return null;
	}else{
		update_option("halkbank_last_display_error","API_COMM: {$tc_last_net_err}");
		return $result;
	}
}

function np_subscription_cancel($order_id, $ref_id){
	
	$poptions = np_get_plugin_options();

	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;
	
	$req = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
			<CC5Request>
				<Name>$merchant_username</Name>
				<Password>$merchant_password</Password>
				<ClientId>$merchant_id</ClientId>
				<Extra>
					<RECURRINGOPERATION>Cancel</RECURRINGOPERATION>
					<RECORDTYPE>".($order_id ? "Order" : "Recurring" )."</RECORDTYPE>
					<RECORDID>{$ref_id}</RECORDID>
				</Extra>
			</CC5Request>";
	
	$post_variables = array("DATA" => $req);
	
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, $post_variables);
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
	
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	
	if($result){
		$xresp = simplexml_load_string($result);
		if($xresp){
			$xresp = json_decode(json_encode($xresp), true);
			
			if($xresp){
				if(isset($xresp["Extra"])){
					foreach($xresp["Extra"] as $key => $val){
						$xresp["EXTRA_" . strtoupper($key)] = $val;
					}
				}
				return $xresp;
			}
		}		
	}
	
	global $tc_last_net_err;  
	global $tc_last_net_err_code; 
	
	return array(
			'oid'            => $order_id,
			'TransId'        => "--",
			'Response'       => __("Request error","halkbank") . " {$tc_last_net_err_code} {$tc_last_net_err}" ,
			'ProcReturnCode' => "--",
			'mdStatus'       => "--",
			'AuthCode'       => "--",
			'EXTRA_TRXDATE'  => "--",
			'instalment'     => "--",
			'ProcReturnCode' => "--"
		);
}

function np_subscription_modify($order_id, $ref_id, $currency_ncode, $amount){
	
	$poptions = np_get_plugin_options();

	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;
	
	$req = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
			<CC5Request>
				<Name>$merchant_username</Name>
				<Password>$merchant_password</Password>
				<ClientId>$merchant_id</ClientId>
				<Currency>{$currency_ncode}</Currency>
				<Extra>
					<RECURRINGOPERATION>Update</RECURRINGOPERATION>
					<RECORDTYPE>".($order_id ? "Order" : "Recurring" )."</RECORDTYPE>
					<RECORDID>{$ref_id}</RECORDID>
					<AMOUNT>{$amount}</AMOUNT>
				</Extra>
			</CC5Request>";
	$post_variables = array("DATA" => $req);
	
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, $post_variables);
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
	
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	
	if($result){
		$xresp = simplexml_load_string($result);
		if($xresp){
			$xresp = json_decode(json_encode($xresp), true);
			
			if($xresp){
				if(isset($xresp["Extra"])){
					foreach($xresp["Extra"] as $key => $val){
						$xresp["EXTRA_" . strtoupper($key)] = $val;
					}
				}
				return $xresp;
			}
		}		
	}
	
	global $tc_last_net_err_code;
	global $tc_last_net_err;
	
	
	return array(
			'oid'            => $order_id,
			'TransId'        => "--",
			'Response'       => __("Request error","halkbank") . " {$tc_last_net_err_code} {$tc_last_net_err}",
			'ProcReturnCode' => "--",
			'mdStatus'       => "--",
			'AuthCode'       => "--",
			'EXTRA_TRXDATE'  => "--",
			'instalment'     => "--",
			'ProcReturnCode' => "--"
		);
}

function np_subscription_create($order_id, $amount , $CC, $EXP, $CVV2, $currency_ncode, $interval ='M', $frequency = 1, $instalments = null){
	
	$poptions = np_get_plugin_options();

	$merchant_username = $poptions->merchant_username;
	$merchant_password = $poptions->merchant_password;
	$merchant_id       = $poptions->merchant_id;

	$req = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
			<CC5Request>
				<Name>$merchant_username</Name>
				<Password>$merchant_password</Password>
				<ClientId>$merchant_id</ClientId>
				<OrderId>$order_id</OrderId>
				<Type>Auth</Type>
				<Number>$CC</Number>
				<Expires>$EXP</Expires>
				<Cvv2Val>$CVV2</Cvv2Val>
				<Total>$amount</Total>
				<Currency>$currency_ncode</Currency>
				<PbOrder>
				<OrderType>".( $instalments ? 1 : 0 )."</OrderType>
				<OrderFrequencyCycle>{$interval}</OrderFrequencyCycle>
				<TotalNumberPayments>".( $instalments === null ? 9999 : $instalments)."</TotalNumberPayments>
				<OrderFrequencyInterval>{$frequency}</OrderFrequencyInterval>
				</PbOrder>
				<VersionInfo>EPAYAPI-1.2.0.32</VersionInfo>
				<Extra></Extra>
			</CC5Request>";
	
	$post_variables = array("DATA" => $req);
	
	$result = null;
	try{
		$result = np_common_curl_request($poptions->api_url, $post_variables);
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
	global $last_curl_resp;
	$last_curl_resp = $result;
	
	
	if($result){
		$xresp = simplexml_load_string($result);
		if($xresp){
			$xresp = json_decode(json_encode($xresp), true);
			
			if($xresp){
				if(isset($xresp["Extra"])){
					foreach($xresp["Extra"] as $key => $val){
						$xresp["EXTRA_" . strtoupper($key)] = $val;
					}
				}
				return $xresp;
			}
		}		
	}
	
	global $tc_last_net_err_code;
	global $tc_last_net_err;
	
	return array(
			'oid'            => $order_id,
			'TransId'        => "--",
			'Response'       => __("Request error","halkbank")  . " {$tc_last_net_err_code} {$tc_last_net_err}",
			'ProcReturnCode' => "--",
			'mdStatus'       => "--",
			'AuthCode'       => "--",
			'EXTRA_TRXDATE'  => "--",
			'instalment'     => "--",
			'ProcReturnCode' => "--"
		);
}

function np_all_tax_classes_and_rates() {
	try{
		$all_tax_rates = array();
		$tax_classes = WC_Tax::get_tax_classes();

		if (!in_array('', $tax_classes)) {
			array_unshift($tax_classes, '');
		}

		foreach ($tax_classes as $tax_class) {
			$rates = WC_Tax::get_rates_for_tax_class($tax_class);
			foreach($rates as $index => $rate){
				$rate->tax_class = $tax_class;
			}
			$all_tax_rates = array_merge($all_tax_rates, $rates);
		}
		
		return $all_tax_rates;
	}catch(Throwable $ex){
		return false;
	}
}

function woo_halkbank_enqueue_script() {
	try{
		
		
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

		global $halkbank_add_logo_image,$halkbank_add_cc_image;

		if(!isset($halkbank_add_logo_image)) $halkbank_add_logo_image = "";
		if(!isset($halkbank_add_cc_image)) $halkbank_add_cc_image = "";

		$checkout_url = null;
		if (function_exists( 'wc_get_checkout_url' ) ) {
			$checkout_url = wc_get_checkout_url();
		}else{
			add_action( 'admin_notices', 'no_woocommerce_detected' );
		}
		
		global $tc_plugin_data;
		if(!isset($tc_plugin_data)){
			if( !function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$tc_plugin_data = get_plugin_data( __DIR__ . DIRECTORY_SEPARATOR . 'index.php' );
		}
		
		wp_enqueue_script( 'hlst_halkbank', plugin_dir_url( __FILE__ ) . 'js/script.js', array('jquery-core' ), $tc_plugin_data['Version']);
		
		if(!wp_script_is('tc-footer-script','queue'))
			wp_enqueue_script( 'tc-footer-script', plugin_dir_url( __FILE__ ) . 'js/tc_enqueue_script.js', array('jquery-core'), $tc_plugin_data['Version'], array("in_footer" => true) );
		
		$tc_self = WC_Gateway_Halkbank::instance();
		
		$method_description      	= trim($tc_self->description);
		$method_title 				= trim($tc_self->title);
		
		if(trim(__($method_description,"halkbank"))){
			$method_description = trim(__($method_description,"halkbank"));
		}
		
		if(trim(__($method_title,"halkbank"))){
			$method_title = trim(__($method_title,"halkbank"));
		}
		
		if(trim(__("TRANSLATE_PAYMENT_TITLE","halkbank")) && trim(__("TRANSLATE_PAYMENT_TITLE","halkbank")) != "TRANSLATE_PAYMENT_TITLE"){
			$method_title = __("TRANSLATE_PAYMENT_TITLE","halkbank");
		}
		
		if(trim(__("TRANSLATE_PAYMENT_DESCRIPTION","halkbank")) && trim(__("TRANSLATE_PAYMENT_DESCRIPTION","halkbank")) != "TRANSLATE_PAYMENT_DESCRIPTION"){
			$method_description = __("TRANSLATE_PAYMENT_DESCRIPTION","halkbank");
		}
		
		if(trim($method_description) == trim($method_title)){
			$method_description      	= trim($tc_self->description);
		}
		
		if(!isset($tc_self->woo_currency)){
			$tc_self->woo_currency = "";
		}
		
		$jsdata = array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'logoHTML'           => $halkbank_add_logo_image,
				'ccHTML'             => $halkbank_add_cc_image,
				'logo'               => isset($poptions->bank_logo) ? $poptions->bank_logo: "",
				'checkoutURL'        => $checkout_url,
				'plugin_version'     => $tc_plugin_data['Version'],
				'woo_currency'       => get_woocommerce_currency(),
				'woo_currency_n'     => tc_get_order_currency(),
				'locale'             => get_locale(), 
				'labels'         => array(
					'method_title'       => $method_title,
					'method_description' => $method_description,
					'method_title_raw'       => trim($tc_self->title),
					'method_description_raw' => trim($tc_self->description)
				) 
		);
		
		if(current_user_can('administrator')){
			$jsdata["admin"] = 1;
		}

		if(is_admin()){
			$jsdata["is_admin"] = 1;
			global $tc_site_cfg_uid;

			$all_tax_rates = array();
			$tax_classes = array();
			try{
				$tax_classes = WC_Tax::get_tax_rate_classes();
			}catch(Throwable $tr_ex){
				try{
					global $wpdb;
					$tax_classes = $wpdb->get_col($wpdb->prepare("SELECT slug FROM {$wpdb->prefix}tax_rate_classes WHERE 1 = %d",1));
				}catch(Throwable  $tr_ex_old){
					echo "<!-- NP_EXCEPTION: " . $tr_ex_old->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
				}
			}
			
			if ( !in_array( '', $tax_classes ) ) {
				array_unshift( $tax_classes, '' );
			}
			foreach ( $tax_classes as $tax_class) {
				if(is_string($tax_class)){
					$rates = WC_Tax::get_rates_for_tax_class( $tax_class );
					if(empty($rates)){
						$tax_class = sanitize_title($tax_class);
						$rates = WC_Tax::get_rates_for_tax_class( $tax_class );
						if(empty($rates)){
							$rates = null;	
						}
					}else{
						$tax_class = sanitize_title($tax_class);
					}
					$all_tax_rates[$tax_class] = $rates;
				}else{
					$rates = WC_Tax::get_rates_for_tax_class( $tax_class->name );
					if(empty($rates))
						$rates = null;
					$all_tax_rates[$tax_class->slug] = $rates;
				}
			}
			
			try{
				$rates2 = np_all_tax_classes_and_rates();
				if($rates2 && !empty($rates2)){
					foreach($rates2 as $rate){
						if(!isset($all_tax_rates[$rate->tax_class])){
							$all_tax_rates[$rate->tax_class] = array();
						}
						if(!isset($all_tax_rates[$rate->tax_class][$rate->tax_rate_id])){
							$all_tax_rates[$rate->tax_class][$rate->tax_rate_id] = $rate;
						}
					}
				}
			}catch(Throwable $trex){
				//
			}
			

			$jsdata["all_tax_rates"]       = $all_tax_rates;
						
			$jsdata["all_payment_methods"] = array();
			$jsdata["site_cfg_uid"]        = $tc_site_cfg_uid;
			$jsdata["current_screen_id"]   = get_current_screen()->id;

			$jsdata["LABELS"] = array(
					"sale" => __("Sale","halkbank"),
					"refund" => __("Refund","halkbank"),
					"view" => __("View receipt log","halkbank"),
					"advance" => __("Advance","halkbank"),
					"order" => __("Order","halkbank"),
					"print" => __("Print","halkbank"),
					"close" => __("Close","halkbank"),
					"refunded" => __("Refunded","halkbank"),
					"mail_sent_to" => __("The e-mail has been sent to:","halkbank") . " ",
					"error" => __("Unknown error",'halkbank'),
					"download_pdf" => __("Download PDF","halkbank"),
					"pdf_email_customer" => __("E-mail PDF to customer","halkbank"),
					"receipt_time" => __("Receipt time",'halkbank'),
					"receipt_invoice_type" => __("Invoice type",'halkbank'),
					"receipt_transaction_type" => __("Transaction type",'halkbank'),
					"receipt_invoice_no" => __("Tax. Invoice no.",'halkbank'),
					"receipt_total" => __("Total",'halkbank'),
					"receipt_ref" => __("Ref.",'halkbank'),
					"referent_tax_no_not_found" => __("was not found as Tax Invoice No. in previous receipts for this order",'halkbank')
				);

			foreach(WC()->payment_gateways->get_available_payment_gateways() as $method){
				$jsdata["all_payment_methods"][$method->id] = $method->method_title;
			}

			$jsdata["all_payment_methods"][""] = "--" . __("default","halkbank") . "--";

			if($jsdata["current_screen_id"] == "edit-shop_order" || $jsdata["current_screen_id"] == "woocommerce_page_wc-orders" || $jsdata["current_screen_id"] == "shop_order"){
				wp_enqueue_script('media-upload');
				wp_enqueue_script('thickbox');
				wp_enqueue_style('thickbox');
			}
		}else{
			$tc_self = WC_Gateway_Halkbank::instance();
			if(isset($tc_self->nphide))
				$jsdata["hidden"] = $tc_self->nphide;
		}
		
		wp_localize_script( 'hlst_halkbank', 'Halkbank',$jsdata);
		wp_enqueue_style( 'halkbankcss', plugin_dir_url( __FILE__ ) . 'css/style.css', array(  ), $tc_plugin_data['Version']);

		// Enqueue modern admin settings styles and scripts for Halkbank settings page
		if(is_admin() && isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['section']) && ($_GET['section'] == 'halkbank' || $_GET['section'] == 'wc_gateway_halkbank')){
			wp_enqueue_style( 'halkbank-admin-settings', plugin_dir_url( __FILE__ ) . 'css/admin-settings.css', array('halkbankcss'), $tc_plugin_data['Version']);
			wp_enqueue_script( 'halkbank-admin-settings', plugin_dir_url( __FILE__ ) . 'js/admin-settings.js', array('jquery', 'hlst_halkbank'), $tc_plugin_data['Version'], true);
		}
		
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		echo "<!-- NP_EXCEPTION: " . $ex->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
	}
}

function np_delete_cached_fonts(){
						
	$font_dir = __DIR__ . DIRECTORY_SEPARATOR . "pdf". DIRECTORY_SEPARATOR ."font". DIRECTORY_SEPARATOR ."unifont". DIRECTORY_SEPARATOR; 
	foreach(glob($font_dir."*.cw.dat") as $cachedfont){
		@unlink($font_dir. basename($cachedfont));	
	}
	
	foreach(glob($font_dir."*.cw127.php") as $cachedfont){
		@unlink($font_dir. basename($cachedfont));	
	}
	
	foreach(glob($font_dir."*.mtx.php") as $cachedfont){
		@unlink($font_dir. basename($cachedfont));	
	}
	
}


function halkbank_order_actions_buttons( $actions, $order ) {
	
	if(!class_exists('WC_Gateway_Halkbank'))
		return $actions;

	$payment_method = null;
	if(method_exists($order, 'get_payment_method')){
		$payment_method = $order->get_payment_method();
	}else{
		$payment_method = $order->payment_method;
	}

	$order_id = null;
	if(method_exists($order, 'get_id')){
		$order_id = $order->get_id();
	}else{
		$order_id = $order->id;
	}
	
	if(!($order->get_total() > 0)){
		return $actions;
	}

	if($payment_method == "halkbank"){
		
		$actions["cmd_halkbank_refresh"] = array(
							"action" => "halkbank_refresh",
							"name"   => __( 'Read status', 'halkbank' ),
							"url"    => add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_refresh", home_url( '/' )))
						);
						
						
		if(!$order->has_status( array('refunded' ))){
			$transaction_data = $order->get_meta('_halkbank_transation_data');

			if($transaction_data){
				$transaction_data = json_decode($transaction_data,true);
				if($transaction_data){

					if(
							isset($transaction_data["void"])
							||
							isset($transaction_data["refund"])
					){
						return $actions;
					}

					if(isset($transaction_data["auth"])){
						if(isset($transaction_data["auth"]["Response"])){
							if(stripos($transaction_data["auth"]["Response"],"approved") !== false){
								$actions["cmd_halkbank_refund"] = array(
									"action" => "halkbank_refund",
									"name"   => __( 'Refund', 'halkbank' ),
									"url"    => add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_refund", home_url( '/' )))
								);
							}
						}	
					}else if(isset($transaction_data["preauth"])){
						if(isset($transaction_data["postauth"])){
							if(isset($transaction_data["postauth"]["Response"])){
								if(stripos($transaction_data["postauth"]["Response"],"approved") !== false){
									$actions["cmd_halkbank_refund"] = array(
										"action" => "halkbank_refund",
										"name"   => __( 'Refund', 'halkbank' ),
										"url"    => add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_refund", home_url( '/' )))
									);
								}
							}
						}else{
							if(isset($transaction_data["preauth"]["Response"])){
								if(stripos($transaction_data["preauth"]["Response"],"approved") !== false){
									$actions["cmd_halkbank_void"] = array(
										"action" => "halkbank_void",
										"name"   => __( 'VOID (release money)', 'halkbank' ),
										"url"    => add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_void", home_url( '/' )))
									);
									
									$actions["cmd_halkbank_capure"] = array(
										"action" => "halkbank_capure",
										"name"   => __( 'Capture (PostAutorization)', 'halkbank' ),
										"url"    => add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_capture", home_url( '/' )))
									);
									
								}
							}	
						}
					}
				}
			}
		}

		$actions["cmd_halkbank_useportal"] = array(
			"action" => "use_bank_portal",
			"name"   => __( 'Open merchant virtual terminal...', 'halkbank' ),
			"url"    => str_ireplace("fim/est3Dgate","bib/report/user.login",$tc_self->gateway_url)
		);
	}
	
	tc_commit_orders();
	return $actions;
}
	
function action_woocommerce_order_halkbank_item_add_action_buttons( $order ){
	$payment_method = null;
	if(method_exists($order, 'get_payment_method')){
		$payment_method = $order->get_payment_method();
	}else{
		$payment_method = $order->payment_method;
	}

	if($payment_method == "halkbank"){


		global $npoptions;
		if(!isset($npoptions)){
			$poptions = np_get_plugin_options();
		}


		$order_id = null;
		if(method_exists($order, 'get_id')){
			$order_id = $order->get_id();
		}else{
			$order_id = $order->id;
		}

		$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);

		$href = add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_refresh", home_url( '/' )));
		echo "<a title='" .__( 'Re-Query status from gateway server', 'halkbank' )."' class='halkbank_button button halkbank_refresh button-primary order-details-page' href='{$href}'><i class='dashicons dashicons-update'></i> " .__( 'Read status', 'halkbank' )."</a>";

		if(!$order->has_status( array( 'refunded' )) && $transaction_data){
			$transaction_data = json_decode($transaction_data,true);
			if($transaction_data){


				if(
						isset($transaction_data["void"])
						||
						isset($transaction_data["refund"])
				){
					return;
				}

				if(isset($transaction_data["preauth"])){
					if(!isset($transaction_data["postauth"])){
						if(isset($transaction_data["preauth"]["Response"])){
							if(stripos($transaction_data["preauth"]["Response"],"approved") !== false){
								$href = add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_void", home_url( '/' )));
								echo "<a style='margin:4px;' title='" .__( 'VOID (relese reserved mony on customer credit card)', 'halkbank' )."' class='halkbank_button button halkbank_void button-primary order-details-page' href='{$href}'><i class='dashicons dashicons-welcome-comments'></i> " .__( 'VOID (release money)', 'halkbank' )."</a>";
								if($order->get_meta("_halkbank_last_tran_type") != "declined"){
									$href = add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_capture", home_url( '/' )));
									echo "<a style='margin:4px;' title='" .__( 'Capture (get reserved money from customer credit card)', 'halkbank' )."' class='halkbank_button button halkbank_capure button-primary order-details-page' href='{$href}'><i class='dashicons dashicons-download'></i> " .__( 'Capture (PostAutorization)', 'halkbank' )."</a>";
								}
							}
						}
					}
				}
				
				if(isset($transaction_data["postauth"])){
					if(isset($transaction_data["postauth"]["Response"])){
						if(stripos($transaction_data["postauth"]["Response"],"approved") !== false){
							$href = add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_void", home_url( '/' )));
							echo "<a style='margin:4px;' title='" .__( 'VOID (relese reserved mony on customer credit card)', 'halkbank' )."' class='halkbank_button button halkbank_void button-primary order-details-page' href='{$href}'><i class='dashicons dashicons-welcome-comments'></i> " .__( 'VOID (release money)', 'halkbank' )."</a>";
						}
					}
				}
				
				if(isset($transaction_data["auth"])){
					if(isset($transaction_data["auth"]["Response"])){
						if(stripos($transaction_data["auth"]["Response"],"approved") !== false){
							$href = add_query_arg( 'order_id', $order_id, add_query_arg( 'wc-api', 'wc_gateway_' . $payment_method ."_void", home_url( '/' )));
							echo "<a style='margin:4px;' title='" .__( 'VOID (relese reserved mony on customer credit card)', 'halkbank' )."' class='halkbank_button button halkbank_void button-primary order-details-page' href='{$href}'><i class='dashicons dashicons-welcome-comments'></i> " .__( 'VOID (release money)', 'halkbank' )."</a>";
						}
					}
				}
			}
		}
		
		
		$href = str_ireplace("fim/est3Dgate","bib/report/user.login",$poptions->gateway_url);
		echo "<a style='margin:4px;' target='_blank' title='" .__( 'Open merchant virtual terminal...', 'halkbank' )."' class='halkbank_button button use_bank_portal button-primary order-details-page' href='{$href}'><i class='dashicons dashicons-admin-site-alt3'></i> " .__( 'Open merchant virtual terminal...', 'halkbank' )."</a>";
	
	}
}

add_action( 'wp_footer',  'np_insert_footer_branding', 999);

function np_insert_footer_branding($footer_template){
	try{
		if(is_admin())
			return;
		
		$tcself = WC_Gateway_Halkbank::instance();
		if($tcself->footer_template){
			if(file_exists(__DIR__ . "/footer_branding/{$tcself->footer_template}/cards")){
				
				?>
				<div class="np_footer_branding" style='display:flex;justify-content:center;padding:4px 0;'>
					<div class="np-footer-branding-cards" style='display:flex'>
						<?php 
						$ftemplate = $tcself->footer_template;
						echo implode(" ",array_map(function($img_path) use($ftemplate){
							$imgname = basename( $img_path );
							$img_url = rtrim(HALKBANK_PLUGIN_URL,"/") . "/footer_branding/{$ftemplate}/cards/" . $imgname; 
							return "<img style='height:30px;' src='{$img_url}' alt='{$imgname}' />";
						}, glob(__DIR__ . "/footer_branding/{$tcself->footer_template}/cards/*.{jpg,png,gif,jgeg,svg}" ,GLOB_BRACE)));
						
						?>
					</div>
					<div style='padding: 0 25px;'>&nbsp;</div>
					<div class="np-footer-branding-bank" style='display:flex'>
						<?php
							$bimg = glob(__DIR__ . "/footer_branding/{$tcself->footer_template}/bank/*.{jpg,png,gif,jgeg,svg}" ,GLOB_BRACE);
							if(!empty($bimg)){
								$bimg = rtrim(HALKBANK_PLUGIN_URL,"/") . "/footer_branding/{$ftemplate}/bank/" . basename($bimg[0]);
								$link = "";
								if(file_exists(__DIR__ . "/footer_branding/{$tcself->footer_template}/bank/link.txt")){
									$link = trim(file_get_contents(__DIR__ . "/footer_branding/{$tcself->footer_template}/bank/link.txt"));
								}
								echo "<a href='{$link}' target='_blank' ><img style='height:32px;' src='{$bimg}' /></a>";
							}
						?>
					</div>
					<div style='padding: 0 25px;'>&nbsp;</div>
					<div class="np-footer-branding-3ds" style='display:flex'>
						<?php
							foreach( array("visa_secure", "master_id_check", "key_safe") as $s3ds_method){
								$s3ds_img = glob(__DIR__ . "/footer_branding/{$tcself->footer_template}/{$s3ds_method}/*.{jpg,png,gif,jgeg,svg}" ,GLOB_BRACE);
								if(!empty($s3ds_img)){
									$s3ds_img = rtrim(HALKBANK_PLUGIN_URL,"/") . "/footer_branding/{$ftemplate}/{$s3ds_method}/" . basename($s3ds_img[0]);
									$link = "";
									if(file_exists(__DIR__ . "/footer_branding/{$tcself->footer_template}/{$s3ds_method}/link.txt")){
										$link = trim(file_get_contents(__DIR__ . "/footer_branding/{$tcself->footer_template}/{$s3ds_method}/link.txt"));
									}
									echo "<a href='{$link}' target='_blank' ><img style='height:32px;' src='{$s3ds_img}' /></a>";
								}
							}
						?>
					</div>
				</div>
				<?php
				
			}
		}
	}catch(Throwable $ex){
		//
	}
}

if(!defined("TCCALLS_LOADED")){
	define("TCCALLS_LOADED",1);
}
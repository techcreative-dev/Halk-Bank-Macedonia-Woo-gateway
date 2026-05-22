<?php
if(!function_exists("add_action")){
	die("Direct access is not allowed");
};

if(!function_exists("np_server_variable")){
	function np_server_variable($name, $default = null){
		if(isset($_SERVER[$name])){
			return $_SERVER[$name];
		}
		return $default;
	}
}

try{
	require_once(__DIR__ .DIRECTORY_SEPARATOR . "tccalls.php");
}catch(Throwable $ex){
	echo "<!-- TC_EXCEPTION: " . $ex->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
}

if(!defined("TCCALLS_LOADED")){
	return;
}

try{
	require_once(__DIR__ .DIRECTORY_SEPARATOR . "tc_actions.php");
}catch(Throwable $ex){
	echo "<!-- TC_EXCEPTION: " . $ex->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
}

if(!defined("TC_ACTIONS_LOADED")){
	return;
}

if(isset($_GET["halkbank_oid"]) || isset($_POST["ProcReturnCode"])){
	if(isset($_POST["lang"])){
		unset($_POST["lang"]);//Fix for "A variable mismatch has been detected."
	}
}

function halkbank_method_id(){
	return "halkbank";
}

global $TC_CLIENT_SCHEMA; 
$TC_CLIENT_SCHEMA = stripos(np_server_variable('SERVER_PROTOCOL','https'),'https') === true ? 'https://' : 'http://';
$plugin = HLST_NPAY_PLUGIN;

define("HALKBANK_PLUGIN_NP",$plugin);

add_filter( 'wp_redirect_status', 'np_redirect_status', 99, 2 );
add_filter( "woocommerce_currency_symbols" ,"np_csymbol_fix_registry" ,20    ,1);
add_filter( "woocommerce_currency_symbol"  ,"np_csymbol_fix_symbol",9999     ,2);
add_filter( "woocommerce_email_headers", "np_woocommerce_email_headers",1, 4);
add_action( 'woocommerce_view_order', 'np_woocommerce_current_order', 1);
add_action( 'woocommerce_thankyou', 'np_woocommerce_current_order', 1);
add_action( 'woocommerce_view_order', 'np_in_order_view', 15);
add_action( 'woocommerce_thankyou', 'np_in_thankyou', 15);
add_action( 'woocommerce_email_after_order_table', 'np_in_order_email', 99, 1);
add_filter( 'woocommerce_email_attachments', 'np_order_add_attachments', 99, 3 );
add_filter( "plugin_action_links_$plugin", 'np_settings' );
add_action( 'wp_enqueue_scripts', 'woo_halkbank_enqueue_script_front' );
add_action( 'admin_enqueue_scripts', 'woo_halkbank_enqueue_script' );
add_action( 'plugins_loaded', 'halkbank_plugin_textdomain' );
add_action( 'wp_loaded', 'halkbank_check_pending_orders');
add_filter( 'the_content', 'np_add_to_content' );
add_action( 'wp_ajax_nopriv_halkbankrecalcform', 'np_halkbankrecalcform' );
add_action( 'plugins_loaded', 'do_init_np_woo_gateway',1);
add_filter( 'woocommerce_payment_gateways', 'add_halkbank_payment_gateway' );
add_action( 'woocommerce_after_order_object_save', 'tc_on_order_saved', 99, 1 );

add_action( 'woocommerce_blocks_loaded', 'np_woocommerce_gateway_block_support' );

if(isset($_REQUEST["halkbank_oid"]) && np_server_variable('REQUEST_METHOD','') == 'POST'){
	add_filter('the_content', 'halkbank_exlpicit_response_output');
}

global $currencies;
$currencies = null;

if(isset($_GET["key"]) || isset($_GET["order-received"])){
	add_filter( 'gettext', 'fix_nazalost_message', 10, 3 );
}

function fix_nazalost_message( $translated_text, $untranslated_text, $domain ) {
    if ( 'woocommerce' === $domain ) {
		if(stripos($untranslated_text,"originating bank/merchant has declined your transaction") !== false){
			return __("Unfortunately the payment has failed. Please try agian.","halkbank");
		}
    }
	return $translated_text;
}

function add_halkbank_payment_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Halkbank';
	return $methods;
}


function no_woocommerce_detected(){
	echo '<div class="error"><p>'. __("Halkbank plugin: no wooCommerce? This is a plugin for wooCommerce.") . '</p></div>';
}

function np_only_once($what){
	global $_np_once;
	if(!isset($_np_once)){
		$_np_once = array();
	}
	
	if(!isset($_np_once[$what])){
		$_np_once[$what] = 1;
	}else
		return false;
	
	try{
		
		$done = get_site_transient( '_np_once_' . $what );
		if($done)
			return false;
		
		set_site_transient( '_np_once_' . $what, time());
	}catch(Throwable $ex){
		//
	}
	return true;
}

function woo_halkbank_enqueue_script_front() {
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	woo_halkbank_enqueue_script();
}

if(!function_exists("debugErrorHandler")){
	function debugErrorHandler($severity, $message, $file, $line = null , $ctx = null){

	}
}

function do_init_np_woo_gateway(){
	try{
		init_np_woo_gateway();
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		echo "<!-- NP_EXCEPTION: " . $ex->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
	}
}

function np_prevent_cart_empty($arg1){
	global $halkbank_clean_cart_flag;
	if(isset($halkbank_clean_cart_flag)){
		if($halkbank_clean_cart_flag)
			return;
	}
	remove_action('get_header', 'wc_clear_cart_after_payment');
}



require_once(__DIR__ . DIRECTORY_SEPARATOR . "tc_order_storage.php");

function init_np_woo_gateway(){

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	if(!defined("TCCALLS_LOADED") || !defined("TC_ACTIONS_LOADED")){
		return;
	}

	add_filter( 'woocommerce_admin_order_actions', 'halkbank_order_actions_buttons', 100, 2 );
	add_action( 'woocommerce_order_item_add_action_buttons', 'action_woocommerce_order_halkbank_item_add_action_buttons', 10, 1);
	add_action( 'woocommerce_init', 'np_prevent_cart_empty');

	set_error_handler("halkbank_error_handler",E_ERROR | E_USER_ERROR | E_PARSE | E_COMPILE_ERROR | E_RECOVERABLE_ERROR);
	set_exception_handler("halkbank_uncaught_exception_handler");

    

    #[\AllowDynamicProperties]
	class WC_Gateway_Halkbank extends WC_Payment_Gateway {
		
		public $instance_uid       = null;
		public $plugin             = HLST_NPAY_PLUGIN;
		public $plugin_url         = null;
		public $tc_site_cfg_uid    = null;
		public $DPDAT              = null;
		
		public $MDSATUS_MAP = array(
			0 => false,
			1 => true,
			3 => null,
			4 => null,
			5 => null,
			6 => null,
			7 => null
		);	
		
		use WC_Gateway_Halkbank_actions;
	
		public static function instance($nocreate = false){
			global $tc_payment_plugin_instance;
			if(isset($tc_payment_plugin_instance)){
				return $tc_payment_plugin_instance;
			}
			
			if($nocreate){
				return null;
			}
			
			$tc_payment_plugin_instance = new WC_Gateway_Halkbank();
			return $tc_payment_plugin_instance;
		}

		public function __construct(){
			
			try{
				global $tc_payment_plugin_instance;
				global $woocommerce, $tc_payment_plugin_instance;
				
				$this->plugin_url     = rtrim(rtrim(__DIR__,"/"),"\\");
				$this->instance_uid   = uniqid("np");
				
				$__is_first_instance = WC_Gateway_Halkbank::instance(true) === null;

				$tc_payment_plugin_instance = $this;
				

				global $tc_site_cfg_uid;
				$tc_site_cfg_uid = get_option("unique_site_uid",null);

				if(!$tc_site_cfg_uid){
					$tc_site_cfg_uid = uniqid("wooc_site_");
					update_option("unique_site_uid",$tc_site_cfg_uid,true);
				}

				$this->np_site_cfg_uid      = $tc_site_cfg_uid;

				$this->id 					= 'halkbank';
				$this->has_fields   		= false;
				$this->method_title 		= 'Halkbank Payment';
				$this->method_description 	= __('Accept credit card payments via Halkbank.', 'halkbank');
			
				
				$this->supports[] = 'refunds';
				$this->init_form_fields();
				$chk_fields = array();
				
				foreach($this->form_fields as $parm_name => $field_cfg){
					if(isset($field_cfg["type"])){
						if($field_cfg["type"] == "checkbox"){
							$chk_fields[] = $parm_name;
						}
					}
				}
				
				if(isset($_POST["woocommerce_halkbank_title"]) && isset($_REQUEST["page"]) && isset($_REQUEST["tab"]) && isset($_REQUEST["section"])){
					if($_REQUEST["page"] == "wc-settings" && $_REQUEST["tab"] == "checkout" && stripos(str_ireplace(array("_","-"),array("",""),$_REQUEST["section"]),$this->id) !== false){

						$settings = get_option("woocommerce_{$this->id}_settings",array());
						$fis_enabled = false;
						
						foreach($this->form_fields as $parm_name => $field_cfg){

							if(isset($_POST["woocommerce_{$this->id}_{$parm_name}"])){
								$val = $_POST["woocommerce_{$this->id}_{$parm_name}"];
							}else{
								$val = NULL;
							}
							
							if(in_array($parm_name,$chk_fields)){
								if(strtolower($val ? $val : "") == "true"  || $val == "1" || strtolower($val ? $val : "") == "yes")
									$val = "yes";
								else
									$val = "no";
							}

								if(!is_array($val))
								$settings[$parm_name] = trim($val ? $val : "");
							else 
								$settings[$parm_name] = $val;
						}

						update_option("woocommerce_{$this->id}_settings",$settings,true);
						$this->settings = $settings;
						np_delete_cached_fonts();
						
					}
				}

				if(empty($this->settings)){
					$this->init_settings();
					if(empty($this->settings)){
						$this->settings = (array)get_option("woocommerce_{$this->id}_settings",array());
					}else if(!isset($this->settings["order_completed"])){
						$this->settings = (array)get_option("woocommerce_{$this->id}_settings",array());
					}
				}

				foreach($chk_fields  as $chk_field){
					$this->{$chk_field} = $this->get_option( $chk_field, null);
					if($this->{$chk_field} === null){
						if(isset($this->form_fields[$chk_field])){
							if(isset($this->form_fields[$chk_field]['default'])){
								$this->{$chk_field} = $this->form_fields[$chk_field]['default'];
							}	
						}
					}
					
					if($this->{$chk_field} == '0' || stripos($this->{$chk_field},"no") !== false || stripos($this->{$chk_field},"false") !== false){
						$this->{$chk_field} = false;
					}else
						$this->{$chk_field} = !!$this->{$chk_field};
				}
				
				$this->description      	= trim($this->get_option( 'description' ));
				$this->title 				= trim($this->get_option( 'title' ));
				
				if(trim(__("TRANSLATE_PAYMENT_TITLE","halkbank")) && trim(__("TRANSLATE_PAYMENT_TITLE","halkbank")) != "TRANSLATE_PAYMENT_TITLE"){
					$this->title = __("TRANSLATE_PAYMENT_TITLE","halkbank");
				}
				
				if(trim(__("TRANSLATE_PAYMENT_DESCRIPTION","halkbank")) && trim(__("TRANSLATE_PAYMENT_DESCRIPTION","halkbank")) != "TRANSLATE_PAYMENT_DESCRIPTION"){
					$this->description = __("TRANSLATE_PAYMENT_DESCRIPTION","halkbank");
				}
				
				$this->merchant_id 			= $this->get_option( 'merchant_id' );
				$this->gateway_url 			= "https://epay.halkbank.mk/fim/est3Dgate";
				$this->api_url              = "https://epay.halkbank.mk/fim/api";

				$this->merchant_username    = $this->get_option( 'merchant_username' );
				$this->merchant_password    = $this->get_option( 'merchant_password' );

				$this->cancel_url           = trim($this->get_option( 'cancel_url' ));
				if($this->cancel_url){
					if(strpos($this->cancel_url,".") === false){
						$this->cancel_url = site_url($this->cancel_url);
					}	
				}
			
				$this->merchant_currency    = $this->get_option( 'merchant_currency' );
				if(!$this->merchant_currency)
					$this->merchant_currency = "";

				$this->conversion_rate_adjust      = $this->get_option( 'conversion_rate_adjust' );
				if(!$this->conversion_rate_adjust)
					$this->conversion_rate_adjust = 0;
				else{
					$this->conversion_rate_adjust = floatval($this->conversion_rate_adjust);
					if(!$this->conversion_rate_adjust)
						$this->conversion_rate_adjust = 0;
				}


				$this->store_key			= trim($this->get_option( 'store_key' ));

				$this->completed			= $this->get_option( 'order_completed','wc-completed' );
				$this->failed				= $this->get_option( 'order_failed','wc-failed' );
				$this->postauthorised       = $this->get_option( 'order_transaction_postauthorised','wc-completed');
				$this->voided               = $this->get_option( 'order_transaction_voided','wc-canceled');
				
				if($this->completed == "wc-pending"){
					$this->completed = "wc-processing";
				}
				
				if($this->failed == "wc-pending"){
					$this->failed = 'wc-failed';
				}
				
				if($this->postauthorised == "wc-pending"){
					$this->postauthorised = 'wc-completed';
				}
				
				if($this->voided == "wc-pending"){
					$this->voided = 'wc-canceled';
				}

				$this->use_recaptcha        = $this->get_option( 'use_recaptcha' );
				$this->installmentonhpp     = $this->get_option( 'installmentonhpp' );

				$this->additional_form_variables = $this->get_option( 'additional_form_variables' );

				if(stripos($this->use_recaptcha,"no") !== false)
					$this->use_recaptcha = false;
				else
					$this->use_recaptcha = !!$this->use_recaptcha;

				if(stripos($this->installmentonhpp,"no") !== false)
					$this->installmentonhpp = false;
				else
					$this->installmentonhpp = !!$this->installmentonhpp;

				$this->recaptcha_site_key   = trim($this->get_option( 'recaptcha_site_key' ));
				$this->recaptcha_secret_key = trim($this->get_option( 'recaptcha_secret_key' ));
				$this->override_language    = $this->get_option( 'override_language' );

				$this->tran_type            = $this->get_option( 'tran_type' );
				$this->nphide                 = $this->get_option( 'nphide' );

				$this->refreshtime          = $this->get_option( 'refreshtime' );
				$this->store_type           = $this->get_option( 'store_type' );
				if(!$this->store_type)
					$this->store_type = "3d_pay_hosting";

				$this->instalment_plans     = trim($this->get_option( 'instalment_plans' ));

				$this->override_back_url     = trim($this->get_option( 'override_back_url' ));
				$this->after_override_timout = trim($this->get_option( 'after_override_timout' ));
				if($this->after_override_timout === "0" || intval($this->after_override_timout)){
					$this->after_override_timout = intval($this->after_override_timout);
				}else{
					$this->after_override_timout = null;
				}

				$this->user_language_code   = trim($this->get_option( 'user_language_code' ));
				$this->footer_template   = $this->get_option( 'footer_template','');

				if (np_server_variable('REQUEST_METHOD','') === 'POST') {
					if(isset($_REQUEST["page"]) && isset($_REQUEST["tab"]) && isset($_REQUEST["section"])){
						if($_REQUEST["page"] == "wc-settings" && $_REQUEST["tab"] == "checkout" && stripos(str_ireplace(array("_","-"),array("",""),$_REQUEST["section"]),$this->id) !== false){

							if(isset($_REQUEST["remove_bank_logo"])){
								if($_REQUEST["remove_bank_logo"]){
									delete_option( "woocommerce_halkbank_bank_logo");
								}
							}

							if(isset($_REQUEST["remove_cc_logo"])){
								if($_REQUEST["remove_cc_logo"]){
									delete_option( "woocommerce_halkbank_cc_logo");
								}
							}

							if(isset($_FILES["woocommerce_halkbank_bank_logo"])){
								if(isset($_FILES["woocommerce_halkbank_bank_logo"]["tmp_name"])){
									if($_FILES["woocommerce_halkbank_bank_logo"]["tmp_name"]){
										$upload = wp_upload_bits($_FILES["woocommerce_halkbank_bank_logo"]["name"], null, file_get_contents($_FILES["woocommerce_halkbank_bank_logo"]["tmp_name"]));
										update_option( "woocommerce_halkbank_bank_logo", $upload["url"]);
									}
								}
							}

							if(isset($_FILES["woocommerce_halkbank_cc_logo"])){
								if(isset($_FILES["woocommerce_halkbank_cc_logo"]["tmp_name"])){
									if($_FILES["woocommerce_halkbank_cc_logo"]["tmp_name"]){
										$upload = wp_upload_bits($_FILES["woocommerce_halkbank_cc_logo"]["name"], null, file_get_contents($_FILES["woocommerce_halkbank_cc_logo"]["tmp_name"]));
										update_option( "woocommerce_halkbank_cc_logo", $upload["url"]);
									}
								}
							}

						}
					}
				}

				$this->bank_logo            = get_option( 'woocommerce_halkbank_bank_logo',"" );
				$this->cc_logo              = get_option( 'woocommerce_halkbank_cc_logo',"" );

				$bank_logo            = $this->bank_logo;
				$cc_logo              = $this->cc_logo;

				global $halkbank_add_logo_image,$halkbank_add_cc_image;

				if(trim($bank_logo)){
					$this->icon                 = $this->bank_logo;
				}

				if(trim($cc_logo)){
					$halkbank_add_cc_image = "<img class='halkbank_cards halkbank_cards_in_title' style='margin-left:10px;display:inline-block;float:right;' src='{$cc_logo}' alt='' />";
				}

				if(!$this->tran_type)
					$this->tran_type = "PreAuth";

				$this->init_settings();
				$this->init_form_fields();
				
				
				if($__is_first_instance){
					global $halkbank_clean_cart_flag;
					if(get_option("_npclean_cart_once","0") == "1"){
						$halkbank_clean_cart_flag = true;
						update_option("_npclean_cart_once", "0", true);
					}
					
					if(isset($this->order_abandon_cancel)){
						if($this->order_abandon_cancel){
							if(isset($_GET["np_cancel_order"])){
								$t = explode(":",$_GET["np_cancel_order"]);
								if($t[1] == md5("{$this->merchant_id}".$t[0]."{$this->store_key}")){
									//OK
									global $tc_order_to_cancel;
									$tc_order_to_cancel = $t[0];
									tc_update_order_status($tc_order_to_cancel, 'cancelled', __( 'The order was cancelled due to no payment from customer.', 'halkbank'));
								}
							}
						}
					}
					
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
					add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
					add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
					add_action('woocommerce_api_wc_gateway_' . $this->id, array( $this, 'on_gateway_response' ) );
					add_action( 'wp_footer', array($this, 'np_on_footer'), 99);

					add_action( 'init', array( $this,'clean_cart_if_flaged'),5);
					add_action( 'plugins_loaded', array( $this,'clean_cart_if_flaged'),99);
					
					if(current_user_can( 'manage_woocommerce' ) || current_user_can('administrator') || current_user_can('editor')){
						add_action('woocommerce_api_wc_gateway_' . $this->id . "_void", array( $this, 'process_void_action' ) );
						add_action('woocommerce_api_wc_gateway_' . $this->id . "_refund", array( $this, 'process_refund_action' ) );
						add_action('woocommerce_api_wc_gateway_' . $this->id . "_refresh", array( $this, 'process_query_action' ) );
						add_action('woocommerce_api_wc_gateway_' . $this->id . "_capture", array( $this, 'process_capture_action' ) );
					}

					add_action('woocommerce_api_wc_gateway_' . $this->id . "_verifyrecaptacha", array( $this, 'verifyrecaptacha' ) );

					if(is_admin()){
						add_action( 'admin_notices', array($this,'show_admin_notices'));
					}
					
					if($this->override_back_url){
						add_filter('loop_start', array($this,'insert_override_response'),99,1);
						add_filter('the_content', array($this,'insert_override_response'),99,1);
					}
					
					add_filter( 'woocommerce_gateway_description', array( $this, 'adapt_method_description' ), 25, 2 );
				}
				
				tc_commit_orders();
			
			}catch(Throwable $cex){
				
			}	
		}
		
		public function get_order_db_currency($order_id){
			if(is_a($order_id,"WC_Order")){
				$order_id = $order_id->get_id();
			}
			$currency = null;
			try{
				global $wpdb;
				if(tc_wchps_enabled()){
					$currency = $wpdb->get_var($wpdb->prepare("SELECT currency FROM {$wpdb->prefix}wc_orders WHERE id = %d",$order_id));
				}else{
					$currency = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key='_order_currency' AND post_id = %d ",$order_id));
				}
			}catch(Throwable $ex){
				//
			}
			return $currency;
		}
		
		public function adapt_method_description($description, $gateway_id){
			try{
				if($gateway_id == $this->id && $this->merchant_currency){
					
					if($this->nphide == "yes" || $this->nphide == "1" || $this->nphide == "true"){
						$description = "<span style='color:red;font-size:80%'> -- " . __("NOTE: Hide parameter is activated. Only administartors can see this payment method!","halkbank") . " -- </span><br/> " . $description;
					}
					
					if(!function_exists("WC")){
						return $description;
					}
					
					if(!WC()->cart){
						return $description;
					}
					
					if(!WC()->cart->total){
						return $description;
					}
					
					global $currencies;
					$currencies_arr = array();
					foreach($currencies as $code3 => $curr){
						$currencies_arr[$curr["currency_numeric_code"]] = $code3;
					}
					
					if(tc_get_order_currency() != $currencies_arr[$this->merchant_currency]){
						$total = WC()->cart->total * halkbank_getExchangeRate(tc_get_order_currency(), $currencies_arr[$this->merchant_currency], null);
						$total = number_format($total,2,".","");
						return $description . "<br/><span style='font-size:85%;font-style: italic;'>" . __("Amount in payment currency","halkbank") . " " . number_format(WC()->cart->total,2,".","") . " " . tc_get_order_currency() . " &rarr; " . $total . " ". $currencies_arr[$this->merchant_currency] . "</span>"; 
					}
				}
			}catch(Throwable $ex){
				//
			}
			return $description;
		}
		
		public function insert_override_response($arg){
			try{
				global $halkbank_exlout_done;
				if($halkbank_exlout_done)
					return $arg;
				
				$content = is_string($arg) ? $arg : "";
				if(isset($_REQUEST["np_plgresp"]) || isset($_GET["halkbank_fail"]) || isset($_GET["halkbank_success"])){
					$doid = null;
					if(isset($_REQUEST["halkbank_oid"])){
						$doid = $_REQUEST["halkbank_oid"];
					}else if(isset($_POST["oid"])){
						$doid = $_POST["oid"];
					}
					if( $doid ){
						ob_start();
						if($this->after_override_timout !== null){
							$order = tc_get_order(intval($doid)); 
							$url = $order->get_checkout_order_received_url();
							$out = "<script type='text/javascript'>setTimeout(function(){ window.location.href = '{$url}';}, {$this->after_override_timout} * 1000);</script>";
						}else{
							$out = halkbank_exlpicit_response_output($content);
						}
						$tmp = ob_get_clean();
						if($content)
							return $out;
						else {
							echo $out;
						}
					}
				}
			}catch(Throwable $ex){
				//
			}
			return $arg;
		}

		public function show_admin_notices(){
			try{
				tc_show_orders_admin_notices();
			}catch(Throwable $ex){
				//
			}
		}

		public function hide_method_from_customers($gateways){
		  if(current_user_can('administrator')){
			  return $gateways;
		  }

		  if(is_checkout() && !is_checkout_pay_page()){
			  if(isset($gateways[halkbank_method_id()]))
				  unset($gateways[halkbank_method_id()]);
		  }

		  return $gateways;
		}

		public function verifyrecaptacha(){
			
			$response = '';
			try{
				$response = np_common_curl_request('https://www.google.com/recaptcha/api/siteverify', array(
					'secret'   => $this->recaptcha_secret_key,
					'response' => $_REQUEST["recaptcha_v3_t"]
				));
			}catch(Throwable $ex){
				@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
				//
			}
			if(strpos($response, '"success": true') !== false) {
				echo '{"nppassed":true}';
			} else {
				echo '{"error":true}';
			}
			die;
		}

		public function responseToHtml($arr, $title ,$title_color = "red", $subtitle = "", $for_archive = false){
			try{
				if(!isset($arr["oid"])){
					if(isset($arr["ReturnOid"]))
						$arr["oid"] = $arr["ReturnOid"];
					else if(isset($resp["order_id"]))
						$arr["oid"] = $arr["order_id"];
				}

				global $result_labels;
				if(!isset($result_labels)){
					$result_labels = array(
						'orderid'               => __("Order ID (transaction)","halkbank"),
						'extra_auth_code'       => __("Authorization Code","halkbank"),
						'response'              => __("Payment Status","halkbank"),
						'procreturncode'        => __("Transaction Status Code","halkbank"),
						'transid'               => __("Transaction ID","halkbank"),
						'extra_hostdate'        => __("Transaction Date","halkbank"),
						'mdstatus'              => __("Status code for the 3D transaction","halkbank"),
						'amount'                => __("Amount in payment currency","halkbank"),
						'currency'              => __("Payment currency code","halkbank")
					);
				}
				
				if(isset($_GET['wc-api'])){
					
					$result_labels["extra_cardbrand"]  = __("CARD BRAND","halkbank");
					$result_labels["extra.cardbrand"]  = __("CARD BRAND","halkbank");
					$result_labels["extra_pan"]        = __("MASKED PAN","halkbank");
					$result_labels["maskedcreditcard"] = __("MASKED PAN","halkbank");
					$result_labels["maskedpan"]        = __("MASKED PAN","halkbank");
				}

				$out = "<h3  style='color:{$title_color}' class='halkbank_response_title'>{$title}</h3>";
				$out .= "<h4 class='halkbank_response_subtitle'>{$subtitle}</h3>";
				$out .= "<ul class='halkbank_response'>";
				foreach($arr as $key => $val){
					if(strtolower($key) == "response"){
						if(!$for_archive){
							$out .=  "<li><span>" . __("Transaction Outcome","halkbank"). "</span>: <b>{$val}</b></li>";
							continue;
						}
					}
					if(isset($result_labels[strtolower($key)])){
						if(is_array($val))
							continue;

						$out .=  "<li><span>" . $result_labels[strtolower($key)] . "</span>: <b>{$val}</b></li>";
					}
				}
				$out .= "</ul>";
				
				
				return $out;
			}catch(Throwable $ex){
				return "";
			}
		}

		function transactionNotification($order_id, $subject, $html){
			global $tc_transaction_notification_error;
			try{
				if(!$this->no_transaction_email && np_only_once("t{$order_id}" . strtolower(str_replace(" ","",$subject)))){
					$order = tc_get_order($order_id);
					$mail_sent = false;
					try{
						if(function_exists("WC")){
							$mailer  = WC()->mailer();

							if($mailer){
								$message = $mailer->wrap_message( $subject, $html );
								$mail_sent = $mailer->send( $order->get_billing_email(), strip_tags( $subject ), $message ) ? true : false;
							}
						}
					}catch(Throwable $mex){
						$tc_transaction_notification_error = $mex->getMessage();
					}

					if(!$mail_sent){
						$headers = array('Content-Type: text/html; charset=UTF-8');
						return wp_mail($order->get_billing_email(),strip_tags( $subject ), $subject ."<br/>". $html, $headers );
					}else
						return true;
				}else
					return true;
			}catch(Throwable $mexg){
				$tc_transaction_notification_error = $mexg->getMessage();
				return false;
			}
		}

		public function clean_cart_if_flaged(){
			try{
				global $halkbank_clean_cart_flag, $tc_user_success_once;
				if(isset($halkbank_clean_cart_flag)){
					if($halkbank_clean_cart_flag){
						global $woocommerce;
						
						if($tc_user_success_once && !isset($_GET["np_cancel_order"]) && !isset($_GET["halkbank_fail"])){
							if(isset($woocommerce)){
								if(isset($woocommerce->cart)){
									if($woocommerce->cart){
										if(method_exists($woocommerce->cart,"empty_cart")){
											$woocommerce->cart->empty_cart();
										}
									}
								}
							}

							if(function_exists("WC")){
								try{
									WC()->cart->empty_cart();
								}catch(Exception $cex){
									//
								}
							}
						}
					}
				}
			}catch(Throwable $ex){
				
			}
		}

		function halkbank_recalc_form(){
			die;
		}

		function np_on_footer() {
			try{
				global $tc_set_order_status_id, $tc_set_order_status_status, $tc_set_order_paid;
				if(class_exists("WC_Order")){
					if($tc_set_order_status_id && $tc_set_order_status_status){

						$order 		= $this->getOrder( $tc_set_order_status_id );
						if($order){
							
							if(strtolower(str_ireplace("wc-","",$order->get_status())) !=  strtolower(str_ireplace("wc-","",$tc_set_order_status_status))){
								tc_update_order_status($tc_set_order_status_id, $tc_set_order_status_status);
							}
							
							if(method_exists($order, "payment_complete")){
								if($tc_set_order_paid && !$order->is_paid()){
									$order->payment_complete();
								}
							}
						}
					}
				}
				$this->clean_cart_if_flaged();
				tc_commit_orders();
			}catch(Throwable $ex){
				
			}
		}

		function getPostBackURL($order_id, $success = null){
			try{
				if(is_a($order_id,"WC_Order")){
					$order_id = $order_id->get_id();
				}
				//$url = add_query_arg( 'wc-api', 'wc_gateway_' . $this->id, home_url( '/' ));
				$order = tc_get_order($order_id); 
				$url = $order->get_checkout_order_received_url();
				if($this->override_back_url){
					global $TC_CLIENT_SCHEMA;
					$this->override_back_url = trim(explode("?",$this->override_back_url)[0]);
					
					if(stripos($this->override_back_url,"http") !== 0){
						$this->override_back_url = get_site_url(null,$this->override_back_url,'https');
					}else{
						$this->override_back_url = str_ireplace("http://","https://",$this->override_back_url);
					}
					
					//$this->override_back_url = str_ireplace("https://",$TC_CLIENT_SCHEMA,$this->override_back_url);
					$url = $this->override_back_url;
					//$url = add_query_arg( 'wc-api', 'wc_gateway_' . $this->id, $this->override_back_url);
				}
				
				$url = add_query_arg( $this->id ."_oid", $order_id, $url);
				if($success !== null){
					if($success){
						$url = add_query_arg( "np_plgresp", "ok", $url);
					}else{
						$url = add_query_arg( "np_plgresp", "no3d", $url);
					}
				}

				if($this->override_language || get_locale()){
					if($this->override_language == "auto2"){
						$this->override_language = substr(get_locale(),0,2);	
					}
					$url = add_query_arg( "lang", $this->override_language ? $this->override_language : get_locale() , $url);
				}
				
				if($success === null){
					$h = rtrim(np_server_variable("HTTP_HOST",""),"/");
					$r = ltrim(np_server_variable("REQUEST_URI",""),"/");
					if($h && $r){
						$current_link = (is_ssl() ? "https" : "http") . "://{$h}/{$r}";
						if(url_to_postid($current_link) == url_to_postid($url)){
							
						}
					}
				}
				
				if(stripos($url,"https://") === false){
					$site_url = home_url(); // Get the full home URL (e.g., https://www.example.com)
					$site_url = str_replace(array('http://', 'https://'), '', $site_url);
					$site_url = str_replace('www.', '', $site_url);
					$site_url = rtrim($site_url, '/');
					$rurl = explode($site_url,$url);
					if(isset($rurl[1])){
						$url = site_url("/" . ltrim($rurl,"/"));
					}else{
						$url = site_url();
					}
				}
				return $url;
			}catch(Throwable $ex){
				return site_url();
			}
		}

		function init_form_fields(){
			try{
				if(isset($this->form_fields)){
					if(!empty($this->form_fields))
						return $this->form_fields;
				}
				
				global $woocommerce;
				global $currencies;
				halkbank_get_currencies();

				$currencies_arr = array("" => __('As in order','halkbank'));
				foreach($currencies as $code3 => $curr){
					$currencies_arr[$curr["currency_numeric_code"]] = $code3 . ", " . __($curr["currency_name"],'halkbank');
				}

				$poptions = np_get_plugin_options();
				if(!$poptions)
					$poptions = new stdClass;

				$disp_error = get_option("halkbank_last_display_error","");
				
				if($disp_error){
					$err = $disp_error;
					if($err){
						if(stripos($err,"ORDER_NOT_FOUND") !== false){
							$err = "";
						}
						$disp_error = "<br/><p style='color:red'>{$err}</p>";
					}
				}
				
				$bank_logo            = get_option( 'woocommerce_halkbank_bank_logo',"" );
				$cc_logo              = get_option( 'woocommerce_halkbank_cc_logo',"" );

				if($bank_logo){
					$bank_logo = "<br/><img id='bank_logo' style='width:160px;' src='{$bank_logo}' alt='' />";
				}

				if($cc_logo){
					$cc_logo = "<br/><img id='cc_logo' style='width:160px;' src='{$cc_logo}' alt='' />";
				}

				$statuses = wc_get_order_statuses();

				if(isset($statuses["wc-pending"])){
					$statuses["wc-pending"] .= (" ". __("(WARNING: auto-cancel by WooCommerce!)",'halkbank'));
				}
				
				require_once(__DIR__ . DIRECTORY_SEPARATOR . "fields.php");
				global $tc_settings_fields;
				$this->form_fields = $tc_settings_fields;
			}catch(Throwable $ex){
				
			}

		}

		/**
		* Receipt Page
		**/
		function receipt_page($order){
			try{
				echo $this->generate_authorize_form($order);
			}catch(Throwable $ex){
				echo "<p style='color:red'>" . $ex->getMessage() . "</p>";
			}
		}

		public function add_order_note($order_id, $note ) {
			try{
				$is_customer_note = 0;
				$added_by_user    = false;

				$comment_author       = __( 'WooCommerce', 'woocommerce' );
				$comment_author_email = 'halkbank-plugin@noreply.com';
				$comment_author_email = sanitize_email( $comment_author_email );

				$commentdata = apply_filters( 'woocommerce_new_order_note_data', array(
				  'comment_post_ID'      => $order_id,
				  'comment_author'       => $comment_author,
				  'comment_author_email' => $comment_author_email,
				  'comment_author_url'   => '',
				  'comment_content'      => $note,
				  'comment_agent'        => 'WooCommerce',
				  'comment_type'         => 'order_note',
				  'comment_parent'       => 0,
				  'comment_approved'     => 1,
				), array( 'order_id' => $order_id, 'is_customer_note' => $is_customer_note ) );

				$comment_id = wp_insert_comment( $commentdata );

				return $comment_id;
			}catch(Throwable $ex){
				return false;
			}
		}

		function getOrder($order_id, $requery = false){
			return tc_get_order($order_id, $requery);
		}

		function getCurrencySymbol($num_currency){
			try{
				halkbank_get_currencies();
				global $currencies;

				foreach($currencies as $code3 => $curr){
					if($curr["currency_numeric_code"] == $num_currency)
						return $code3;
				}
			}catch(Throwable $ex){
				
			}
			return "";
		}

		function getHtmlResponse($order_id, $trantype = null, $transaction_data = null){
			
			$html = "";
			try{
				if($trantype === null){
					$trantype = strtolower(tc_get_order_meta($order_id, '_halkbank_last_tran_type',true));
				}else{
					$trantype = strtolower($trantype);
				}

				if($transaction_data === null){
					$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
					if(empty($transaction_data)){
						$transaction_data = array();
					}else{
						if(is_string($transaction_data)){
							$transaction_data = json_decode($transaction_data, true);
						}
					}
				}

				if(!empty($transaction_data)){

					if(!$trantype){
						$trantype = end(array_keys($transaction_data));
					}

					if(isset($transaction_data[$trantype])){

						$npv_data = $transaction_data[$trantype];
						if(isset($npv_data["Response"]) && !isset($npv_data["npresult"])){
							$npv_data["npresult"] = strtolower($npv_data["Response"]);
						}


						if(isset($npv_data["npresult"])){
							$html = "<div class='np-transaction-info'>";
							if(stripos($npv_data["npresult"],"success") !== false || (isset($npv_data["order_key"]) && $npv_data["order_key"] && isset($npv_data['Response']) && stripos($npv_data['Response'],"approved") !== false)){

								if(strtolower($trantype) == "auth"){
									$html .= "<br/><h2>".__( 'Your credit card has been successfully charged', 'halkbank' )."</h2>";
								}else{
									$html .= "<br/><h2>".__( 'Payment successful', 'halkbank' )."</h2>";
								}

								$html .=  "<h4>".__( 'Transaction info', 'halkbank' )."</h4>";
								$html .=  "<p>".__( 'Order number/key', 'halkbank' ).": ". $npv_data['order_key'] ."</p>";
								$html .=  "<p>".__( 'Order id', 'halkbank' ).": ".$npv_data['oid']."</p>";
								$html .=  "<p>".__( 'Transaction id', 'halkbank' ).": ".$npv_data['TransId']."</p>";
								$html .=  "<p>".__( 'Payment Status', 'halkbank' ).": ".$npv_data['Response']."</p>";
								$html .=  "<p>".__( 'Transaction Status Code', 'halkbank' ).": ".$npv_data['ProcReturnCode']."</p>";
								$html .=  "<p>".__( 'Status code for the 3D transaction', 'halkbank' ).": ".$npv_data['mdStatus']."</p>";
								$html .=  "<p>".__( 'Authorization Code', 'halkbank' ).": ".$npv_data['AuthCode']."</p>";
								$html .=  "<p>".__( 'Transaction Date', 'halkbank' ).": ".$npv_data['EXTRA_TRXDATE']."</p>";

								if(isset($npv_data['amount'])){
									$html .=  "<p>".__( 'Amount in payment currency', 'halkbank' ).": ". $npv_data['amount'] . " ";
									if(isset($npv_data['currency'])){
										$html .=  $this->getCurrencySymbol($npv_data['currency']);
									}
									$html .=  "</p>";
								}

								if(isset($npv_data['instalment'])){
									if(trim($npv_data['instalment']))
										$html .=  "<p>".__( 'Instalment', 'halkbank' ).": ".$npv_data['instalment']."</p>";
								}
								
							}else if(isset($npv_data["order_key"]) && isset($npv_data['Response']) && $npv_data["order_key"] && trim(str_replace("-","",$npv_data['Response']))){

								if(strtolower($trantype) == "auth"){
									$html .= "<br/><h2>".__( 'The payment has failed. Your credit card is not charged.', 'halkbank' )."</h2>";
								}else{
									$html .= "<br/><h2>".__( 'The payment has failed.', 'halkbank' )."</h2>";
								}

								$html .=  "<h4>".__( 'Transaction info', 'halkbank' )."</h4>";
								$html .=  "<p>".__( 'Order number/key', 'halkbank' ).": ". $npv_data["order_key"] ."</p>";
								$html .=  "<p>".__( 'Order id', 'halkbank' ).": ".$npv_data['oid']."</p>";
								$html .=  "<p>".__( 'Transaction id', 'halkbank' ).": ".$npv_data['TransId']."</p>";
								$html .=  "<p>".__( 'Payment Status', 'halkbank' ).": ".$npv_data['Response']."</p>";
								$html .=  "<p>".__( 'Transaction Status Code', 'halkbank' ).": ".$npv_data['ProcReturnCode']."</p>";
								$html .=  "<p>".__( 'Status code for the 3D transaction', 'halkbank' ).": ".$npv_data['mdStatus']."</p>";
								$html .=  "<p>".__( 'Authorization Code', 'halkbank' ).": ".$npv_data['AuthCode']."</p>";
								$html .=  "<p>".__( 'Transaction Date', 'halkbank' ).": ".$npv_data['EXTRA_TRXDATE']."</p>";
								if(isset($npv_data["shopper_info"])){
									$html .=  "<p>".__( 'Shopper details', 'halkbank' ).": ". $npv_data["shopper_info"] ."</p>";
								}
								if(isset($npv_data['instalment'])){
									if(trim($npv_data['instalment']))
										$html .=  "<p>".__( 'Instalment', 'halkbank' ).": ".$npv_data['instalment']."</p>";
								}

								if(isset($npv_data['amount'])){
									$html .=  "<p>".__( 'Amount in payment currency', 'halkbank' ).": ". $npv_data['amount'] . " ";
									if(isset($npv_data['currency'])){
										$html .=  $this->getCurrencySymbol($npv_data['currency']);
									}
									$html .=  "</p>";
								}
							}
						}
					}
				}

				if($html)
					$html .= "</div>";
			}catch(Throwable $ex){
				//
			}

			return $html;
		}

		function process_payment_response($order, $background = false){

			global $woocommerce;
			

			halkbank_plugin_textdomain();

			$NP_REQUEST = array();
			foreach($_REQUEST as $key => $val){
				$NP_REQUEST[$key] = "{$val}";
			}

			$NP_POST = array();
			foreach($_POST as $key => $val){
				$NP_POST[$key] = "{$val}";
			}

			$NP_GET = array();
			foreach($_GET as $key => $val){
				$NP_GET[$key] = "{$val}";
			}

			if(isset($_GET["halkbank_oid"])){

				$redirected_resp = json_decode(get_post_meta(intval($_GET["halkbank_oid"]), "halkbank_redirected_data" ,true),true);
				if($redirected_resp){
					if(isset($redirected_resp["GET"])){
						foreach($redirected_resp["GET"] as $key => $val){
							if(!isset($NP_GET[$key])){
								$NP_GET[$key] = "{$val}";
							}
							if(!isset($NP_REQUEST[$key])){
								$NP_REQUEST[$key] = "{$val}";
							}
						}
					}
					if(isset($redirected_resp["POST"])){
						foreach($redirected_resp["POST"] as $key => $val){
							if(!isset($NP_POST[$key])){
								$NP_POST[$key] = "{$val}";
							}
							if(!isset($NP_REQUEST[$key])){
								$NP_REQUEST[$key] = "{$val}";
							}
						}
					}
				}
			}

			$mustParameters = array("clientid","oid","Response");
			$order_id       = null;

			if(!isset($this->DPDAT))
				$this->DPDAT = array();
			
			foreach($NP_REQUEST as $k => $v){
				$this->DPDAT[$k] = $v;
			}

			$npv_data = $this->DPDAT;

			if(!$order_id && isset($NP_REQUEST["halkbank_oid"]))
				$order_id = intval($NP_REQUEST["halkbank_oid"]);

			if(!$order_id && isset($npv_data['oid']))
				$order_id = intval($npv_data['oid']);

			if(!$order_id && isset($NP_GET["order"]))
				$order_id = intval($NP_GET["order"]);
			
			if(!$order_id && isset($NP_GET["order-received"]))
				$order_id = intval($NP_GET["order-received"]);
			
			if(!$order_id && isset($NP_GET["order-pay"]))
				$order_id = intval($NP_GET["order-pay"]);
			
			if(!$order){
				if($order_id){
					$order = $this->getOrder($order_id);
				}
			}else if($order && is_object($order)){
				$order_id = $order->ID;
				if(!$order_id){
					$order_id = null;
					if(method_exists($order, 'get_id')){
						$order_id = $order->get_id();
					}else{
						$order_id = $order->id;
					}
				}
				if(!$order_id && $NP_REQUEST["oid"]){
					$order_id = $NP_REQUEST["oid"];
					$order = $this->getOrder($order_id);
				}
			}else{
				if(!$order_id && $order)
					$order_id = $order;

				if($order_id)
					$order = $this->getOrder($order_id);

				if(!$order){
					if($NP_REQUEST["oid"]){
						$order_id = $NP_REQUEST["oid"];
						$order = $this->getOrder($order_id);
					}
				}
			}

			global $tc_current_order_id;
			$tc_current_order_id = $order_id;

			if(isset($NP_REQUEST["TRANID"]) && !isset($NP_REQUEST["TransId"])){
				$NP_REQUEST["TransId"] = $NP_REQUEST["TRANID"];
				if(isset($this->DPDAT))
					$this->DPDAT["TransId"] = $NP_REQUEST["TRANID"];
				$npv_data = $this->DPDAT;
			}

			$failed_for_sure   = false;
			$approved_for_sure = false;

			if(isset($NP_REQUEST["halkbank_fail"])){
				if($NP_REQUEST["halkbank_fail"] == "1"){
					$failed_for_sure = true;
				}
			}

			if(!$failed_for_sure && isset($NP_REQUEST["Response"])){
				if($NP_REQUEST["Response"] == "Approved"){
					$approved_for_sure = true;
				}
			}

			global $tc_pay_response_received;
			if(isset($tc_pay_response_received)){
				if($tc_pay_response_received){
					if($order)
						return $this->getPostBackURL($order->get_id());
					return null;
				}
			}
			
			$tc_pay_response_received = true;

			$ddata_set = false;
			if(isset($NP_POST["rnd"]) && isset($NP_POST["ProcReturnCode"])){
				
				if(!isset($NP_REQUEST["Response"])){
					$NP_REQUEST["Response"] = "Failed";
				}
				
				$checkv = md5($this->store_key."/".$NP_POST["amount"]."/".$order_id);
				if(isset($NP_REQUEST["checkv"])){
					if($checkv == $NP_REQUEST["checkv"]){
						foreach($NP_REQUEST as $k => $v){
							$this->DPDAT[$k] = $v;
						}
						$npv_data = $this->DPDAT;
						$ddata_set = true;
					}
				}
			}

			if(!$ddata_set){
				$qres = np_queryFromGateway($order_id, true);
				if($qres){
					if(isset($qres["TransId"])){
						if(trim(str_replace("-","",$qres["TransId"]))){
							$this->DPDAT = $qres;
							foreach($NP_REQUEST as $k => $v){
								$this->DPDAT[$k] = $v;
							}
							$npv_data = $this->DPDAT;
						}
					}
				}
			}
			
			if(!$approved_for_sure && isset($npv_data["Response"])){
				if(stripos($npv_data["Response"],"Approved") !== false){
					$approved_for_sure = true;
					$failed_for_sure   = false;
				}
			}
			
			if(!isset($npv_data['ProcReturnCode'])){
				
				$qres = np_queryFromGateway($order_id, true);
				if($qres){
					if(isset($qres["TransId"])){
						if(trim(str_replace("-","",$qres["TransId"]))){
							$this->DPDAT = $qres;
							foreach($NP_REQUEST as $k => $v){
								$this->DPDAT[$k] = $v;
							}
							$npv_data = $this->DPDAT;
						}
					}
				}
				
				if(!$npv_data){
					$npv_data = array();
					$npv_data['oid']            = $order_id;
					$npv_data['TransId']        = "--";
					$npv_data['Response']       = "--";
					$npv_data['ProcReturnCode'] = "--";
					$npv_data['mdStatus']       = "--";
					$npv_data['AuthCode']       = "--";
					$npv_data['EXTRA_TRXDATE']  = "--";
					$npv_data['instalment']     = "--";
				}
			}
			
			if(!$approved_for_sure && !$failed_for_sure && isset($NP_REQUEST["halkbank_fail"])){
				if($NP_REQUEST["halkbank_fail"] == "1"){
					$failed_for_sure = true;
				}
				
				if(!isset($npv_data['Response'])){
					if($failed_for_sure){
						$npv_data['Response'] = "Failed";
					}
				}
			}

			$presp = tc_get_order_meta($order_id, '_pay_response_received',true);
			if($presp && isset($npv_data["Response"])){
				if(stripos($presp,"Approved") === 0 && $presp == $npv_data["Response"]){
					if($order)
						return $this->getPostBackURL($order->get_id());
					return null;
				}
			}

			$order 		= tc_get_order( $order_id);
			
			$isValid = isset($npv_data["oid"]) && isset($npv_data["ProcReturnCode"]);
			
			global $allow_cart_empty;
			if(!isset($allow_cart_empty))
				$allow_cart_empty = false;

			$html  = "";
			if(isset($NP_REQUEST["mdErrorMsg"])){
				if(intval($NP_REQUEST["mdStatus"]) < 7)
					$html .= ("<!-- <p style='color:red;'>" . str_ireplace("DS error", "", $NP_REQUEST["mdErrorMsg"]) . "</p> -->");
			}else if(isset($npv_data["mdErrorMsg"])){
				$html .= ("<!-- <p style='color:red;'>" . str_ireplace("DS error", "", $npv_data["mdErrorMsg"]) . "</p> -->");
			}
			
			if($this->debug_mode){
				ob_start();
				echo "<br/>DEBUG RESPONSE<br/>";
				echo "<pre>\r\n" . json_encode($npv_data, JSON_PRETTY_PRINT) . "\r\n</pre>";
				echo "<br/>";
				$html .= ob_get_clean();
			}
			
			if(isset($npv_data["ErrMsg"])){
				if($npv_data["ErrMsg"]){
					if(stripos($npv_data["ErrMsg"],"wrong sec") !== false){
						update_option("halkbank_last_display_error","Invalid store key! Make sure it is set same as defined on the bank portal and that it is combination of 12-20 letters and numbers (avoid other characters)!");
					}else if(stripos($npv_data["ErrMsg"],"merchant does not support") !== false){
						update_option("halkbank_last_display_error","Merchant account is set to use different payment module. This plugin does not support 3d, swap to another plugin.");
					}
				}
			}
			
			$redirect_to_order_payment = false;

			if($approved_for_sure || ($isValid && stripos($npv_data['Response'],"Approved") !== false)){
				global $halkbank_clean_cart_flag, $tc_user_success_once;
				$halkbank_clean_cart_flag = true;
				$this->clean_cart_if_flaged();
				
				if(!$background){
					$tc_user_success_once = true;
				}

				update_option("_npclean_cart_once", "1", true);

				tc_update_order_meta($order_id, '_pay_response_received', 'Approved' );

				$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
				if(empty($transaction_data)){
					$transaction_data = array();
				}else{
					if(is_string($transaction_data)){
						$transaction_data = json_decode($transaction_data, true);
					}
				}
				if(isset($npv_data["trantype"]))
					$trantype = $npv_data["trantype"];
				else
					$trantype = $this->tran_type;

				$npv_data["npresult"] = "success";

				$npv_data["order_key"] = $order->get_order_key();
				
				
				$transaction_data[strtolower($trantype)] = $npv_data;

				tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
				tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);
				
				if(strtolower($trantype) == "preauth"){
					tc_update_order_meta($order_id, '_halkbank_preauth_ts', time());
					if(isset($npv_data["amount"])){
						tc_update_order_meta($order_id, '_halkbank_preauth_amount', $npv_data["amount"]);
					}
				}

				$html .= $this->getHtmlResponse($order_id, $trantype, $transaction_data);

				tc_update_order_meta($order_id, 'pay_response', $html );

				$this->add_order_note($order_id, $html);
				
				if(isset($npv_data['TransId']))
					tc_update_order_meta($order_id, "_transaction_id",$npv_data['TransId']);
				
				if(strtolower($trantype) == "auth" || $this->preauth_means_paid){
					if(method_exists($order, "payment_complete") && !$order->is_paid())
						$order->payment_complete($npv_data['TransId']);
				}
				
				if(!$order->has_status($this->completed)){
					tc_update_order_status($order_id, $this->completed);
				}
				
				
				global $tc_set_order_status_id, $tc_set_order_status_status, $tc_set_order_paid;

				$tc_set_order_status_id      = $order_id;
				$tc_set_order_status_status  = $this->completed;
				$tc_set_order_paid           = strtolower($trantype) == "auth" || $this->preauth_means_paid;
			
				if(strtolower($trantype) == "auth"){
					$subject = __( 'Your credit card has been successfully charged', 'halkbank' );
				}else{
					$subject = __( 'Payment successful', 'halkbank' );
				}

				if(!$this->no_transaction_email && np_only_once("paid{$order_id}" . strtolower(str_replace(" ","","{$npv_data['TransId']}")))){

					if($this->include_order_details_mail){
						$html .= $this->order_details_html($order_id);
					}

					$mail_sent = false;
					try{
						if(function_exists("WC")){
							$mailer  = WC()->mailer();

							if($mailer){

								$message = $mailer->wrap_message( $subject, $html );
								$mail_sent = $mailer->send( $order->get_billing_email(), strip_tags( $subject ), $message ) ? true : false;
							}
						}
					}catch(Throwable $mex){

					}

					if(!$mail_sent){
						$headers = array('Content-Type: text/html; charset=UTF-8');
						wp_mail($order->get_billing_email(),strip_tags( $subject ), $subject ."<br/>". $html, $headers );
					}
				}
			}else if( $failed_for_sure ){

				tc_update_order_meta( $order_id, '_pay_response_received', 'Failed' );

				$transaction_data = tc_get_order_meta($order_id, '_halkbank_transation_data',true);
				if(empty($transaction_data)){
					$transaction_data = array();
				}else{
					if(is_string($transaction_data)){
						$transaction_data = json_decode($transaction_data, true);
					}
				}

				if(isset($npv_data["trantype"]))
					$trantype = $npv_data["trantype"];
				else
					$trantype = $this->tran_type;

				$npv_data["npresult"] = "failed";
				$npv_data["order_key"] = $order->get_order_key();
				$npv_data["shopper_info"] = $order->get_billing_first_name()." ".$order->get_billing_last_name().", ".$order->get_billing_email();
				$transaction_data[strtolower($trantype)] = $npv_data;

				tc_update_order_meta($order_id, '_halkbank_transation_data', json_encode($transaction_data));
				tc_update_order_meta($order_id, '_halkbank_last_tran_type', $trantype);

				$html .= $this->getHtmlResponse($order_id, $trantype, $transaction_data);

				tc_update_order_meta( $order_id, 'pay_response', $html );

				$this->add_order_note($order_id, $html);

				tc_update_order_status($order_id, $this->failed);

				global $tc_set_order_status_id, $tc_set_order_status_status,$tc_set_order_paid;

				$tc_set_order_status_id      = $order_id;
				$tc_set_order_status_status  = $this->failed;
				$tc_set_order_paid           = false;

				if($this->tran_type == "Auth"){
					$subject = __( 'The payment has failed. Your credit card is not charged.', 'halkbank' );
				}else{
					$subject = __( 'The payment has failed.', 'halkbank' );
				}

				if(!$this->no_transaction_email && np_only_once("failed{$order_id}" . strtolower(str_replace(" ","","{$npv_data['TransId']}")))){
					if($this->include_order_details_mail){
						$html .= $this->order_details_html($order_id);
					}

					$mail_sent = false;
					try{
						if(function_exists("WC")){
							$mailer  = WC()->mailer();
							if($mailer){
								$message = $mailer->wrap_message( $subject, $html );
								$mail_sent = $mailer->send( $order->get_billing_email(), strip_tags( $subject ), $message ) ? true : false;

							}
						}
					}catch(Exception $mex){

					}

					if(!$mail_sent){
						$headers = array('Content-Type: text/html; charset=UTF-8');
						wp_mail($order->get_billing_email(),strip_tags( $subject ), $subject ."<br/>". $html, $headers);
					}
				}
			}else if(isset($_GET["halkbank_fail"]) && isset($_GET["checkv"]) && isset($_GET["halkbank_oid"])){
				if($_GET["halkbank_fail"] == 1){
					
					$html .= "<!-- MARKED FAILED ONLY BY INSPECTING QUERY PARAMATER -->";
					
					$order_id = intval($_GET["halkbank_oid"]);
					$order = tc_get_order($order_id);
					
					if($order){
						
						$payment_currency = $this->merchant_currency;
						global $currencies;
						halkbank_get_currencies();
						
						$currencies_arr = array();
						
						foreach($currencies as $code3 => $curr){
							$currencies_arr[$curr["currency_numeric_code"]] = $code3;
						}
				
						$ocurr = tc_get_order_currency($order);
						$order_currency   = $currencies[$ocurr]['currency_numeric_code'];
				
						if(!$payment_currency){
							$payment_currency = $order_currency;
						}
						
						$code3_payment = "";
						if($order_currency != $payment_currency){
							$code3_order     = substr($currencies_arr[$order_currency],0,3);
							$code3_payment   = $currencies_arr[$payment_currency];
						}
						
						$conversion_rate = halkbank_getExchangeRate($code3_order,$code3_payment, floatval($this->conversion_rate_adjust),$order_id);
						$conversion_rate_saved = tc_get_order_meta($order_id,"exchange_rate_" . strtoupper($code3_order) . strtoupper($code3_payment), true);
						
						if($conversion_rate_saved){
							$conversion_rate = $conversion_rate_saved;
						}
						
						if(!$conversion_rate)
							$conversion_rate = 1;
						
						$amount =  number_format(round(floatval($order->get_total()) * $conversion_rate ,2),2, '.', '');
						$checkv = md5($this->store_key."/".$amount."/".$order_id);
						
						if($checkv == $_GET["checkv"]){
							if(!$order->is_paid()){
								$redirect_to_order_payment = true;
							}
						}
					}
				}
			}

			global $wpdb;
			$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient%';");
			
			tc_commit_orders();

			if(class_exists("WC_Order"))
				$order = tc_get_order($order_id);

			if($order){
				if($redirect_to_order_payment){
					if ( wp_redirect($order->get_checkout_payment_url() ) ) {
						exit;
					}else{
						echo "<script type='text/javascript'>window.location.href='" . $order->get_checkout_payment_url() . "';</script>";
						die;
					}
				}
				return $this->getPostBackURL($order->get_id());
			}
			return null;
		}

		function on_gateway_response($arg = null){
			//
		}

		function thankyou_page($order){
			global $woocommerce;

			halkbank_plugin_textdomain();

			$order_id       = null;
			if(is_object($order)){
				$order_id = $order->ID;
				if(!$order_id){
					$order_id = null;
					if(method_exists($order, 'get_id')){
						$order_id = $order->get_id();
					}else{
						$order_id = $order->id;
					}
				}
			}else{
				$order_id = $order;
				$order = $this->getOrder($order_id);
			}

			$html = tc_get_order_meta($order_id, 'pay_response',true);
			if(!trim($html)){
				$this->process_payment_response($order_id);
				$html = tc_get_order_meta($order_id, 'pay_response',true);
			}

			$t_html = $this->getHtmlResponse($order_id);
			if($t_html){
				$html = $t_html;
			}

			global $tc_pay_response_output;
			if(isset($tc_pay_response_output)){
				if($tc_pay_response_output){
					return "<!-- RETURN : -->" . $tc_pay_response_output;
				}
			}	

			$order_details_html = "";

			if($this->include_order_details){
				$order_details_html = $this->order_details_html($order_id);
			}

			$add_class = "halkbank_resp_" . trim(strtolower(tc_get_order_meta($order_id, '_pay_response_received',true)));

			$tc_pay_response_output = "<div locale='".get_locale()."' style='' class='halkbank_resp halkbank_resp_js {$add_class}'>{$html}{$order_details_html}</div> <script type='text/javascript'>document.body.className += ' {$add_class}_page';</script>";


			$refresh_after_post = false;
			if(np_server_variable('REQUEST_METHOD','') == "POST"){
				$refresh_after_post = true;
			}
			
			global $tc_plugin_data;
			if(!isset($tc_plugin_data)){
				if( !function_exists('get_plugin_data') ){
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				}
				$tc_plugin_data = get_plugin_data( __DIR__ . DIRECTORY_SEPARATOR . 'index.php' );
			}

			wp_enqueue_script( 'tc-footer-script', plugin_dir_url( __FILE__ ) . 'js/tc_enqueue_script.js' , array('jquery-core'), $tc_plugin_data['Version'], array("in_footer" => true));
			
			global $tc_footer_script_data;
			$sdata = array(
					'Transaction'         => $tc_pay_response_output,
					"RefreshAfterPost"    => $refresh_after_post
				);
			if(!isset($tc_footer_script_data)){
				$tc_footer_script_data = $sdata;
			}else{
				foreach($sdata as $key => $val){
					$tc_footer_script_data[$key] = $val;
				}
			}
			wp_localize_script( 'tc-footer-script', 'HalkbankTransaction',$tc_footer_script_data);
			
			return "<!-- RETURN: -->" . str_replace("halkbank_resp_js","halkbank_resp_return",$tc_pay_response_output);
		}

		function order_details_html($order_id, $mail = false){
			ob_start();
			try{
				$order = tc_get_order( $order_id );
				wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
				wc_get_template( 'order/order-details.php', array(
					'order_id'    => $order_id
				));
			}catch(Throwable $ex){
				
			}
			return ob_get_clean();
		}

		//random string generator
		function generateRandomString($length = 10) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < $length; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
			return $randomString;
		}

		function strToHex($string){
			if(!$string) return "";
			$hex = '';
			for ($i=0; $i<strlen($string); $i++){
				$ord = ord($string[$i]);
				if($ord >= 0 && $ord <= 255){
					$hexCode = dechex($ord);
					$hex .= (" " . substr('0'.$hexCode, -2));
				}
			}
			return strToUpper($hex);
		}

		function hexToStr($hex){
			if(!$hex) return "";
			$hex = str_replace(" ","",$hex);
			$string='';
			for ($i=0; $i < strlen($hex)-1; $i+=2){
				$dec = hexdec($hex[$i].$hex[$i+1]);
				if($dec >= 0 && $dec <= 255){
					$string .= chr($dec);
				}
			}
			return $string;
		}

		/**
		* Generate Halkbank payment button link
		**/
		public function generate_authorize_form($order_id){
			try{
				global $woocommerce;
				global $currencies;
				
				halkbank_get_currencies();
				
				ob_start();

				do_action("halkbank_before_render_authorize_form", $this, $order_id);

				$html_form = ob_get_clean();
				
				$currencies_arr = array();
				foreach($currencies as $code3 => $curr){
					$currencies_arr[$curr["currency_numeric_code"]] = $code3;
				}

				$tc_ajax = isset($_REQUEST["np_ajax"]);

				if(is_object($order_id)){
					$order_id = $order_id->id;
					if(!$order_id)
						$order_id = $order_id->ID;
				}

				$order      = $this->getOrder($order_id, true);
				$timeStamp  = time();

				$conversion_rate  = 1;
				$payment_currency = $this->merchant_currency;
				
				$ocurr = tc_get_order_currency($order);
				try{
					if(!$ocurr){
						return "<p style='color:red'>halkbank: Invalid or missing order currency: '" . $order->get_currency() . "'??? It may be that you wanted to change currency label and you forgot to change '{$ocurr}' to your actual currency code like 'RSD','MKD','BAM','TRY','HUF' in code you copied from the internet or got from a AI assistant...</p>";
					}
				}catch(Throwable $crex){
					return "<p style='color:red'>halkbank: ". $crex->getMessage() . "</p>";
				}
				
				$order_currency   = $currencies[$ocurr]['currency_numeric_code'];
				
				if(!$payment_currency){
					$payment_currency = $order_currency;
				}
				
				if(!$order_currency){
					$html_form .= "<!-- halkbank: warning no order currency code (order->get_currency : " . $order->get_currency() . ")-->";
				}
				
				if(!$payment_currency){
					return "<p style='color:red'>halkbank: missing payment currency - please set explicitly!</p>";
				}

				$code3_payment = "";
				if($order_currency != $payment_currency){
					$code3_order     = $currencies_arr[$order_currency];
					$code3_payment   = $currencies_arr[$payment_currency];
					$conversion_rate = halkbank_getExchangeRate($code3_order,$code3_payment, floatval($this->conversion_rate_adjust),$order_id);
					tc_update_order_meta($order_id,"exchange_rate_" . strtoupper($code3_order) . strtoupper($code3_payment), $conversion_rate);
				}

				$amount =  number_format(round(floatval($order->get_total()) * $conversion_rate ,2),2, '.', '');

				$cancel_url = wc_get_cart_url();

				if(trim($this->cancel_url)){
					$cancel_url = $this->cancel_url;
				}
				
				$checkv = md5($this->store_key."/".$amount."/".$order_id);

				$halkbank_args = array(
					'clientid'		=> $this->merchant_id,
					'amount'		=> $amount,
					'okUrl'			=> htmlspecialchars_decode(add_query_arg("halkbank_success","1",$this->getPostBackURL($order_id,true))) . "&checkv={$checkv}",
					'failUrl'		=> htmlspecialchars_decode(add_query_arg("halkbank_fail","1",$this->getPostBackURL($order_id,false))). "&checkv={$checkv}",
					'shopurl'       => htmlspecialchars_decode(add_query_arg("np_cancel_order",$order_id . ":" . md5("{$this->merchant_id}{$order_id}{$this->store_key}"),$cancel_url)). "&checkv={$checkv}",
					'trantype'		=> $this->tran_type ,
					'currency'		=> $payment_currency,
					'rnd'			=> $this->generateRandomString(20),
					'storetype'		=> $this->store_type,
					'hashAlgorithm'	=> "ver3",
					'lang'			=> "en",
					'oid'			=> $order_id,
					'instalment'    => apply_filters('hinstallments_installments',isset($_REQUEST["instalment"]) ? trim($_REQUEST["instalment"]) : "")
				);
				
				if($halkbank_args['instalment'] == "1"){
					$halkbank_args['instalment'] = "";
				}

				if(trim($this->instalment_plans)){
					if(strpos($this->instalment_plans ,"1,") === 0 || trim($this->instalment_plans) === "1"){
						if(!$halkbank_args['instalment']){
							$halkbank_args['instalment'] = "1";
						}
					}
				}

				if($this->add_bill_to){
					$userdata = array();
					
					$userdata["email"] = $order->get_billing_email();
					$userdata["tel"]   = $order->get_billing_phone();
					
					if($order->get_billing_company()){
						$userdata["BillToCompany"] = $order->get_billing_company();
					}else{
						$userdata["BillToCompany"] = __("Not specified","halkbank");
					}
					
					$userdata["BillToName"]       = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
					
					$userdata["BillToStreet1"]    = substr($order->get_billing_address_1(),0,255);
					$userdata["BillToStreet2"]    = substr($order->get_billing_address_2(),0,255);
					$userdata["BillToStreet3"]    = "--";
					$userdata["BillToCity"]       = substr($order->get_billing_city(),0,64);
					$userdata["BillToStateProv"]  = substr($order->get_billing_state(),0,32);
					$userdata["BillToPostalCode"] = substr($order->get_billing_postcode(),0,32);
					$userdata["BillToCountry"]    = substr($order->get_billing_country(),0,32);
					
					foreach($userdata as $ukey => $uval){
						if(!trim($uval)){
							$userdata[$ukey] = "--";
						}
						try{
							if(function_exists('transliterator_transliterate')){
								$halkbank_args[$ukey] = transliterator_transliterate('Any-Latin; Latin-ASCII;', $uval);
								if(!trim($halkbank_args[$ukey])){
									$halkbank_args[$ukey] = "--";
								}
							}
						}catch(Throwable $ex){
							
						}
					}
				}

				if($this->installmentonhpp){
					$halkbank_args["installmentonHPP"] = "YES";
				}

				$halkbank_args["encoding"] = "UTF-8";

				if($this->refreshtime){
					$halkbank_args["refreshtime"] = $this->refreshtime;
				}

				if($this->user_language_code){
					$halkbank_args["lang"] = $this->user_language_code;
				}else
					$halkbank_args["lang"] = str_replace(array("rs"),array("sr"), strtolower(substr(get_locale(),0,2)));

				$halkbank_args_array = array();
				if($this->instalment_plans){
					$this->instalment_plans = str_replace(" ","", trim($this->instalment_plans));
				}
				
				$tmp_args = apply_filters("halkbank_form_variables",$halkbank_args);
				if(!empty($tmp_args)){
					$halkbank_args = $tmp_args;
				}
				
				$halkbank_args_s = json_encode($halkbank_args);
				
				if(isset($this->additional_form_variables)){
					if(trim($this->additional_form_variables)){
						$this->additional_form_variables = str_replace("|","\n",trim($this->additional_form_variables));
						$additional_form_variables = explode("\n",$this->additional_form_variables);
						foreach($additional_form_variables as $line){
							if(strpos($line,"=") !== false){
								$n = trim(substr($line, 0,stripos($line,"=")));
								$v = trim(substr($line, stripos($line,"=") + 1));
								if($n)
									$halkbank_args[$n] = $v;
							}
						}
					}	
				}
				
				$all_params = array_keys($halkbank_args);
				foreach ($all_params as $index => $param){
					$paramValue = $halkbank_args[$param];
					if(!trim($paramValue) || $paramValue == "--"){
						unset($halkbank_args[$param]);
					}else{
						$encoded_val = $paramValue;
						if(stripos($param,"url") === false){
							$encoded_val = json_encode(trim($encoded_val . ""));
							$encoded_val = rtrim(ltrim($encoded_val,'"'),'"');
						}
						$halkbank_args[$param] = $encoded_val;
					}
				}
				
				//FORM DATA UNMUTABLE FURTHER//////////////////////////////////////////////////////////////////////////////////////////////
				//HASH CALC (ver3)////////////////////////////////////////////////////////////////////////////////////////////////////////////
				$hash = "";
				$all_params = array_keys($halkbank_args);
				natcasesort($all_params);
				$plaintext = "";
				foreach ($all_params as $index => $param){
					$paramValue = $halkbank_args[$param];
					$escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));
					if(trim($paramValue)){
						$lowerParam = strtolower($param);
						if($lowerParam != "hash" && $lowerParam != "encoding" )	{
							$plaintext = $plaintext . $escapedParamValue . "|";
						}
					}
				}
				$escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $this->store_key));
				$plaintext = $plaintext . $escapedStoreKey;

				$calculatedHashValue = hash('sha512', $plaintext);
				$hash = base64_encode(pack('H*',$calculatedHashValue));

				//END HASH CALC////////////////////////////////////////////////////////////////////////////////////////////////////////////

				foreach($halkbank_args as $key => $value){
					if($key != "path" && $key != "storeKey" && $key != "NPV_RequestHashHandler"){
						if($key == "instalment"){
							if(trim($value) == "")
								continue;
						}
						$halkbank_args_array[] = ("<input type='hidden' name='{$key}' id='halkbankfield_$key' class='halkbankfield_$key' value='{$value}'/>");
					}
				}

				$halkbank_args_array[] = "<input type='hidden' name='hash' value='{$hash}'/>";
				//FORM DATA END////////////////////////////////////////////////////////////////////////////////////////////////////////////
				
				$instalment_form = "";
				if($this->instalment_plans){
					$tmp = explode(",",trim($this->instalment_plans));
					if($tmp){

						$instalment_plans = array();
						$instalment_plans[""] = __("No instalments","halkbank");

						foreach($tmp as $im){
							if(strpos($im,":") !== false){
								$im = explode(":",$im);
								if(!(floatval($amount) >= floatval($im[1]))){
									continue;
								}
								$im = $im[0];
							}
							$im = intval($im);
							if($im){
								if($im > 1){
									$instalment_plans[$im] = ("$im " . __("months","halkbank"));
								}
							}
						}

						if(count($instalment_plans) > 1){
							$instalment_form .= "<div id='instalment_picker_wrapper' class='instalment-picker'>";
							$instalment_form .= ("<h4>". __("Number of instalments:") ."</h4>");
							$instalment_form .= ("<select style='display:inline-block; margin-right:20px;' id='instalment_picker'>");

							foreach($instalment_plans as $months => $name){

								$selected = $halkbank_args['instalment'] == $months ? ' selected="selected" ' : "";

								$instalment_form .= "<option $selected value='$months'>$name</option>";
							}

							$inst = 1;
							if($instalment)
								$inst = $instalment;
							$inst_amount = number_format(round(floatval($amount / $inst) ,2),2, '.', '');



							$instalment_form .= ("</select>");
							$instalment_form .= ("<p style='display:inline-block; font-weight:bold;' >$inst x $inst_amount</p>");
							$instalment_form .= "</div>";
						}
					}
				}


				$total_in_payment_currency_note = "";
				if($conversion_rate != 1){
					$total_in_payment_currency_note = "<p>" . __("Total in payment currency","halkbank") . ": {$amount} {$code3_payment}</p>";
				}

				$html_form .= $instalment_form . '<form class="np-form" action="'.$this->gateway_url.'" method="post" id="authorize_payment_form">
				   '
				   . implode("\n", $halkbank_args_array)
				   . ' </form>
				   '. ('<p>'.__('Thank you for your order, please click the button below to proceed to the HPP payment page.', 'halkbank').'</p>') .

				   $total_in_payment_currency_note
				   . '<p class="np-pay-buttons-line" style="display:flex">
					<a class="button button-default button-primary btn btn-primary wc-block-components-button wp-element-button" style="margin:5px" id="submit_halkbank_payment_form" >' . __('Pay', 'halkbank') . '</a>
					<a class="button button-alt button-secondary cancel btn btn-secondary btn-alt is-style-outline is-style-outline--3" style="margin:5px" href="'. $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'halkbank') . '</a></p>';
				if(!$tc_ajax){
					if($this->use_recaptcha)
						$html_form .= '<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?render='. $this->recaptcha_site_key .'" ></script>';

					$html_form .= '<script type="text/javascript">';
					if($this->use_recaptcha){
						$html_form .= ' ///////////////////////////////////////////////////////
							window.np_recap_err_msg = "' . __("We were unable to verify basic security requirements on your side. Please try later or width different device. You may also contact us directly.","halkbank") . '";////
							window.np_verify_captacha_url = "'. add_query_arg( 'wc-api', 'wc_gateway_halkbank_verifyrecaptacha', home_url( '/' )) .'";////
							window.np_recaptcha_site_key = "'. $this->recaptcha_site_key .'";////
							/////////////////////////////////////////////////////// ';
					}

					if($instalment_form){

						  $loading_img = get_site_url(null,"/wp-content/plugins/woocommerce-halkbank-payment/images/loading.gif");
						  $html_form .= ' //////////////////////////////////////////////////////////////////////////////
										   window.np_instalment_order_id = "'.$order_id.'";////
										   window.np_loading_image       = '.json_encode($loading_img."").';////
										 /////////////////////////////////////////////////////////////////////////////// ';
					}

					if($this->auto_proceed_with_form){
						  if(!$this->use_recaptcha){
							  $html_form .= ' //////////////////////////////////////////////////////////////////////////////
											   jQuery(document).ready(function(){////
												 np_submit_pay_form(); ////
											   });////
											 /////////////////////////////////////////////////////////////////////////////// ';
						  }else{

							  $html_form .= ' //////////////////////////////////////////////////////////////////////////////
											   var auto_submit_interval = setInterval(function(){////
												   let ts = (new Date()).getTime();
												   if(window.__np_submit_pay_form_called && window.__np_submit_pay_form_called + 3500 > ts)
													   return;
												   if(window.grecaptcha){////
													if(window.grecaptcha.execute){
													   clearInterval(auto_submit_interval);
													   window.__np_submit_pay_form_called = ts;
													   np_submit_pay_form();
													}////
												   }////
											   },800);////
											 /////////////////////////////////////////////////////////////////////////////// ';
						  }

						   $html_form .= ' //////////////////////////////////////////////////////////////////////////////
											   jQuery("body").addClass("np_pending_pay_redirect");////
											   jQuery("body").attr("redirect_message","'. __("Please wait...","halkbank")  .'");////
										  /////////////////////////////////////////////////////////////////////////////// ';

					  }

					  $html_form .= "</script>";
				}

				try{
					global $tc_plugin_data;
					if(!isset($tc_plugin_data)){
						if( !function_exists('get_plugin_data') ){
							require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
						}
						$tc_plugin_data = get_plugin_data( __DIR__ . DIRECTORY_SEPARATOR . 'index.php' );
					}
			
					wp_enqueue_script( 'tc-footer-script', plugin_dir_url( __FILE__ ) . 'js/tc_enqueue_script.js' , array('jquery-core'), $tc_plugin_data['Version'], array("in_footer" => true));
					
					global $tc_footer_script_data;
					$sdata = array(
							'Form'     => $html_form
						);
					
					if(!isset($tc_footer_script_data)){
						$tc_footer_script_data = $sdata;
					}else{
						foreach($sdata as $key => $val){
							$tc_footer_script_data[$key] = $val;
						}
					}
					
					wp_localize_script( 'tc-footer-script', 'HalkbankTransaction',$tc_footer_script_data);
				}catch(Throwable $ex){
					@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
					echo "<!-- NP_EXCEPTION: " . $ex->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
				}
				
				tc_commit_orders();

				return $html_form;
			}catch(Throwable $fex){
				echo "<p style='color:red'>" . $fex->getMessage() . "</p>";
			}
		}

		function process_payment( $order_id ) {

			$order = $this->getOrder( $order_id );

			return array(
					'result' 	=> 'success',
					'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}

		function get_product_object(){
			return $product;
		}
	}
}



function np_woocommerce_gateway_block_support(){
	try{
		
		if(!class_exists('WC_Gateway_Halkbank'))
			return;
		
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once(rtrim(__DIR__,"/") . '/tc_blocks.php');
			
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					try{
						$payment_method_registry->register(new WC_Gateway_Halkbank_Block());
					}catch(Throwable $ex){
						//
					}
				}
			);
		}
	
	}catch(Throwable $ex){
		//
	}
}



?>

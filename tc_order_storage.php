<?php
if(!function_exists("add_action")){
	die("Direct access is not allowed");
};

global $TC_ORDER_DIRECTORY;
$TC_ORDER_DIRECTORY = array();


function tc_wchps_enabled(){
	try{
		return Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . "\r\n" . $ex->getMessage(), FILE_APPEND);
		return null;
	}
}

function tc_id_is_wc_order($id){

	try{
		$type = Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($id);
		return $type == "shop_order" || $type == "shop_order_placehold";
	}catch(Throwable $ex){
		global $TC_ORDER_DIRECTORY;
		if(isset($TC_ORDER_DIRECTORY[$id])){
			return true;
		}
		$type = get_post_type($id);
		return $type == "shop_order" || $type == "shop_order_placehold";
	}
}

function tc_get_order($order_id, $requery = false){
	global $TC_ORDER_DIRECTORY;
	if(!isset($TC_ORDER_DIRECTORY[$order_id]) || $requery){

		$ord = wc_get_order($order_id);
		if(!$ord)
			return null;

		$TC_ORDER_DIRECTORY[$order_id] = array("dirty" =>  false, "dirty_meta" => array(), "order" => $ord, "wpmeta" => array());
		global $wpdb;
		$res = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $order_id));

		if(!empty($res)){
			foreach($res as $record){
				if(stripos($record->meta_value,":") === 1){
					$uns = @unserialize($record->meta_value);
					if ($uns !== false) {
						 $record->meta_value = $uns;
					}
				}
				$TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$record->meta_key] = $record->meta_value;
			}
		}
	}
	return $TC_ORDER_DIRECTORY[$order_id]["order"];
}

function tc_get_order_meta($order_id, $meta_key, $single = true){
	if(!$order_id)
		return null;
	try{
		global $TC_ORDER_DIRECTORY;
		$order = tc_get_order($order_id);
		$val = $order->get_meta($meta_key);
		if(!$val){
			if(isset($TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key])){
				if($TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key] == "-TCDELETED-"){
					return null;
				}
				return $TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key];
			}
		}
		return $val;
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n" . $ex->getMessage(), FILE_APPEND);
		return null;
	}
}

function tc_delete_order_meta($order_id, $meta_key){
	global $TC_ORDER_DIRECTORY;
	try{
		if(!$order_id)
			return false;

		$order = tc_get_order($order_id);

		if(!$order)
			return false;

		$TC_ORDER_DIRECTORY[$order_id]["dirty_meta"][] = $meta_key;

		$order->delete_meta_data($meta_key);
		if(isset($TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key])){
			$TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key] = "-TCDELETED-";
		}
		return true;
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n" . $ex->getMessage(), FILE_APPEND);
		return false;
	}
}

function tc_update_order_meta($order_id, $meta_key, $data){
	global $TC_ORDER_DIRECTORY;
	if(!$order_id)
			return false;
	try{
		$order = tc_get_order($order_id);
		if($order){
			$TC_ORDER_DIRECTORY[$order_id]["dirty_meta"][] = $meta_key;
			$order->update_meta_data($meta_key, $data);
			$TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key] = $data;
			return 1;
		}
		return false;
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n" . $ex->getMessage(), FILE_APPEND);
		return false;
	}
}

function tc_update_order_status($order_id, $status, $message = null){
	global $TC_ORDER_DIRECTORY;
	$order = null;
	try{
		$order = tc_get_order($order_id);
		if($order){
			$TC_ORDER_DIRECTORY[$order_id]["dirty"] = true;
			if($message){
				$order->update_status($status, $message);
			}else{
				$order->update_status($status);
			}
		}
	}catch(Throwable $ex){
		try{
			if($order){
				$order->add_order_note("HALKBANK CRITICAL WARNING! Failed to update order status to {$status}. Error is caused by some on-status-change handler. Check other plugins and custom codes. Error:" . $ex->getMessage());
			}
		}catch(Throwable $notimportant){
			//
		}
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n order: {$order_id}, error: " . $ex->getMessage(), FILE_APPEND);
		return false;
	}
}

function tc_commit_orders(){
	global $TC_ORDER_DIRECTORY;
	if(!empty($TC_ORDER_DIRECTORY)){
		foreach($TC_ORDER_DIRECTORY as $order_id => $odata){
			try{
			if($TC_ORDER_DIRECTORY[$order_id]["dirty"]){
				$TC_ORDER_DIRECTORY[$order_id]["dirty"] = false;
				$TC_ORDER_DIRECTORY[$order_id]["order"]->save();
			}else if(!empty($TC_ORDER_DIRECTORY[$order_id]["dirty_meta"])){
				$TC_ORDER_DIRECTORY[$order_id]["order"]->save_meta_data();
			}

			foreach($TC_ORDER_DIRECTORY[$order_id]["dirty_meta"] as $index => $meta_key){
				if($TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key] == "-TCDELETED-"){
					delete_post_meta($order_id, $meta_key);
				}else{
					update_post_meta($order_id, $meta_key, $TC_ORDER_DIRECTORY[$order_id]["wpmeta"][$meta_key]);
				}
			}

			$TC_ORDER_DIRECTORY[$order_id]["dirty_meta"] = array();
			}catch(Throwable $ex){
				@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n" . $ex->getMessage(), FILE_APPEND);
			}
		}
	}
}

function tc_on_order_saved( $order ) {
	if($order)
		tc_commit_orders();
}


//register_shutdown_function('tc_commit_orders');

add_action( 'before_woocommerce_init', function() {
	try{
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HALKBANK_PLUGIN_FILE , true );
		}
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n" . $ex->getMessage(), FILE_APPEND);
		//
	}
});

function tc_show_orders_admin_notices(){
	try{
		global $tc_admin_notices_shown;

		if(isset($tc_admin_notices_shown))
			return;

		$current_screen_id = get_current_screen()->id;
		if(!in_array($current_screen_id,array('dashboard','edit-shop_order','edit-product'))){
			return;
		}

		$tc_admin_notices_shown = true;
		$notices = array();
		global $wpdb;
		$waiting_post_auth = array();

		if(tc_wchps_enabled()){
			$waiting_post_auth = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT
						count(pm.order_id)
					 FROM
						{$wpdb->prefix}wc_orders_meta as pm
					 LEFT JOIN
						{$wpdb->prefix}wc_orders as p on p.id = pm.order_id
					 WHERE
						DATEDIFF(p.date_created_gmt, NOW()) > 5 AND DATEDIFF(p.date_created_gmt, NOW()) < 30
					AND
						pm.meta_key = '_testpay_last_tran_type'
					AND
						pm.meta_value LIKE %s
					AND
						p.type = 'shop_order'
					AND p.status != 'wc-cancelled'",'preauth'));

		}else{
			$waiting_post_auth = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT
						count(pm.post_id)
					 FROM
						{$wpdb->prefix}postmeta as pm
					 LEFT JOIN
						{$wpdb->prefix}posts as p on p.ID = pm.post_id
					 WHERE
						DATEDIFF(p.post_date, NOW()) > 5 AND DATEDIFF(p.post_date, NOW()) < 30
					AND
						pm.meta_key = '_testpay_last_tran_type'
					AND
						pm.meta_value LIKE %s
					AND
						p.post_type = 'shop_order'
					AND p.post_status != 'wc-cancelled'",'preauth'));
		}


		if(!empty($waiting_post_auth)){
			if($waiting_post_auth[0]){
				$msg = sprintf(__("There are %d non-canceled orders pre-authorizated more than 5 days ago whose payments have not been captured/post-authorized yet (orange blinking). Safe period to do post-authorization is 7 days. Make sure you do capture/post-authorize(transfer of money to your bank account) on time or do VOID action on them so you don't keep your customer card money trapped. When you do a capture/post-authorize action you can also capture part of full amount if you don't have all of the items available.",'halkbank'),$waiting_post_auth[0]);

				if(!trim($msg))
					return;

				$notices["preauth"] = array(
					"type"    => "warning",
					"message" => "HALKBANK: " . $msg
				);
			}
		}

		if(empty($notices))
			return;

		foreach($notices as $uid => $notice){
			$type_class = $notice["type"];
			$class = 'notice notice-' . $type_class . '';
			printf( '<div class="%1$s">
						<p>%2$s</p>
					</div>', esc_attr( $class ), esc_html( $notice["message"] ) );
		}
	}catch(Throwable $ex){
		@file_put_contents(__DIR__. "/tcerr" . date("Y-m") . ".log.txt", "\r\n" . date("Y-m-d H:i:s") . __FILE__ .":" . __LINE__ .  "\r\n" . $ex->getMessage(), FILE_APPEND);
	}
}

if(!defined("TC_ORDER_STORAGE_LOADED")){
	define("TC_ORDER_STORAGE_LOADED",1);
}

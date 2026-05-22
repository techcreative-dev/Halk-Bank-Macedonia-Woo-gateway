<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if(!function_exists("add_action")){
	die("Direct access is not allowed");
};

class WC_Gateway_Halkbank_Block  extends AbstractPaymentMethodType {
	private $gateway;
	
	protected $name = 'halkbank';
	
	public function __construct(){
		if(class_exists('WC_Gateway_Halkbank'))
			$this->gateway = WC_Gateway_Halkbank::instance();
	}
	
	public function is_active() {
		if(!$this->gateway)
			return false;
		
		return $this->gateway->is_available();
	}
	
	public function initialize() {
		//
	}
	
	public function get_payment_method_script_handles() {
		
		if(!class_exists('WC_Gateway_Halkbank'))
			return array();
					
		$tc_self = WC_Gateway_Halkbank::instance();

		if(!isset($tc_self->plugin_data)){
			if( !function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$tc_self->plugin_data = get_plugin_data( HALKBANK_PLUGIN_FILE );
		}
			
		$script_path       = '/js/blocks.js';
		$script_asset      = array(
				'dependencies' => array(),
				'version'      => $tc_self->plugin_data["Version"]
		);
		$script_url        = rtrim(HALKBANK_PLUGIN_URL,"/") . $script_path;
		wp_register_script(
			"wc-{$this->name}-payments-blocks",
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( "wc-{$this->name}-payments-blocks", 'halkbank', rtrim(dirname(HALKBANK_PLUGIN_FILE),"/") . '/languages/' );
		}
		return array("wc-{$this->name}-payments-blocks" );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->gateway->title,
			'description' => $this->gateway->adapt_method_description($this->gateway->description, $this->name),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}
}
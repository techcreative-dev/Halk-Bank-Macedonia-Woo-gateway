<?php
	if(!function_exists("add_action")){
		die("Direct access is not allowed");
	}

	global $tc_settings_fields;
	if(!isset($tc_settings_fields)){
	$selectableroles = array("" => __('Non-authenticated users', 'halkbank'));
	 
	global $wp_roles;
	if(isset($wp_roles)){
		if(isset($wp_roles->roles)){
			$roles = $wp_roles->roles; 
			try{
				 foreach($roles as $role_uid => $role_data){
					 $selectableroles[$role_uid] = $role_data["name"];
				 }
			}catch(Throwable $rex){
				 //
			}
		}
	}
	 
	 $footer_templates = array("" => __("Not used", 'halkbank'));
	 
	 foreach(glob(__DIR__ . "/footer_branding/*",GLOB_ONLYDIR) as $footer_template){
		 $footer_templates[basename($footer_template)] = basename($footer_template);
	 }
	 
	 $store_key_warn = "<p id='store_key_warn' style='color:red;font-size: 9px;display:none;'>  " . __('Use combination of 12-20 letters small|big and numbers. There are known problems with special characters!!!', 'halkbank') . "</p>";
	 $tc_settings_fields = array(
			'enabled' => array(
					'title' => __( 'Enable', 'halkbank' ) . $disp_error,
					'type' => 'checkbox',
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no'
			),

			'nphide' => array(
				'title'       => __( 'Hide for customers', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'If turned on you will see method only when signed in to site as administrator', 'halkbank' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'options'     => array(
				  "yes"              => __( 'Yes', 'halkbank' ),
				  "no"               => __( 'No', 'halkbank' )
				)
			),

			'title' => array(
					'title' => __( 'Title', 'halkbank' ),
					'type' 			=> 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'halkbank' ),
					'default' =>  __( 'Pay with payment card', 'halkbank' ),
					'desc_tip'      => true,
					'placeholder'	=> __( 'enter title', 'halkbank' )
			),

			'description'  => array(
					'title'        => __('Description:', 'halkbank'),
					'type'         => 'textarea',
					'description'  => __('This controls the description which the user sees during checkout.', 'halkbank'),
					'default'      => __('Pay securely by Credit or Debit Card via Halkbank', 'halkbank')
			),

			'merchant_currency' => array(
				'title'       => __( 'Use currency', 'halkbank' ) . " (" . __( 'probaby must be domestic', 'halkbank' ) .")",
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select Currency. Usualy you have to use your bussness domestic currecy.', 'halkbank' ) ,
				'default'     => '807',
				'desc_tip'    => true,
				'options'     => $currencies_arr
			),

			'conversion_rate_adjust' => array(
					'title' => __( 'Conversion rate correction in %', 'halkbank' ) . "(EUR-RSD: ". halkbank_getExchangeRate("EUR","RSD") .", EUR-BAM: ". halkbank_getExchangeRate("EUR","BAM") .", EUR-MKD: ". halkbank_getExchangeRate("EUR","MKD") .") ",
					'type' => 'text',
					'description' => __( 'Conversion rate is accured from the public service. If you need to adapt it enter correction in %', 'halkbank' ),
					'default' => '0.00',
					'desc_tip' => true,
			),

			'user_language_code' => array(
					'title' => __( 'Language code', 'halkbank' ),
					'type' => 'text',
					'description' => __( 'Enter two letter lowercase code for language to be used on gateway page. Leave empty to use WP current locale.', 'halkbank' ),
					'default' => 'sr',
					'desc_tip' => true,
					'onchange' => "function(e){ this.value = this.value.toLowerCase() }",
					'placeholder' => __('Set explicitly 2 letter language code. Current automatic: ', 'halkbank'  ) . str_replace(array("rs"),array("sr"), strtolower(substr(get_locale(),0,2)))
			),

			'merchant_id' => array(
					'title' => __( 'Merchant ID', 'halkbank' ),
					'type' => 'text',
					'description' => __( 'Enter merchant id from Virtual Terminal backend (provided by bank system).', 'halkbank' ),
					'default' => '',
					'desc_tip'      => true
			),

			'merchant_username' => array(
					'title' => __( 'API User Name', 'halkbank' ),
					'type' => 'text',
					'description' => __("API user is created and managed on the merchmant portal (at bank)",'halkbank') .". ". __( 'Enter your API user/merchant user name. You can use master account but it\'s recommended you create API user for this purphose form bank portal.', 'halkbank' ),
					'default' => '',
					'desc_tip'      => true,
					'custom_attributes' => array(
                       'autocomplete' => 'new-username'
					)
			),

			'merchant_password' => array(
					'title' => __( 'API User Password', 'halkbank' ),
					'type' => 'password',
					'description' => __("API user is created and managed on the merchmant portal (at bank)",'halkbank') .". " . __( 'Enter your API user/merchant password.', 'halkbank' ),
					'default' => '',
					'desc_tip'      => true,
					'custom_attributes' => array(
                       'autocomplete' => 'new-password'
					)
			),
			'store_key' => array(
					'title' => __( 'Store key', 'halkbank' ) . $store_key_warn,
					'type' => 'text',
					'description' => __( 'Enter Store Key you did set on the bank merchant portal under Administration->Store Key. Store key must be complicated!!! Good example: S@3a54T6x-2-3-XXxkQ#ert_X', 'halkbank' ),
					'default' => '',
					'desc_tip'      => true,
					'placeholder'	=> __( 'enter store key you generated on bank portal', 'halkbank' ),
					'custom_attributes' => array(
                       'autocomplete' => 'new-username'
					)
			),
			'tran_type' => array(
				'title'       => __( 'Transaction type', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select transaction type. If you use Pre-Authorization money will only be reserved for you on the customer CC unti you do the Post-Authorization (Capure) to transfer a mony to your bank account or a VOID transaction to cancel reservarion and relese the customer money. If you use Authorization mony is transfered immediatlly.', 'halkbank' ),
				'default'     => 'PreAuth',
				'desc_tip'    => true,
				'options'     => array(
				  "PreAuth"  => __( "Pre-Authorization (reserve money on CC)", 'halkbank' ),
				  "Auth"     => __( "Authorization (simple immediate CC charge)", 'halkbank' )
				)
			),
			'preauth_means_paid' => array(
				'title'       => __( 'Set order as paid on Pre-Authorization', 'halkbank' ),
				'type'        => 'checkbox',
				'description' => __( 'Pre-Authorization is money reservation. Depending on your business needs you may decide if you want to consider order as paid in this moment or not.', 'halkbank' ),
				'label'       => __( 'Yes', 'halkbank' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'postauth_after_days' => array(
				'title'       => __( 'Auto Post-Authorization(Capture) after days', 'halkbank' ),
				'type'        => 'text',
				'description' => __( 'Prevent case of missing to Post-Authorize(Capture) Pre-Authorized payments. Good value is 7 becuse 1 week should be eaught for you to know if you can order deliver or not. If batch option is activated at the bank side then there is no sence to set value greather than 0.99.', 'halkbank' ),
				'label'       => __( 'Yes', 'halkbank' ),
				'default'     => '',
				'placeholder' => __( 'Number of days (0.5 - 12h, 0.33 - 8h)', 'halkbank' ),
				'desc_tip'    => true
			),
			'refreshtime' => array(
				'title' => __( 'Refresh time', 'halkbank' ),
				'type' 			=> 'text',
				'description' => __( 'Timout before returning user to site during checkout. 15 would be ok value.', 'halkbank' ),
				'default' => '',
				'desc_tip'      => true,
				'placeholder'	=> __('enter timeout in sec', 'halkbank')
			),
			'add_bill_to' => array(
				'title' => __( 'Include "Bill To" info in payment request', 'halkbank' ),
				'type' => 'checkbox',
				'description' => __( 'Set to "Yes" to include "Bill To" data in request.', 'halkbank' ),
				'label' => __( 'Yes', 'halkbank' ),
				'default' => 'no',
				'desc_tip'    => true
			),
			// 'hash_version' => array(
				// 'title'       => __( 'Hash version', 'halkbank' ),
				// 'type'        => 'select',
				// 'class'       => 'wc-enhanced-select',
				// 'description' => __( 'Select hash version (version 3 only)', 'halkbank' ),
				// 'default'     => 'ver2',
				// 'desc_tip'    => true,
				// 'options'     => array(
				  // "ver2"  => "ver2",
				  // "ver3"  => "ver3"
				// )
			// ),

			'store_type' => array(
				'title'       => __( 'Store type', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select store type', 'halkbank' ),
				'default'     => '3d_pay_hosting',
				'desc_tip'    => true,
				'options'     => array(
				  "3d_pay_hosting"  => "3d_pay_hosting"
				)
			),

			'bank_logo'  => array(
					'title'        => __('Bank logo', 'halkbank') . $bank_logo ,
					'type'         => 'file',
					'description'  => __('Select image for bank logo', 'halkbank'),
					'default'      => ''
			),

			'cc_logo'  => array(
					'title'        => __('CC logo', 'halkbank') . $cc_logo ,
					'type'         => 'file',
					'description'  => __('Select image for CC logo', 'halkbank'),
					'default'      => ''
			),

			'instalment_plans' => array(
				'title'       => __( 'Enabled instalment plans', 'halkbank' ),
				'type'        => 'text',
				'description' => __( 'Enter comma separated list of available payment by instalments months like 2,3:80,6:120 meaning there is option to pay with 2 instalments or 3 instalments if total is bigger than 80 or 6 instalments if total is bigger than 120.', 'halkbank' ),
				'default'     => '',
				'placeholder' => "available numbers of instalments like 2,3:80,6:120",
				'desc_tip'    => true
			),

			'installmentonhpp' => array(
					'title' => __( 'Insert installmentonHPP = YES in POST request', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'May be required if you are sending instalments data to payment page', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),

			'auto_proceed_with_form' => array(
					'title' => __( 'Auto-submit payment form', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'Do not use this if you use instalments. Tick this to auto-submit payment POST request (this enables no user click requirement on "PAY FOR ORDER" page)', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),
			'no_capture_void_email' => array(
					'title' => __( 'No capture and void e-mail', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'Turns off transaction email for capture and VOID transactions', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),
			
			'no_transaction_email' => array(
					'title' => __( 'No transaction e-mail', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'Turns off transaction email', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),
			
			'no_transaction_data' => array(
					'title' => __( 'No transaction data', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'Turns off transaction data', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),

			'include_order_details' => array(
					'title' => __( 'Include order details on thankyou page', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'If your theme does not output required order details you can enable this option to output order printout on thankyou page.', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),

			'include_order_details_mail' => array(
					'title' => __( 'Include order details in transaction e-mail', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'You can enable this option to include order details in transaction e-mail', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),

			'override_back_url' => array(
					'title'         => __( 'Explicit payment return url (result will be displayed by force)', 'halkbank' ),
					'type' 			=> 'text',
					'description'   => __( 'Explicitly define customer after-payment return url to inject output by force!', 'halkbank' ),
					'default'       => "",
					'desc_tip'      => true,
					'placeholder'	=> __('Set only if you have problem!!! Url that customer sees after payment before "?" character', 'halkbank' )
			),
			
			'after_override_timout' => array(
					'title'         => __( 'If explicit payment return url is set, then proceed to regular thank-you page after seconds', 'halkbank' ),
					'type' 			=> 'text',
					'description'   => __( 'If you set above filed you can here set number of seconds to wait until return to normal thank you page', 'halkbank' ),
					'default'       => "",
					'desc_tip'      => true,
					'placeholder'	=> __('leave empty for no effect or set number of seconds', 'halkbank' )
			),
			
			'cancel_url' => array(
					'title'         => __( 'Explicit cancel payment url', 'halkbank' ),
					'type' 			=> 'text',
					'description'   => __( 'URL to return a client to when he clicks the Cancel button on the CC entry page. If empty it will default to the cart page.', 'halkbank' ),
					'default'       => "",
					'desc_tip'      => true,
					'placeholder'	=> __('leave empty for return to cart page', 'halkbank' ),
			),
			'order_completed' => array(
				'title'       => __( 'On-payment success order status', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select np-status-select',
				'description' => __( 'Select order status for completed payment.', 'halkbank' ),
				'default'     => 'wc-completed',
				'desc_tip'    => true,
				'options'     => $statuses
			),
			'order_transaction_postauthorised' => array(
				'title'       => __( 'On transaction Post-Authorise (Capture) order status', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select np-status-select',
				'description' => __( 'Select order status for order after Post-Authorisation.', 'halkbank' ),
				'default'     => 'wc-completed',
				'desc_tip'    => true,
				'options'     => $statuses
			),
			'order_transaction_voided' => array(
				'title'       => __( 'On transaction VOID order status', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select np-status-select',
				'description' => __( 'Select order status for order after VOID.', 'halkbank' ),
				'default'     => 'wc-canceled',
				'desc_tip'    => true,
				'options'     => $statuses
			),
			/*
			'order_completed_stock' => array(
					'title' => __( 'Update product stock on order complited', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'If checked plugin will decrease stock leveles of tangable products.', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),*/
			'order_failed' => array(
				'title'       => __( 'On-payment failed order status', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select np-status-select',
				'description' => __( 'Select order status for failed payment.', 'halkbank' ),
				'default'     => 'wc-failed',
				'desc_tip'    => true,
				'options'     => $statuses
			),

			'order_abandon_cancel' => array(
					'title' => __( 'Cancel orders for abandoned payments(Cancel button on HPP page)', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'If customer clicks "Cancel" button on HPP page cancel order', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),
			
			'additional_form_variables' => array(
				'title'         => __( 'Additional form variables', 'halkbank' ),
				'type' 			=> 'textarea',
				'description'   => __( 'name=value, one per line or separate with |. Values will even override halkbank_form_variables filter', 'halkbank' ),
				'default'       => '',
				'desc_tip'      => true,
				'placeholder'	=> __('name1=value1|name2=value2...', 'halkbank')
			),

			'use_recaptcha' => array(
					'title' => __( 'Use plugin internal reCAPTCHA v3', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'If your site does not meet the security requirements you can enable the plugin\'s internal reCAPTCHA v3.', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),

			'recaptcha_site_key' => array(
				'title' => __( 'reCAPTCHA v3 site key', 'halkbank' ) . " <br/><br/><a href='https://www.google.com/recaptcha/admin/create' target='_blank'>GENERATE V3 KEYS: https://www.google.com/recaptcha/admin/create</a>",
				'type' 			=> 'text',
				'description' => __( 'Enter site key. You can create site and secret key here: https://www.google.com/recaptcha/admin/create', 'halkbank' ),
				'default' => '',
				'desc_tip'      => true,
				'placeholder'	=> __('site key', 'halkbank')
			),

			'recaptcha_secret_key' => array(
				'title' => __( 'reCAPTCHA v3 secret key', 'halkbank' ),
				'type' 			=> 'text',
				'description' => __( 'Enter secret key. You can create site and secret key here: https://www.google.com/recaptcha/admin/create', 'halkbank' ),
				'default' => '',
				'desc_tip'      => true,
				'placeholder'	=> __('secret key', 'halkbank')
			),

			'override_language' => array(
				'title' 		=> __( 'Override language', 'halkbank' ),
				'type' 			=> 'text',
				'description' 	=> __( 'If you enter auto2 - it will be current langugae will be 2 cahr code of current language en, rs... .If you want to force plugin use certain language set language tag here (sr_RS, hr_HR, bs_BA, it_IT, mk_MK...) , otherwise leave it empty', 'halkbank' ),
				'default' 		=> '',
				'desc_tip'      => true,
				'placeholder'	=> __('leave empty for no override', 'halkbank')
			),
			
			'debug_mode' => array(
					'title' => __( 'Debug', 'halkbank' ),
					'type' => 'checkbox',
					'description' => __( 'Do not tick this unless instructed by support', 'halkbank' ),
					'label' => __( 'Yes', 'halkbank' ),
					'default' => 'no',
					'desc_tip'    => true
			),

			'footer_template' => array(
				'title'       => __( 'Footer branding template. If you would like to set other images create new folder based on one of existing template folders under (plugin_dir)/footer_branding/ ', 'halkbank' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Insert on-hand footer branding', 'halkbank' ),
				'default'     => '',
				'desc_tip'    => true,
				'options'     => $footer_templates
			)
	);
}

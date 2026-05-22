<?php
/**
 * Plugin Name: Halkbank Payment Gateway for WooCommerce
 * Plugin URI: https://techcreative.dev/halkbank-payment-gateway
 * Description: Halkbank payment gateway plugin for WooCommerce. Enables credit card payments through Halkbank.
 * Version: 3.0
 * Requires at least: 3.0
 * WC requires at least: 4.5.2
 * WC tested up to: 10.4.3
 * Tested up to: 6.7.1
 * Author: TechCreative
 * Author URI: https://techcreative.dev
 * Text Domain: halkbank
 * Domain Path: /languages
 */

/*
  TO ADD/MODIFY INPUTS FOR REQUEST FORM USE FILTER IN YOUR THEME functions.php:
  add_filter("halkbank_form_variables", function($halkbank_args){
	  $halkbank_args["details1"] = "nesto moje";
	  return $halkbank_args;
  });
*/ 

if(isset($_GET["__tc_recovery_skip__"])){
	return;
}

if(isset($_GET["__tc_enable_error_reporting__"])){
	error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
	ini_set("display_errors","on");
}

if(!defined('HLST_NPAY_PLUGIN'))
	define("HLST_NPAY_PLUGIN", plugin_basename( __FILE__ ));

if(!defined('HALKBANK_PLUGIN_FILE'))
	define("HALKBANK_PLUGIN_FILE",__FILE__);

if(!defined('HALKBANK_PLUGIN_URL'))
	define("HALKBANK_PLUGIN_URL", plugin_dir_url( __FILE__ ));

try{
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "tc.php");
}catch(Throwable $ex){
	echo "<!-- TC_EXCEPTION: " . $ex->getMessage() . "|" . basename(__FILE__). ':' . __LINE__ . " -->";
}
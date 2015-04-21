<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Plugin Name: PriceWaiter
 * Plugin URI: https://pricewaiter.com
 * Description: Name your price with PriceWaiter
 * Author: PriceWaiter
 * Text Domain: woocommerce-pricewaiter
 * Author URI: http://pricewaiter.com/
 * Version: 0.0.1
 *
 */
// Standard check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	/**
	* New PriceWaiter
	*/
	if ( !class_exists( 'WC_PriceWaiter' ) ) {
		class WC_PriceWaiter {
			const VERSION = "0.0.1";
			const PLUGIN_ID = 'pw';
			const TEXT_DOMAIN = 'woocommerce-pricewaiter';
			/**
			* Main array key is the plugins primary class.
			* The class is checked to see if plugin is active.
			* 
			* Sub array is used to store meta key locations
			* 'product type' => 'post_meta key for stored cost'
			*/
			public $supported_cost_plugins = array(
				'WC_COG' => array(
					'simple'		=> '_wc_cog_cost',
					'variable'		=> '_wc_cog_cost_variable',
					'variation'		=> '_wc_cog_cost'
				)
			);
			public $supported_product_types = array( 
				'simple',
				'variable',
				'variation'
			);

			public function __construct() {

				// Include Required Files
				$this->includes();

				// Init API
				$this->api = new WC_PriceWaiter_API();

				add_action( 'plugins_loaded', array( $this, 'init' ) );
			}

			public function init() {
				$this->supported_product_types = apply_filters( 'wc_pricewaiter_supported_product_types', $this->supported_product_types );
				$this->supported_cost_plugins = apply_filters( 'wc_pricewaiter_supported_cost_plugins', $this->supported_cost_plugins );
				
				if ( class_exists( 'WC_Integration' ) ) {
					require_once( 'includes/class-wc-pricewaiter-product.php' );
					require_once( 'includes/class-wc-pricewaiter-embed.php' );
					require_once( 'includes/admin/class-wc-pricewaiter-integration.php' );
					add_filter( 'woocommerce_integrations', array( $this, 'add_pw_integration' ) );
				} else {
					// Ya do nothin' homie.
				}
			}

			/**
			 * Include required core files used in admin and on the frontend.
			 */
			private function includes() {

				// API Class
				include_once( 'includes/class-wc-pricewaiter-api.php' );

			}


			public function add_pw_integration() {
				$integrations[] = 'WC_PriceWaiter_Integration';
				return $integrations;
			}
		}

		$GLOBALS['wc_pricewaiter'] = new WC_PriceWaiter( __FILE__ );
	}
}
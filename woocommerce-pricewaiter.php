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
			public $supported_cost_plugins;

			public function __construct() {
				add_action( 'plugins_loaded', array( $this, 'init' ) );
			}

			public function init() {
				/**
				* Establish known supported cost/margin plugins
				*/
				$this->add_cost_plugin_support('WC_COG', 'Cost of Goods', '_wc_cog_cost', '_wc_cog_cost_variable', 'variable_cost_of_good');
				if ( class_exists( 'WC_Integration' ) ) {
					require_once( 'includes/class-wc-pricewaiter-product.php' );
					require_once( 'includes/class-wc-pricewaiter-embed.php' );
					require_once( 'includes/admin/class-wc-pricewaiter-integration.php' );
					add_filter( 'woocommerce_integrations', array( $this, 'add_pw_integration' ) );
				} else {
					// Ya do nothin' homie.
				}
			}

			public function add_pw_integration( $integrations ) {
				$integrations[] = 'WC_PriceWaiter_Integration';
				return $integrations;
			}

			/**
			* Builds array value to check for various cost/margin plugins with class_exists().
			* This method can hopefully be used by other plugins to announce themselves to
			* pricewaiter and inject support for themselves.
			* @param string|Plugin Class Name
			* @param string|Meta key for simple product cost/margin
			* @param string|Meta key for variable product cost/margin
			*/
			public function add_cost_plugin_support( $plugin_class, $plugin_name, $simple_meta_key, $variable_meta_key, $variation_field_name) {
				$this->supported_cost_plugins[$plugin_class] = array(
					'name'	=> $plugin_name,
					'simple'		=> $simple_meta_key,
					'variable'		=> $variable_meta_key,
					'variation'		=> $variation_field_name
				);
			}
		}

		$GLOBALS['wc_pricewaiter'] = new WC_PriceWaiter( __FILE__ );
	}
}
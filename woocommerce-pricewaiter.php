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
 * Requires at least: 3.8
 *
 */

// Minimum supported version of WooCommerce
$wc_minimum_version = '2.2.0';

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
				add_action( 'plugins_loaded', array( $this, 'init' ) );
			}

			public function init() {
				global $wc_minimum_version;

				// We version_compare() after plugins_loaded()
				// to make sure we have access to WC()->version
				if( version_compare( WC()->version, $wc_minimum_version ) < 0 ) {
					// Unsupported version
					add_action( 'admin_notices' , array($this, 'alert_woocommerce_minimum_version') );
					return;
				}

				// Include Required Files
				$this->includes();

				// Init API
				$this->api = new WC_PriceWaiter_API();

				$this->supported_product_types = apply_filters( 'wc_pricewaiter_supported_product_types', $this->supported_product_types );
				$this->supported_cost_plugins = apply_filters( 'wc_pricewaiter_supported_cost_plugins', $this->supported_cost_plugins );
				
				if ( class_exists( 'WC_Integration' ) ) {
					require_once( 'includes/class-wc-pricewaiter-product.php' );
					require_once( 'includes/class-wc-pricewaiter-embed.php' );
					require_once( 'includes/admin/class-wc-pricewaiter-integration.php' );
					add_filter( 'woocommerce_integrations', array( $this, 'add_pricewaiter_integration' ) );
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


			public function add_pricewaiter_integration( $integrations ) {
				$integrations[] = 'WC_PriceWaiter_Integration';
				return $integrations;
			}

			/**
			* Notify Admin to update WooCommerce to minimum version
			*/
			public function alert_woocommerce_minimum_version(){
				global $wc_minimum_version;
				?>
					<div class="error">
						<h3><?php _e( 'WooCommerce update required to continue.', WC_PriceWaiter::TEXT_DOMAIN ); ?></h3>
						<p><?php printf( __( "It appears you're currently using WooCommerce v%s", WC_PriceWaiter::TEXT_DOMAIN ), WC()->version ); ?></p>
						<p><?php printf( __( "WooCommerce v%s or higher is required to use the WooCommerce PriceWaiter plugin.", WC_PriceWaiter::TEXT_DOMAIN ), $wc_minimum_version); ?></p>
					</div>
				<?
			}
		}

		$GLOBALS['wc_pricewaiter'] = new WC_PriceWaiter( __FILE__ );
	}
} else {

	function alert_woocommerce_required() {
		global $wc_minimum_version;
		?>
			<div class="error">
				<h3>WooCommerce required to continue.</h2>
				<p><?php echo 'WooCommerce v' . $wc_minimum_version . ' or higher is required to use the WooCommerce PriceWaiter plugin.'; ?></p>
				<p><a href="http://www.woothemes.com/woocommerce/">Install WooCommerce to get started.</a></p>
			</div>
		<?
	}
	add_action( 'admin_notices', 'alert_woocommerce_required' );
}

/**
* This should only fire once after activation
* intended to make sure flush_rewrite_rules fires
* just after the plugin is activated.
* The traditional activation hook fires too soon.
*/
function wc_pricewaiter_after_activation() {
	if( get_option( 'wc_pricewaiter_flush_activation_rules_flag' ) ) {
		delete_option( 'wc_pricewaiter_flush_activation_rules_flag' );
		flush_rewrite_rules();
	}
}
function wc_pricewaiter_activated() {
	add_option( 'wc_pricewaiter_flush_activation_rules_flag', true );
}
function wc_pricewaiter_deactivated() {
	flush_rewrite_rules();
}
// Priority needs to always be after the api init action
add_action( 'init', 'wc_pricewaiter_after_activation', 20 );
register_activation_hook( __FILE__, 'wc_pricewaiter_activated' );
register_deactivation_hook( __FILE__, 'wc_pricewaiter_deactivated' );

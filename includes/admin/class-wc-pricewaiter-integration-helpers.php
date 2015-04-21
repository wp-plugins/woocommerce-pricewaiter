<?php
/**
 * PriceWaiter Admin Helper functions
 *
 * Facilitiate the easiest possible PriceWaiter Signup
 *
 * @author      Sole Graphics
 * @category    Class
 * @package     WooCommerce/Classes
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists( 'WC_PriceWaiter_Integration_Helpers' ) ):

    /**
    * Admin PriceWaiter Integration Helpers
    */
    class WC_PriceWaiter_Integration_Helpers {

        public static $error_messages = array();

        public function __construct() {

            add_action( 'wp_ajax_call_create_api_user_ajax', array( $this, 'create_api_user_ajax' ) );

        }

        /**
         * Get store data to prefill PriceWaiter sign up
         *
         * @access public
         * @param bool $encode
         * @param array of additional url parameters
         * @return mixed
         */
        public static function get_sign_up_url( $base_url, $additional_args = array(), $params = array() ) {

            if ( !$base_url ) {
                $base_url = 'https://manage.pricewaiter.com/sign-up';
            }

            // Get current user info
            $user = wp_get_current_user();
            $user_fullname = trim($user->user_firstname . ' ' . $user->user_lastname );

            // Get store locale info
            $locale = new WC_Countries;

            // Get API user
            $api_user = get_user_by( 'id', get_option( '_wc_pricewaiter_api_user_id' ) );

            if (!$api_user) {
                $api_user = new stdClass;
                $api_user->woocommerce_api_consumer_key = '';
                $api_user->woocommerce_api_consumer_secret = '';
            }

            // Get PayPal standard settings
            $paypal = new WC_Gateway_Paypal;

            // Params to be json encoded and passed to /sign-up?prefill_store=
            $prefill_args = array(
                // User information
                'name' => $user_fullname,
                'email' => $user->user_email,
                'user_firstname' => $user->user_firstname,
                'user_lastname' => $user->user_lastname,

                // Store information
                'domain' => site_url(),
                'shop_owner' => $user_fullname,
                'shop_country' => $locale->get_base_country(),
                'shop_state' => $locale->get_base_state(),
                'shop_city' => $locale->get_base_city(),
                'shop_zip' => $locale->get_base_postcode(),
                'allowed_countries' => $locale->get_shipping_countries(),
                'default_currency' => get_woocommerce_currency(),
                
                // API Access
                'woo_api_key' => $api_user->woocommerce_api_consumer_key,
                'woo_api_secret' => $api_user->woocommerce_api_consumer_secret,
                'woo_api_endpoint' => get_woocommerce_api_url(''),

                // PayPal settings (don't send if not both set?)
                'paypal_enabled' => $paypal->is_valid_for_use(),
                'paypal_email' => $paypal->receiver_email
            );

            // Additional url args
            $additional_args['utm_campaign'] = 'signup';
            $additional_args['utm_source'] = 'woocommerce';
            $additional_args['utm_medium'] = 'integrations';
            $additional_args['utm_content'] = site_url();
            $additional_args['utm_term'] = '';

            // Merge custom params and send over
            $prefill_args = array_merge( $prefill_args, $params );
            $additional_args['prefill_store'] = json_encode( $prefill_args, ENT_QUOTES );

            return $base_url . '?' . http_build_query($additional_args, '', '&amp;');

        }

        /**
         * Get wp user with login, id or email
         *
         * @access private
         * @param string $handle wordpress user id, login or email
         * @return mixed false or user stdClass object
         */
        private function get_wp_user( $handle ) {

            $key = 'login';
            if ( is_numeric($handle) ) {
                $key = 'id';
            } elseif ( strstr( $handle, '@' ) ) {
                $key = 'email';
            }

            $user = get_user_by( $key, $handle );

            return $user;

        }

        /**
         * Get or create the user account for woo REST API use.
         *
         * @access private
         * @param string $login wordpress user id, login or email
         * @param string $email email address for the new user
         * @return int user id
         */
        private function create_wp_administrator( $login = null, $email = null ) {

            // Check that user doesn't exist already
            if ( $user = self::get_wp_user( $login ) ) {
                return $user->ID; 
            }
            
            // Create user
            $pw_user_data = array(
                'user_login'    => $login,
                'user_pass'     => hash( 'md5', $login . date( 'U' ) . mt_rand() ),
                'user_email'    => $email,
                'first_name'    => 'PriceWaiter',
                'last_name'     => '(API User)',
                'role'          => 'administrator',
                'description'   => 'This is an "API" user that is needed for PriceWaiter to work with WooCommerce.'
            );

            $user_id = wp_insert_user( $pw_user_data ) ;

            if ( is_wp_error($user_id) ) {
                self::$error_messages[] = $user_id->get_error_message();
                return false;
            } else {
                return $user_id;
            }
        }

        /**
         * Check if Woo REST API is disabled, enable if so.
         *
         * @access private
         * @return mixed
         */
        private function enable_wc_rest_api() {

            // Ensure the WC REST API is enabled
            if ( false !== get_option( 'woocommerce_api_enabled' ) || 'no' === get_option( 'woocommerce_api_enabled' ) ) {
                return update_option( 'woocommerce_api_enabled', 'yes' );
            }

            return true;

        }

        /**
         * Grant Woo read_write REST API access to a user
         *
         * @access private
         * @param int $user_id a valid wp admin user id
         * @return bool
         */
        private function grant_wc_api_access_to_user( $user_id ) {
            
            // Use WC Logic for generating API keys & permissions
            // WC_Admin_Profile::generate_api_key( $user_id );
            
            // Assign API key, secret and permissions for the given user.
            if ( !get_user_meta( $user_id, 'woocommerce_api_consumer_key', true ) ) {
                $consumer_key = 'ck_' . hash( 'md5', $user_id . date( 'U' ) . mt_rand() );
                update_user_meta( $user_id, 'woocommerce_api_consumer_key', $consumer_key );
            }

            if ( !get_user_meta( $user_id, 'woocommerce_api_consumer_secret', true ) ) {
                $consumer_secret = 'cs_' . hash( 'md5', $user_id . date( 'U' ) . mt_rand() );
                update_user_meta( $user_id, 'woocommerce_api_consumer_secret', $consumer_secret );
            }

            update_user_meta( $user_id, 'woocommerce_api_key_permissions', 'read_write' );

            // Flag our api user
            update_option( '_wc_pricewaiter_api_user_id', $user_id );

            return true;

        }

        /**
         * Check if PriceWaiter is fully configured
         *
         * @access public
         * @return bool
         */
        public static function is_pw_configured() {
            $checks = array(
                'wc_api_user',
                'pw_api_key'
            );

            foreach ( $checks as $check ) {
                if ( !self::has_configured( $check ) ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Check if specific PriceWaiter config option is set
         *
         * @access public
         * @return bool
         */
        public static function has_configured( $key ) {

            switch ( $key ) {
                case "wc_api_user":
                    return get_option( '_wc_pricewaiter_api_user_id' );
                break;

                case "pw_api_key":
                    return WC_PriceWaiter()->get_pricewaiter_setting('api_key');
                break;

                default:
                    return false;
            }
        }

        public static function load_setup_screen() {
            
            // Integrations screen styles
            wp_enqueue_style( 'wc-pricewaiter-admin', plugins_url( '/woocommerce-pricewaiter/assets/css/admin.css' ), null, 1 );

            if ( !self::has_configured( 'wc_api_user' ) ) {

                // Our js callbacks
                wp_enqueue_script( 'wc-pricewaiter-integration', plugins_url( '/woocommerce-pricewaiter/assets/js/admin/integration.js' ), array( 'jquery' ), 1, true );

                // Get administrator list for <select>
                $admin_users = get_users( array(
                    'blog_id'      => $GLOBALS['blog_id'],
                    'role'         => 'administrator',
                    'orderby'      => 'login',
                    'order'        => 'ASC',
                    'number'       => 100,
                    'count_total'  => false,
                    'fields'       => 'all'
                ) );
            }

            if ( !self::is_pw_configured() ) {
                require_once('templates/integration_configure.php');
            }
        }

        /**
         * Admin ajax callback handler for Woo REST API setup
         *
         * @access public
         * @return mixed
         */
        public function create_api_user_ajax() {

            $posted = $_POST;
            $response = array(
                'posted' => $posted
            );

            // Reset Error Messages
            self::$error_messages = array();

            // Turn on the REST API
            self::enable_wc_rest_api();

            // Are we creating a new user or granting API access to exsiting
            if ( !empty( $posted['id'] ) && is_numeric( $posted['id'] ) ) {
                $admin_id = $posted['id'];
            } elseif ( !empty( $posted['login'] ) && !empty( $posted['email'] ) ) {
                $admin_id = self::create_wp_administrator( $posted['login'], $posted['email'] );
            }

            if ( is_numeric( $admin_id ) ) {
                self::grant_wc_api_access_to_user($admin_id);
                wp_send_json_success( $response );
            } else {
                $response['errors'] = implode('<br>', self::$error_messages);
                wp_send_json_error( $response );
            }
        }
    }

    return new WC_PriceWaiter_Integration_Helpers();

endif;

<?php
/**
 * Status handler for the API user assigned to PriceWaiter
 * 
 * Performs checks when editing the API user profile to make
 * sure the changes don't break the functionality of the API.
 *
 * Displays admin notice of issue and how to resolve it, then
 * temporarily disables the plugin until the issue is corrected.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_PriceWaiter_API_User_Monitor {

	public function __construct() {
		$this->api_user_status = $this->get_api_user_status();

		/** 
		 * A user is being deleted. Check if it's the api user.
		 */
		add_action( 'delete_user', array( $this, 'check_if_api_user_deleted' ) );
		
		/**
		 * A user is being modified.
		 * Check if it's the API user and if anything important has been modified.
		 */
		add_action( 'edit_user_profile_update', array( $this, 'check_status_of_api_user' ), 50 );

		/**
		 * Create an Admin notice to state what's wrong with the user.
		 * 
		 * DELETED         = Ask to go to PW setup and reassign an API user.
		 * MISSING_KEYS    = Ask to generate API KEYS for user or go to PW setup and assign API user.
		 * LOW_PERMISSIONS = Reset permissions to read_write or go to PW setup and assign API user.
		 */
		if ( $this->api_user_status !== 'ACTIVE' ) {
			$user_id = get_option( '_wc_pricewaiter_api_user_id' );
			$user_info = get_userdata( $user_id );

			// Hide notices on PriceWaiter settings tab
			if ( !( isset( $_GET['tab'] ) && 'integration' === $_GET['tab'] ) ) {	
				switch ( $this->api_user_status ) {
					case 'DELETED':
						wc_pricewaiter()->notice_handler->add_notice( 
							'<h3>PriceWaiter is temporarily disabled.</h3>
							<p><strong>The user PriceWaiter associates with the WooCommerce REST API access appears to have been removed.</strong></p>
							<p>Please grant a user WooCommerce REST API access or select a user with access in the PriceWaiter Settings to continue selling more with PriceWaiter.</p>
							<p><a href="' . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . '" class="button-primary">
							' . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . '
							</a></p>',
							'error',
							'admin-user-deleted' );
						break;

					case 'MISSING_KEYS':
						wc_pricewaiter()->notice_handler->add_notice( 
							'<h3>PriceWaiter is temporarily disabled.</h3>
							<p>The user PriceWaiter associates with the WooCommerce REST API <strong>' . $user_info->user_login . '</strong> appears to have had the API Key revoked.</strong></p>
							<p>Please regenerate ' . $user_info->user_login . '\'s keys, or select a user in the PriceWaiter settings (<em>Reselecting the user <b>' . $user_info->user_login . '</b> will regenerate the keys for you.</em>)</p>
							<p><a href="' . add_query_arg( array( 'user_id' => $user_id ), admin_url( 'user-edit.php' ) ) . '" class="button-primary">Edit API User</a>
							<a href="' . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . '" class="button-secondary">
							' . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . '
							</a></p>',
							'error',
							'admin-user-missing-keys' );
						break;

					case 'LOW_PERMISSIONS':
						wc_pricewaiter()->notice_handler->add_notice( 
							'<h3>PriceWaiter is temporarily disabled.</h3>
							<p>The user PriceWaiter associates with the WooCommerce REST API <strong>' . $user_info->user_login . '</strong>, requires <em>read/write</em> permission.</strong></p>
							<p>Please update <strong>' . $user_info->user_login . '</strong>\'s API permissions, or select a user in PriceWaiter settings (<em>Reselecting the user <b>' . $user_info->user_login . '</b> will reset the API permissions for you.</em>)</p>
							<p><a href="' . add_query_arg( array( 'user_id' => $user_id ), admin_url( 'user-edit.php' ) ) . '" class="button-primary">Edit API User</a>
							<a href="' . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . '" class="button-secondary">
							' . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . '
							</a></p>',
							'error',
							'admin-user-low-permissions' );
						break;
				}
			}
		}
	}

	/**
	 * Returns known status of the api user
	 * 
	 * @return string, ACTIVE, DELETED, MISSING_KEYS, LOW_PERMISSIONS
	 */
	public static function get_api_user_status() {
		return get_option( '_wc_pricewaiter_api_user_status' );
	}

	public function check_if_api_user_deleted( $user_id ) {
		if ( $user_id == get_option( '_wc_pricewaiter_api_user_id' ) ) {
			update_option( '_wc_pricewaiter_api_user_status', 'DELETED' );
			delete_option( '_wc_pricewaiter_api_user_id' );
			$this->invalidate_pricewaiter_setup();
		}
	}

	/*
	 * Check that the proper settings still exist for the API user
	 * If anything is modified outside accepted options remove user
	 * from the PriceWaiter settings.
	 */
	public function check_status_of_api_user( $user_id ) {
		if ( $user_id == get_option( '_wc_pricewaiter_api_user_id' ) ) {
			$api_user = get_userdata( $user_id );
			$api_user_invalidated = false;
			$user_status = $this->get_api_user_status();
			$api_user_revalidated = false;


			if ( "ACTIVE" == $user_status ) {
				/**
				 * check if user api keys are missing,
				 * or invalid permissions
				 */
				if ( !$api_user->woocommerce_api_consumer_key || !$api_user->woocommerce_api_consumer_secret ) {
					update_option( '_wc_pricewaiter_api_user_status', 'MISSING_KEYS' );
					$api_user_invalidated = true;
				} else if ( 'read_write' !== $api_user->woocommerce_api_key_permissions ) {
					update_option( '_wc_pricewaiter_api_user_status', 'LOW_PERMISSIONS' );
					$api_user_invalidated = true;
				}

				/* invalidate if anything is wrong */
				if ( $api_user_invalidated ) {
					$this->invalidate_pricewaiter_setup();
				}
			}

			if ( "LOW_PERMISSIONS" == $user_status ) {
				if ( 'read_write' == $api_user->woocommerce_api_key_permissions ) {
					update_option( '_wc_pricewaiter_api_user_status', 'ACTIVE' );
					$this->revalidate_pricewaiter_setup();
				}
			}

			if ( "MISSING_KEYS" == $user_status ) {
				if ( $api_user->woocommerce_api_consumer_key || $api_user->woocommerce_api_consumer_secret ) {
					$api_user_revalidated = true;
				}
				if ( 'read_write' !== $api_user->woocommerce_api_key_permissions ) {
					update_option( '_wc_pricewaiter_api_user_status', 'LOW_PERMISSIONS' );
					$api_user_revalidated = false;
				}

				if ( $api_user_revalidated ) {
					$this->revalidate_pricewaiter_setup();
				}
			}

		}
	}

	/**
	 * Something wrong with API user, let's invalidate that option
	 * and flag setup as incomplete to prevent issues caused by invalid
	 * user settings.
	 */
	public function invalidate_pricewaiter_setup() {
		$existing_pw_settings = get_option( 'woocommerce_pricewaiter_settings' );
		$existing_pw_settings['setup_complete'] = false;
		update_option( 'woocommerce_pricewaiter_settings', $existing_pw_settings );
	}

	/**
	 * For when manual action was taken to resolve
	 * the user issues by updating the user profile.
	 */
	public function revalidate_pricewaiter_setup() {
		$existing_pw_settings = get_option( 'woocommerce_pricewaiter_settings' );
		$existing_pw_settings['setup_complete'] = true;
		update_option( 'woocommerce_pricewaiter_settings', $existing_pw_settings );
	}


}

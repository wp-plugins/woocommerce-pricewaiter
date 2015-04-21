<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists( 'WC_PriceWaiter_Integration' ) ):
/**
* New PriceWaiter Integration
*/
class WC_PriceWaiter_Integration extends WC_Integration {
	public function __construct() {
		global $woocommerce, $wc_pricewaiter;

		$this->id 					= 'pricewaiter';
		$this->method_title			= __( 'PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		$this->method_descrption	= __( 'Name your price through PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		$this->cost_plugin = array();
		$this->messages = array();

		/**
		* Check for any supported plugins to use for cost/margin data
		*/
		if( isset( $wc_pricewaiter->supported_cost_plugins ) ) {
			foreach ($wc_pricewaiter->supported_cost_plugins as $plugin => $meta_fields) {
				if( class_exists($plugin) ) {
					$this->cost_plugin = $meta_fields;
					break;
				}
			}
		}

		/**
		* If there are no cost plugins activated, create our own.
		* This sets up meta files similar to Cost Of Goods plugin.
		* Allows for easy migration to the COG plugin by preserving
		* meta fields that COG can use.
		* http://www.woothemes.com/products/cost-of-goods/
		*/
		if ( count($this->cost_plugin) <= 0 ) {
			/*
			* Default PriceWaiter cost fields
			*/
			$this->cost_plugin = array(
				'name'				=> 'PriceWaiter',
				'simple'			=> '_wc_cog_cost',
				'variable'			=> '_wc_cog_cost_variable',
				'variation'			=> 'variable_cost_of_good'
			);

			/**
			* Add cost text box to products
			*/
			add_action( 'woocommerce_product_options_pricing', array( $this, 'add_cost_field_to_simple_product' ) );
			add_action( 'woocommerce_product_options_sku', array( $this, 'add_cost_field_to_variable_product' ) );
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_cost_field_to_product_variation' ), 15, 3 );
			/**
			* Save cost text boxes on products
			*/
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_product_cost' ) );
			add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_variable_product_cost' ) );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_variation_cost' ) );
		}
		
		$this->init_form_fields();
		$this->init_settings();
		$this->check_pricewaiter_action();

		$this->api_key				= $this->get_option( 'api_key' );
		$this->debug 				= $this->get_option( 'debug' );

		// integration settings hooks
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_settings_api_sanitized_fields)'. $this->id, array( $this, 'sanitize_settings' ) );
		
		// add_action( 'admin_init', array( $this, 'check_pricewaiter_action' ) );
		
		/**
		*	Add PriceWaiter setting fields to products
		*/
		// add PriceWaiter Disable field to products under 'General' tab
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_pricewaiter_fields_to_product' ) );
		
		// save PriceWaiter fields on products
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_pricewaiter' ), 10, 2 );

		/**
		* Cross save cost meta values to PriceWaiter named meta keys
		* Meta keys depend on the active cost/meta plugin
		*/
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_product_cost_to_pricewaiter' ) );
		add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_variable_product_cost_to_pricewaiter' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_variation_cost_to_pricewaiter' ) );
		
		/**
		* Check Cost/Margin plugins for products and use their values
		*/
		// If API key is set, add the button, if not, notify Admin to add Key
		if( isset( $this->api_key ) && $this->api_key !== '' ){
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_pricewaiter_embed' ) );
		}else{
			add_action( 'admin_notices', array( $this, 'alert_admin_to_activate' ) );
		}
	}

	public function check_pricewaiter_action(){
		global $woocommerce;

		$pricewaiter_action = ( empty( $_REQUEST['pricewaiter_action'] ) ) ? null : sanitize_text_field( urldecode( $_REQUEST['pricewaiter_action'] ) );

		if ( $pricewaiter_action == 'clone_costs' ) {

			@set_time_limit( 0 ); // attempt to prevent timeouts if there are many products to update
			$update_count = 0;
			$query_offset = get_option( 'pricewaiter_cost_clone_offset', 0 );
			$posts_per_page = 500;

			do {
				$query_args = array(
					'post_type'			=> 'product',
					'posts_per_page'	=> $posts_per_page,
					'offset'			=> $query_offset
				);

				$product_ids = get_posts( $query_args );

				if( is_wp_error( $product_ids ) ) {
					$redirect_url = remove_query_arg( 'pricewaiter_action', stripslashes( $_SERVER['REQUEST_URI'] ) );
					wp_redirect($redirect_url);
					exit;
				}

				if( is_array( $product_ids ) ) {
					foreach ($product_ids as $product_id) {
						$pf = new WC_Product_Factory();
						$product = $pf->get_product( $product_id );
						if ( $product->is_type( 'simple' ) ) {
							update_post_meta( $product->id, '_wc_pricewaiter_cost', get_post_meta( $product->id, $this->cost_plugin['simple'], true ) );
						}elseif($product->is_type( 'variable' ) ){
							update_post_meta( $product->id, '_wc_pricewaiter_cost', get_post_meta( $product->id, $this->cost_plugin['variable'], true ) );
						}
						$update_count++;
					}
				}

				$query_offset += $posts_per_page;
				update_option( 'pricewaiter_cost_clone_offset', $query_offset );
			} while ( count( $product_ids ) == $posts_per_page );

			delete_option( 'pricewaiter_cost_clone_offset' );

			$redirect_url = add_query_arg('pw_cloned_costs', $update_count, remove_query_arg( 'pricewaiter_action', stripslashes( $_SERVER['REQUEST_URI'] ) ) );
			wp_redirect($redirect_url);
			exit;
		}
	}


	/**
	*
	*	TODO:	Create better messaging method.
	*			Look into woocommerce add_notice() errors.
	*			Consider how COG performs message queues.
	*
	*/
	public function alert_admin_to_activate() {
		?>
		<div class="update-nag">
			<p>
				<?php _e( 'Don&rsquo;t lose potential customers to the competition. Complete your PriceWaiter activation now.', WC_PriceWaiter::TEXT_DOMAIN ); ?>
			</p>
			<a href="https://pricewaiter.com" class="button-primary">
				<?php _e( 'Sign Up ', WC_PriceWaiter::TEXT_DOMAIN ); ?>
			</a>
			<a href="<?php echo add_query_arg( 'tab', 'integration', admin_url( 'admin.php?page=wc-settings' ) ); ?>" class="button-secondary">
				<?php _e( 'Add your API Key', WC_PriceWaiter::TEXT_DOMAIN ); ?>
			</a>
		</div>
		<?php
	}

	/**
	*	Add PriceWaiter global settings to settings > 'Integration' tab
	*/
	public function init_form_fields() {
		$this->form_fields = array(
			'api_key' => array(
				'title'				=> __( 'API Key', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'				=> 'text',
				'description'		=> __( 'Enter your PriceWaiter store API key. You can find this [continue with instructions...]', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'			=> true,
				'default'			=> ''
			),
			'customize_button' => array(
				'title'				=> __( 'Customize Button', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'				=> 'button',
				'custom_attributes' => array(
					'onclick'		=> "location.href='http://pricewaiter.com'"
				),
				'description'		=> __( 'Customize the PriceWaiter button by going to your PriceWaiter account', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'			=> true
			)
			// ,'debug'	=> array(
			// 	'title'				=> __( 'Debug Log', WC_PriceWaiter::TEXT_DOMAIN ),
			// 	'type'				=> 'checkbox',
			// 	'label'				=> __( 'Enable Debug Log', WC_PriceWaiter::TEXT_DOMAIN ),
			// 	'description'		=> __( 'Enable logging of debug data', WC_PriceWaiter::TEXT_DOMAIN )
			// )
		);
		if( count( $this->cost_plugin ) > 0 && $this->cost_plugin['name'] !== "PriceWaiter" ) {
			$this->form_fields['clone_costs'] = array(
				'title'				=> __( 'Clone Product Cost Data', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'				=> 'clone_costs_button',
				'class'				=> 'button-secondary',
				'description'		=> sprintf( __( 'It looks like you have the plugin %1$s installed.<br />%1$s supports product cost or margin data that PriceWaiter can use for enhanced features.<br />Clicking "Clone Costs" will clone the product cost/margin data in a format PriceWaiter can understand.<br />(This action only needs to be performed once.)', WC_PriceWaiter::TEXT_DOMAIN ), $this->cost_plugin['name'] ),
				'id'				=> 'wc_pricewaiter_clone_costs_from_plugin',
				'button_text'		=> __( 'Clone Costs', WC_PriceWaiter::TEXT_DOMAIN )
			);
		}
	}

	public function generate_clone_costs_button_html( $key, $data ){
		$field = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class' 				=> 'button-secondary',
			'css'					=> '',
			'custom_attributes' 	=> array(),
			'desc_tip' 				=> false,
			'description' 			=> '',
			'title'					=> ''
		);

		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo esc_attr( $field ) ?>"><?php echo wp_kses_post( $data['title'] ) ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<a href="<?php echo add_query_arg( 'pricewaiter_action', 'clone_costs' ); ?>" class="<?php echo esc_attr( $data['class'] ); ?>"><?php echo esc_html( $data['button_text'] ) ?></a>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>

		<?php 
		return ob_get_clean();
	}

	/*
	*	Customize appearance of button
	*/
	public function generate_button_html( $key, $data ) {
		$field = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class' 				=> 'button-secondary',
			'css'					=> '',
			'custom_attributes' 	=> array(),
			'desc_tip' 				=> false,
			'description' 			=> '',
			'title'					=> ''
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/*
	*	Sanitize Settings
	*	Currently nothing to sanitize
	*/
	public function sanitize_settings( $settings ) {
		return $settings;
	}
	
	/*
	*	Validate api key is set
	*/
	public function validate_api_key_field( $key ) {
		$value = $_POST[$this->plugin_id . $this->id . '_' . $key];

		// Verify API Key
		if( isset( $value ) && strlen( $value ) == 0 ) {
			$this->errors[] = $key;
		}

		return $value;
	}

	/*
	*	Display errors by overriding display_errors();
	*/
	public function display_errors(){
		foreach ( $this->errors as $key => $value ) {
			?>
			<div class="error">
				<p><?php _e( 'Looks like you made a mistake with the '. $value . ' field. Please make sure it is valid.', WC_PriceWaiter::TEXT_DOMAIN ) ?></p>
			</div>
			<?php
		}
	}

	/**
	* 	Create checkbox on variable product in the 'General' tab
	*	to toggle the PriceWaiter button on the product page
	*/
	public function add_pricewaiter_fields_to_product() {
		woocommerce_wp_checkbox(
			array(
				'id'				=> '_wc_pricewaiter_disabled',
				'class'				=> 'checkbox',
				'wrapper_class'		=> 'show_if_variable show_if_simple',
				'label'				=> __( 'Disable PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'		=> __( 'Hide PriceWaiter button for this product', WC_PriceWaiter::TEXT_DOMAIN ),

			)
		);	
	}
	/**
	 *	Save Disable PriceWaiter value for product
	 */
	public function save_product_pricewaiter( $post_id ) {
		// Update checkbox for disabling PriceWaiter button
		$checkbox_disabled = isset( $_POST['_wc_pricewaiter_disabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wc_pricewaiter_disabled', $checkbox_disabled );
	}


	/**
	* PriceWaiter 'cost' fields emulate Cost Of Goods meta fields for ease
	* of transition to that specific plugin.
	*/

	/**
	* Add Cost field to simple product's 'General' tab
	*/
	public function add_cost_field_to_simple_product() {
		woocommerce_wp_text_input(
			array(
				'id'                => '_wc_cog_cost',
				'class'             => 'wc_input_price short',
				'label'             => sprintf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), get_woocommerce_currency_symbol() ),
				'data_type'         => 'price',
			)
		);
	}
	/**
	* Add Cost field to variable product's 'General' tab
	*/
	public function add_cost_field_to_variable_product() {
		woocommerce_wp_text_input(
			array(
				'id'                => '_wc_cog_cost_variable',
				'class'             => 'wc_input_price short',
				'wrapper_class'     => 'show_if_variable',
				'label'             => sprintf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), get_woocommerce_currency_symbol() ),
				'data_type'         => 'price',
				'desc_tip'          => true,
				'description'       => __( 'Default cost for product variations', WC_PriceWaiter::TEXT_DOMAIN ),
			)
		);		
	}
	/**
	* Add cost field to product variations under 'variations' tab for each.
	*/
	public function add_cost_field_to_product_variation( $loop, $variation_data, $variation ) {
		$default_cost = get_post_meta( $variation->post_parent, '_wc_cog_cost_variable', true );
		$cost = ( isset( $variation_data['_wc_cog_cost'][0] ) ) ? $variation_data['_wc_cog_cost'][0] : '';
		if ( isset( $variation_data['_wc_cog_default_cost'][0] ) && $variation_data['_wc_cog_default_cost'][0] == 'yes' ) {
			$cost = '';
		}
		?>
			<tr>
				<td>
					<label><?php  printf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), esc_html( get_woocommerce_currency_symbol() ) ); ?></label>
					<input type="text" size="6" name="variable_cost_of_good[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( $cost ); ?>" class="wc_input_price" placeholder="<?php echo esc_attr( $default_cost ); ?>" >
				</td>
				<td>&nbsp;</td>
			</tr>

		<?php
	}

	/**
	* No cost/margin plugin: save custom cost data.
	*/
	public function save_simple_product_cost( $post_id ) {
		$product_type = empty( $_POST['product-type'] ) ? 'simple' : sanitize_title( stripslashes( $_POST['product-type'] ) );
		if ( $product_type !== 'variable' ) {
			update_post_meta( $post_id, '_wc_cog_cost', stripcslashes( $_POST['_wc_cog_cost'] ) );
		}
	}
	public function save_variable_product_cost( $post_id ) {
		$default_cost = stripcslashes( $_POST['_wc_cog_cost_variable'] );
		update_post_meta( $post_id, '_wc_cog_cost_variable', $default_cost );

	}
	public function save_product_variation_cost( $variation_id ) {
		$default_cost = stripcslashes( $_POST['_wc_cog_cost_variable'] );

		if ( ($i = array_search( $variation_id, $_POST['variable_post_id'] ) ) !== false ) {
			$cost = $_POST['variable_cost_of_good'][$i];

			if ( $cost !== '' ) {
				update_post_meta( $variation_id, '_wc_cog_cost', $cost );
				update_post_meta( $variation_id, '_wc_cog_default_cost', 'no' );
			} else {
				if( $default_cost ) {
					update_post_meta( $variation_id, '_wc_cog_cost', $default_cost );
					update_post_meta( $variation_id, '_wc_cog_default_cost', 'yes' );
				}else{
					update_post_meta( $variation_id, '_wc_cog_cost', '' );
					update_post_meta( $variation_id, '_wc_cog_default_cost', 'no' );
				}
			}
		}
	}

	/**
	* Clone cost meta to PriceWaiter standardized keys
	*/
	public function save_simple_product_cost_to_pricewaiter( $post_id ) {
		$product_type = empty( $_POST['product-type'] ) ? 'simple' : sanitize_title( stripslashes( $_POST['product-type'] ) );
		if ( $product_type !== 'variable' ) {
			update_post_meta( $post_id, '_wc_pricewaiter_cost', stripcslashes( $_POST[$this->cost_plugin['simple']] ) );
		}
	}
	public function save_variable_product_cost_to_pricewaiter( $post_id ) {
		$default_cost = stripcslashes( $_POST[$this->cost_plugin['variable']] );
		update_post_meta( $post_id, '_wc_pricewaiter_cost', $default_cost );
	}
	public function save_product_variation_cost_to_pricewaiter( $variation_id ) {
		$default_cost = stripcslashes( $_POST[$this->cost_plugin['variable']] );

		if ( ($i = array_search( $variation_id, $_POST['variable_post_id'] ) ) !== false ) {
			$cost = $_POST[$this->cost_plugin['variation']][$i];

			if ( $cost !== '' ) {
				update_post_meta( $variation_id, '_wc_pricewaiter_cost', $cost );
			} else {
				if( $default_cost ) {
					update_post_meta( $variation_id, '_wc_pricewaiter_cost', $default_cost );
				}else{
					update_post_meta( $variation_id, '_wc_pricewaiter_cost', '' );
				}
			}
		}
	}


	/**
	*	Add PriceWaiter Widget to page
	*/
	public function add_pricewaiter_embed() {
		new WC_PriceWaiter_Embed($this->api_key);
	}
}
endif;
<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists( 'WC_PriceWaiter_Integration' ) ):
/**
* New PriceWaiter Integration
*/
class WC_PriceWaiter_Integration extends WC_Integration {
	public function __construct() {
		global $woocommerce, $wc_pricewaiter;

		$this->id					= 'pricewaiter';
		$this->method_title			= __( 'PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		$this->method_descrption	= __( 'Name your price through PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		$this->cost_plugin			= array();
		$this->messages				= array();

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
				'simple'			=> '_wc_cog_cost',
				'variable'			=> '_wc_cog_cost_variable',
				'variation'			=> '_wc_cog_cost'
			);

			$this->cost_plugin = apply_filters( 'wc_pricewaiter_default_cost_pluign', $this->cost_plugin );

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

		$this->api_key				= $this->get_option( 'api_key' );
		$this->debug				= $this->get_option( 'debug' );
		$this->cost_plugin			= apply_filters( 'wc_pricewaiter_cost_plugin_fields', $this->cost_plugin, $wc_pricewaiter->supported_cost_plugins );

		// integration settings hooks
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_settings_api_sanitized_fields)'. $this->id, array( $this, 'sanitize_settings' ) );
		
		/**
		*	Add PriceWaiter setting fields to products
		*/
		// add PriceWaiter Disable field to products under 'General' tab
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_pricewaiter_fields_to_product' ) );
		
		// save PriceWaiter fields on products
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_pricewaiter' ), 10, 2 );
		
		/**
		* Check Cost/Margin plugins for products and use their values
		*/
		// If API key is set, add the button, if not, notify Admin to add Key
		if( isset( $_POST['woocommerce_pricewaiter_api_key'] ) && $_POST['woocommerce_pricewaiter_api_key'] ) {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_pricewaiter_embed' ) );
		}else if( $this->api_key == "" ) {
			add_action( 'admin_notices', array( $this, 'alert_admin_to_activate' ) );
		}

		/**
		* Filter API get_product() to inject pricewaiter data
		*/
		add_filter( 'woocommerce_api_product_response', array( $this, 'wc_product_api_inject_pricewaiter'), 10, 4 );
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
				<?php _e( 'Don&rsquo;t lose potential customers to the competition. Complete your PriceWaiter configuration now.', WC_PriceWaiter::TEXT_DOMAIN ); ?>
			</p>
			<a href="<?php echo add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ); ?>" class="button-primary">
				<?php _e( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ); ?>
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
	}

	/*
	*	Customize appearance of button
	*/
	public function generate_button_html( $key, $data ) {
		$field = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'					=> 'button-secondary',
			'css'					=> '',
			'custom_attributes'		=> array(),
			'desc_tip'				=> false,
			'description'			=> '',
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
		global $wc_pricewaiter;

		$wrapper_class_string = '';
		foreach ($wc_pricewaiter->supported_product_types as $type) {
			$wrapper_class_string .= 'show_if_' . $type . ' ';
		}
		woocommerce_wp_checkbox(
			array(
				'id'				=> '_wc_pricewaiter_disabled',
				'class'				=> 'checkbox',
				'wrapper_class'		=> $wrapper_class_string,
				'label'				=> __( 'Disable PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'		=> __( 'Hide PriceWaiter button for this product', WC_PriceWaiter::TEXT_DOMAIN ),

			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'				=> '_wc_pricewaiter_conversion_tools_disabled',
				'class'				=> 'checkbox',
				'wrapper_class'		=> $wrapper_class_string,
				'label'				=> __( 'Disable Conversion Tools', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'		=> __( 'Turns off conversion tools set in your PricecWaiter dashboard', WC_PriceWaiter::TEXT_DOMAIN ),
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
		
		// Update checkbox for enabled Conversion Tools
		$checkbox_conversion = isset( $_POST['_wc_pricewaiter_conversion_tools_disabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wc_pricewaiter_conversion_tools_disabled', $checkbox_conversion );
	}

	/**
	* PriceWaiter 'cost' fields emulate Cost Of Goods meta fields for ease
	* of transition to that specific plugin.
	*/

	/**
	* Add Cost field to simple product's 'General' tab
	*/
	public function add_cost_field_to_simple_product() {
		global $wc_pricewaiter;

		$wrapper_class_string = '';

		foreach ($wc_pricewaiter->supported_product_types as $type) {
			$wrapper_class_string .= 'show_if_' . $type . ' ';
		}

		woocommerce_wp_text_input(
			array(
				'id'				=> '_wc_cog_cost',
				'class'				=> 'wc_input_price short',
				'wrapper_class'		=> $wrapper_class_string,
				'label'				=> sprintf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), get_woocommerce_currency_symbol() ),
				'data_type'			=> 'price',
			)
		);
	}

	/**
	* Add Cost field to variable product's 'General' tab
	*/
	public function add_cost_field_to_variable_product() {
		woocommerce_wp_text_input(
			array(
				'id'				=> '_wc_cog_cost_variable',
				'class'				=> 'wc_input_price short',
				'wrapper_class'		=> 'show_if_variable',
				'label'				=> sprintf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), get_woocommerce_currency_symbol() ),
				'data_type'			=> 'price',
				'desc_tip'			=> true,
				'description'		=> __( 'Default cost for product variations', WC_PriceWaiter::TEXT_DOMAIN ),
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
	* Inject pricewaiter data into product api response
	*/
	public function wc_product_api_inject_pricewaiter( $product_data, $product, $fields, $server ) {
		global $wc_pricewaiter;

		if ( !in_array( $product->product_type, $wc_pricewaiter->supported_product_types ) ) {
			$product_data['pricewaiter'] = false;
			return $product_data;
		}

		$cost_field = isset( $this->cost_plugin[$product->product_type] ) ? $product->product_type : 'simple';
		$product_data['pricewaiter'] = array(
			'cost'				=> get_post_meta( $product->id, $this->cost_plugin[$cost_field], true ),
			'button'			=> get_post_meta( $product->id, '_wc_pricewaiter_disabled', true ) == 'yes' ? false : true,
			'conversion_tools'	=> get_post_meta( $product->id, '_wc_pricewaiter_conversion_tools_disabled', true ) == 'yes' ? false : true
		);

		return $product_data;
	}

	/**
	*	Add PriceWaiter Widget to page
	*/
	public function add_pricewaiter_embed() {
		new WC_PriceWaiter_Embed($this->api_key);
	}
}

endif;

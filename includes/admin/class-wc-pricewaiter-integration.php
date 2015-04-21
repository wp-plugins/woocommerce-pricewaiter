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
		// $this->cost_plugin			= array();
		$this->messages				= array();
		
		$this->init_form_fields();
		$this->init_settings();

		$this->api_key				= $this->get_option( 'api_key' );
		$this->debug				= $this->get_option( 'debug' );

		// integration settings hooks
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_settings_api_sanitized_fields)'. $this->id, array( $this, 'sanitize_settings' ) );
		
		/**
		* Check Cost/Margin plugins for products and use their values
		*/
		// If API key is set, add the button, if not, notify Admin to add Key
		if( isset( $_POST['woocommerce_pricewaiter_api_key'] ) && $_POST['woocommerce_pricewaiter_api_key'] ) {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_pricewaiter_embed' ) );
		}else if( $this->api_key == "" ) {
			add_action( 'admin_notices', array( $this, 'alert_admin_to_activate' ) );
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
	*	Add PriceWaiter Widget to page
	*/
	public function add_pricewaiter_embed() {
		new WC_PriceWaiter_Embed($this->api_key);
	}
}

endif;

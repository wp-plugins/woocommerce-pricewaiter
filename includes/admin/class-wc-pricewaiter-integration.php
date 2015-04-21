<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists( 'WC_PriceWaiter_Integration' ) ):
/**
* New PriceWaiter Integration
*/
class WC_PriceWaiter_Integration extends WC_Integration {
	public function __construct() {
		global $woocommerce;

		$this->id					= 'pricewaiter';
		$this->method_title			= __( 'PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		$this->method_descrption	= __( 'Name your price through PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		// $this->cost_plugin			= array();
		$this->messages				= array();
		
		$this->init_form_fields();
		$this->init_settings();

		$this->api_key				= $this->get_option( 'api_key' );
		$this->setup_complete 		= $this->get_option( 'setup_complete' );
		$this->debug				= $this->get_option( 'debug' );

		// integration settings hooks
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_settings_api_sanitized_fields_'. $this->id, array( $this, 'sanitize_settings' ) );
		
		if( !$this->setup_complete ) {
			add_action( 'admin_notices', array( $this, 'alert_admin_to_configure' ) );
		}
	}

	/**
	*
	*	TODO:	Create better messaging method.
	*			Look into woocommerce add_notice() errors.
	*			Consider how COG performs message queues.
	*
	*/
	public function alert_admin_to_configure() {

		// Don't nag if we're on the integrations tab
		if (isset($_GET['tab']) && 'integration' === $_GET['tab']) {
			return;
		}
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
				'description'		=> __( 'Enter your PriceWaiter store API key.', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'			=> true,
				'default'			=> ''
			),
			'customize_button' => array(
				'title'				=> __( 'Customize Button', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'				=> 'button',
				'custom_attributes' => array(
					'onclick'		=> "location.href='http://pricewaiter.com'"
				),
				'description'		=> __( 'Customize the PriceWaiter button by going to your PriceWaiter account.', WC_PriceWaiter::TEXT_DOMAIN ),
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

	/**
	* Display content above the admin options fields
	*/
	public function admin_options() {
	?>
		<h2><?php echo $this->method_title; ?></h2>
		<?php WC_PriceWaiter_Integration_Helpers::load_setup_screen(); ?>
		<table class="form-table <?php if (!WC_PriceWaiter_Integration_Helpers::has_configured('wc_api_user')) : ?>wc_pricewaiter_setup_defaults<?php endif; ?>">
		<?php $this->generate_settings_html(); ?>
		</table> <?php
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
	*	And validate complete setup
	*/
	public function sanitize_settings( $settings ) {
		$completed = true;
		if( isset( $settings ) ) {
			// check if required setup is completed
			foreach ($settings as $setting => $value) {
				switch ( $setting ) {
					case 'api_key':
						if( empty( $value ) ){
							$completed = false;
						}
						break;
				}
			}
		}
		$settings['setup_complete'] = $completed;

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
}

endif;

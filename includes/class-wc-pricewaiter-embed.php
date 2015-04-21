<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

if (!class_exists( 'WC_PriceWaiter_Embed' ) ):

	class WC_PriceWaiter_Embed {
		public function __construct(){
			global $prouct, $woocommerce, $current_user;

			$this->api_key = wc_pricewaiter()->get_pricewaiter_setting( 'api_key' );

			if ($this->can_embed()) {

				// PriceWaiterOptions and button
				$this->embed_code();

				/**
				* Hook widget script include into the footer for best site performance
				* - wp_enqueue_script doesn't allow async attribute
				* - allow developers to customize this priority
				*/
				add_action( 'wp_footer', array( $this, 'widget_scripts' ), apply_filters( 'wc_pricewaiter_widget_script_priority', 10) );
			}
		}

		/**
		* Whether or not to output any PW code
		*/
		public function can_embed() {
			global $product;

			// Only allow on single pages for now.
			if (is_single() && in_array( $product->product_type, wc_pricewaiter()->supported_product_types ) ) {
				return true;
			}

			return false;
		}
		
		public function embed_code() {
			global $product, $woocommerce, $current_user;

			if ( $product->is_type( 'variable' ) ) {
				// sets default product data to the first variation.
				$children = $product->get_children();
				$first_child = $product->get_child( $children[0] );
				$product_data = WC_PriceWaiter_Product::get_data( $first_child );
				$product_meta = WC_PriceWaiter_Product::get_meta( $first_child );
				$variation_data = array();
				$variation_meta = array();
				foreach ( $product->get_children() as $key => $value ) {
					$variation_id = $product->get_child( $value );
					$variation_data[$value] = WC_PriceWaiter_Product::get_data( $variation_id );
					$variation_meta[$value] = WC_PriceWaiter_Product::get_meta( $variation_id );
				}
			} else {
				$product_data = WC_PriceWaiter_Product::get_data( $product );
				$product_meta = WC_PriceWaiter_Product::get_meta( $product );
			}

			do_action( 'wc_pricewaiter_before_button' );
			?>
			<div id="pricewaiter_button_wrap" class="pricewaiter_button_wrap">
				<span id="pricewaiter"></span>
			</div>
			<script>
				var PriceWaiterOptions = {};
				(function(document, window, $, undefined){
					<?php if ( $product->is_type( 'variable' ) ): ?>
					$('#pricewaiter_button_wrap').appendTo('.single_variation_wrap .variations_button');
					var variation_data = <?php echo json_encode( $variation_data ); ?>;
					var variation_meta = <?php echo json_encode( $variation_meta ); ?>;
					<?php endif; ?>
				
					PriceWaiterOptions.product 		= <?php echo json_encode( $product_data ); ?>;
					PriceWaiterOptions.currency 	= '<?php echo get_woocommerce_currency(); ?>';
					PriceWaiterOptions.addToPage	= <?php echo WC_PriceWaiter_Product::can_add_pricewaiter($product); ?>;
					PriceWaiterOptions.exit			= <?php echo WC_PriceWaiter_Product::is_using_conversion_tools($product); ?>;
					PriceWaiterOptions.user = {
						email: 	'<?php echo $current_user->user_email; ?>',
						name: 	'<?php echo esc_attr($current_user->user_firstname) . " " . esc_attr($current_user->user_lastname); ?>'
					};
					PriceWaiterOptions.hide_quantity_field = <?php echo $product->is_sold_individually() ? 'true' : 'false'; ?>;
					PriceWaiterOptions.metadata = {
					<?php 
						foreach ( $product_meta as $key => $value ) {
							echo '"' . $key . '": "' . $value . '",';
						}
					 ?>
					};
					PriceWaiterOptions.onButtonClick = function() {
						var cart_data = $('.cart').serializeArray();

						$.each(cart_data, function(k,v) {
							if (v.name == 'quantity') {
								PriceWaiter.setQuantity(parseInt(v.value,10));
							}
							if ( typeof variation_data !== 'undefined' ) {
								if (v.name.indexOf('attribute_') != -1) {
									PriceWaiter.setProductOption(v.name.replace('attribute_', ''), v.value);
								}
								if (v.name == 'variation_id') {
									var variation = variation_data[v.value.toString()];
									var variation_m = variation_meta[v.value.toString()];
									PriceWaiter.setSKU(variation.sku);
									PriceWaiter.setProduct(variation.name);
									PriceWaiter.setRegularPrice(variation.regular_price);
									PriceWaiter.setPrice(variation.price);
									PriceWaiter.setProductImage(variation.image);
									$.each( variation_m, function(mk,mv) {
										PriceWaiter.setMetadata(mk,mv);
									} );
								}
							}
						});
					};
				})(document, window, jQuery);
			</script>
			<?php
			do_action( 'wc_pricewaiter_after_button' );
		}

		public function widget_scripts() {
			$widget_script_url = apply_filters( 'wc_pricewaiter_widget_script_url', 'https://widget.pricewaiter.com/script/' . $this->api_key .'.js' );
			echo '<script src="' . $widget_script_url . '" async></script>';
		}

		public function add_button() {

		}

		public function get_user_info() {

		}
	}

endif;

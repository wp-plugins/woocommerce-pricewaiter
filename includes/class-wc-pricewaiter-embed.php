<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
if (!class_exists( 'WC_PriceWaiter_Embed' ) ):

	class WC_PriceWaiter_Embed {
		public function __construct($api_key){
			global $prouct, $woocommerce, $current_user;
			$this->api_key = $api_key;
			$this->pw_embed_code();
		}
		
		public function pw_embed_code() {
			global $product, $woocommerce, $current_user;

			if ( $product->is_type( 'variable' ) ) {
				// sets default product data to the first variation.
				$product_data = WC_PriceWaiter_Product::get_data( $product->get_child( $product->get_children()[0] ) );
				$variation_data = array();
				foreach ( $product->get_children() as $key => $value ) {
					$variation_id = $product->get_child( $value );
					$variation_data[$value] = WC_PriceWaiter_Product::get_data( $variation_id );
				}
			}else{
				$product_data = WC_PriceWaiter_Product::get_data( $product );
			}

			do_action( 'woocommerce-pricewaiter-before-button' );
			?>
			<span id="pricewaiter"></span>
			<script>
				var PriceWaiterOptions = {};
				(function(document, window, $, undefined){
					<?php echo 'console.log('.get_post_meta( $product->id, '_wc_pricewaiter_cost', true).');'; ?>
					<?php if ( $product->has_child() ): ?>
					$('#pricewaiter').appendTo('.single_variation_wrap');
					var variation_data = <?php echo json_encode( $variation_data ); ?>;
					
					<?php endif; ?>
				
					PriceWaiterOptions.product 		= <?php echo json_encode( $product_data ); ?>;
					PriceWaiterOptions.currency 	= '<?php echo get_woocommerce_currency(); ?>';
					PriceWaiterOptions.addToPage 	= <?php echo WC_PriceWaiter_Product::can_add_pricewaiter($product); ?>;
					PriceWaiterOptions.user = {
						email: 	'<?php echo $current_user->user_email; ?>',
						name: 	'<?php echo $current_user->user_firstname . " " . $current_user->user_lastname; ?>'
					};
					PriceWaiterOptions.hide_quantity_field = <?php echo $product->is_sold_individually() ? 'true' : 'false'; ?>;
					PriceWaiterOptions.onButtonClick = function() {
						var cart_data = $('.cart').serializeArray();
						console.dir(cart_data);
						$.each(cart_data, function(k,v) {
							if (v.name == 'quantity') {
								PriceWaiter.setQuantity(parseInt(v.value,10));
							}
							<?php if ( $product->is_type( 'variable' ) ): ?>
							if (v.name.indexOf('attribute_') != -1) {
								PriceWaiter.setProductOption(v.name.replace('attribute_', ''), v.value);
							}
							if (v.name == 'variation_id') {
								var variation = variation_data[v.value.toString()];
								PriceWaiter.setSKU(variation.sku);
								PriceWaiter.setProduct(variation.name);
								PriceWaiter.setRegularPrice(variation.regular_price);
								PriceWaiter.setPrice(variation.price);
								PriceWaiter.setProductImage(variation.image);
							}
							<?php endif; ?>

						});
					};
				})(document, window, jQuery);
			</script>
			<script src="https://widget.pricewaiter.com/script/<?php echo $this->api_key; ?>.js" async></script>
			<?php
			do_action( 'woocommerce-pricewaiter-after-button' );

		}

		public function pw_add_button() {

		}

		public function pw_get_user_info() {

		}
	}

endif;
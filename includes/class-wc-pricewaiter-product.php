<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! class_exists( 'WC_PriceWaiter_Product' ) ):
	
class WC_PriceWaiter_Product {

	/**
	* Retrieves basic product data for PriceWaiter
	* @param WC_Product|int product object or product id
	* @return associate array of product data formatted for PriceWaiter
	*/
	public static function get_data( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

		return array(
			'sku' 				=> $product->get_sku() ? $product->get_sku() : $product->id,
			'name' 				=> $product->get_title(),
			'description'		=> $product->post->post_excerpt,
			'regular_price'		=> $product->get_regular_price(),
			'price' 			=> $product->get_price(),
			'image'				=> has_post_thumbnail( $product->id ) ? wp_get_attachment_url( $product->get_image_id() ) : ''
		);
	}
	
	/**
	* Gets select product meta to be passed to PriceWaiter
	* @param WC_Product|int product object or product id
	* @return associative array of meta attributes and values
	*/
	public static function get_meta( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	}
		
	/**
	* Checks various if product is purchasable and if PriceWaiter is enabled
	* @param WC_Product|int product object or product id
	* @return bool
	*/
	public static function can_add_pricewaiter( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if( $product->is_type( 'variable' ) ) {
			$pricewaiter_disabled_metakey = '_wc_pricewaiter_disabled_variable';
		}else {
			$pricewaiter_disabled_metakey = '_wc_pricewaiter_disabled';
		}

		$pricewaiter_disabled = get_post_meta( $product->id, $pricewaiter_disabled_metakey, true ) == 'yes' ? true : false;
		
		return $product->is_purchasable() && !$pricewaiter_disabled ? true : false;
	}
}

endif;
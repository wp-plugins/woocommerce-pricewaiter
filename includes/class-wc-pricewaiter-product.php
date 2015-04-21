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
			'sku' 				=> $product->id,
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

		$metadata = array(
			'sku'				=> $product->get_sku()
		);

		return apply_filters( 'wc_pricewaiter_product_metadata', $metadata, $product );
	}
		
	/**
	* Checks various if product is purchasable and if PriceWaiter is enabled
	* @param WC_Product|int product object or product id
	* @return bool
	*/
	public static function can_add_pricewaiter( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

		$pricewaiter_disabled = get_post_meta( $product->id, '_wc_pricewaiter_disabled', true ) == 'yes' ? true : false;

		// Allow override on whether PriceWaiter can be used for the current product
		$pricewaiter_disabled = apply_filters( 'wc_pricewaiter_product_disable', $pricewaiter_disabled, $product );
		
		return $product->is_purchasable() && !$pricewaiter_disabled ? 'true' : 'false';
	}
	/**
	* Checks if current product has Conversion Tools Enabled
	* @param WC_Product|int product object or product id
	* @return bool
	*/
	public static function is_using_conversion_tools( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

		$conversion_tools_enabled = get_post_meta( $product->id, '_wc_pricewaiter_conversion_tools_disable', true ) == 'yes' ? false : true;

		// Allow override on whether PriceWaiter is using Conversion Tools for the current product
		$conversion_tools_enabled = apply_filters( 'wc_pricewaiter_product_conversion_tools', $conversion_tools_enabled, $product );

		return $conversion_tools_enabled ? 'true' : 'false';
	}
}

endif;
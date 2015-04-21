<?php
/**
 * PriceWaiter API IPN Class
 *
 * Handles requests to the ipn endpoint
 *
 * TODO:
 * - Version check woo for >= 2.2
 * - Check that order is not a duplicate w/ pw order meta
 *
 * @author      Sole Graphics
 * @category    API
 * @package     WooCommerce/API
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_PriceWaiter_API_Ipn {

    /** @var string $endpoint the IPN endpoint */
    protected $endpoint = 'https://api.pricewaiter.com/order/verify';

    /**
     * Setup class
     */
    public function __construct() {

        // Actions
        add_action( 'pricewaiter_ipn_success', array( $this, 'successful_request' ) );

        // Payment listener/API hook
        add_action( 'woocommerce_pricewaiter_api_ipn', array( $this, 'check_ipn_response' ) );

    }

    /**
     * Check PayPal IPN validity
     **/
    public function check_ipn_request_is_valid( $ipn_response ) {

        // WARNING!!! REMOVE ME - Testing only
        $this->endpoint = 'http://woo.pricewaiter.dev/ipntest.php';

        // Get received values from post data
        $validate_ipn = stripslashes_deep( $ipn_response );

        // Send back post vars to paypal
        $params = array(
            'body'          => $validate_ipn,
            'sslverify'     => false,
            'timeout'       => 60,
            'httpversion'   => '1.1',
            'compress'      => false,
            'decompress'    => false,
            'user-agent'    => 'WooCommerce/' . WC()->version
        );

        // Post back to get a response
        $response = wp_remote_post( $this->endpoint, $params );

        // check to see if the request was valid
        if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && $response['body'] == '1' ) {
            return true;
        }

        return false;
    }

    /**
     * Check for PriceWaiter IPN Response
     */
    public function check_ipn_response() {

        @ob_clean();

        $ipn_response = ! empty( $_POST ) ? $_POST : false;

        if ( $ipn_response && $this->check_ipn_request_is_valid( $ipn_response ) ) {

            header( 'HTTP/1.1 200 OK' );

            do_action( "pricewaiter_ipn_success", $ipn_response );

        } else {

            wp_die( "PriceWaiter IPN Request Failure", "PriceWaiter IPN", array( 'response' => 200 ) );

        }

    }

    /**
     * Successful Payment via PriceWaiter!
     *
     * @param array $posted
     */
    public function successful_request( $posted ) {

        $posted = stripslashes_deep( $posted );

        // Custom holds post ID
        if ( ! empty( $posted['pricewaiter_id'] ) && ! empty( $posted['api_key'] ) ) {

            // Check if this order was already created.

            // Check if the customer has an account
            $customer = get_user_by( 'email', $posted['buyer_email'] );

            // Create the order
            $address_billing = array(
                'first_name' => $posted['buyer_billing_first_name'],
                'last_name'  => $posted['buyer_billing_last_name'],
                //'company'    => 'WooThemes',
                'email'      => $posted['buyer_email'],
                'phone'      => $posted['buyer_billing_phone'],
                'address_1'  => $posted['buyer_billing_address'],
                'address_2'  => $posted['buyer_billing_address2'] . ' ' . $posted['buyer_billing_address3'], 
                'city'       => $posted['buyer_billing_city'],
                'state'      => $posted['buyer_billing_state'],
                'postcode'   => $posted['buyer_billing_zip'],
                'country'    => $posted['buyer_billing_country']
            );

            $address_shipping = array(
                'first_name' => $posted['buyer_shipping_first_name'],
                'last_name'  => $posted['buyer_shipping_last_name'],
                //'company'    => 'WooThemes',
                //'email'      => $posted['buyer_email'],
                'phone'      => $posted['buyer_shipping_phone'],
                'address_1'  => $posted['buyer_shipping_address'],
                'address_2'  => $posted['buyer_shipping_address2'] . ' ' . $posted['buyer_shipping_address3'], 
                'city'       => $posted['buyer_shipping_city'],
                'state'      => $posted['buyer_shipping_state'],
                'postcode'   => $posted['buyer_shipping_zip'],
                'country'    => $posted['buyer_shipping_country']
            );


            // Create the order record
            $order_args = array(
                'status'        => 'processing', // '' (is default)
                'customer_id'   => ($customer === false) ? null : $customer->ID
            );

            $order = wc_create_order($order_args);

            // Handle product line items
            $product = get_product( $posted['product_sku'] );
            $quantity = $posted['quantity'];


            // Variable Products
            $variant_attributes = array();
            if ( $product->variation_id ) {
                $variant = new WC_Product_Variation( $posted['product_sku'] );
                $variant_attributes = $variant->get_variation_attributes();
            }
            

            $product_args = array(
                'totals' => array(
                    'subtotal' => $product->get_price_excluding_tax( $quantity, $posted['unit_price'] ),
                    'total' => $product->get_price_excluding_tax( $quantity, $posted['unit_price'] ),
                    'subtotal_tax' => $posted['tax'],
                    'tax' => $posted['tax'],
                    'tax_data' => array(
                        'total' => $posted['tax'],
                        'subtotal' => $posted['tax']
                    )
                ),
                'variation' => $variant_attributes
            );

            $order->add_product( $product, $posted['quantity'], $product_args ); // $product, $qty = 1, $args = array()
            

            // Handle customer data
            $order->set_address( $address_billing, 'billing' );
            $order->set_address( $address_shipping, 'shipping' );


            // Handle totals rows
            // - 'tax' seems to be buggy.
            // - Available types - 'shipping', 'order_discount', 'tax', 'shipping_tax', 'total', 'cart_discount'

            // $order->set_total( $posted['tax'], 'tax' );
            $order->set_total( 0, 'shipping_tax' );
            $order->set_total( $posted['shipping'], 'shipping' );

            $order->set_total( 0, 'cart_discount' );
            $order->set_total( 0, 'order_discount' );

            $order->set_total( $posted['total'], 'total' );

            // Add a private (non-customer facing) note to the order
            $order_note_data = array(
                'intro' => 'This order was created via PriceWaiter checkout!',
                'pricewaiter_id' => 'PriceWaiter ID: ' . $posted['pricewaiter_id'],
                'transaction_id' => 'Transaction ID: ' . $posted['transaction_id'],
                'payment_method' => 'Payment Method: ' . $posted['payment_method']
            );

            $order->add_order_note( implode( "\n", $order_note_data ) );

            // Custom PriceWaiter order meta
            update_post_meta( $order->id, '_pw_pricewaiter_id', $posted['pricewaiter_id'] );
            update_post_meta( $order->id, '_pw_transaction_id', $posted['transaction_id'] );
            update_post_meta( $order->id, '_pw_payment_method', $posted['payment_method'] );

        }

        exit;

    }
}

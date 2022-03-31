<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/Door-2-Door/d2d-wc-deliveries
 * @package           D2D_WC_Deliveries
 * @author			  Samuel Hassid
 *
 * @wordpress-plugin
 * Plugin Name:       Door 2 Door Deliveries WooCommerce
 * Description:       Send a webhook to Door 2 Door Deliveries Management System when a WooCommerce order is processing
 * Version:           2.0.0
 * Requires PHP:      7.4.0
 * Author:            Door 2 Door
 * Author URI:        https://door2doormalta.com
 * Text Domain:       d2d-wc-deliveries
 * Domain Path:       /d2d-wc-deliveries
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


class D2D_WC_Deliveries {

	protected static $instance = null;

	public static function get_instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static;
		}

		return static::$instance;
	}

	protected function __construct() {

		$this->require_files();
		$this->init_self_update();

		// Register the plugin configuration page
		add_action('admin_menu', [$this, 'register_configuration_page']);

		// Listen for order processing event
        add_action( 'woocommerce_order_status_processing', [$this, 'd2d_order_processing_callback'], 10, 1 );

	}

	/**
	 * Register the plugin configuration page
	 */
	public function register_configuration_page() {
		add_menu_page(
			esc_html__('D2D Deliveries', 'd2d-wc-deliveries'),
			esc_html__('D2D Deliveries', 'd2d-wc-deliveries'),
			'administrator',
			'd2d-deliveries-setup',
			[$this, 'display_configuration_page'],
			'dashicons-location',
		);
	}

    /**
	 * Triggered by `woocommerce_order_status_processing`.
	 *
	 * @param int $order_id The WC_Order id
	 */
	public static function d2d_order_processing_callback( $order_id ) {

		/**
		 * If the slug & keys are not properly inputted yet, abort
		 */
		if (! D2D_Admin_Page::check_inputs_are_valid() ) {
			return;
		}
		
		// Collect the WooCommerce order details
		$order = new WC_Order( $order_id );

		// Prepare the payload with the order data
		$payload = $order->data;
		
		// Get the shipping methods details
		$shipping_methods = $order->get_shipping_methods();
		
		// Fill the payload with the shipping methods details
		$payload['shipping_lines'] = array_values( array_map(function ($shipping_line) {
			return $shipping_line->get_data();
		}, $shipping_methods) );
				
		// Setup request args.
		$http_args = [
			'method'      => 'POST',
			'timeout'     => MINUTE_IN_SECONDS,
			'redirection' => 0,
			'httpversion' => '1.0',
			'blocking'    => true,
			'user-agent'  => sprintf( 'Door2Door Hookshot (WordPress/%s)', $GLOBALS['wp_version'] ),
			'body'        => trim( wp_json_encode( $payload ) ),
			'headers'     => [
				'Content-Type' => 'application/json',
			],
			'cookies'     => [],
		];

		// Generate the webhook signature
		$http_args['headers']['X-WC-Webhook-Signature'] = self::generate_signature( $http_args['body'] );

		// Webhook away!
		$response = wp_safe_remote_request( self::get_delivery_url(), $http_args );

		/**
		 * In case of success we store the result on the order
		 * That will allow to display the tracking link on the WC Order details page
		 */
		if ( !is_array($response) || $response['response']['code'] != 200 ) {
			return;
		}

		$body = $response['body'];

		if ( is_string($body) && $body == 'No shipping needed') {
			
			/**
			 * No shipping needed for this order
			 * Let's persist that info on the WC Order
			 */
			update_post_meta( $order_id, 'd2d_order_needs_shipping', false );
		
		} elseif ( is_array($body) && array_key_exists('pickups', $body) && array_key_exists('result_tracking_link', $body['pickups'])) {

			/**
			 * Shipping needed and tracking link available
			 * Let's persist that info on the WC Order
			 */
			update_post_meta( $order_id, 'd2d_order_needs_shipping', true );
			update_post_meta( $order_id, 'd2d_tracking_link', wc_clean( $body['pickups']['result_tracking_link'] ) );

		}

	}

	/**
	 * Generate the webhook signature
	 * 
	 * @param string $body The webhook body
	 * @return string The generated signature
	 */
	private static function generate_signature($body) {
		return base64_encode(hash_hmac('sha256', $body, get_option('d2d_secret_key'), true));
	}

	/**
	 * Get the webhook delivery url
	 * 
	 * @return string The webhook delivery url
	 */
	private static function get_delivery_url() {
		return 'https://api-delivery.door2doormalta.com/tookan-integrator/woocommerce/order-created/' . get_option('d2d_account_slug');
	}

	/**
	 * Display the plugin configuration page
	 */
	public function display_configuration_page() {
		D2D_Admin_Page::wrap_ui();
	}

	private function require_files() {
		require 'admin-page.php';
		require 'plugin-update-checker/plugin-update-checker.php';
	}

	private function init_self_update() {
		Puc_v4_Factory::buildUpdateChecker(
			'https://api-delivery.door2doormalta.com/wordpress-assets/d2d-wc-deliveries.json',
			__FILE__, //Full path to the main plugin file or functions.php.
			'd2d-wc-deliveries'
		);
	}
}

add_action( 'init', 'd2d_order_processing_webhook_init' );

function d2d_order_processing_webhook_init() {
	D2D_WC_Deliveries::get_instance();
}

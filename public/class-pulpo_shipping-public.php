<?php

class Pulpo_Shipping_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Pulpo_shipping_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pulpo_shipping_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pulpo_shipping-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Pulpo_shipping_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pulpo_shipping_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pulpo_shipping-public.js', array( 'jquery' ), $this->version, false );

	}

	public function register_pulpo_api_endpoints() {
		register_rest_route('wc', '/pulpo_webhook', [
			'methods'  => 'POST',
			'permission_callback' => '__return_true',
			'callback' => function( WP_REST_Request $request) {
				$params = $request->get_json_params();

				$jsonParams = json_encode($params);
				plugin_log("[info] Request received via webhook: {$jsonParams}", 'a', PULPO_SHIPPING_LOG_FILENAME);

				if (!isset($params['type']) || !isset($params['data'])) {
					return;
				}

				plugin_log("[info] Webhook type: {$params['type']}", 'a', PULPO_SHIPPING_LOG_FILENAME);
				
				$service = new \PulpoService();
				if ('sales_order_finished' === $params['type']) {
					$order_data = $service->_get('sales/orders/' . $params['data']['id']);
					if (!$order_data) {
						return;
					}

					$order = new WC_Order($order_data['attributes']['woocommerce_order_id']);

					if (empty($order)) {
						return;
					}

					$new_status = get_option('pulpo_send_order_state', 'wc-completed');
					$order->update_status($new_status);
					plugin_log('[info] Order id ' . $order->get_id() . ': set to wc-completed', 'a', PULPO_SHIPPING_LOG_FILENAME);
				} else if ('sales_order_cancelled' === $params['type']) {
					$order_data = $service->_get('sales/orders/' . $params['data']['id']);
					if (!$order_data) {
						return;
					}

					$order = new WC_Order($order_data['attributes']['woocommerce_order_id']);

					if (empty($order)) {
						return;
					}

					$order->update_status('cancelled');
					plugin_log("[info] {$params['type']} Order id " . $order->get_id() . ': set to cancelled', 'a', PULPO_SHIPPING_LOG_FILENAME);
				} else if ('incoming_good_created' === $params['type']) {
					if (isset($params['data']) && isset($params['data']['items'])) {
						foreach ($params['data']['items'] as $item) {
							if ('accepted' === $item['state'] && isset($item['product']['sku'])) {
								$quantity = isset($item['quantity'])? $item['quantity'] : 0;
								$product = wc_get_product(wc_get_product_id_by_sku($item['product']['sku']));

								if ($product) {
									$manage_stock = $product->get_manage_stock();
									if (false !== $manage_stock) {
										$current_stock = $product->get_stock_quantity();
										wc_update_product_stock($product->get_id(), $current_stock + $quantity);

										plugin_log("[info] {$params['type']} Product sku {$item['product']['sku']} - id " . $product->get_id() . ": set stock from {$current_stock} to " . ( $current_stock + $quantity ), 'a', PULPO_SHIPPING_LOG_FILENAME);
									} else {
										plugin_log("[info] {$params['type']} Product manage_stock = false ", 'a', PULPO_SHIPPING_LOG_FILENAME);
									}
								} else {
									plugin_log("[info] {$params['type']} Product sku " . $item['product']['sku'] . ' not found ', 'a', PULPO_SHIPPING_LOG_FILENAME);
								}
							}
						}
					}
				} else if ('counting_task_closed' === $params['type']) {
					if (isset($params['data']) && isset($params['data']['items'])) {
						foreach ($params['data']['items'] as $item) {
							if (true === $item['is_valid'] && isset($item['product']['sku'])) {
								$quantity = $item['quantity'] - $item['current_stock_quantity'];
								$product = wc_get_product(wc_get_product_id_by_sku($item['product']['sku']));

								if ($product) {
									$manage_stock = $product->get_manage_stock();
									if (false !== $manage_stock) {
										$current_stock = $product->get_stock_quantity();
										wc_update_product_stock($product->get_id(), $current_stock + $quantity);

										plugin_log("[info] {$params['type']} Product sku {$item['product']['sku']} - id " . $product->get_id() . ": set stock from {$current_stock} to " . ( $current_stock + $quantity ), 'a', PULPO_SHIPPING_LOG_FILENAME);
									} else {
										plugin_log("[info] {$params['type']} Product manage_stock = false ", 'a', PULPO_SHIPPING_LOG_FILENAME);
									}
								} else {
									plugin_log("[info] {$params['type']} Product sku " . $item['product']['sku'] . ' not found ', 'a', PULPO_SHIPPING_LOG_FILENAME);
								}
							}
						}
					}
				} else if ('stock_correction_finished' === $params['type']) {
					if (isset($params['data']) && isset($params['data']['items'])) {
						foreach ($params['data']['items'] as $item) {
							if (isset($item['product']['sku'])) {
								$quantity = $item['quantity'];
								$product = wc_get_product(wc_get_product_id_by_sku($item['product']['sku']));

								if ($product && 0 != $quantity) {
									$manage_stock = $product->get_manage_stock();
									if (false !== $manage_stock) {
										$current_stock = $product->get_stock_quantity();
										wc_update_product_stock($product->get_id(), $current_stock + $quantity);

										plugin_log("[info] {$params['type']} Product sku {$item['product']['sku']} - id " . $product->get_id() . ": set stock from {$current_stock} to " . ( $current_stock + $quantity ), 'a', PULPO_SHIPPING_LOG_FILENAME);
									} else {
										plugin_log("[info] {$params['type']} Product manage_stock = false ", 'a', PULPO_SHIPPING_LOG_FILENAME);
									}
								} else {
									plugin_log("[info] {$params['type']} Product sku " . $item['product']['sku'] . ' not found ', 'a', PULPO_SHIPPING_LOG_FILENAME);
								}
							}
						}
					}
				} else if ('replenishment_order_finished' === $params['type']) {
					if (isset($params['data']) && isset($params['data']['items'])) {
						foreach ($params['data']['items'] as $item) {
							if (isset($item['product']['sku'])) {
								$quantity = isset($item['requested_quantity'])? $item['requested_quantity'] : 0;
								$product = wc_get_product(wc_get_product_id_by_sku($item['product']['sku']));

								if ($product && 0 != $quantity) {
									$manage_stock = $product->get_manage_stock();
									if (false !== $manage_stock) {
										$current_stock = $product->get_stock_quantity();
										$origin_location_type = isset($item['origin_location_type']['name'])? $item['origin_location_type']['name'] : '';
										$destination_location_type = isset($item['destination_location_type']['name'])? $item['destination_location_type']['name'] : '';
										$new_stock = 0;
										if ('Return Location Type' === $origin_location_type && 'Storage Location Type' === $destination_location_type) {
											$new_stock = $current_stock + $quantity;
										} else if ('Storage Location Type' === $origin_location_type && 'Return Location Type' === $destination_location_type) {
											$new_stock = $current_stock - $quantity;
										}

										if ($new_stock) {
											wc_update_product_stock($product->get_id(), $new_stock);
											plugin_log("[info] {$params['type']} Product sku {$item['product']['sku']} - id " . $product->get_id() . ": set stock from {$current_stock} to " . ( $new_stock ), 'a', PULPO_SHIPPING_LOG_FILENAME);
										}
									} else {
										plugin_log("[info] {$params['type']} Product manage_stock = false ", 'a', PULPO_SHIPPING_LOG_FILENAME);
									}
								} else {
									plugin_log("[info] {$params['type']} Product sku " . $item['product']['sku'] . ' not found ', 'a', PULPO_SHIPPING_LOG_FILENAME);
								}
							}
						}
					}
				}

				plugin_log("[info] {$params['type']} Webhook finished ", 'a', PULPO_SHIPPING_LOG_FILENAME);

				wp_send_json_success();
			},
		]);
	}


	public function register_pulpo_shipping_methods( $methods) {
		$methods[ 'pulpo_method' ] = 'WC_Shipping_Pulpo';

		return $methods;
	}
}

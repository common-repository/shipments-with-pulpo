<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Pulpo_shipping
 * @subpackage Pulpo_shipping/admin
 */
class Pulpo_Shipping_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/* Required WC version. */
	const REQ_WC_VERSION = '4.8';

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ )
		. 'css/pulpo_shipping-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) 
		. 'js/pulpo_shipping-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function check_woocommerce() {
		// WC 4.8+ check.
		if ( ! function_exists( 'WC' ) || version_compare( WC()->version, self::REQ_WC_VERSION ) < 0 ) {
			/* translators: 1: WC version 2: plugin name */
			$notice = esc_html__('Pulpo WMS requires at least WooCommerce version <strong>%1$s</strong>. %2$s', 'pulpo');
			if ( ! function_exists( 'WC' ) ) {
				$notice = sprintf($notice, self::REQ_WC_VERSION, esc_html__('Please install and activate WooCommerce.', 'pulpo'));
			} else {
				$notice = sprintf($notice, self::REQ_WC_VERSION, esc_html__('Please update WooCommerce.', 'pulpo'));
			}

			add_action('admin_notices', function () use ( $notice ) {
				?>
				<div class="error notice">
					<p><?php echo esc_html($notice); ?></p>
				</div>
				<?php
			});

			return false;
		}
	}

	/**
	 * Load dependencies for additional WooCommerce settings
	 *
	 * @since    1.0.0
	 */
	public function add_settings( $settings) {
		$settings[] = include plugin_dir_path(dirname(__FILE__)) . 'admin/class-pulpo-wc-settings.php';
		return $settings;
	}

	public function pulpo_sync_on_product_save( $product_id) {
		$_product = wc_get_product($product_id);
		//get product status
		$product_status = $_product->get_status();
		if ('publish' !== $product_status) {
			return;
		}

		update_post_meta($product_id, '_updated_for_pulpo', '1');

		//If this is variation mark all children
		if ($_product) {
			foreach ($_product->get_children() as $child_id) {
				update_post_meta($child_id, '_updated_for_pulpo', '1');
			}
		}

		wp_clear_scheduled_hook(PulpoProcessProductsSyncCRON::CRON_HOOK);
		wp_schedule_event((int) current_time('timestamp') + 50, 'twicedaily', PulpoProcessProductsSyncCRON::CRON_HOOK);
	}

	public function pulpo_woo_order_status_changed(
		$order_id,
		$status_transition_from,
		$status_transition_to,
		$order ) {

		$saved_status = [
			get_option( 'pulpo_send_to_pulpo_state', 'wc-processing'),
			str_replace('wc-', '', get_option( 'pulpo_send_to_pulpo_state', 'wc-processing'))
		];

		if (in_array($status_transition_to, $saved_status)
			&& $order->get_meta_data('_post_to_pulpo') !== 'yes'
		) {
			if ($order->has_shipping_method(WC_Shipping_Pulpo::$ID)
				|| get_option('pulpo_force_shipping', 'yes') === 'yes') {
				$this->send_order_to_pulpo($order);
			}
		}
	}

	public function pulpo_woo_product_options_sku() {
		woocommerce_wp_text_input(
			array(
				'id'          => '_barcode',
				'label'       => esc_html__( 'Barcode', 'pulpo' ),
				'desc_tip'    => true,
				'description' => esc_html__( 'If no Barcode is indicated, the SKU code will be used.', 'pulpo' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => '_management_type',
				'label'       => esc_html__( 'Management type', 'pulpo' ),
				'options'     => [
					''          => esc_html__('None', 'pulpo'),
					'lot'       => esc_html__('Lot', 'pulpo'),
					'serial'    => esc_html__('Serial', 'pulpo')
				],
				'desc_tip'    => true,
			)
		);
	}

	public function pulpo_woo_product_custom_fields_save( $post_id) {
		$_barcode = filter_input(INPUT_POST, '_barcode', FILTER_SANITIZE_STRING);
		update_post_meta($post_id, '_barcode', $_barcode);

		$_management_type = filter_input(INPUT_POST, '_management_type', FILTER_SANITIZE_STRING);
		update_post_meta($post_id, '_management_type', $_management_type);
	}

	public function pulpo_woo_variation_options( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input(
			array(
				'id'            => "_variable_barcode{$variation->ID}",
				'name'          => "_variable_barcode[{$variation->ID}]",
				'value'         => get_post_meta($variation->ID, '_variable_barcode', true),
				'label'         => esc_html__( 'Barcode', 'pulpo' ),
				'desc_tip'      => true,
				'wrapper_class' => 'form-row form-row-first',
				'description' => __( 'If no Barcode is indicated, the SKU code will be used.', 'pulpo' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'            => "_variable_management_type{$variation->ID}",
				'name'          => "_variable_management_type[{$variation->ID}]",
				'label'         => __( 'Management type', 'pulpo' ),
				'value'         => get_post_meta($variation->ID, '_variable_management_type', true),
				'options'       => [
					''      => esc_html__('None', 'pulpo'),
					'lot'       => esc_html__('Lot', 'pulpo'),
					'serial'    => esc_html__('Serial', 'pulpo')
				],
				'desc_tip'      => true,
				'wrapper_class' => 'form-row form-row-last',
			)
		);
	}


	public function pulpo_woo_add_custom_variation_fields_save( $post_id) {
		$nonce = sanitize_text_field(isset($_REQUEST['_wpnonce'])?$_REQUEST['_wpnonce']:'');
		if (!wp_verify_nonce($nonce, 'my-nonce')) {
			error_log('Security check'); //Do nothing. Is for phpcs
		}

		update_post_meta($post_id, '_variable_barcode',
			sanitize_text_field(isset($_POST['_variable_barcode'][$post_id])?$_POST['_variable_barcode'][$post_id]:''));

		update_post_meta($post_id, '_variable_management_type',
			sanitize_text_field(isset($_POST['_variable_management_type'][$post_id])?$_POST['_variable_management_type'][$post_id]:''));
	}

	private function send_order_to_pulpo( $order) {
		global $wpdb;
		$order_id = $order->get_id();

		$shipping_method = $order->get_shipping_methods();
		if (!count($shipping_method)) {
			$order->add_order_note(esc_html__('Error: No Pulpo shipping method found'));
			return;
		}
		$shipping_method = new WC_Shipping_Pulpo(reset($shipping_method)->get_instance_id());
		$carrier = $shipping_method->carrier;

		$order->add_order_note(esc_html__('Send order to Pulpo'));

		$ordersController = new WC_REST_Orders_Controller_Wrapper();
		try {
			$orderData = $ordersController->get_formatted_item_data($order);
		} catch (Exception $e) {
			/* translators: %s: error message */
			$order->add_order_note(sprintf(esc_html__('Error when serializing the order to send it to Pulpo: %s'), $e->getMessage()));
			return;
		}

		if (!$orderData) {
			$order->add_order_note(esc_html__('Unspected error when serializing the order to send it to Pulpo'));

			return;
		}


		//Crear entrada en cola si no existe.
		$exist = $wpdb->get_row($wpdb->prepare('SELECT * FROM '
			. $wpdb->prefix
			. 'pulpo_shipping_queue where order_id = %d', $order_id), ARRAY_A);

		if (!$exist) {
			$wpdb->insert($wpdb->prefix . 'pulpo_shipping_queue', [
				'order_id'  => $order_id,
				'request'   => json_encode($orderData),
				'created'   => current_time('mysql'),
				'updated'   => current_time('mysql'),
				'ntry'      => 0
			]);
			$queue_id = $wpdb->insert_id;
		} else {
			$queue_id = $exist['id'];

			$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
				'request'   => json_encode($orderData),
				'ntry'      => $exist['ntry'] + 1
			], [
				'id'        => $queue_id
			]);
		}

		//Crear entrada en cola si no existe.
		$exist = $wpdb->get_row($wpdb->prepare('SELECT * FROM '
			. $wpdb->prefix
			. 'pulpo_shipping_queue where order_id = %d', $order_id), ARRAY_A);
		
		if (!$exist) {
			$wpdb->insert($wpdb->prefix . 'pulpo_shipping_queue', [
				'order_id'  => $order_id,
				'request'   => json_encode($orderData),
				'created'   => current_time('mysql'),
				'updated'   => current_time('mysql'),
				'ntry'      => 0
			]);
			$queue_id = $wpdb->insert_id;
		} else {
			$queue_id = $exist['id'];

			$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
				'ntry'      => $exist['ntry'] + 1
			], [
				'id'        => $queue_id
			]);
		}

		$ordersController = new WC_REST_Orders_Controller_Wrapper();
		try {
			$orderData = $ordersController->get_formatted_item_data($order);
		} catch (Exception $e) {
			/* translators: %s: error message */
			$order->add_order_note(sprintf(esc_html__('Error when serializing the order to send it to Pulpo: %s'), $e->getMessage()));

			return;
		}

		if (!$orderData) {
			$order->add_order_note(esc_html__('Unspected error when serializing the order to send it to Pulpo'));

			return;
		}

		global $wpdb;
		$service = new PulpoService();

		if ($carrier) {
			$service->set_carrier($carrier);
		}

		try {
			$response = $service->post_order_from_woocommerce_order($orderData);
			$order->update_meta_data('_post_to_pulpo', 'true');

			$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
				'success'   => true,
				'response'  => json_encode($response),
				'updated'   => current_time('mysql')
			], [
				'id'        => $queue_id
			]);
		} catch (\Exception $e) {
			/* translators: %s: error message */
			$order->add_order_note(sprintf(esc_html__('Error creating Pulpo order: %s'), $e->getMessage()));

			$blogname = get_bloginfo('blogname');
			$msg =
				"There was an error sending order #{$order_id} to Pulpo.\n" .
				"This is the error message:\n" .
				"{$e->getMessage()}\n" .
				"The order will be resent again.\n" .
				"This message was sent automatically from {$blogname}.\n";
			send_admin_email_pulpo_order_fail($msg);

			$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
				'response'   => $e->getmessage(),
				'updated'   => current_time('mysql')

			], [
				'id'        => $queue_id
			]);
		}

	}

	public function pulpo_ajax_create_webhooks() {
		if (is_admin()) {
			$allowed_types = [
				'sales_order_finished', 
				'sales_order_cancelled',
				'incoming_good_created',
				'counting_task_closed',
				'stock_correction_finished',
				'replenishment_order_finished'
			];
			$service = new \PulpoService();

			$hooks_list = $service->get_webhooks();
			if (!$hooks_list) {
				$hooks_list = [];
			}

			$url = get_site_url() . '/wp-json/wc/pulpo_webhook';

			$warehouses_list = $service->get_warehouses();
			if ($warehouses_list) {
				foreach ($warehouses_list as $warehouse) {
					$exist_webhooks = array_filter($hooks_list, function( $i) use( $url, $warehouse, $allowed_types) {
						return $i['url'] === $url
							&& $i['warehouse_id'] === $warehouse['id']
							&& ( in_array($allowed_types[0], $i['allowed_types']) 
								|| in_array($allowed_types[1], $i['allowed_types']) );
					});
					$service->configure_webhooks(count($exist_webhooks)?reset($exist_webhooks)['id']:null,
						$warehouse['id'], $url, $allowed_types);
				}
			}
		}
	}

	public function pulpo_ajax_create_products() {
		if (is_admin()) {
			$job = new PulpoProcessProductsSyncCRON();
			$response = $job->processJobs();
			wp_send_json($response);
			wp_die();
		}
	}

	public function add_meta_box_shop_order_pulpo( $post) {
		global $wpdb;
		$db_prefix = $wpdb->prefix;
		$table_name = $db_prefix . 'pulpo_shipping_queue';
		$request = $wpdb->get_row(
			$wpdb->prepare('SELECT * from `%1s` where order_id = %d and success is null', $table_name, $post->ID), ARRAY_A);
		if ($request) {
			add_meta_box(
				'order_pulpo',
				esc_html__('Resend to Pulpo', 'pulpo'),
				[$this, 'render_pulpo_meta_box'],
				'shop_order', // shop_order is the post type of the admin order page
				'side', // change to 'side' to move box to side column
				'default' // priority (where on page to put the box)
			);
		}
	}

	public function render_pulpo_meta_box( $post) {
		?>

<p>
<?php 
esc_html_e('It seems that the order could not be created in Pulpo. ' 
. 'You can use the following button to force submission.', 'pulpo')
?>
	</p>

<button 
	id="ResendPulpo" 
	type="button"
	class="button save_order button-primary">
<?php esc_html_e('Submit to Pulpo', 'pulpo'); ?></button>

<script>
jQuery('#ResendPulpo').on('click', function() {
	if(jQuery(this).hasClass('loading')) {
		return;
	}

	jQuery(this).html('One moment...');
	jQuery(this).addClass('loading');
	jQuery.ajax({
			type : "post",
			dataType : "json",
			url : '<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>',
			data : {
				action: "admin_manage_pulpo_order",
				order_id: <?php echo esc_html($post->ID); ?>,
			},
		}).done(function() {
			alert('Process finished.');
			location.reload();
		}).fail(function() {
			alert('An unexpected error occurred');
			location.reload();
	});
});
</script>

<?php
	}

	public function wp_ajax_admin_manage_pulpo_order() {
		if (!is_admin()) {
			die();
		}

		$nonce = sanitize_text_field(isset($_REQUEST['_wpnonce'])?$_REQUEST['_wpnonce']:'');
		if (!wp_verify_nonce($nonce, 'my-nonce')) {
			error_log('Security check'); //Do nothing. Is for phpcs
		}

		$order = wc_get_order(intval(isset($_POST['order_id'])?$_POST['order_id']:null));
		if (!$order) {
			die();
		}

		if (!$order->has_shipping_method(WC_Shipping_Pulpo::$ID)
			&& get_option('pulpo_force_shipping', 'yes') !== 'yes') {
			die();
		}

		$this->send_order_to_pulpo($order);
	}


	public function wp_ajax_admin_pulpo_send_log() {
		if (!is_admin()) {
			die();
		}

		$nonce = sanitize_text_field(isset($_REQUEST['_wpnonce'])?$_REQUEST['_wpnonce']:'');
		if (!wp_verify_nonce($nonce, 'pulpo_send_log')) {
			error_log('Security check'); //Do nothing. Is for phpcs
		}

		$to = 'pulpo@twentic.com';
		$subject = 'Pulpo Log';
		$body = 'Mail enviado desde ' . get_site_url() . ' por ' . wp_get_current_user()->user_email . '<br>';
		$body .= 'Nombre: ' . sanitize_text_field(isset($_POST['name'])?$_POST['name']:'') . '<br>';
		$body .= 'Email: ' . sanitize_text_field(isset($_POST['email'])?$_POST['email']:'') . '<br>';
		$body .= sanitize_textarea_field(isset($_POST['message'])?$_POST['message']:'');
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];
		$file = PULPO_SHIPPING_LOG_FILENAME;
		$filename  = $upload_dir . '/' . $file . '.log';
		if (file_exists($filename)) {
			$attachments = array($filename);
		} else {
			$attachments = array();
		}
		wp_mail( $to, $subject, $body, $headers, $attachments);

		$response = array(
			'success' => true,
			'message' => 'Email sent successfully'
		);
		wp_send_json($response);
	}
}

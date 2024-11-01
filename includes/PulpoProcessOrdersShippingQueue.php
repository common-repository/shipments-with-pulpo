<?php

class PulpoProcessOrdersShippingQueueCron {

	const CRON_HOOK = 'process_orders_shipping_queue_cron';
	private $MAX_ATTEMPTS = 3;
	private $service;

	public static function setupCronJob() {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);

		if (false === $timestamp) {
			wp_schedule_event(time(), 'twicedaily', self::CRON_HOOK);
		}
	}

	public static function unsetCronJob() {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		wp_unschedule_event($timestamp, self::CRON_HOOK);
	}

	public function __construct() {
		add_action(self::CRON_HOOK, array($this, 'doCron'));
	}

	public function doCron() {
		$this->processJobs();

		return true;
	}

	public function processJobs() {
		global $wpdb;

		$db_prefix  = $wpdb->prefix;
		$table_name = $db_prefix . 'pulpo_shipping_queue';
		$service = new PulpoService();

		$requests = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * from `%1s` where success is null and ntry < %d', $table_name, $this->MAX_ATTEMPTS
			), ARRAY_A);

		foreach ($requests as $request) {
			$order = wc_get_order($request['order_id']);
			if (!$order) {
				continue;
			}

			if (!$order->has_shipping_method(WC_Shipping_Pulpo::$ID)
				&& get_option('pulpo_force_shipping', 'yes') !== 'yes') {
				continue;
			}

			$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
				'ntry'      => $request['ntry'] + 1
			], [
				'id'        => $request['id']
			]);

			try {
				$carrier = null;

				$shipping_method = $order->get_shipping_methods();
				if (count($shipping_method)) {
					$shipping_method = new WC_Shipping_Pulpo(reset($shipping_method)->get_instance_id());
					$carrier = $shipping_method->carrier;
				}

				if ($carrier) {
					$service->set_carrier($carrier);
				}

				$response = $service->post_order_from_woocommerce_order(json_decode($request['request'], true));
				$order->update_meta_data('_post_to_pulpo', 'true');

				$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
					'success'   => true,
					'response'  => json_encode($response),
					'updated'   => current_time('mysql')
				], [
					'id'        => $request['id']
				]);
			} catch (\Exception $e) {
				$ntry = $request['ntry'] + 1;
				$blogname = get_bloginfo('blogname');
				$next = '';
				if ($request['ntry'] < ( $this->MAX_ATTEMPTS - 1 )) {
					$next = 'The order will be resent again.';
				}

				$msg =
					"There was an error resending order #{$request['order_id']} to Pulpo on attempt number {$ntry}.\n" .
					"This is the error message:\n" .
					"{$e->getMessage()}\n" .
					"{$next}\n" .
					"This message was sent automatically from {$blogname}.";
				send_admin_email_pulpo_order_fail($msg);


				$wpdb->update($wpdb->prefix . 'pulpo_shipping_queue', [
					'response'   => $e->getmessage(),
					'updated'   => current_time('mysql')

				], [
					'id'        => $request['id']
				]);
			}
		}
	}
}


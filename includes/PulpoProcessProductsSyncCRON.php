<?php

class PulpoProcessProductsSyncCRON {

	const CRON_HOOK = 'process_producs_sync_cron';

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
		/**
		 * Filter to check if plugin is active
		 *
		 * @since 1.0.0
		 */
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			add_action('woocommerce_loaded', 'my_function_with_wc_functions');
		}

		$this->processJobs();

		return true;
	}

	public function processJobs( $sku = null) {
		$service = new \PulpoService();
		$service->log('info', 'ProcessProductsSyncCRON::processJobs()');
		global $wpdb;
		if ($sku) {
			$where = "and wp.meta_value ='{$sku}'";
		}

		$tablename = $wpdb->prefix . 'posts';
		$tablepostmeta = $wpdb->prefix . 'postmeta';
		$list = $wpdb->get_results($wpdb->prepare(
			"select p.ID from `%1s` p
			left join `%1s` wp on wp.post_id = p.ID and wp.meta_key = '_sku'
			where p.post_type in ('product', 'product_variation')
			and p.post_status = 'publish'
			%1s;", $tablename, $tablepostmeta, $where
		), ARRAY_A);

		$service->log('info', 'ProcessProductsSyncCRON::processJobs() query results: ' . json_encode($list));

		$products_list = [];
		foreach ($list as $row) {
			$wc_product = wc_get_product($row['ID']);
			if ($wc_product) {
				if (!$wc_product->get_sku() || $wc_product->get_sku() === '') {
					continue;
				}

				$products_list[] = $this->product_to_json($wc_product);
			}
		}

		$service->log('info', 'ProcessProductsSyncCRON::processJobs() products_list size: ' . count($products_list));

		//Obtener los productos de pulpo
		$pulpo_products = $service->get_products();
		if (null === $pulpo_products) {
			$service->log('info', 'ProcessProductsSyncCRON::processJobs() $pulpo_products error. Abort and try again in 1 hour');

			//Try again in 1 hour
			wp_clear_scheduled_hook(self::CRON_HOOK);
			wp_schedule_event((int) current_time('timestamp') + 3600, 'twicedaily', self::CRON_HOOK);

			return [
				'status'    => 'error',
				'message'   => esc_html('An error occurred while getting Pulpo products. It will be tried again in 1 hour.', 'pulpo')
			];
		}

		$products_to_create = [];
		$products_to_update = [];
		$error = false;

		if (count($products_list)) {
			foreach ($products_list as $product) {
				$wc_product = wc_get_product($product['id']);
				if (!$wc_product) {
					continue;
				}

				$pulpo_product = null;
				foreach ($pulpo_products as $item) {
					if ($item['sku'] === $product['sku'] || get_post_meta($wc_product->get_id() === $item['id'], '_pulpo_id', true)) {
						$pulpo_product = $item;
						break;
					}
				}

				if (!$pulpo_product) {
					$products_to_create[] = $product;
				} else {
					//está marcado para actualizar?
					$_updated_for_pulpo = get_post_meta($wc_product->get_id(), '_updated_for_pulpo', true);
					if ('1' === $_updated_for_pulpo) {
						$products_to_update[] = $this->product_to_json($wc_product);
						delete_post_meta($wc_product->get_id(), '_updated_for_pulpo');
					}
				}
			}

			$service->log('info', 'ProcessProductsSyncCRON::processJobs() products_to_create size: ' . count($products_to_create));
			$service->log('info', 'ProcessProductsSyncCRON::processJobs() products_to_update size: ' . count($products_to_update));
			if (count($products_to_create)) {
				try {
					$response = $service->post_products($products_to_create);
					$this->updateProductsIds(isset($response['created'])? $response['created'] : array());
				} catch (Exception $e) {
					$error = true;
				}
			}

			if (count($products_to_update)) {
				try {
					$response = $service->post_products($products_to_update, 'put');
					$this->updateProductsIds(isset($response['updated']) ? $response['updated'] : array());
				} catch (Exception $e) {
					$error = true;
				}
			}
		}

		return [
			'status'    => $error?'fail':'success',
			'message'   => esc_html($error?'The process has been executed with errors. Access the log to know more.':'Products synced successfully.', 'pulpo'),
			'products'  => count($products_list) ,
			'created'   => count($products_to_create),
			'updated'   => count($products_to_update)
		];
	}

	protected function updateProductsIds( $pulpo_response) {
		foreach ($pulpo_response as $pulpo_product) {
			//get product by sku
			$_wc_product = wc_get_product(wc_get_product_id_by_sku($pulpo_product['sku']));
			if ($_wc_product) {
				update_post_meta($_wc_product->get_id(), '_pulpo_id', $pulpo_product['id']);
			}
		}
	}

	protected function product_to_json( $wc_product) {
		$barcodes = [];


		if ($wc_product->is_type('simple')) {
			$barcode = get_post_meta($wc_product->get_id(), '_barcode', true);
			$management_type = get_post_meta($wc_product->get_id(), '_management_type', true);


			$image_url = wp_get_attachment_image_src(
				get_post_thumbnail_id($wc_product->get_id()), 'large');
		} else if ($wc_product->is_type('variation')) {
			$barcode = get_post_meta($wc_product->get_id(), '_variable_barcode', true);
			$management_type = get_post_meta($wc_product->get_id(), '_variable_management_type', true);

			$image_url = wp_get_attachment_image_src(
				get_post_thumbnail_id($wc_product->get_parent_id()), 'large');
		}

		if ($barcode) {
			$barcodes[] = $barcode;
		} else {
			$barcodes[] = $wc_product->get_sku();
		}
		$pulpo_id = get_post_meta($wc_product->get_id(), '_pulpo_id', true);
		if ('' === $pulpo_id) {
			$pulpo_id = null;
		}

		$data = [
			'id'        =>  $wc_product->get_id(),
			'pulpo_id'  =>  $pulpo_id,
			'sku'       =>  $wc_product->get_sku(),
			'barcodes'  =>  $barcodes,
			'name'      =>  $wc_product->get_name(),
			'price'     =>  $wc_product->get_price(),
			'weight'    =>  $wc_product->get_weight(),
			'length'    =>  $wc_product->get_length(),
			'width'     =>  $wc_product->get_width(),
			'height'    =>  $wc_product->get_height(),
		];

		if ($management_type) {
			$data['management_type'] = $management_type;
		}

		if (isset($image_url[0])) {
			$data['attributes']['image_url'] = $image_url[0];
		}

		return $data;
	}
}

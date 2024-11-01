<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Pulpo_shipping
 * @subpackage Pulpo_shipping/includes
 */
class Pulpo_Shipping_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		/**
		 * Base de datos
		 */
		global $wpdb;
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$charset_collate = $wpdb->get_charset_collate();
		$db_prefix = $wpdb->prefix;

		$table_name = $db_prefix . 'pulpo_shipping_queue';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
			$sql =
				"CREATE TABLE IF NOT EXISTS `$table_name` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`created` TIMESTAMP NULL DEFAULT NULL,
					`updated` TIMESTAMP NULL DEFAULT NULL,
					`order_id` INT(11) NOT NULL,
					`request` text NULL,
					`response` text NULL,
					`success` int(1) NULL,
					`ntry` tinyint NOT NULL DEFAULT 0,
					PRIMARY KEY (`id`),
					UNIQUE INDEX `id_UNIQUE` (`id` ASC),
					INDEX `index3` (`order_id` ASC)
) ENGINE = InnoDB $charset_collate;";

			dbDelta($sql);
		}

		add_option('pulpo_shipping_db_version', PULPO_SHIPPING_DB_VERSION);

		PulpoProcessOrdersShippingQueueCron::setupCronJob();
	}

}

<?php
/**
 * Extends the WC_Settings_Page class
 *
 * @link       twentic
 * @since      1.0.0
 *
 * @package    Pulpo
 * @subpackage Pulpo/admin
 *
 */

if (! defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

if (! class_exists('Pulpo_WC_Settings')) {

	/**
	 * Settings class
	 *
	 * @since 1.0.0
	 */
	class Pulpo_WC_Settings extends WC_Settings_Page {


		/**
		 * Constructor
		 *
		 * @since  1.0
		 */
		public function __construct() {
			$this->id    = 'pulpo';
			$this->label = esc_html__('PULPO WMS', 'pulpo');

			// Define all hooks instead of inheriting from parent
			add_filter('woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20);
			add_action('woocommerce_sections_' . $this->id, array( $this, 'output_sections' ));
			add_action('woocommerce_settings_' . $this->id, array( $this, 'output' ));
			add_action('woocommerce_settings_save_' . $this->id, array( $this, 'save' ));
		}


		/**
		 * Get sections.
		 *
		 * @return array
		 */
		public function get_sections() {
			$sections = array(
				'' => esc_html__('General Settings', 'pulpo'),
				'test_mode' => esc_html__('Test Mode', 'pulpo'),
				'prod_mode' => esc_html__('Production Mode', 'pulpo'),
				'logs_view' => esc_html__('Logs', 'pulpo')
			);

			/**
			 * Filter woo settings tabs array.
			 *
			 * @since 1.0.0
			 */
			return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
		}


		/**
		 * Get settings array
		 *
		 * @return array
		 */
		public function get_settings( $reset_pulpo = false) {
			global $current_section;
			$settings = [];

			switch ($current_section) {
				case 'test_mode':
					$mode = 'test';
					include 'partials/pulpo-settings-mode.php';
					break;
				case 'prod_mode':
					$mode = 'prod';
					include 'partials/pulpo-settings-mode.php';
					break;
				case 'logs_view':
					include 'partials/pulpo-settings-logs.php';
					break;
				default:
					include 'partials/pulpo-settings-main.php';
			}


			/**
			 * Filter settings array.
			 *
			 * @since 1.0.0
			 */
			return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
		}

		/**
		 * Output the settings
		 */
		public function output() {
			include 'partials/pulpo-messages-settings.php';

			$settings = $this->get_settings(false);

			WC_Admin_Settings::output_fields($settings);

			$this->checkActions();
		}

		/**
		 * Save settings
		 *
		 * @since 1.0
		 */
		public function save() {
			$settings = $this->get_settings(true);

			WC_Admin_Settings::save_fields($settings);
		}

		private function checkActions() {
			global $current_section;

			if ('logs_view' !== $current_section ) {
				try {
					$service = new \PulpoService();
					$users_list = $service->get_users();
					if (null === $users_list) {
						return; 
					}
				} catch (Exception $e) {
					return;
				}

				add_thickbox();
				include 'partials/pulpo-settings-actions.php';
			}
		}
	}
}


return new Pulpo_WC_Settings();

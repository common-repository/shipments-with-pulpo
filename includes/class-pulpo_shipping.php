<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Pulpo_shipping
 * @subpackage Pulpo_shipping/includes
 */
class Pulpo_Shipping {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @var      Pulpo_shipping_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PULPO_SHIPPING_VERSION' ) ) {
			$this->version = PULPO_SHIPPING_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'pulpo_shipping';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Pulpo_shipping_Loader. Orchestrates the hooks of the plugin.
	 * - Pulpo_shipping_i18n. Defines internationalization functionality.
	 * - Pulpo_shipping_Admin. Defines all hooks for the admin area.
	 * - Pulpo_shipping_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pulpo_shipping-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pulpo_shipping-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pulpo_shipping-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-pulpo_shipping-public.php';

		/**
		 * Filter to check if WooCommerce is active
		 *
		 * @since 1.0.0
		 */
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			include_once(plugin_dir_path( dirname( __FILE__ ) ) . '../woocommerce/woocommerce.php');
			include_once(plugin_dir_path( dirname( __FILE__ ) ) . '../woocommerce/includes/abstracts/abstract-wc-shipping-method.php');
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-shipping-pulpo.php';
		}

		$this->loader = new Pulpo_Shipping_Loader();

		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libs.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/PulpoProcessProductsSyncCRON.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/PulpoProcessOrdersShippingQueue.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/services/class-pulpo-service.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/includes/class_rest_orders_controller.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Pulpo_shipping_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 */
	private function set_locale() {

		$plugin_i18n = new Pulpo_Shipping_I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Pulpo_Shipping_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'plugins_loaded', $plugin_admin, 'check_woocommerce' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action('add_meta_boxes_shop_order', $plugin_admin, 'add_meta_box_shop_order_pulpo', 10);

		$this->loader->add_filter('woocommerce_get_settings_pages', $plugin_admin, 'add_settings');

		$this->loader->add_action('woocommerce_new_product', $plugin_admin, 'pulpo_sync_on_product_save', 10, 1);
		$this->loader->add_action('woocommerce_update_product', $plugin_admin, 'pulpo_sync_on_product_save', 10, 1);
		$this->loader->add_action('woocommerce_order_status_changed', $plugin_admin, 'pulpo_woo_order_status_changed', 10, 4);

		$this->loader->add_action('woocommerce_product_options_sku', $plugin_admin, 'pulpo_woo_product_options_sku');
		$this->loader->add_action('woocommerce_process_product_meta', $plugin_admin, 'pulpo_woo_product_custom_fields_save');
		$this->loader->add_action('woocommerce_variation_options', $plugin_admin, 'pulpo_woo_variation_options', 10, 3);
		$this->loader->add_action('woocommerce_save_product_variation', $plugin_admin, 'pulpo_woo_add_custom_variation_fields_save', 10, 2 );

		$this->loader->add_action('wp_ajax_pulpo_ajax_create_webhooks', $plugin_admin, 'pulpo_ajax_create_webhooks');
		$this->loader->add_action('wp_ajax_nopriv_pulpo_ajax_create_webhooks', $plugin_admin, 'pulpo_ajax_create_webhooks');

		$this->loader->add_action('wp_ajax_pulpo_ajax_create_products', $plugin_admin, 'pulpo_ajax_create_products');
		$this->loader->add_action('wp_ajax_nopriv_pulpo_ajax_create_products', $plugin_admin, 'pulpo_ajax_create_products');

		$this->loader->add_action('wp_ajax_admin_manage_pulpo_order', $plugin_admin, 'wp_ajax_admin_manage_pulpo_order');
		$this->loader->add_action('wp_ajax_admin_pulpo_send_log', $plugin_admin, 'wp_ajax_admin_pulpo_send_log');

		new PulpoProcessProductsSyncCRON();
		new PulpoProcessOrdersShippingQueueCron();
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 */
	private function define_public_hooks() {

		$plugin_public = new Pulpo_Shipping_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		/**
		 * Filter to check if WooCommerce is active
		 *
		 * @since 1.0.0
		 */
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			$this->loader->add_filter('woocommerce_shipping_methods', $plugin_public, 'register_pulpo_shipping_methods');
			$this->loader->add_action('rest_api_init', $plugin_public, 'register_pulpo_api_endpoints');
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Pulpo_shipping_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

<?php

/**
 * PulpoWMS plugin helps you to sync your woocommerce with Pulpo WMS app to manage products, stock and shipping orders.
 *
 * @link              twentic.com
 * @since             1.0.25
 * @package           Pulpo_shipping
 *
 * @wordpress-plugin
 * Plugin Name:       WMS with Pulpo WMS
 * Plugin URI:        twentic.com
 * Description:       Use Pulpo to send your sales
 * Version:           1.0.25
 * Author:            twentic
 * Author URI:        twentic.com/#contacto
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pulpo_shipping
 * Domain Path:       /languages
 *
 * WC requires at least: 4.8
 * WC tested up to: 7.3
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path(__FILE__) . 'includes/global.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PULPO_SHIPPING_VERSION', '1.0.25' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pulpo_shipping-activator.php
 */
function activate_pulpo_shipping() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pulpo_shipping-activator.php';
	Pulpo_Shipping_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pulpo_shipping-deactivator.php
 */
function deactivate_pulpo_shipping() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pulpo_shipping-deactivator.php';
	Pulpo_Shipping_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_pulpo_shipping' );
register_deactivation_hook( __FILE__, 'deactivate_pulpo_shipping' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pulpo_shipping.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pulpo_shipping() {

	$plugin = new Pulpo_Shipping();
	$plugin->run();

}
run_pulpo_shipping();

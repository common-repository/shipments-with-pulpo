<?php
$settings = array(
	array(
		'name'  => esc_html__('Pulpo Configuration', 'pulpo'),
		'type'  => 'title',
		'id'    => 'general_config_settings_1'
	)
);

$settings[] = array(
	'id'        => 'pulpo_test_mode',
	'name'      => esc_html__('Test Mode', 'pulpo'),
	'desc'      => esc_html__('Check to use test mode instead production mode', 'pulpo'),
	'default'   => 'yes',
	'type'      => 'checkbox'
);

$settings[] = array(
	'id'        => 'pulpo_send_to_pulpo_state',
	'name'      => esc_html__('Order State', 'pulpo'),
	'type'      => 'select',
	'desc'      => esc_html__('Indicate in which order status it will be sent to Pulpo', 'pulpo'),
	'default'   => 'wc-processing',
	'options'   => wc_get_order_statuses()
);

$settings[] = array(
	'id'        => 'pulpo_send_order_state',
	'name'      => esc_html__('Order State after Pulpo sent', 'pulpo'),
	'type'      => 'select',
	'desc'      => esc_html__('What state to change the order to when Pulpo ships it', 'pulpo'),
	'default'   => 'wc-completed',
	'options'   => wc_get_order_statuses()
);

$settings[] = array(
	'id'        => 'pulpo_force_shipping',
	'name'      => esc_html__('Forcing shipments through Pulpo', 'pulpo'),
	'default'   => 'yes',
	'type'      => 'checkbox',
	'desc'      => esc_html__('If you check this option, all orders will be shipped through Pulpo. To configure different shipping methods and carriers by zones and countries uncheck this option.', 'pulpo')
	. '<br><br><strong>' . esc_html__('If you uncheck this option, you must configure different shipping methods and carriers by zones and countries from Woocommerce options. Otherwise the orders will not arrive to Pulpo.', 'pulpo') . '</strong>'
);

$settings[] = array(
			'type'  => 'sectionend',
			'desc'  => '',
			'id'    => 'general_config_settings_1',
);


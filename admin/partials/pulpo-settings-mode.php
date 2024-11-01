<?php

$shipping_methods_options = [
	'' => esc_html__('Please complete Pulpo configuration')
];
$merchants_options = [
	'' => esc_html__('Please complete Pulpo configuration')
];
$channels_options = [
	'' => esc_html__('Please complete Pulpo configuration')
];

$users_list = null;
$shipping_methods_list = null;

$mode3pl = get_option("pulpo_{$mode}_3plmode") === 'yes';
$selected_merchant = get_option("pulpo_{$mode}_merchant_id");

if ($reset_pulpo) {
	\PulpoService::clean_token_file($mode);
}

try {
	$service = new \PulpoService($mode);
	$users_list = $service->get_users();
	$shipping_methods_list = $service->get_shipping_methods();

	if ($mode3pl) {
		$merchants_list = $service->get_merchants();

		foreach ($merchants_list as $merchant) {
			$merchants_options[$merchant['id']] = $merchant['name'];
		}
	}
	if($selected_merchant) {
		$channels_list = $service->get_channels($selected_merchant);

		if($channels_list) {
			foreach ($channels_list as $channel) {
				$channels_options[$channel['id']] = $channel['name'];
			}
		}
	}

} catch (\Exception $e) {
	?>

<div id="message" class="updated inline">
<p>
<strong><php esc_html_e('Unespected error:', 'pulpo')?> <?php echo esc_html($e->getMessage()); ?></strong>
</p>
</div>
<?php
}

$username = get_option("pulpo_{$mode}_username");
$password = get_option("pulpo_{$mode}_password");

if (( !get_option('pulpo_' . $mode . '_tenant_id')
	|| get_option('pulpo_' . $mode . '_tenant_id') === '' )
	&& $users_list && count($users_list) && $users_list[0]['tenant']) {
	update_option('pulpo_' . $mode . '_tenant_id', $users_list[0]['tenant']['id']);
}

if ($shipping_methods_list) {
	$shipping_methods_options = [
		''  =>  esc_html__('Do not use any method', 'pulpo')
	];

	foreach ($shipping_methods_list as $shipping_method) {
		$shipping_methods_options[$shipping_method['id']] = $shipping_method['name'];
	}
}

$settings = array(
	array(
		'name'  => esc_html('prod'===$mode?__('Production mode', 'pulpo'):__('Testing mode', 'pulpo')),
		'type'  => 'title',
		'id'    => 'general_config_settings_2'
	)
);

$settings[] = array(
	'id'    => 'pulpo_' . $mode . '_username',
	'name'  => esc_html__('Username', 'pulpo'),
	'type'  => 'text'
);

$settings[] = array(
	'id'    => 'pulpo_' . $mode . '_password',
	'name'  => esc_html__('Password', 'pulpo'),
	'type'  => 'password'
);

$settings[] = array(
	'id'    => 'pulpo_' . $mode . '_url',
	'name'  => esc_html__('API url', 'pulpo'),
	'type'  => 'url'
);

$settings[] = array(
	'id'        => 'pulpo_' . $mode . '_3plmode',
	'name'      => esc_html__('3PL mode', 'pulpo'),
	'default'   => 'no',
	'type'      => 'checkbox',
	'desc'      => esc_html__('Enable 3PL/fulfiller mode.', 'pulpo'),
);


if (null !== $users_list) {
	$settings[] = array(
		'id'    => 'pulpo_' . $mode . '_tenant_id',
		'name'  => esc_html__('Default tenant id', 'pulpo'),
		'type'  => 'number'
	);

	if($mode3pl) {
		$settings[] = array(
			'id'        => 'pulpo_'.$mode.'_merchant_id',
			'name'      => esc_html__('Default merchant', 'pulpo'),
			'type'      => 'select',
			'options'   => $merchants_options
		);

		$settings[] = array(
			'id'        => 'pulpo_'.$mode.'_channel_id',
			'name'      => esc_html__('Default channel', 'pulpo'),
			'type'      => 'select',
			'options'   => $channels_options
		);
	}
}

$settings[] = array(
	'type'  => 'sectionend',
	'desc'  => '',
	'id'    => 'general_config_settings_2',
);

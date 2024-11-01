<?php
$message = null;

$env = get_option('pulpo_test_mode', 'yes') === 'yes'?'test':'prod';
$username = get_option("pulpo_{$env}_username");
$password = get_option("pulpo_{$env}_password");
$tenant_id = get_option("pulpo_{$env}_tenant_id");
$shipping_method_id = get_option("pulpo_{$env}_shipping_method_id");


if (!$username || !$password || !$tenant_id || !$shipping_method_id) {
	if ('test' === $env) {
		$message = esc_html__('Configuration is not complete for active Test mode', 'pulpo');
		$url = esc_url(admin_url('admin.php?page=wc-settings&tab=pulpo&section=test_mode'));
	} else {
		$message = esc_html__('Configuration is not complete for active Prod mode', 'pulpo');
		$url = esc_url(admin_url('admin.php?page=wc-settings&tab=pulpo&section=prod_mode'));
	}
}
?>
	
<?php if ($message) : ?>
	<div class="components-surface components-card woocommerce-store-alerts is-alert-update css-1pd4mph e19lxcc00">
		<div class="e19lxcc00">
			<div data-wp-c16t="true"
				data-wp-component="CardHeader"
				class="components-flex components-card__header components-card-header css-18lkm91 e19lxcc00">
			<h2 data-wp-c16t="true"
				data-wp-component="Text"
				class="components-truncate components-text css-c38ds3 e19lxcc00"
				><?php esc_html_e('Action required: Please complete the plugin configuration', 'pulpo'); ?></h2>
			</div>
			<div data-wp-c16t="true"
			data-wp-component="CardBody"
			class="components-card__body components-card-body css-xuyuoy e19lxcc00">
				<div class="woocommerce-store-alerts__message"><?php echo esc_html($message); ?></div>
			</div>
			<div class="components-flex components-card__footer components-card-footer css-mtqbyf e19lxcc00">
				<div class="woocommerce-store-alerts__actions">
				<a href="<?php echo esc_url($url); ?>" class="components-button is-secondary"
					><?php esc_html_e('OK'); ?></a>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

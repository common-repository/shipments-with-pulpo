<?php
	$hooks_list = $service->get_webhooks();
if ($hooks_list) {
	$hooks_message = 'There are already configured WebHooks. You can reconfigure them';
} else {
	$hooks_message = 'There are no WebHooks created in Pulpo. You can configure them';
}
?>

<hr>
<table class="form-table">
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e(__($hooks_message, 'pulpo')); ?></label>
		</th>
		<td>
			<a href="#TB_inline?width=200&height=150&inlineId=ModalLoading&modal=true"
				class="thickbox button-secondary"
				id="ConfWebhooksButton"><?php esc_html_e('Conf. WebHooks', 'pulpo'); ?></a>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row">
			<label><?php esc_html_e('Add all products to Pulpo database'); ?></label>
		</th>
		<td>
			<a href="#TB_inline?width=200&height=150&inlineId=ModalLoading&modal=true"
				class="thickbox button-secondary"
				id="SendProductsButton"><?php esc_html_e('Add products', 'pulpo'); ?></a>
		</td>
	</tr>
</table>
<hr/>

<div id="ModalLoading" style="display:none;">
	<p style="text-align: center;">
		<br/>
		<strong><?php esc_html_e('One moment, please...', 'pulpo'); ?></strong>
		</br>
		<img src="/wp-admin/images/spinner.gif"/>
	</p>
</div>

<script>
	(function($){
		$(document).ready(function() {
			$('#ConfWebhooksButton').on('click', function() {
				$.ajax({
					type: 'post',
					url: '<?php echo esc_url(admin_url('admin-ajax.php', 'relative')); ?>',
					data: {
						action: 'pulpo_ajax_create_webhooks',
					},
					complete: function () {
						alert('<?php esc_html_e('WebHooks configured successfully. Please access to the Pulpo panel to validate that they are correct.', 'pulpo'); ?>');
						location.reload();
					}
				});
			});

			$('#SendProductsButton').on('click', function() {
				$.ajax({
					type: 'post',
					url: '<?php echo esc_url(admin_url('admin-ajax.php', 'relative')); ?>',
					data: {
						action: 'pulpo_ajax_create_products',
					},
					complete: function (response) {
						if(response.responseJSON.message && response.responseJSON.message) {
							var message = response.responseJSON.message;
							if(response.responseJSON.status !== 'error') {
								message += "\n" +
									response.responseJSON.created + ' products created. ' +
									response.responseJSON.updated + ' products updated. ' +
									response.responseJSON.products + ' total products in store.';
							}
							alert(message);
						} else {
							alert('<?php esc_html_e('The product synchronization process has been initiated. Please, after a few minutes, log in to your Pulpo panel to confirm that the data is correct.', 'pulpo'); ?>');
						}
						location.reload();
					}
				});
			});
		});
	})(jQuery);
</script>

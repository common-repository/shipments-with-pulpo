<?php
add_thickbox();
$GLOBALS['hide_save_button'] = true;
$upload_dir = wp_upload_dir();
$upload_dir = $upload_dir['basedir'];
$file = PULPO_SHIPPING_LOG_FILENAME;
$file  = $upload_dir . '/' . $file . '.log';
?>

<a class="thickbox button button-primary"
name="Set Follow Up Date"
href="#TB_inline?width=200&height=300&inlineId=SendLogModal&modal=true"
style="float:right">
<?php esc_html_e('Send log to Technical support', 'pulpo'); ?>
</a>


<h1><?php esc_html_e('Logs', 'pulpo'); ?></h1>

<textarea style="width:100%; height: 600px" id="textarea_log">
<?php
$content = '';
if (file_exists($file)) {
	$handle = fopen($file, 'r');
	$content = fread($handle, filesize($file));
	fclose($handle);
}

echo esc_textarea($content);
?>
</textarea>


<div id="SendLogModal" style="display:none;">
	<strong>Send log to Technical support</strong>
	<table class="form-table">
		<tbody>
		<tr valign="top">
		<th scope="row" class="titledesc">
		<label>Your name:</label>
		</th>
		<td class="forminp forminp-text">
		<input name="name" type="text" maxlength="255" style="width: 100%" required>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" class="titledesc">
		<label>Your email:</label>
		</th>
		<td class="forminp forminp-text">
		<input name="email" type="email" maxlength="255" style="width: 100%" required>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" class="titledesc">
		<label>Message:</label>
		</th>
		<td class="forminp forminp-text">
		<textarea name="message" rows="4" maxlength="545" style="width: 100%" required></textarea>
		</td>
		</tr>
		</tbody>
	</table>

	<button onclick="tb_remove();" class="button button-primary">Cancel</button>
	<button type="button" class="button" style="float: right;">Send</button>
</div>

<script>
var textarea = document.getElementById('textarea_log');
textarea.scrollTop = textarea.scrollHeight;

($ => {
var $button = $('#SendLogModal button[type="button"]');
$button.click(function() {
	if($(this).hasClass('loading')) {
		return;
		}

		var name = $('.TB_modal input[name="name"]').val();
		var email = $('.TB_modal input[name="email"]').val();
		var message = $('.TB_modal textarea[name="message"]').val();

		if(name == '' || email == '' || message == '') {
			alert('All fields are required');
			return;
		}


		$button.html('One moment...');
		$button.addClass('loading');

		var data = {
		action: 'admin_pulpo_send_log',
			name: name,
			email: email,
			message: message,
			_wpnonce: '<?php echo esc_html(wp_create_nonce('pulpo_send_log')); ?>',
		};

		$.ajax({
		type : "post",
			dataType : "json",
			url : '<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>',
			data : data,
		}).done(function() {
			alert('Process finished.');
			tb_remove();

			$button.html('Send');
			$button.removeClass('loading');
}).fail(function() {
	alert('An unexpected error occurred');
	tb_remove();

	$button.html('Send');
	$button.removeClass('loading');
});
	});
})(jQuery)
</script>

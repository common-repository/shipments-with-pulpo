<?php

/**
 * Write an entry to a log file in the uploads directory.
 *
 * @param mixed $entry String or array of the information to write to the log.
 * @param string $file Optional. The file basename for the .log file.
 * @param string $mode Optional. The type of write. See 'mode' at https://www.php.net/manual/en/function.fopen.php.
 * @return boolean|int Number of bytes written to the lof file, false otherwise.
 */
if (!function_exists('plugin_log')) {
	function plugin_log( $entry, $mode = 'a', $file = 'plugin' ) {
	  // Get WordPress uploads directory.
	  $upload_dir = wp_upload_dir();
	  $upload_dir = $upload_dir['basedir'];
	  // If the entry is array, json_encode.
		if (is_array($entry)) {
		  $entry = json_encode($entry);
		}

	  // Write the log file.
	  $file  = $upload_dir . '/' . $file . '.log';

	  // if size greater than 5 MB then remove
		if (is_writable($file) && filesize($file) > ( 1024 * 1024 * 5 )) {
			unlink($file);
		}
	  $file  = fopen( $file, $mode );
	  $bytes = fwrite( $file, current_time( 'mysql' ) . '::' . $entry . "\n" );
	  fclose( $file );
	  return $bytes;
	}
}

if (!function_exists('send_admin_email_pulpo_order_fail')) {
	function send_admin_email_pulpo_order_fail( $message) {
		$email = get_bloginfo('admin_email');
		$subject = 'Pulpo order error';
		$heading = 'Administration';
		$mailer = WC()->mailer();
		$wrapped_message = $mailer->wrap_message($heading, $message);
		$wc_email = new WC_Email();
		$html_message = $wc_email->style_inline($wrapped_message);
		$mailer->send($email, $subject, $html_message, "Content-Type: text/html\r\n", []);
	}
}

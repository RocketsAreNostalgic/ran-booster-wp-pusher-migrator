<?php
/**
 * WP Pusher migration mode chooser.
 */

defined( 'ABSPATH' ) || exit;
?>
<button type="button" class="ran-booster-portability__mode" data-portability-mode="wp-pusher" aria-controls="ran-booster-portability-wp-pusher" aria-expanded="false" aria-pressed="false">
	<span class="ran-booster-portability__mode-icon dashicons dashicons-migrate" aria-hidden="true"></span>
	<span class="ran-booster-portability__mode-text">
		<span class="ran-booster-portability__mode-title"><?php esc_html_e( 'Migrate from WP Pusher', 'ran-booster-wp-pusher-migrator' ); ?></span>
		<span class="ran-booster-portability__mode-description"><?php esc_html_e( 'Move retained WP Pusher packages into Booster.', 'ran-booster-wp-pusher-migrator' ); ?></span>
	</span>
</button>

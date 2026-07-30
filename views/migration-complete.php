<?php
/**
 * WP Pusher package migration completion advisory.
 *
 * @var bool                $completionVisible
 * @var string              $pluginsUrl
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="ran-booster-wp-pusher-migration-complete" class="ran-booster-wp-pusher-migrator__completion-panel<?php echo $completionVisible ? ' ran-booster-wp-pusher-migrator__completion-panel--visible' : ''; ?>">
	<div class="notice notice-success inline ran-booster-wp-pusher-migrator__completion-advisory">
		<h4><?php esc_html_e( 'Package migration complete!', 'ran-booster-wp-pusher-migrator' ); ?></h4>
		<p><?php esc_html_e( 'No WP Pusher packages remain. You can now uninstall WP Pusher.', 'ran-booster-wp-pusher-migrator' ); ?></p>
		<ol>
			<li>
				<strong><?php esc_html_e( 'Delete WP Pusher from WordPress.', 'ran-booster-wp-pusher-migrator' ); ?></strong>
				<?php esc_html_e( 'WP Pusher will remove its own settings and package table, and attempt to revoke this site’s license activation.', 'ran-booster-wp-pusher-migrator' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Confirm the site was deactivated.', 'ran-booster-wp-pusher-migrator' ); ?></strong>
				<?php esc_html_e( 'Sign in to WP Pusher and revoke the site manually if it still appears.', 'ran-booster-wp-pusher-migrator' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Review repository webhooks.', 'ran-booster-wp-pusher-migrator' ); ?></strong>
				<?php esc_html_e( 'If push-to-deploy was enabled, check your repository provider separately for any WP Pusher webhooks.', 'ran-booster-wp-pusher-migrator' ); ?>
			</li>
		</ol>
		<p class="ran-booster-wp-pusher-migrator__completion-actions">
			<a class="button" href="<?php echo esc_url( $pluginsUrl ); ?>"><?php esc_html_e( 'Open Installed Plugins', 'ran-booster-wp-pusher-migrator' ); ?></a>
			<a class="button" href="https://dashboard.wppusher.com/login" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open WP Pusher dashboard', 'ran-booster-wp-pusher-migrator' ); ?></a>
		</p>
	</div>
</div>

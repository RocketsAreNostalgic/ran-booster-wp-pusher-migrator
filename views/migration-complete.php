<?php
/**
 * WP Pusher package migration completion and optional cleanup.
 *
 * @var bool                $completionVisible
 * @var list<string>        $unusedOptions
 * @var bool                $tablePresent
 * @var string              $migrationUrl
 * @var string              $optionsAction
 * @var string              $tableAction
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="ran-booster-wp-pusher-migration-complete" class="ran-booster-wp-pusher-migrator__completion-panel<?php echo $completionVisible ? ' ran-booster-wp-pusher-migrator__completion-panel--visible' : ''; ?>">
	<div class="ran-booster-wp-pusher-migrator__complete">
		<h4><?php esc_html_e( 'Package migration complete', 'ran-booster-wp-pusher-migrator' ); ?></h4>
		<p><?php esc_html_e( 'No WP Pusher package records remain. You can now review the optional cleanup below.', 'ran-booster-wp-pusher-migrator' ); ?></p>
	</div>
	<div class="ran-booster-wp-pusher-migrator__cleanup">
		<?php if ( array() !== $unusedOptions ) { ?>
			<form method="post" action="<?php echo esc_url( $migrationUrl ); ?>">
				<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="delete_options">
				<?php wp_nonce_field( $optionsAction ); ?>
				<p><?php esc_html_e( 'Remove known WP Pusher settings that Booster does not use. The WP Pusher license key and unknown settings are kept.', 'ran-booster-wp-pusher-migrator' ); ?></p>
				<button class="button" type="submit"><?php esc_html_e( 'Remove unused settings', 'ran-booster-wp-pusher-migrator' ); ?></button>
			</form>
		<?php } ?>
		<?php if ( $tablePresent ) { ?>
			<form method="post" action="<?php echo esc_url( $migrationUrl ); ?>">
				<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="drop_table">
				<?php wp_nonce_field( $tableAction ); ?>
				<p><?php esc_html_e( 'Remove the empty WP Pusher package table after checking it again.', 'ran-booster-wp-pusher-migrator' ); ?></p>
				<button class="button" type="submit"><?php esc_html_e( 'Remove empty package table', 'ran-booster-wp-pusher-migrator' ); ?></button>
			</form>
		<?php } ?>
	</div>
	<details class="ran-booster-wp-pusher-migrator__removal">
		<summary><?php esc_html_e( 'Before removing WP Pusher', 'ran-booster-wp-pusher-migrator' ); ?></summary>
		<p><?php esc_html_e( 'This migrator does not delete either plugin or contact WP Pusher. Review the WP Pusher license and any provider webhooks separately before deleting it.', 'ran-booster-wp-pusher-migrator' ); ?></p>
	</details>
</div>

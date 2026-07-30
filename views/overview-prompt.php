<?php
/**
 * Overview prompt shown when an exact supported WP Pusher table remains.
 *
 * @var string $migrationUrl
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="ran-booster-onboarding__storage ran-booster-wp-pusher-prompt" aria-labelledby="ran-booster-wp-pusher-prompt-heading">
	<div class="ran-booster-onboarding__storage-heading">
		<h3 id="ran-booster-wp-pusher-prompt-heading"><?php esc_html_e( 'Move your WP Pusher packages', 'ran-booster-wp-pusher-migrator' ); ?></h3>
		<a class="button button-primary" href="<?php echo esc_url( $migrationUrl ); ?>"><?php esc_html_e( 'Migrate to Booster', 'ran-booster-wp-pusher-migrator' ); ?></a>
	</div>
	<p><?php esc_html_e( 'Booster found package records from WP Pusher. Review and move them into Booster before removing WP Pusher.', 'ran-booster-wp-pusher-migrator' ); ?></p>
</section>

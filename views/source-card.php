<?php
/**
 * Retained WP Pusher source review.
 *
 * @var string $error
 * @var list<array<string,mixed>> $rows Complete passive row display models.
 * @var bool                $has_error
 * @var bool                $legacy_data_present
 * @var bool                $apply_visible
 * @var string              $apply_class
 * @var string              $apply_message
 * @var bool                $cleanup_pending
 * @var bool                $completion_visible
 * @var string              $plugins_url
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local values are supplied by the rendering scope.


defined( 'ABSPATH' ) || exit;

?>
<section class="ran-booster-portability__flow ran-booster-wp-pusher-migrator" id="ran-booster-portability-wp-pusher" aria-labelledby="ran-booster-portability-wp-pusher-heading" hidden>
	<header class="ran-booster-portability__flow-header">
		<p class="ran-booster-portability__eyebrow ran-booster-eyebrow"><?php esc_html_e( 'Migration', 'ran-booster-wp-pusher-migrator' ); ?></p>
		<h3 id="ran-booster-portability-wp-pusher-heading" tabindex="-1"><?php esc_html_e( 'Migrate from WP Pusher', 'ran-booster-wp-pusher-migrator' ); ?></h3>
		<p><?php esc_html_e( 'Move each package into Booster and check the result before continuing. Migrated packages start with automatic deployments turned off.', 'ran-booster-wp-pusher-migrator' ); ?></p>
	</header>

	<?php if ( $has_error ) { ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
	<?php } else { ?>
		<?php if ( $apply_visible ) { ?>
			<div class="notice <?php echo esc_attr( $apply_class ); ?> inline">
				<p>
					<?php echo esc_html( $apply_message ); ?>
					<?php if ( $cleanup_pending ) { ?>
						<?php esc_html_e( 'The package is safely stored in Booster, but its old WP Pusher record remains. Keep WP Pusher inactive and try again.', 'ran-booster-wp-pusher-migrator' ); ?>
					<?php } ?>
				</p>
			</div>
		<?php } ?>
		<?php if ( $legacy_data_present && array() !== $rows ) { ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'WP Pusher settings found: Not all Pusher settings can be migrated. Private repositories will need to access credentials.', 'ran-booster-wp-pusher-migrator' ); ?></p></div>
		<?php } ?>

		<?php if ( array() !== $rows ) { ?>
			<div class="ran-booster-portability__table-scroll ran-booster-wp-pusher-migrator__table-scroll" role="region" aria-labelledby="ran-booster-portability-wp-pusher-heading" tabindex="0">
				<table class="widefat striped ran-booster-portability__review-table ran-booster-wp-pusher-migrator__packages">
					<colgroup>
						<col class="ran-booster-wp-pusher-migrator__package-column">
						<col class="ran-booster-wp-pusher-migrator__repository-column">
						<col class="ran-booster-wp-pusher-migrator__status-column">
						<col class="ran-booster-wp-pusher-migrator__action-column">
					</colgroup>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Installed package', 'ran-booster-wp-pusher-migrator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Repository', 'ran-booster-wp-pusher-migrator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'ran-booster-wp-pusher-migrator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Action', 'ran-booster-wp-pusher-migrator' ); ?></th>
						</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) { ?>
								<?php
								require __DIR__ . '/source-row.php';
								?>
							<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>
		<?php
		require __DIR__ . '/migration-complete.php';
		?>
	<?php } ?>
</section>

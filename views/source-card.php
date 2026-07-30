<?php
/**
 * Retained WP Pusher source review.
 *
 * @var string $error
 * @var list<array{source:\RAN\BoosterWpPusherMigrator\WpPusherPackage,candidate:array<string,mixed>|null,error:string,review:\RAN\AddOn\Portability\PortabilityReviewResult|null}> $rows
 * @var array<string, bool> $optionPresence
 * @var string              $formAction
 * @var string              $applyFormAction
 * @var array{result:\RAN\AddOn\Portability\PortabilityApplyResult,cleanup_pending:bool}|null $apply
 * @var array{success:bool,message:string}|null $cleanup
 * @var bool                $tablePresent
 * @var string              $optionsAction
 * @var string              $tableAction
 * @var string              $migrationUrl
 * @var string              $adminPostAction
 * @var object|null         $adminInteraction
 */

defined( 'ABSPATH' ) || exit;

$hasError = '' !== $error;
if ( ! $hasError ) {
	$legacyDataPresent = in_array( true, $optionPresence, true );
	$unusedOptions     = array_diff( array_keys( array_filter( $optionPresence ) ), array( 'wppusher_license_key' ) );
}
?>
<section class="ran-booster-portability__flow ran-booster-wp-pusher-migrator" id="ran-booster-portability-wp-pusher" aria-labelledby="ran-booster-portability-wp-pusher-heading" hidden>
	<header class="ran-booster-portability__flow-header">
		<p class="ran-booster-portability__eyebrow ran-booster-eyebrow"><?php esc_html_e( 'Migration', 'ran-booster-wp-pusher-migrator' ); ?></p>
		<h3 id="ran-booster-portability-wp-pusher-heading" tabindex="-1"><?php esc_html_e( 'Migrate from WP Pusher', 'ran-booster-wp-pusher-migrator' ); ?></h3>
		<p><?php esc_html_e( 'Move each package into Booster and check the result before continuing. Migrated packages start with automatic deployments turned off.', 'ran-booster-wp-pusher-migrator' ); ?></p>
	</header>

	<?php if ( $hasError ) { ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
	<?php } else { ?>
		<?php if ( null !== $apply ) { ?>
			<?php $applyResult = $apply['result']; ?>
			<div class="notice <?php echo $applyResult->targetVerified ? 'notice-success' : 'notice-error'; ?> inline">
				<p>
					<?php echo esc_html( $applyResult->message ); ?>
					<?php if ( $apply['cleanup_pending'] ) { ?>
						<?php esc_html_e( 'The package is safely stored in Booster, but its old WP Pusher record remains. Keep WP Pusher inactive and try again.', 'ran-booster-wp-pusher-migrator' ); ?>
					<?php } ?>
				</p>
			</div>
		<?php } ?>
		<?php if ( null !== $cleanup ) { ?>
			<div class="notice <?php echo $cleanup['success'] ? 'notice-success' : 'notice-error'; ?> inline">
				<p><?php echo esc_html( $cleanup['message'] ); ?></p>
			</div>
		<?php } ?>
		<?php if ( $legacyDataPresent ) { ?>
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
								$checkRequest       = $row['check_request'];
								$rowTargetElementId = null !== $checkRequest ? $checkRequest->targetElementId() : '';
								require __DIR__ . '/source-row.php';
								?>
							<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>
		<?php
		$completionVisible = array() === $rows;
		require __DIR__ . '/migration-complete.php';
		?>
	<?php } ?>
</section>

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
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Saved WP Pusher settings were found. They will not be copied. For a private repository, choose an existing Booster credential.', 'ran-booster-wp-pusher-migrator' ); ?></p></div>
		<?php } ?>

		<?php if ( array() === $rows ) { ?>
			<div class="ran-booster-wp-pusher-migrator__complete">
				<h4><?php esc_html_e( 'Package migration complete', 'ran-booster-wp-pusher-migrator' ); ?></h4>
				<p><?php esc_html_e( 'No WP Pusher package records remain. You can now review the optional cleanup below.', 'ran-booster-wp-pusher-migrator' ); ?></p>
			</div>
			<div class="ran-booster-wp-pusher-migrator__cleanup">
				<?php if ( array() !== $unusedOptions ) { ?>
					<form method="post">
						<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="delete_options">
						<?php wp_nonce_field( $optionsAction ); ?>
						<p><?php esc_html_e( 'Remove known WP Pusher settings that Booster does not use. The WP Pusher license key and unknown settings are kept.', 'ran-booster-wp-pusher-migrator' ); ?></p>
						<button class="button" type="submit"><?php esc_html_e( 'Remove unused settings', 'ran-booster-wp-pusher-migrator' ); ?></button>
					</form>
				<?php } ?>
				<?php if ( $tablePresent ) { ?>
					<form method="post">
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
		<?php } else { ?>
			<div class="ran-booster-portability__table-scroll ran-booster-wp-pusher-migrator__table-scroll" role="region" aria-labelledby="ran-booster-portability-wp-pusher-heading" tabindex="0">
				<table class="widefat striped ran-booster-portability__review-table">
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
							$source    = $row['source'];
							$candidate = $row['candidate'];
							$review    = $row['review'];
							?>
							<tr>
								<th scope="row"><code><?php echo esc_html( $source->package ); ?></code></th>
								<td><code><?php echo esc_html( $source->repository ); ?></code></td>
								<td>
									<?php if ( '' !== $row['error'] ) { ?>
										<strong><?php esc_html_e( 'Cannot migrate', 'ran-booster-wp-pusher-migrator' ); ?></strong>
										<span><?php echo esc_html( $row['error'] ); ?></span>
									<?php } elseif ( null !== $review ) { ?>
										<strong><?php esc_html_e( 'Checked', 'ran-booster-wp-pusher-migrator' ); ?></strong>
										<span><?php echo esc_html( $review->message ); ?></span>
									<?php } else { ?>
										<?php esc_html_e( 'Ready to check', 'ran-booster-wp-pusher-migrator' ); ?>
									<?php } ?>
								</td>
								<td>
									<?php if ( null !== $candidate ) { ?>
										<div class="ran-booster-wp-pusher-migrator__actions">
											<form method="post">
												<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="review">
												<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>">
												<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $source->fingerprint() ); ?>">
												<?php wp_nonce_field( $formAction ); ?>
												<?php if ( 1 === $source->private ) { ?>
													<label>
														<span><?php esc_html_e( 'Booster credential', 'ran-booster-wp-pusher-migrator' ); ?></span>
														<input type="text" name="credential_id" maxlength="64" pattern="[A-Za-z0-9_-]{3,64}" autocomplete="off" required>
													</label>
												<?php } ?>
												<button class="button" type="submit"><?php esc_html_e( 'Check package', 'ran-booster-wp-pusher-migrator' ); ?></button>
											</form>
											<?php if ( null !== $review && in_array( $review->action, array( 'adopt', 'managed' ), true ) ) { ?>
												<form method="post">
													<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="apply">
													<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>">
													<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $source->fingerprint() ); ?>">
													<input type="hidden" name="review_fingerprint" value="<?php echo esc_attr( $review->fingerprint ); ?>">
													<input type="hidden" name="credential_id" value="<?php echo esc_attr( (string) ( $review->candidate->credentialId ?? '' ) ); ?>">
													<?php wp_nonce_field( $applyFormAction ); ?>
													<button class="button button-primary" type="submit"><?php esc_html_e( 'Move to Booster (deployments off)', 'ran-booster-wp-pusher-migrator' ); ?></button>
												</form>
											<?php } ?>
										</div>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
		<?php } ?>
	<?php } ?>
</section>

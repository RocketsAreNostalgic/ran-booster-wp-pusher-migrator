<?php
/**
 * Retained WP Pusher source review.
 *
 * @var list<array{source:\RAN\BoosterWpPusherMigrator\WpPusherPackage,candidate:array<string,mixed>|null,error:string,review:\RAN\AddOn\Portability\PortabilityReviewResult|null}> $rows
 * @var array<string, bool> $optionPresence
 * @var string              $formAction
 * @var string              $applyFormAction
 * @var array{result:\RAN\AddOn\Portability\PortabilityApplyResult,cleanup_pending:bool}|null $apply
 */

defined( 'ABSPATH' ) || exit;

$legacyDataPresent = in_array( true, $optionPresence, true );
?>
<section class="ran-booster-card ran-booster-wp-pusher-migrator">
	<h2><?php esc_html_e( 'Migrate from WP Pusher', 'ran-booster-wp-pusher-migrator' ); ?></h2>
	<p><?php esc_html_e( 'This temporary bridge reads retained WP Pusher 3.0.13 package settings. It does not read or import legacy credentials, install files, or enable deployments.', 'ran-booster-wp-pusher-migrator' ); ?></p>
	<?php if ( null !== $apply ) { ?>
		<?php $applyResult = $apply['result']; ?>
		<div class="notice <?php echo $applyResult->targetVerified ? 'notice-success' : 'notice-error'; ?> inline">
			<p>
				<?php echo esc_html( $applyResult->message ); ?>
				<?php if ( $apply['cleanup_pending'] ) { ?>
					<?php esc_html_e( 'Booster ownership is verified and Disabled, but exact WP Pusher row cleanup is still pending. Keep WP Pusher inactive and retry from the fresh source state.', 'ran-booster-wp-pusher-migrator' ); ?>
				<?php } ?>
			</p>
		</div>
	<?php } ?>
	<?php if ( $legacyDataPresent ) { ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Legacy WP Pusher credential or settings options are present. Their values have not been read and will not be imported. Private repositories require an existing Booster credential profile.', 'ran-booster-wp-pusher-migrator' ); ?></p></div>
	<?php } ?>
	<?php if ( array() === $rows ) { ?>
		<p><?php esc_html_e( 'No retained WP Pusher package rows remain.', 'ran-booster-wp-pusher-migrator' ); ?></p>
	<?php } else { ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Installed package', 'ran-booster-wp-pusher-migrator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Repository', 'ran-booster-wp-pusher-migrator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Migration state', 'ran-booster-wp-pusher-migrator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Review', 'ran-booster-wp-pusher-migrator' ); ?></th>
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
								<strong><?php esc_html_e( 'Unsupported', 'ran-booster-wp-pusher-migrator' ); ?></strong>
								<?php echo esc_html( $row['error'] ); ?>
							<?php } elseif ( null !== $review ) { ?>
								<strong><?php echo esc_html( ucfirst( $review->action ) ); ?></strong>
								<?php echo esc_html( $review->message ); ?>
							<?php } else { ?>
								<?php esc_html_e( 'Ready for a fresh Core review.', 'ran-booster-wp-pusher-migrator' ); ?>
							<?php } ?>
						</td>
						<td>
							<?php if ( null !== $candidate ) { ?>
								<form method="post">
									<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="review">
									<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>">
									<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $source->fingerprint() ); ?>">
									<?php wp_nonce_field( $formAction ); ?>
									<label>
										<span><?php esc_html_e( 'Booster credential profile ID', 'ran-booster-wp-pusher-migrator' ); ?></span>
										<input type="text" name="credential_id" maxlength="64" pattern="[A-Za-z0-9_-]{3,64}" autocomplete="off"<?php echo 1 === $source->private ? ' required' : ''; ?>>
									</label>
									<button class="button" type="submit"><?php esc_html_e( 'Review package', 'ran-booster-wp-pusher-migrator' ); ?></button>
								</form>
								<?php if ( null !== $review && in_array( $review->action, array( 'adopt', 'managed' ), true ) ) { ?>
									<form method="post">
										<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="apply">
										<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>">
										<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $source->fingerprint() ); ?>">
										<input type="hidden" name="review_fingerprint" value="<?php echo esc_attr( $review->fingerprint ); ?>">
										<input type="hidden" name="credential_id" value="<?php echo esc_attr( (string) ( $review->candidate->credentialId ?? '' ) ); ?>">
										<?php wp_nonce_field( $applyFormAction ); ?>
										<button class="button button-primary" type="submit"><?php esc_html_e( 'Adopt into Booster as Disabled', 'ran-booster-wp-pusher-migrator' ); ?></button>
									</form>
								<?php } ?>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</section>

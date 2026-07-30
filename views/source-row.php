<?php
/**
 * One retained WP Pusher source row.
 *
 * @var array<string, mixed> $row
 * @var string               $migrationUrl
 * @var string               $formAction
 * @var string               $applyFormAction
 * @var string               $adminPostAction
 * @var object|null          $adminInteraction
 * @var string               $rowTargetElementId
 */

defined( 'ABSPATH' ) || exit;

$source           = $row['source'];
$candidate        = $row['candidate'];
$review           = $row['review'];
$imported         = true === $row['imported'];
$checkRequest     = $row['check_request'];
$importRequest    = $row['import_request'];
$actionableReview = null !== $review && in_array( $review->action, array( 'adopt', 'managed' ), true );
?>
<tr<?php echo '' === $rowTargetElementId ? '' : ' id="' . esc_attr( $rowTargetElementId ) . '"'; ?>>
	<th scope="row"><code><?php echo esc_html( $source->package ); ?></code></th>
	<td><code><?php echo esc_html( $source->repository ); ?></code></td>
	<td>
		<?php if ( $imported ) { ?>
			<strong><?php esc_html_e( 'Imported', 'ran-booster-wp-pusher-migrator' ); ?></strong>
			<span><?php esc_html_e( 'Managed by Booster.', 'ran-booster-wp-pusher-migrator' ); ?></span>
		<?php } elseif ( '' !== $row['error'] ) { ?>
			<strong><?php esc_html_e( 'Cannot migrate', 'ran-booster-wp-pusher-migrator' ); ?></strong>
			<span><?php echo esc_html( $row['error'] ); ?></span>
		<?php } elseif ( null !== $review ) { ?>
			<strong><?php esc_html_e( 'Checked', 'ran-booster-wp-pusher-migrator' ); ?></strong>
			<span><?php echo esc_html( $review->message ); ?></span>
		<?php } else { ?>
			<?php esc_html_e( 'Ready to check', 'ran-booster-wp-pusher-migrator' ); ?>
		<?php } ?>
		<div id="<?php echo esc_attr( $row['error_region_id'] ); ?>" class="ran-booster-wp-pusher-migrator__row-error" role="alert" aria-live="polite"></div>
	</td>
	<td class="ran-booster-wp-pusher-migrator__action-cell">
		<?php if ( $imported ) { ?>
			<a class="button" href="<?php echo esc_url( $row['manage_url'] ); ?>"><?php echo esc_html( $row['manage_label'] ); ?></a>
		<?php } elseif ( null !== $candidate ) { ?>
			<div class="ran-booster-wp-pusher-migrator__actions">
				<?php if ( ! $actionableReview ) { ?>
					<form method="post" action="<?php echo esc_url( $migrationUrl ); ?>"
					<?php
					if ( null !== $checkRequest && null !== $adminInteraction ) {
						$adminInteraction->renderFormAttributes( $checkRequest ); }
					?>
					>
						<input type="hidden" name="action" value="<?php echo esc_attr( $adminPostAction ); ?>">
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
				<?php } else { ?>
					<form method="post" action="<?php echo esc_url( $migrationUrl ); ?>"
					<?php
					if ( null !== $importRequest && null !== $adminInteraction ) {
						$adminInteraction->renderFormAttributes( $importRequest ); }
					?>
					>
						<input type="hidden" name="action" value="<?php echo esc_attr( $adminPostAction ); ?>">
						<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="apply">
						<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $source->id ); ?>">
						<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $source->fingerprint() ); ?>">
						<input type="hidden" name="review_fingerprint" value="<?php echo esc_attr( $review->fingerprint ); ?>">
						<input type="hidden" name="credential_id" value="<?php echo esc_attr( (string) ( $review->candidate->credentialId ?? '' ) ); ?>">
						<?php wp_nonce_field( $applyFormAction ); ?>
						<button class="button button-primary" type="submit"><?php esc_html_e( 'Import', 'ran-booster-wp-pusher-migrator' ); ?></button>
					</form>
				<?php } ?>
			</div>
		<?php } ?>
	</td>
</tr>

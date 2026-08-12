<?php
/**
 * One retained WP Pusher source row.
 *
 * @var array<string, mixed> $row
 * @var object               $adminInteraction Exact Core form-attribute presentation seam.
 */

defined( 'ABSPATH' ) || exit;

$migrationComplete = true === $row['migration_complete'];
?>
<tr<?php echo '' === $row['target_element_id'] ? '' : ' id="' . esc_attr( $row['target_element_id'] ) . '"'; ?><?php echo $migrationComplete ? ' data-ran-booster-wp-pusher-migration-complete="true"' : ''; ?>>
	<th scope="row"><code><?php echo esc_html( $row['package'] ); ?></code></th>
	<td><code><?php echo esc_html( $row['repository'] ); ?></code></td>
	<td>
		<?php if ( $row['status_strong'] ) { ?>
			<strong><?php echo esc_html( $row['status_heading'] ); ?></strong>
			<?php if ( $migrationComplete ) { ?>
				<span class="screen-reader-text" role="status" aria-live="polite"><?php esc_html_e( 'Package migration complete.', 'ran-booster-wp-pusher-migrator' ); ?></span>
			<?php } ?>
		<?php } else { ?>
			<?php echo esc_html( $row['status_heading'] ); ?>
		<?php } ?>
		<?php if ( '' !== $row['status_message'] ) { ?>
			<span><?php echo esc_html( $row['status_message'] ); ?></span>
		<?php } ?>
		<div id="<?php echo esc_attr( $row['error_region_id'] ); ?>" class="ran-booster-wp-pusher-migrator__row-error" role="alert" aria-live="polite"></div>
	</td>
	<td class="ran-booster-wp-pusher-migrator__action-cell">
		<?php if ( 'manage' === $row['action'] ) { ?>
			<a class="button" href="<?php echo esc_url( $row['manage_url'] ); ?>"><?php echo esc_html( $row['manage_label'] ); ?></a>
		<?php } elseif ( 'none' !== $row['action'] ) { ?>
			<div class="ran-booster-wp-pusher-migrator__actions">
				<?php if ( 'check' === $row['action'] ) { ?>
					<form method="post" action="<?php echo esc_url( $row['migration_url'] ); ?>"<?php $adminInteraction->renderFormAttributes( $row['form_request'] ); ?>>
						<input type="hidden" name="action" value="<?php echo esc_attr( $row['admin_post_action'] ); ?>">
						<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="review">
						<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $row['source_id'] ); ?>">
						<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $row['source_fingerprint'] ); ?>">
						<?php wp_nonce_field( $row['form_action'] ); ?>
						<?php if ( $row['private'] ) { ?>
							<label>
								<span><?php esc_html_e( 'Booster credential', 'ran-booster-wp-pusher-migrator' ); ?></span>
								<input type="text" name="credential_id" maxlength="64" pattern="[A-Za-z0-9_-]{3,64}" autocomplete="off" required>
							</label>
						<?php } ?>
						<button class="button" type="submit"><?php esc_html_e( 'Check', 'ran-booster-wp-pusher-migrator' ); ?></button>
					</form>
				<?php } else { ?>
					<form method="post" action="<?php echo esc_url( $row['migration_url'] ); ?>"<?php $adminInteraction->renderFormAttributes( $row['form_request'] ); ?>>
						<input type="hidden" name="action" value="<?php echo esc_attr( $row['admin_post_action'] ); ?>">
						<input type="hidden" name="ran_booster_wp_pusher_migrator_action" value="apply">
						<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $row['source_id'] ); ?>">
						<input type="hidden" name="source_fingerprint" value="<?php echo esc_attr( $row['source_fingerprint'] ); ?>">
						<input type="hidden" name="review_fingerprint" value="<?php echo esc_attr( $row['review_fingerprint'] ); ?>">
						<input type="hidden" name="credential_id" value="<?php echo esc_attr( $row['credential_id'] ); ?>">
						<?php wp_nonce_field( $row['apply_form_action'] ); ?>
						<button class="button button-primary" type="submit"><?php echo esc_html( $row['action_label'] ); ?></button>
					</form>
				<?php } ?>
			</div>
		<?php } ?>
	</td>
</tr>

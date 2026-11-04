<?php
/**
 * WP Pusher migration guidance.
 *
 * @var string $migration_url
 */

declare(strict_types=1);
?>
<p><?php esc_html_e( 'The WP Pusher Migrator supports single-site WordPress installations. Back up your site before you begin and keep WP Pusher installed but inactive while migrating.', 'ran-booster-wp-pusher-migrator' ); ?></p>
<p><?php esc_html_e( 'The retained package data must have a supported schema and valid records. GitHub and Bitbucket Cloud packages require the matching Booster provider; GitLab packages must be migrated manually. Private repositories need replacement Booster credentials: legacy credentials are never copied.', 'ran-booster-wp-pusher-migrator' ); ?></p>
<ol>
	<li><?php esc_html_e( 'Open migration tools and check each retained package. Review the result and select a Booster credential when needed.', 'ran-booster-wp-pusher-migrator' ); ?></li>
	<li><?php esc_html_e( 'Adopt each ready package with deployment Disabled. The Migrator removes its exact unchanged WP Pusher record only after Booster verifies adoption. If Booster already manages a package, use Finish to verify it and remove the retained record.', 'ran-booster-wp-pusher-migrator' ); ?></li>
	<li><?php esc_html_e( 'Verify each package and review deployment settings and remote webhooks before enabling deployment. After verification, remove this temporary Migrator. Removing remaining WP Pusher settings and tables is a separate WP Pusher uninstall, which may contact its license service.', 'ran-booster-wp-pusher-migrator' ); ?></li>
</ol>
<?php if ( '' !== $migration_url ) : ?>
	<p><a href="<?php echo esc_url( $migration_url ); ?>"><?php esc_html_e( 'Open migration tools', 'ran-booster-wp-pusher-migrator' ); ?></a></p>
<?php endif; ?>

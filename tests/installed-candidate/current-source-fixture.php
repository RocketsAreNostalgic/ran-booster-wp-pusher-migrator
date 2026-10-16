<?php

// WP-CLI eval-file wraps this file; a strict_types declaration is not supported.

use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RAN\BoosterWpPusherMigrator\WpPusherSource;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'RAN_MIGRATOR_PROOF_DISPOSABLE' ) ) {
	throw new RuntimeException( 'This helper requires the disposable WP-CLI driver.' );
}

function ran_booster_wp_pusher_migrator_current_boundary( bool $preflight = false ): void {
	$root = rtrim( ABSPATH, '/' );
	if ( is_multisite() || realpath( $root ) !== $root || getenv( 'RAN_MIGRATOR_WORDPRESS_PATH' ) !== $root
		|| WP_CONTENT_DIR !== $root . '/wp-content' || WP_PLUGIN_DIR !== WP_CONTENT_DIR . '/plugins'
		|| realpath( WP_CONTENT_DIR ) !== WP_CONTENT_DIR || realpath( WP_PLUGIN_DIR ) !== WP_PLUGIN_DIR
		|| 'localhost' !== wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_HOST )
		|| getenv( 'RAN_MIGRATOR_EXPECTED_SITE_URL' ) !== get_option( 'siteurl' ) ) {
		throw new RuntimeException( 'The helper refuses a noncanonical or unapproved WordPress site.' );
	}
	$marker = $root . '/.ran-booster-disposable-test-site';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read only the exact disposable-site authorization marker.
	if ( is_link( $marker ) || ! is_file( $marker ) || 'RAN Booster disposable test site' !== trim( (string) file_get_contents( $marker ) ) ) {
		throw new RuntimeException( 'The exact disposable-site marker is required.' );
	}
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ) {
		throw new RuntimeException( 'The fixture requires an authorized administrator.' );
	}
	if ( $preflight ) {
		return;
	}
	if ( ! is_plugin_active( 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php' )
		|| is_plugin_active( WpPusherSource::PLUGIN )
		|| ! has_action( 'admin_post_ran_booster_wp_pusher_migrator_package' )
		|| ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
		|| 4 !== constant( 'RAN_BOOSTER_PORTABILITY_API_VERSION' ) ) {
		throw new RuntimeException( 'The actual API4 Migrator route must be composed and WP Pusher inactive.' );
	}
	$reflection = new ReflectionClass( WpPusherSource::class );
	if ( WP_PLUGIN_DIR . '/ran-booster-wp-pusher-migrator/src/WpPusherSource.php' !== $reflection->getFileName() ) {
		throw new RuntimeException( 'The reader must come from the installed Migrator.' );
	}
}

function ran_booster_wp_pusher_migrator_current_package(): WpPusherPackage {
	return new WpPusherPackage( 734001, 'ran-migrator-fixture/plugin.php', 'fixtures/ran-migrator-fixture', 'main', 1, 1, 0, 'gh', 0, null );
}

/** @return array<string, mixed> */
function ran_booster_wp_pusher_migrator_current_run( string $phase ): array {
	global $wpdb;

	ran_booster_wp_pusher_migrator_current_boundary( 'preflight' === $phase );
	if ( 'preflight' === $phase ) {
		return array(
			'phase'      => 'preflight',
			'authorized' => true,
		);
	}
	$source   = new WpPusherSource();
	$expected = ran_booster_wp_pusher_migrator_current_package();
	$rows     = $source->packages();
	$ledger   = 'ran_booster_wp_pusher_migrator_fixture_owner';
	$owner    = get_option( $ledger, null );
	if ( 'seed' === $phase ) {
		if ( array() !== $rows || null !== $owner ) {
			throw new RuntimeException( 'Seeding requires empty source inventory and no existing fixture ownership.' );
		}
		if ( ! $source->supported_package_table_present() ) {
			if ( 1 !== preg_match( '/\A[A-Za-z0-9_]+\z/D', $wpdb->prefix ) ) {
				throw new RuntimeException( 'Invalid database prefix.' );
			}
			$table = $wpdb->prefix . 'wppusher_packages';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated prefix and constant schema; CREATE refuses an existing table and never replaces it.
			$created = $wpdb->query( "CREATE TABLE `{$table}` (`id` mediumint NOT NULL,`package` varchar(255) NOT NULL,`repository` varchar(255) NOT NULL,`branch` varchar(255) NOT NULL,`type` int NOT NULL,`status` int NOT NULL,`ptd` int NOT NULL,`host` varchar(10) NOT NULL,`private` int NOT NULL,`subdirectory` varchar(255) NULL,PRIMARY KEY (`id`))" );
			if ( false === $created ) {
				throw new RuntimeException( 'Could not create the absent source table.' );
			}
		}
		if ( ! add_option( $ledger, $expected->fingerprint(), '', false ) ) {
			throw new RuntimeException( 'Could not claim fixture ownership.' );
		}
		if ( 1 !== $wpdb->insert( $wpdb->prefix . 'wppusher_packages', $expected->to_array() ) ) {
			throw new RuntimeException( 'Could not insert the fixture row; retain ownership for diagnosis.' );
		}
		$rows = $source->packages();
	} elseif ( ! in_array( $phase, array( 'readback', 'cleanup' ), true ) ) {
		throw new RuntimeException( 'Expected seed, readback or cleanup.' );
	}
	$found = null;
	foreach ( $rows as $row ) {
		if ( $row->id === $expected->id ) {
			$found = $row;
		}
	}
	if ( 'cleanup' === $phase ) {
		if ( $owner !== $expected->fingerprint() ) {
			throw new RuntimeException( 'Cleanup requires this exact fixture ownership.' );
		}
		if ( null !== $found && ( ! hash_equals( $expected->fingerprint(), $found->fingerprint() ) || ! $source->delete_exact( $expected ) ) ) {
			throw new RuntimeException( 'Cleanup refuses a changed source row.' );
		}
		delete_option( $ledger );
		$found = null;
	}
	if ( 'seed' === $phase && ( null === $found || $found->fingerprint() !== $expected->fingerprint() ) ) {
		throw new RuntimeException( 'Seed readback did not match the real source fingerprint.' );
	}
	return array(
		'phase'              => $phase,
		'source_id'          => $expected->id,
		'present'            => null !== $found,
		'source_fingerprint' => $found?->fingerprint(),
		'row'                => $found?->to_array(),
		'route_composed'     => true,
	);
}

$ran_booster_wp_pusher_migrator_current_phase = getenv( 'RAN_MIGRATOR_FIXTURE_PHASE' );
WP_CLI::line( (string) wp_json_encode( ran_booster_wp_pusher_migrator_current_run( false === $ran_booster_wp_pusher_migrator_current_phase ? '' : $ran_booster_wp_pusher_migrator_current_phase ) ) );

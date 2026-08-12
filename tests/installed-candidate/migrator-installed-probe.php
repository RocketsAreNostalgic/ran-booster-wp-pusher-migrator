<?php

// Executed only through WP-CLI by migrator-installed-proof.sh.
// Do not add strict_types: WP-CLI eval-file wraps the file before evaluation.
// phpcs:disable -- This private hostile fixture intentionally uses exact local files, serialization and proof-owned SQL.

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'RAN_MIGRATOR_PROOF_DISPOSABLE' ) ) {
	throw new RuntimeException( 'The Migrator proof is restricted to its disposable WP-CLI driver.' );
}

/** @return string */
function ran_migrator_required_env( string $name ) {
	$value = getenv( $name );
	if ( false === $value || '' === $value ) {
		throw new RuntimeException( 'Missing proof input: ' . $name );
	}

	return $value;
}

function ran_migrator_assert_boundary(): void {
	$root    = realpath( ABSPATH );
	$content = realpath( WP_CONTENT_DIR );
	if ( false === $root || false === $content
		|| ! hash_equals( rtrim( ran_migrator_required_env( 'RAN_MIGRATOR_EXPECTED_ABSPATH' ), '/\\' ), rtrim( $root, '/\\' ) )
		|| ! hash_equals( rtrim( ran_migrator_required_env( 'RAN_MIGRATOR_EXPECTED_WP_CONTENT_DIR' ), '/\\' ), rtrim( $content, '/\\' ) ) ) {
		throw new RuntimeException( 'ABSPATH or WP_CONTENT_DIR escaped the authorized disposable site.' );
	}
	$marker = $root . DIRECTORY_SEPARATOR . '.ran-booster-disposable-test-site';
	if ( is_link( $marker ) || 'RAN Booster disposable test site' !== trim( (string) @file_get_contents( $marker ) )
		|| ! hash_equals( ran_migrator_required_env( 'RAN_MIGRATOR_EXPECTED_SITE_URL' ), (string) get_option( 'siteurl' ) ) ) {
		throw new RuntimeException( 'The marker or site URL does not match the caller-authorized fixture.' );
	}
}

/** @return array<mixed> */
function ran_migrator_active_snapshot() {
	$path    = ran_migrator_required_env( 'RAN_MIGRATOR_ACTIVE_SNAPSHOT' );
	$parent  = realpath( dirname( $path ) );
	$payload = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $parent || 1 !== preg_match( '#\A/private/tmp/ran-migrator-proof-recovery\.[A-Za-z0-9]+\z#D', $parent ) || false === $payload ) {
		throw new RuntimeException( 'The active_plugins recovery snapshot is unavailable.' );
	}
	$decoded = json_decode( $payload, true, 8, JSON_THROW_ON_ERROR );
	$bytes   = is_string( $decoded['serialized_base64'] ?? null ) ? base64_decode( $decoded['serialized_base64'], true ) : false;
	if ( array( 'schema', 'schema_version', 'serialized_base64', 'sha256' ) !== array_keys( $decoded )
		|| 'ran-migrator-active-plugins-recovery' !== $decoded['schema'] || 1 !== $decoded['schema_version']
		|| false === $bytes || ! hash_equals( (string) $decoded['sha256'], hash( 'sha256', $bytes ) ) ) {
		throw new RuntimeException( 'The active_plugins recovery snapshot is invalid.' );
	}
	$value = unserialize( $bytes, array( 'allowed_classes' => false ) );
	if ( ! is_array( $value ) || ! hash_equals( $bytes, serialize( $value ) ) ) {
		throw new RuntimeException( 'The active_plugins snapshot is not an exact array.' );
	}

	return $value;
}

function ran_migrator_load_source(): void {
	$autoload = WP_PLUGIN_DIR . '/ran-booster-wp-pusher-migrator/src/Autoloader.php';
	if ( ! is_file( $autoload ) ) {
		throw new RuntimeException( 'The installed Migrator autoloader is unavailable.' );
	}
	require_once $autoload;
	\RAN\BoosterWpPusherMigrator\Autoloader::register();
}

function ran_migrator_assert_http_blocker(): void {
	$blocker   = $GLOBALS['ran_migrator_proof_http_blocker'] ?? null;
	$hook      = $GLOBALS['wp_filter']['pre_http_request'] ?? null;
	$callbacks = is_object( $hook ) && is_array( $hook->callbacks ?? null ) ? $hook->callbacks : array();
	$last      = array_values( $callbacks[ PHP_INT_MAX ] ?? array() );
	$entry     = array_pop( $last );
	if ( ! $blocker instanceof Closure || PHP_INT_MAX !== array_key_last( $callbacks )
		|| ! is_array( $entry ) || ( $entry['function'] ?? null ) !== $blocker ) {
		throw new RuntimeException( 'The HTTP blocker is not the final pre-transport callback.' );
	}
}

function ran_migrator_expect_runtime_message( Closure $operation, string $expected ): void {
	try {
		$operation();
	} catch ( RuntimeException $error ) {
		if ( hash_equals( $expected, $error->getMessage() ) ) {
			return;
		}

		throw new RuntimeException( 'A refusal raised the wrong failure: ' . $error->getMessage(), 0, $error );
	}

	throw new RuntimeException( 'The expected refusal unexpectedly succeeded: ' . $expected );
}

/** @return list<string> */
function ran_migrator_owned_callbacks(): array {
	$callbacks = array();
	foreach ( $GLOBALS['wp_filter'] as $hookName => $hook ) {
		foreach ( is_object( $hook ) && is_array( $hook->callbacks ?? null ) ? $hook->callbacks : array() as $priority => $entries ) {
			foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
				$callback = is_array( $entry ) ? ( $entry['function'] ?? null ) : null;
				if ( ! is_array( $callback ) || ! is_object( $callback[0] ?? null )
					|| ! in_array( get_class( $callback[0] ), array( 'RAN\\BoosterWpPusherMigrator\\Plugin', 'RAN\\BoosterWpPusherMigrator\\MigrationRequestController' ), true ) ) {
					continue;
				}
				$callbacks[] = sprintf( '%s|%d|%s|%d', (string) $hookName, (int) $priority, (string) ( $callback[1] ?? '' ), (int) ( $entry['accepted_args'] ?? 1 ) );
			}
		}
	}
	sort( $callbacks, SORT_STRING );

	return $callbacks;
}

ran_migrator_assert_boundary();
$mode = ran_migrator_required_env( 'RAN_MIGRATOR_PROOF_MODE' );

if ( 'db-identity' === $mode ) {
	global $wpdb;
	$identity = $wpdb->get_row( 'SELECT DATABASE() AS database_name, @@hostname AS server_hostname, @@port AS server_port, @@socket AS server_socket', ARRAY_A );
	if ( ! is_array( $identity ) || array( 'database_name', 'server_hostname', 'server_port', 'server_socket' ) !== array_keys( $identity )
		|| in_array( '', array_map( 'strval', $identity ), true ) ) {
		throw new RuntimeException( 'WordPress could not prove its exact database server identity.' );
	}
	WP_CLI::line( implode( '|', array_map( 'strval', $identity ) ) );
	return;
}

if ( 'snapshot-active' === $mode ) {
	$active = get_option( 'active_plugins', null );
	if ( ! is_array( $active ) ) {
		throw new RuntimeException( 'The active_plugins baseline is not an array.' );
	}
	$bytes   = serialize( $active );
	$payload = wp_json_encode(
		array(
			'schema'            => 'ran-migrator-active-plugins-recovery',
			'schema_version'    => 1,
			'serialized_base64' => base64_encode( $bytes ),
			'sha256'            => hash( 'sha256', $bytes ),
		),
		JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	);
	$path = ran_migrator_required_env( 'RAN_MIGRATOR_ACTIVE_SNAPSHOT' );
	if ( strlen( $payload ) !== file_put_contents( $path, $payload, LOCK_EX ) || ! chmod( $path, 0600 ) ) {
		throw new RuntimeException( 'The active_plugins recovery snapshot could not be retained.' );
	}
	WP_CLI::success( 'Exact active_plugins baseline retained.' );
	return;
}

if ( 'compare-active' === $mode ) {
	$actual = get_option( 'active_plugins', null );
	if ( ! is_array( $actual ) || ! hash_equals( serialize( ran_migrator_active_snapshot() ), serialize( $actual ) ) ) {
		throw new RuntimeException( 'The exact sparse active_plugins baseline was not restored.' );
	}
	WP_CLI::success( 'Exact active_plugins baseline matches.' );
	return;
}

if ( 'set-order' === $mode ) {
	$order = ran_migrator_required_env( 'RAN_MIGRATOR_LOAD_ORDER' );
	if ( 'core-first' === $order ) {
		$active = array( 2 => 'ran-booster/ran-booster.php', 7 => 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php' );
	} elseif ( 'addon-first' === $order ) {
		$active = array( 2 => 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', 7 => 'ran-booster/ran-booster.php' );
	} else {
		throw new RuntimeException( 'Unsupported physical load order.' );
	}
	update_option( 'active_plugins', $active );
	if ( ! hash_equals( serialize( $active ), serialize( get_option( 'active_plugins', null ) ) ) ) {
		throw new RuntimeException( 'The requested sparse physical load order was not stored exactly.' );
	}
	WP_CLI::success( 'Requested physical load order stored.' );
	return;
}

if ( 'seed-fixture' === $mode ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wppusher_packages';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Disposable proof table restored from the full SQL baseline.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Disposable proof table with validated WordPress prefix.
	$created = $wpdb->query( "CREATE TABLE `{$table}` (`id` mediumint NOT NULL,`package` varchar(255) NOT NULL,`repository` varchar(255) NOT NULL,`branch` varchar(255) NOT NULL,`type` int NOT NULL,`status` int NOT NULL,`ptd` int NOT NULL,`host` varchar(10) NOT NULL,`private` int NOT NULL,`subdirectory` varchar(255) NULL,PRIMARY KEY (`id`))" );
	if ( false === $created ) {
		throw new RuntimeException( 'The exact WP Pusher fixture table could not be created.' );
	}
	$stylesheet = (string) get_option( 'stylesheet' );
	$rows       = array(
		array( 1, 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator', 'main', 1, 1, 0, 'gh', 0, null ),
		array( 2, 'ran-booster/ran-booster.php', 'workspace/core-public', 'stable', 1, 1, 0, 'bb', 0, null ),
		array( 3, 'wppusher/wppusher.php', 'workspace/private-legacy', 'main', 1, 1, 0, 'bb', 1, null ),
		array( 4, $stylesheet, 'RocketsAreNostalgic/theme-fixture', 'main', 2, 1, 0, 'gh', 0, null ),
		array( 5, 'unsupported-gitlab/plugin.php', 'group/unsupported', 'main', 1, 1, 0, 'gl', 0, null ),
	);
	foreach ( $rows as $row ) {
		$result = $wpdb->insert( $table, array_combine( array( 'id', 'package', 'repository', 'branch', 'type', 'status', 'ptd', 'host', 'private', 'subdirectory' ), $row ) );
		if ( 1 !== $result ) {
			throw new RuntimeException( 'A WP Pusher fixture row could not be inserted.' );
		}
	}
	WP_CLI::success( 'Exact WP Pusher fixture rows retained.' );
	return;
}

if ( 'source-cases' === $mode ) {
	ran_migrator_assert_http_blocker();
	if ( array() !== ( $GLOBALS['ran_migrator_proof_http_requests'] ?? null ) ) {
		throw new RuntimeException( 'A request occurred before the source-case proof began.' );
	}
	ran_migrator_load_source();
	$source   = new \RAN\BoosterWpPusherMigrator\WpPusherSource();
	$packages = $source->packages();
	if ( 5 !== count( $packages ) || array( 'gh', 'bb', 'bb', 'gh', 'gl' ) !== array_map( static fn ( $package ): string => $package->host, $packages ) ) {
		throw new RuntimeException( 'The exact plugin, theme and Bitbucket fixture inventory was not read.' );
	}
	$factory    = new \RAN\BoosterWpPusherMigrator\CandidateFactory();
	$stylesheet = (string) get_option( 'stylesheet' );
	$expectedCandidates = array(
		array( 'type' => 'plugin', 'identifier' => 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', 'provider' => 'gh', 'repository' => 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator', 'branch' => 'main', 'subdirectory' => null, 'credential_id' => null ),
		array( 'type' => 'plugin', 'identifier' => 'ran-booster/ran-booster.php', 'provider' => 'bb', 'repository' => 'workspace/core-public', 'branch' => 'stable', 'subdirectory' => null, 'credential_id' => null ),
		array( 'type' => 'plugin', 'identifier' => 'wppusher/wppusher.php', 'provider' => 'bb', 'repository' => 'workspace/private-legacy', 'branch' => 'main', 'subdirectory' => null, 'credential_id' => 'proof_profile' ),
		array( 'type' => 'theme', 'identifier' => $stylesheet, 'provider' => 'gh', 'repository' => 'RocketsAreNostalgic/theme-fixture', 'branch' => 'main', 'subdirectory' => null, 'credential_id' => null ),
	);
	foreach ( $expectedCandidates as $index => $expectedCandidate ) {
		$candidate = $factory->candidate( $packages[ $index ], 2 === $index ? 'proof_profile' : null );
		foreach ( $expectedCandidate as $field => $expectedValue ) {
			if ( $expectedValue !== ( $candidate[ $field ] ?? null ) ) {
				throw new RuntimeException( 'An installed candidate field was not mapped exactly: ' . $field );
			}
		}
	}
	ran_migrator_expect_runtime_message(
		static fn (): array => $factory->candidate( $packages[2] ),
		'Choose an existing Booster credential profile for this private repository.'
	);
	ran_migrator_expect_runtime_message(
		static fn (): array => $factory->candidate( $packages[4] ),
		'GitLab WP Pusher packages are not supported.'
	);
	$plugins = static fn (): array => array( 'wppusher/wppusher.php' => array( 'Version' => '3.0.13' ) );
	$activeSource = new \RAN\BoosterWpPusherMigrator\WpPusherSource( null, $plugins, static fn (): array => array( 'wppusher/wppusher.php' ) );
	ran_migrator_expect_runtime_message( static fn (): array => $activeSource->packages(), 'Deactivate WP Pusher before assessing retained packages.' );
	$wrongVersionSource = new \RAN\BoosterWpPusherMigrator\WpPusherSource( null, static fn (): array => array( 'wppusher/wppusher.php' => array( 'Version' => '3.0.12' ) ) );
	ran_migrator_expect_runtime_message( static fn (): array => $wrongVersionSource->packages(), 'Only retained WP Pusher 3.0.13 data is supported.' );
	global $wpdb;
	$old = $packages[0];
	$wpdb->update( $wpdb->prefix . 'wppusher_packages', array( 'branch' => 'changed-after-review' ), array( 'id' => $old->id ) );
	if ( $source->deleteExact( $old ) ) {
		throw new RuntimeException( 'Stale exact cleanup unexpectedly deleted the changed source row.' );
	}
	$wpdb->update( $wpdb->prefix . 'wppusher_packages', array( 'branch' => $old->branch ), array( 'id' => $old->id ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Disposable proof schema drift.
	$wpdb->query( "ALTER TABLE `{$wpdb->prefix}wppusher_packages` ADD `proof_extra` varchar(10) NULL" );
	ran_migrator_expect_runtime_message( static fn (): array => $source->packages(), 'The retained WP Pusher package schema is unsupported.' );
	ran_migrator_assert_http_blocker();
	if ( array() !== ( $GLOBALS['ran_migrator_proof_http_requests'] ?? null ) ) {
		throw new RuntimeException( 'A source/refusal case attempted provider or network contact.' );
	}
	WP_CLI::success( 'Supported, refusal, stale and schema cases passed without provider contact.' );
	return;
}

if ( 'compatible' === $mode ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$core     = get_plugin_data( WP_PLUGIN_DIR . '/ran-booster/ran-booster.php', false, false );
	$migrator = get_plugin_data( WP_PLUGIN_DIR . '/ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', false, false );
	$wpPusher = get_plugin_data( WP_PLUGIN_DIR . '/wppusher/wppusher.php', false, false );
	if ( '1.0.0-beta.15' !== $core['Version'] || '0.1.0-beta.6' !== $migrator['Version'] || '3.0.13' !== $wpPusher['Version'] ) {
		throw new RuntimeException( 'An installed plugin header is not the exact approved version.' );
	}
	if ( in_array( 'wppusher/wppusher.php', (array) get_option( 'active_plugins', array() ), true ) ) {
		throw new RuntimeException( 'WP Pusher must remain physically installed and inactive.' );
	}
	if ( ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' ) || 2 !== RAN_BOOSTER_PORTABILITY_API_VERSION
		|| ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' ) || 2 !== RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION ) {
		throw new RuntimeException( 'Core did not expose the exact required API 2 generations.' );
	}
	$expected = 'core-first' === ran_migrator_required_env( 'RAN_MIGRATOR_LOAD_ORDER' )
		? array( 'ran-booster/ran-booster.php', 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php' )
		: array( 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', 'ran-booster/ran-booster.php' );
	$loaded = array_values( array_filter( (array) ( $GLOBALS['ran_migrator_proof_loaded_plugins'] ?? array() ), static fn ( $plugin ): bool => in_array( $plugin, $expected, true ) ) );
	if ( $expected !== $loaded ) {
		throw new RuntimeException( 'The executed plugin_loaded order did not match the requested physical order.' );
	}
	$expectedCallbacks = array(
		'admin_enqueue_scripts|20|enqueueAssets|1',
		'admin_notices|10|renderCompatibilityNotice|1',
		'admin_post_ran_booster_wp_pusher_migrator_package|10|handleAdminPost|1',
		'ran_booster_admin_interaction_ready|10|captureAdminInteraction|1',
		'ran_booster_overview_render_migration_prompt|20|renderOverviewPrompt|1',
		'ran_booster_portability_ready|10|connect|1',
		'ran_booster_portability_render_migration_flows|20|renderPanel|1',
		'ran_booster_portability_render_migration_modes|20|renderMode|1',
	);
	sort( $expectedCallbacks, SORT_STRING );
	if ( $expectedCallbacks !== ran_migrator_owned_callbacks() ) {
		throw new RuntimeException( 'The exact installed Migrator callback set was not composed.' );
	}
	ran_migrator_assert_http_blocker();
	if ( array() !== ( $GLOBALS['ran_migrator_proof_http_requests'] ?? null ) ) {
		throw new RuntimeException( 'The compatible installed proof attempted provider/network contact.' );
	}
	WP_CLI::success( 'Exact installed headers, load order and native composition passed.' );
	return;
}

throw new RuntimeException( 'Unknown Migrator proof mode.' );

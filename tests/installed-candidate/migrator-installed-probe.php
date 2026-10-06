<?php


// Executed only through WP-CLI by migrator-installed-proof.sh.
// Do not add strict_types: WP-CLI eval-file wraps the file before evaluation.

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'RAN_MIGRATOR_PROOF_DISPOSABLE' ) ) {
	throw new RuntimeException( 'The Migrator proof is restricted to its disposable WP-CLI driver.' );
}

/** @return string */
function ran_booster_wp_pusher_migrator_required_env( string $name ) {
	$value = getenv( $name );
	if ( false === $value || '' === $value ) {
		throw new RuntimeException( 'Missing proof input: ' . $name );
	}

	return $value;
}

function ran_booster_wp_pusher_migrator_assert_boundary(): void {
	$root    = realpath( ABSPATH );
	$content = realpath( WP_CONTENT_DIR );
	if ( false === $root || false === $content
		|| ! hash_equals( rtrim( ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_EXPECTED_ABSPATH' ), '/\\' ), rtrim( $root, '/\\' ) )
		|| ! hash_equals( rtrim( ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_EXPECTED_WP_CONTENT_DIR' ), '/\\' ), rtrim( $content, '/\\' ) ) ) {
		throw new RuntimeException( 'ABSPATH or WP_CONTENT_DIR escaped the authorized disposable site.' );
	}
	$marker = $root . DIRECTORY_SEPARATOR . '.ran-booster-disposable-test-site';
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Disposable installed proof validates its caller-authorized local marker or private recovery file; failure aborts the proof.
	if ( is_link( $marker ) || 'RAN Booster disposable test site' !== trim( (string) @file_get_contents( $marker ) )
		|| ! hash_equals( ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_EXPECTED_SITE_URL' ), (string) get_option( 'siteurl' ) ) ) {
		throw new RuntimeException( 'The marker or site URL does not match the caller-authorized fixture.' );
	}
}

/** @return array<mixed> */
function ran_booster_wp_pusher_migrator_active_snapshot() {
	$path   = ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_ACTIVE_SNAPSHOT' );
	$parent = realpath( dirname( $path ) );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Disposable installed proof validates its caller-authorized local marker or private recovery file; failure aborts the proof.
	$payload = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $parent || 1 !== preg_match( '#\A/private/tmp/ran-migrator-proof-recovery\.[A-Za-z0-9]+\z#D', $parent ) || false === $payload ) {
		throw new RuntimeException( 'The active_plugins recovery snapshot is unavailable.' );
	}
	$decoded = json_decode( $payload, true, 8, JSON_THROW_ON_ERROR );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
	$bytes = is_string( $decoded['serialized_base64'] ?? null ) ? base64_decode( $decoded['serialized_base64'], true ) : false;
	if ( array( 'schema', 'schema_version', 'serialized_base64', 'sha256' ) !== array_keys( $decoded )
		|| 'ran-migrator-active-plugins-recovery' !== $decoded['schema'] || 1 !== $decoded['schema_version']
		|| false === $bytes || ! hash_equals( (string) $decoded['sha256'], hash( 'sha256', $bytes ) ) ) {
		throw new RuntimeException( 'The active_plugins recovery snapshot is invalid.' );
	}
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
	$value = unserialize( $bytes, array( 'allowed_classes' => false ) );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
	if ( ! is_array( $value ) || ! hash_equals( $bytes, serialize( $value ) ) ) {
		throw new RuntimeException( 'The active_plugins snapshot is not an exact array.' );
	}

	return $value;
}

function ran_booster_wp_pusher_migrator_load_source(): void {
	$autoload = WP_PLUGIN_DIR . '/ran-booster-wp-pusher-migrator/src/Autoloader.php';
	if ( ! is_file( $autoload ) ) {
		throw new RuntimeException( 'The installed Migrator autoloader is unavailable.' );
	}
	require_once $autoload;
	\RAN\BoosterWpPusherMigrator\Autoloader::register();
}

function ran_booster_wp_pusher_migrator_assert_http_blocker(): void {
	$blocker   = $GLOBALS['ran_booster_wp_pusher_migrator_proof_http_blocker'] ?? null;
	$hook      = $GLOBALS['wp_filter']['pre_http_request'] ?? null;
	$callbacks = is_object( $hook ) && is_array( $hook->callbacks ?? null ) ? $hook->callbacks : array();
	$last      = array_values( $callbacks[ PHP_INT_MAX ] ?? array() );
	$entry     = array_pop( $last );
	if ( ! $blocker instanceof Closure || PHP_INT_MAX !== array_key_last( $callbacks )
		|| ! is_array( $entry ) || ( $entry['function'] ?? null ) !== $blocker ) {
		throw new RuntimeException( 'The HTTP blocker is not the final pre-transport callback.' );
	}
}

function ran_booster_wp_pusher_migrator_expect_runtime_message( Closure $operation, string $expected ): void {
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
function ran_booster_wp_pusher_migrator_owned_callbacks(): array {
	$callbacks = array();
	foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook ) {
		foreach ( is_object( $hook ) && is_array( $hook->callbacks ?? null ) ? $hook->callbacks : array() as $priority => $entries ) {
			foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
				$callback = is_array( $entry ) ? ( $entry['function'] ?? null ) : null;
				if ( ! is_array( $callback ) || ! is_object( $callback[0] ?? null )
					|| ! in_array( get_class( $callback[0] ), array( 'RAN\\BoosterWpPusherMigrator\\Plugin', 'RAN\\BoosterWpPusherMigrator\\MigrationRequestController' ), true ) ) {
					continue;
				}
				$callbacks[] = sprintf( '%s|%d|%s|%d', (string) $hook_name, (int) $priority, (string) ( $callback[1] ?? '' ), (int) ( $entry['accepted_args'] ?? 1 ) );
			}
		}
	}
	sort( $callbacks, SORT_STRING );

	return $callbacks;
}

ran_booster_wp_pusher_migrator_assert_boundary();
$ran_booster_wp_pusher_migrator_mode = ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_PROOF_MODE' );

if ( 'db-identity' === $ran_booster_wp_pusher_migrator_mode ) {
	global $wpdb;
	$ran_booster_wp_pusher_migrator_identity = $wpdb->get_row( 'SELECT DATABASE() AS database_name, @@hostname AS server_hostname, @@port AS server_port, @@socket AS server_socket', ARRAY_A );
	if ( ! is_array( $ran_booster_wp_pusher_migrator_identity ) || array( 'database_name', 'server_hostname', 'server_port', 'server_socket' ) !== array_keys( $ran_booster_wp_pusher_migrator_identity )
		|| in_array( '', array_map( 'strval', $ran_booster_wp_pusher_migrator_identity ), true ) ) {
		throw new RuntimeException( 'WordPress could not prove its exact database server identity.' );
	}
	WP_CLI::line( implode( '|', array_map( 'strval', $ran_booster_wp_pusher_migrator_identity ) ) );
	return;
}

if ( 'snapshot-active' === $ran_booster_wp_pusher_migrator_mode ) {
	$ran_booster_wp_pusher_migrator_active = get_option( 'active_plugins', null );
	if ( ! is_array( $ran_booster_wp_pusher_migrator_active ) ) {
		throw new RuntimeException( 'The active_plugins baseline is not an array.' );
	}
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
	$ran_booster_wp_pusher_migrator_bytes   = serialize( $ran_booster_wp_pusher_migrator_active );
	$ran_booster_wp_pusher_migrator_payload = wp_json_encode(
		array(
			'schema'            => 'ran-migrator-active-plugins-recovery',
			'schema_version'    => 1,
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
			'serialized_base64' => base64_encode( $ran_booster_wp_pusher_migrator_bytes ),
			'sha256'            => hash( 'sha256', $ran_booster_wp_pusher_migrator_bytes ),
		),
		JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	);
	$ran_booster_wp_pusher_migrator_path    = ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_ACTIVE_SNAPSHOT' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Disposable installed proof validates its caller-authorized local marker or private recovery file; failure aborts the proof.
	if ( strlen( $ran_booster_wp_pusher_migrator_payload ) !== file_put_contents( $ran_booster_wp_pusher_migrator_path, $ran_booster_wp_pusher_migrator_payload, LOCK_EX ) || ! chmod( $ran_booster_wp_pusher_migrator_path, 0600 ) ) {
		throw new RuntimeException( 'The active_plugins recovery snapshot could not be retained.' );
	}
	WP_CLI::success( 'Exact active_plugins baseline retained.' );
	return;
}

if ( 'compare-active' === $ran_booster_wp_pusher_migrator_mode ) {
	$ran_booster_wp_pusher_migrator_actual = get_option( 'active_plugins', null );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
	if ( ! is_array( $ran_booster_wp_pusher_migrator_actual ) || ! hash_equals( serialize( ran_booster_wp_pusher_migrator_active_snapshot() ), serialize( $ran_booster_wp_pusher_migrator_actual ) ) ) {
		throw new RuntimeException( 'The exact sparse active_plugins baseline was not restored.' );
	}
	WP_CLI::success( 'Exact active_plugins baseline matches.' );
	return;
}

if ( 'set-order' === $ran_booster_wp_pusher_migrator_mode ) {
	$ran_booster_wp_pusher_migrator_order = ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_LOAD_ORDER' );
	if ( 'core-first' === $ran_booster_wp_pusher_migrator_order ) {
		$ran_booster_wp_pusher_migrator_active = array(
			2 => 'ran-booster/ran-booster.php',
			7 => 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php',
		);
	} elseif ( 'addon-first' === $ran_booster_wp_pusher_migrator_order ) {
		$ran_booster_wp_pusher_migrator_active = array(
			2 => 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php',
			7 => 'ran-booster/ran-booster.php',
		);
	} else {
		throw new RuntimeException( 'Unsupported physical load order.' );
	}
	update_option( 'active_plugins', $ran_booster_wp_pusher_migrator_active );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Disposable proof preserves exact active_plugins recovery bytes; decoding rejects classes and verifies the digest and round trip.
	if ( ! hash_equals( serialize( $ran_booster_wp_pusher_migrator_active ), serialize( get_option( 'active_plugins', null ) ) ) ) {
		throw new RuntimeException( 'The requested sparse physical load order was not stored exactly.' );
	}
	WP_CLI::success( 'Requested physical load order stored.' );
	return;
}

if ( 'seed-fixture' === $ran_booster_wp_pusher_migrator_mode ) {
	global $wpdb;
	$ran_booster_wp_pusher_migrator_table = $wpdb->prefix . 'wppusher_packages';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Disposable proof table restored from the full SQL baseline.
	$wpdb->query( "DROP TABLE IF EXISTS `{$ran_booster_wp_pusher_migrator_table}`" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Disposable proof table with validated WordPress prefix.
	$ran_booster_wp_pusher_migrator_created = $wpdb->query( "CREATE TABLE `{$ran_booster_wp_pusher_migrator_table}` (`id` mediumint NOT NULL,`package` varchar(255) NOT NULL,`repository` varchar(255) NOT NULL,`branch` varchar(255) NOT NULL,`type` int NOT NULL,`status` int NOT NULL,`ptd` int NOT NULL,`host` varchar(10) NOT NULL,`private` int NOT NULL,`subdirectory` varchar(255) NULL,PRIMARY KEY (`id`))" );
	if ( false === $ran_booster_wp_pusher_migrator_created ) {
		throw new RuntimeException( 'The exact WP Pusher fixture table could not be created.' );
	}
	$ran_booster_wp_pusher_migrator_stylesheet = (string) get_option( 'stylesheet' );
	$ran_booster_wp_pusher_migrator_rows       = array(
		array( 1, 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator', 'main', 1, 1, 0, 'gh', 0, null ),
		array( 2, 'ran-booster/ran-booster.php', 'workspace/core-public', 'stable', 1, 1, 0, 'bb', 0, null ),
		array( 3, 'wppusher/wppusher.php', 'workspace/private-legacy', 'main', 1, 1, 0, 'bb', 1, null ),
		array( 4, $ran_booster_wp_pusher_migrator_stylesheet, 'RocketsAreNostalgic/theme-fixture', 'main', 2, 1, 0, 'gh', 0, null ),
		array( 5, 'unsupported-gitlab/plugin.php', 'group/unsupported', 'main', 1, 1, 0, 'gl', 0, null ),
	);
	foreach ( $ran_booster_wp_pusher_migrator_rows as $ran_booster_wp_pusher_migrator_row ) {
		$ran_booster_wp_pusher_migrator_result = $wpdb->insert( $ran_booster_wp_pusher_migrator_table, array_combine( array( 'id', 'package', 'repository', 'branch', 'type', 'status', 'ptd', 'host', 'private', 'subdirectory' ), $ran_booster_wp_pusher_migrator_row ) );
		if ( 1 !== $ran_booster_wp_pusher_migrator_result ) {
			throw new RuntimeException( 'A WP Pusher fixture row could not be inserted.' );
		}
	}
	WP_CLI::success( 'Exact WP Pusher fixture rows retained.' );
	return;
}

if ( 'source-cases' === $ran_booster_wp_pusher_migrator_mode ) {
	ran_booster_wp_pusher_migrator_assert_http_blocker();
	if ( array() !== ( $GLOBALS['ran_booster_wp_pusher_migrator_proof_http_requests'] ?? null ) ) {
		throw new RuntimeException( 'A request occurred before the source-case proof began.' );
	}
	ran_booster_wp_pusher_migrator_load_source();
	$ran_booster_wp_pusher_migrator_source   = new \RAN\BoosterWpPusherMigrator\WpPusherSource();
	$ran_booster_wp_pusher_migrator_packages = $ran_booster_wp_pusher_migrator_source->packages();
	if ( 5 !== count( $ran_booster_wp_pusher_migrator_packages ) || array( 'gh', 'bb', 'bb', 'gh', 'gl' ) !== array_map( static fn ( $ran_booster_wp_pusher_migrator_package ): string => $ran_booster_wp_pusher_migrator_package->host, $ran_booster_wp_pusher_migrator_packages ) ) {
		throw new RuntimeException( 'The exact plugin, theme and Bitbucket fixture inventory was not read.' );
	}
	$ran_booster_wp_pusher_migrator_factory             = new \RAN\BoosterWpPusherMigrator\CandidateFactory();
	$ran_booster_wp_pusher_migrator_stylesheet          = (string) get_option( 'stylesheet' );
	$ran_booster_wp_pusher_migrator_expected_candidates = array(
		array(
			'type'          => 'plugin',
			'identifier'    => 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php',
			'provider'      => 'gh',
			'repository'    => 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator',
			'branch'        => 'main',
			'subdirectory'  => null,
			'credential_id' => null,
		),
		array(
			'type'          => 'plugin',
			'identifier'    => 'ran-booster/ran-booster.php',
			'provider'      => 'bb',
			'repository'    => 'workspace/core-public',
			'branch'        => 'stable',
			'subdirectory'  => null,
			'credential_id' => null,
		),
		array(
			'type'          => 'plugin',
			'identifier'    => 'wppusher/wppusher.php',
			'provider'      => 'bb',
			'repository'    => 'workspace/private-legacy',
			'branch'        => 'main',
			'subdirectory'  => null,
			'credential_id' => 'proof_profile',
		),
		array(
			'type'          => 'theme',
			'identifier'    => $ran_booster_wp_pusher_migrator_stylesheet,
			'provider'      => 'gh',
			'repository'    => 'RocketsAreNostalgic/theme-fixture',
			'branch'        => 'main',
			'subdirectory'  => null,
			'credential_id' => null,
		),
	);
	foreach ( $ran_booster_wp_pusher_migrator_expected_candidates as $ran_booster_wp_pusher_migrator_index => $ran_booster_wp_pusher_migrator_expected_candidate ) {
		$ran_booster_wp_pusher_migrator_candidate = $ran_booster_wp_pusher_migrator_factory->candidate( $ran_booster_wp_pusher_migrator_packages[ $ran_booster_wp_pusher_migrator_index ], 2 === $ran_booster_wp_pusher_migrator_index ? 'proof_profile' : null );
		foreach ( $ran_booster_wp_pusher_migrator_expected_candidate as $ran_booster_wp_pusher_migrator_field => $ran_booster_wp_pusher_migrator_expected_value ) {
			if ( ( $ran_booster_wp_pusher_migrator_candidate[ $ran_booster_wp_pusher_migrator_field ] ?? null ) !== $ran_booster_wp_pusher_migrator_expected_value ) {
				throw new RuntimeException( 'An installed candidate field was not mapped exactly: ' . $ran_booster_wp_pusher_migrator_field );
			}
		}
	}
	ran_booster_wp_pusher_migrator_expect_runtime_message(
		static fn (): array => $ran_booster_wp_pusher_migrator_factory->candidate( $ran_booster_wp_pusher_migrator_packages[2] ),
		'Choose an existing Booster credential profile for this private repository.'
	);
	ran_booster_wp_pusher_migrator_expect_runtime_message(
		static fn (): array => $ran_booster_wp_pusher_migrator_factory->candidate( $ran_booster_wp_pusher_migrator_packages[4] ),
		'GitLab WP Pusher packages are not supported.'
	);
	$ran_booster_wp_pusher_migrator_plugins       = static fn (): array => array( 'wppusher/wppusher.php' => array( 'Version' => '3.0.13' ) );
	$ran_booster_wp_pusher_migrator_active_source = new \RAN\BoosterWpPusherMigrator\WpPusherSource( null, $ran_booster_wp_pusher_migrator_plugins, static fn (): array => array( 'wppusher/wppusher.php' ) );
	ran_booster_wp_pusher_migrator_expect_runtime_message( static fn (): array => $ran_booster_wp_pusher_migrator_active_source->packages(), 'Deactivate WP Pusher before assessing retained packages.' );
	$ran_booster_wp_pusher_migrator_wrong_version_source = new \RAN\BoosterWpPusherMigrator\WpPusherSource( null, static fn (): array => array( 'wppusher/wppusher.php' => array( 'Version' => '3.0.12' ) ) );
	ran_booster_wp_pusher_migrator_expect_runtime_message( static fn (): array => $ran_booster_wp_pusher_migrator_wrong_version_source->packages(), 'Only retained WP Pusher 3.0.13 data is supported.' );
	global $wpdb;
	$ran_booster_wp_pusher_migrator_old = $ran_booster_wp_pusher_migrator_packages[0];
	$wpdb->update( $wpdb->prefix . 'wppusher_packages', array( 'branch' => 'changed-after-review' ), array( 'id' => $ran_booster_wp_pusher_migrator_old->id ) );
	if ( $ran_booster_wp_pusher_migrator_source->delete_exact( $ran_booster_wp_pusher_migrator_old ) ) {
		throw new RuntimeException( 'Stale exact cleanup unexpectedly deleted the changed source row.' );
	}
	$wpdb->update( $wpdb->prefix . 'wppusher_packages', array( 'branch' => $ran_booster_wp_pusher_migrator_old->branch ), array( 'id' => $ran_booster_wp_pusher_migrator_old->id ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Disposable proof schema drift.
	$wpdb->query( "ALTER TABLE `{$wpdb->prefix}wppusher_packages` ADD `proof_extra` varchar(10) NULL" );
	ran_booster_wp_pusher_migrator_expect_runtime_message( static fn (): array => $ran_booster_wp_pusher_migrator_source->packages(), 'The retained WP Pusher package schema is unsupported.' );
	ran_booster_wp_pusher_migrator_assert_http_blocker();
	if ( array() !== ( $GLOBALS['ran_booster_wp_pusher_migrator_proof_http_requests'] ?? null ) ) {
		throw new RuntimeException( 'A source/refusal case attempted provider or network contact.' );
	}
	WP_CLI::success( 'Supported, refusal, stale and schema cases passed without provider contact.' );
	return;
}

if ( 'compatible' === $ran_booster_wp_pusher_migrator_mode ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$ran_booster_wp_pusher_migrator_core      = get_plugin_data( WP_PLUGIN_DIR . '/ran-booster/ran-booster.php', false, false );
	$ran_booster_wp_pusher_migrator_migrator  = get_plugin_data( WP_PLUGIN_DIR . '/ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', false, false );
	$ran_booster_wp_pusher_migrator_wp_pusher = get_plugin_data( WP_PLUGIN_DIR . '/wppusher/wppusher.php', false, false );
	if ( '1.0.0-beta.22' !== $ran_booster_wp_pusher_migrator_core['Version'] || '0.1.0-beta.7' !== $ran_booster_wp_pusher_migrator_migrator['Version'] || '3.0.13' !== $ran_booster_wp_pusher_migrator_wp_pusher['Version'] ) {
		throw new RuntimeException( 'An installed plugin header is not the exact approved version.' );
	}
	if ( in_array( 'wppusher/wppusher.php', (array) get_option( 'active_plugins', array() ), true ) ) {
		throw new RuntimeException( 'WP Pusher must remain physically installed and inactive.' );
	}
	if ( ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' ) || 2 !== RAN_BOOSTER_PORTABILITY_API_VERSION
		|| ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' ) || 2 !== RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION ) {
		throw new RuntimeException( 'Core did not expose the exact required API 2 generations.' );
	}
	$ran_booster_wp_pusher_migrator_expected = 'core-first' === ran_booster_wp_pusher_migrator_required_env( 'RAN_MIGRATOR_LOAD_ORDER' )
		? array( 'ran-booster/ran-booster.php', 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php' )
		: array( 'ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php', 'ran-booster/ran-booster.php' );
	$ran_booster_wp_pusher_migrator_loaded   = array_values( array_filter( (array) ( $GLOBALS['ran_booster_wp_pusher_migrator_proof_loaded_plugins'] ?? array() ), static fn ( $ran_booster_wp_pusher_migrator_plugin ): bool => in_array( $ran_booster_wp_pusher_migrator_plugin, $ran_booster_wp_pusher_migrator_expected, true ) ) );
	if ( $ran_booster_wp_pusher_migrator_expected !== $ran_booster_wp_pusher_migrator_loaded ) {
		throw new RuntimeException( 'The executed plugin_loaded order did not match the requested physical order.' );
	}
	$ran_booster_wp_pusher_migrator_expected_callbacks = array(
		'admin_enqueue_scripts|20|enqueueAssets|1',
		'admin_notices|10|renderCompatibilityNotice|1',
		'admin_post_ran_booster_wp_pusher_migrator_package|10|handleAdminPost|1',
		'ran_booster_admin_interaction_ready|10|captureAdminInteraction|1',
		'ran_booster_overview_render_migration_prompt|20|renderOverviewPrompt|1',
		'ran_booster_portability_ready|10|connect|1',
		'ran_booster_portability_render_migration_flows|20|renderPanel|1',
		'ran_booster_portability_render_migration_modes|20|renderMode|1',
	);
	sort( $ran_booster_wp_pusher_migrator_expected_callbacks, SORT_STRING );
	if ( ran_booster_wp_pusher_migrator_owned_callbacks() !== $ran_booster_wp_pusher_migrator_expected_callbacks ) {
		throw new RuntimeException( 'The exact installed Migrator callback set was not composed.' );
	}
	ran_booster_wp_pusher_migrator_assert_http_blocker();
	if ( array() !== ( $GLOBALS['ran_booster_wp_pusher_migrator_proof_http_requests'] ?? null ) ) {
		throw new RuntimeException( 'The compatible installed proof attempted provider/network contact.' );
	}
	WP_CLI::success( 'Exact installed headers, load order and native composition passed.' );
	return;
}

throw new RuntimeException( 'Unknown Migrator proof mode.' );

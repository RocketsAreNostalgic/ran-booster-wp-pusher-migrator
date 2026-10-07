<?php


declare(strict_types=1);

if ( ! defined( 'ARRAY_A' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Supply the exact WordPress or Core capability constant consumed by the host-contract fixture.
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Supply the exact WordPress or Core capability constant consumed by the host-contract fixture.
	define( 'ABSPATH', '/tmp/wordpress/' );
}

/** @param callable|array{class-string,string}|string $callback */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function add_action( string $hook, callable|array|string $callback, int $priority = 10, int $accepted_args = 1 ): void {
	$GLOBALS['ran_booster_wp_pusher_migrator_test_hooks'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function esc_html( mixed $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function esc_attr( mixed $value ): string {
	return esc_html( $value );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function esc_url( mixed $value ): string {
	return esc_html( $value );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function esc_html__( string $value, string $domain = '' ): string {
	unset( $domain );

	return $value;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function esc_html_e( string $value, string $domain = '' ): void {
	unset( $domain );
	echo esc_html( $value );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function __( string $value, string $domain = '' ): string {
	unset( $domain );

	return $value;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '">';
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function current_user_can( string $capability ): bool {
	$GLOBALS['ran_booster_wp_pusher_migrator_test_events'][] = 'capability:' . $capability;

	return $GLOBALS['ran_booster_wp_pusher_migrator_test_capabilities'][ $capability ]
		?? $GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage']
		?? true;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function wp_unslash( mixed $value ): mixed {
	return $value;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ) ?? '';
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function sanitize_text_field( string $value ): string {
	return trim( preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '' );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function absint( mixed $value ): int {
	return abs( (int) $value );
}

function check_admin_referer( string $action ): void {
	$GLOBALS['ran_booster_wp_pusher_migrator_test_events'][] = 'nonce:' . $action;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test stub verifies the captured nonce immediately below.
	$nonce = $_POST['_wpnonce'] ?? null;
	if ( ! is_string( $nonce )
		|| ! hash_equals( $action, $nonce ) ) {
		throw new Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception( 'Invalid nonce.', 'Invalid request', array( 'response' => 403 ) );
	}
}

function wp_create_nonce( string $action ): string {
	return 'core-nonce:' . $action;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function get_option( string $option, mixed $default_value = false ): mixed {
	unset( $option );

	return $default_value;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function get_site_option( string $option, mixed $default_value = false ): mixed {
	unset( $option );

	return $default_value;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function is_multisite(): bool {
	return false;
}

/** @param array<string, mixed> $args */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function wp_die( string $message, string $title = '', array $args = array() ): never {
	throw new Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception( $message, $title, $args );
}

/** @return array<string, array<string, mixed>> */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- The WordPress stand-in must retain the exact global function name called by the code under test.
function get_plugins(): array {
	return $GLOBALS['ran_booster_wp_pusher_migrator_test_plugins'] ?? array(
		'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ),
	);
}

require_once __DIR__ . '/fixtures/Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception.php';
require_once __DIR__ . '/fixtures/PortabilityApi.php';
require_once __DIR__ . '/fixtures/AdminInteractionApi.php';
require_once dirname( __DIR__ ) . '/src/Autoloader.php';
\RAN\BoosterWpPusherMigrator\Autoloader::register();

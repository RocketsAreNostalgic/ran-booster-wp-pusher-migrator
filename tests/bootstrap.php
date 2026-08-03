<?php

declare(strict_types=1);

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

function esc_html( mixed $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( mixed $value ): string {
	return esc_html( $value );
}

function esc_url( mixed $value ): string {
	return esc_html( $value );
}

function esc_html__( string $value, string $domain = '' ): string {
	unset( $domain );

	return $value;
}

function esc_html_e( string $value, string $domain = '' ): void {
	unset( $domain );
	echo esc_html( $value );
}

function __( string $value, string $domain = '' ): string {
	unset( $domain );

	return $value;
}

function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '">';
}

function current_user_can( string $capability ): bool {
	$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'capability:' . $capability;

	return $GLOBALS['ran_booster_wp_pusher_test_can_manage'] ?? true;
}

function wp_unslash( mixed $value ): mixed {
	return $value;
}

function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ) ?? '';
}

function sanitize_text_field( string $value ): string {
	return trim( preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '' );
}

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function check_admin_referer( string $action ): void {
	$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'nonce:' . $action;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test stub verifies the captured nonce immediately below.
	$nonce = $_POST['_wpnonce'] ?? null;
	if ( ! is_string( $nonce )
		|| ! hash_equals( $action, $nonce ) ) {
		throw new WpDieException( 'Invalid nonce.', 'Invalid request', array( 'response' => 403 ) );
	}
}

function wp_create_nonce( string $action ): string {
	return 'core-nonce:' . $action;
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

/** @param array<string, mixed> $args */
function wp_die( string $message, string $title = '', array $args = array() ): never {
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test stub captures arguments and never emits them.
	throw new WpDieException( $message, $title, $args );
}

/** @return array<string, array<string, mixed>> */
function get_plugins(): array {
	return $GLOBALS['ran_booster_wp_pusher_test_plugins'] ?? array(
		'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ),
	);
}

require_once __DIR__ . '/fixtures/WpDieException.php';
require_once __DIR__ . '/fixtures/PortabilityApi.php';
require_once __DIR__ . '/fixtures/AdminInteractionApi.php';
require_once dirname( __DIR__ ) . '/src/Autoloader.php';
\RAN\BoosterWpPusherMigrator\Autoloader::register();

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

require_once __DIR__ . '/fixtures/LoggingApi.php';
require_once __DIR__ . '/fixtures/PortabilityApi.php';
require_once dirname( __DIR__ ) . '/src/Autoloader.php';
\RAN\BoosterWpPusherMigrator\Autoloader::register();

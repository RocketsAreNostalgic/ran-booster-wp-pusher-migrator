<?php

// Disposable-site helper loaded before ordinary plugins by the owning shell driver.

if ( '1' !== getenv( 'RAN_MIGRATOR_PROOF_DISPOSABLE' ) || ! defined( 'ABSPATH' ) ) {
	return;
}

$GLOBALS['ran_migrator_proof_loaded_plugins'] = array();
$GLOBALS['ran_migrator_proof_http_requests']  = array();

add_action(
	'plugin_loaded',
	static function ( string $plugin ): void {
		$GLOBALS['ran_migrator_proof_loaded_plugins'][] = plugin_basename( $plugin );
	},
	-999999,
	1
);

$GLOBALS['ran_migrator_proof_http_blocker'] = static function ( mixed $response, array $arguments, string $url ): WP_Error {
	unset( $response, $arguments );
	$GLOBALS['ran_migrator_proof_http_requests'][] = hash( 'sha256', $url );

	return new WP_Error( 'ran_migrator_proof_network_blocked', 'Network access is disabled by the installed-candidate proof.' );
};

add_filter( 'pre_http_request', $GLOBALS['ran_migrator_proof_http_blocker'], PHP_INT_MAX, 3 );

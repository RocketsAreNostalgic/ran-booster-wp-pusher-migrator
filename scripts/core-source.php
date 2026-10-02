<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\SourceContract;

use InvalidArgumentException;

require_once __DIR__ . '/core-certification.php';

/** @return array{commit:string,tree:string} */
function read_core_source( string $manifest_path ): array {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI reads a reviewed development dependency identity.
	$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
	$source   = $manifest['extra']['ran-booster-core-source'] ?? null;
	if ( ! is_array( $source ) ) {
		throw new InvalidArgumentException( 'Pinned Core source tuple is missing.' );
	}
	$keys = array_keys( $source );
	sort( $keys );
	if ( array( 'commit', 'tree' ) !== $keys ) {
		throw new InvalidArgumentException( 'Pinned Core source requires exactly commit and tree.' );
	}
	foreach ( $source as $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A[0-9a-f]{40}\z/D', $value ) ) {
			throw new InvalidArgumentException( 'Pinned Core source identities must be full lowercase hexadecimal hashes.' );
		}
	}
	return $source;
}

if ( PHP_SAPI === 'cli' && isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated exact commit for the CLI setup contract.
	echo read_core_source( $argv[1] ?? '' )['commit'];
}

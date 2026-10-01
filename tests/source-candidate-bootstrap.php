<?php
/** Exact-source analysis gate; deliberately not released-host certification. */
declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\SourceCandidate;

use RuntimeException;
use function RAN\BoosterWpPusherMigrator\Certification\command_output;

require_once dirname( __DIR__ ) . '/scripts/core-certification.php';

/**
 * Verify source bytes, even with Git assume-unchanged flags.
 *
 * @param list<string> $scope Committed source roots used by this proof.
 */
function verify_source( string $path, string $commit, array $scope = array( 'RAN/', 'autoload.php', 'composer.lock' ) ): void {
	if ( 1 !== preg_match( '/\A[0-9a-f]{40}\z/D', $commit ) || realpath( $path ) !== $path ) {
		throw new RuntimeException( 'Source candidate requires a canonical path and exact full commit.' );
	}
	if ( command_output( array( 'git', '--no-replace-objects', '-C', $path, 'rev-parse', '--show-toplevel' ) ) !== $path
		|| command_output( array( 'git', '--no-replace-objects', '-C', $path, 'rev-parse', 'HEAD' ) ) !== $commit ) {
		throw new RuntimeException( 'Source candidate checkout identity differs from the requested commit.' );
	}
	$entries = command_output( array( 'git', '--no-replace-objects', '-C', $path, 'ls-tree', '-r', '-z', 'HEAD', '--', ...$scope ) );
	if ( '' === $entries ) {
		throw new RuntimeException( 'Source candidate declarations are missing.' );
	}
	foreach ( explode( "\0", $entries ) as $entry ) {
		if ( 1 !== preg_match( '/\A100(?:644|755) blob ([0-9a-f]{40})\t(.+)\z/sD', $entry, $matches ) ) {
			throw new RuntimeException( 'Source candidate requires ordinary source files.' );
		}
		$file = $path . '/' . $matches[2];
		if ( ! is_file( $file ) || is_link( $file ) || realpath( $file ) !== $file ) {
			throw new RuntimeException( 'Source candidate file is missing or linked.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI verifies source blobs without executing them.
		$contents = file_get_contents( $file );
		if ( false === $contents || ! hash_equals( $matches[1], sha1( 'blob ' . strlen( $contents ) . "\0" . $contents ) ) ) {
			throw new RuntimeException( 'Source candidate declaration bytes differ from the commit.' );
		}
	}
	// Reject new declaration files too, including ignored additions beneath RAN.
	if ( '' !== command_output( array( 'git', '--no-replace-objects', '-C', $path, 'status', '--porcelain', '--untracked-files=all', '--ignored', '--', ...$scope ) ) ) {
		throw new RuntimeException( 'Source candidate declaration tree is not clean.' );
	}
}

verify_source(
	(string) getenv( 'RAN_MIGRATOR_SOURCE_CORE' ),
	(string) getenv( 'RAN_MIGRATOR_SOURCE_CORE_SHA' )
);

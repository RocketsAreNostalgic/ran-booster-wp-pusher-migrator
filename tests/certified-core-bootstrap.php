<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Analysis;

use RuntimeException;

use function RAN\BoosterWpPusherMigrator\Certification\assert_core_certification_checkout;
use function RAN\BoosterWpPusherMigrator\Certification\command_output;
use function RAN\BoosterWpPusherMigrator\Certification\read_core_certification;

require_once dirname( __DIR__ ) . '/scripts/core-certification.php';

// Discover actual certified declarations without loading Core or PHPUnit doubles.
$ran_booster_wp_pusher_migrator_analysis_core = dirname( __DIR__ ) . '/vendor/ran-certified-core/source';
if ( realpath( $ran_booster_wp_pusher_migrator_analysis_core ) !== command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'rev-parse', '--show-toplevel' ) ) ) {
	throw new RuntimeException( 'Analysis requires its own certified Core checkout. Run composer analysis:setup.' );
}
$ran_booster_wp_pusher_migrator_analysis_certification = read_core_certification( dirname( __DIR__ ) . '/composer.json' );
assert_core_certification_checkout(
	$ran_booster_wp_pusher_migrator_analysis_certification,
	command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'rev-parse', 'HEAD' ) ),
	command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'rev-parse', '--verify', 'refs/tags/' . $ran_booster_wp_pusher_migrator_analysis_certification['tag'] . '^{commit}' ) )
);
if ( '' !== command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'status', '--porcelain', '--untracked-files=all', '--ignored' ) ) ) {
	throw new RuntimeException( 'Analysis requires unmodified certified Core source.' );
}

/** Verify scanned bytes independently of index flags and Git status caching. */
function verify_scanned_core_source( string $core_path ): void {
	$entries = command_output( array( 'git', '--no-replace-objects', '-C', $core_path, 'ls-tree', '-r', '-z', 'HEAD', '--', 'RAN/' ) );
	if ( '' === $entries ) {
		throw new RuntimeException( 'Analysis requires certified Core declarations.' );
	}
	foreach ( explode( "\0", $entries ) as $entry ) {
		if ( 1 !== preg_match( '/\A100(?:644|755) blob ([0-9a-f]{40})\t(.+)\z/sD', $entry, $matches ) ) {
			throw new RuntimeException( 'Analysis requires ordinary certified Core source files.' );
		}
		$path = realpath( $core_path ) . '/' . $matches[2];
		if ( ! is_file( $path ) || is_link( $path ) || realpath( $path ) !== $path ) {
			throw new RuntimeException( 'Analysis requires unmodified certified Core source.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI compares scanned source with certified Git blobs.
		$contents = file_get_contents( $path );
		if ( false === $contents || ! hash_equals( $matches[1], sha1( 'blob ' . strlen( $contents ) . "\0" . $contents ) ) ) {
			throw new RuntimeException( 'Analysis requires unmodified certified Core source.' );
		}
	}
}

verify_scanned_core_source( $ran_booster_wp_pusher_migrator_analysis_core );

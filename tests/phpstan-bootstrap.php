<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Analysis;

use RuntimeException;

use function RAN\BoosterWpPusherMigrator\Certification\command_output;
use function RAN\BoosterWpPusherMigrator\SourceContract\read_core_source;

require_once dirname( __DIR__ ) . '/scripts/core-source.php';

// Discover real pinned source declarations; this is not released-host certification.
$ran_booster_wp_pusher_migrator_analysis_core = dirname( __DIR__ ) . '/vendor/ran-source-core/source';
if ( realpath( $ran_booster_wp_pusher_migrator_analysis_core ) !== command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'rev-parse', '--show-toplevel' ) ) ) {
	throw new RuntimeException( 'Analysis requires its own pinned Core checkout. Run composer analysis:setup.' );
}
$ran_booster_wp_pusher_migrator_analysis_source = read_core_source( dirname( __DIR__ ) . '/composer.json' );
foreach ( array(
	'commit' => 'HEAD',
	'tree'   => 'HEAD^{tree}',
) as $ran_booster_wp_pusher_migrator_source_key => $ran_booster_wp_pusher_migrator_source_ref ) {
	if ( ! hash_equals( $ran_booster_wp_pusher_migrator_analysis_source[ $ran_booster_wp_pusher_migrator_source_key ], command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'rev-parse', $ran_booster_wp_pusher_migrator_source_ref ) ) ) ) {
		throw new RuntimeException( 'Checked-out Core does not match the pinned source commit and tree.' );
	}
}
if ( '' !== command_output( array( 'git', '--no-replace-objects', '-C', $ran_booster_wp_pusher_migrator_analysis_core, 'status', '--porcelain', '--untracked-files=all', '--ignored' ) ) ) {
	throw new RuntimeException( 'Analysis requires unmodified pinned Core source.' );
}

/** Verify scanned bytes independently of index flags and Git status caching. */
function verify_scanned_core_source( string $core_path ): void {
	$entries = command_output( array( 'git', '--no-replace-objects', '-C', $core_path, 'ls-tree', '-r', '-z', 'HEAD', '--', 'RAN/' ) );
	if ( '' === $entries ) {
		throw new RuntimeException( 'Analysis requires pinned Core declarations.' );
	}
	foreach ( explode( "\0", $entries ) as $entry ) {
		if ( 1 !== preg_match( '/\A100(?:644|755) blob ([0-9a-f]{40})\t(.+)\z/sD', $entry, $matches ) ) {
			throw new RuntimeException( 'Analysis requires ordinary pinned Core source files.' );
		}
		$path = realpath( $core_path ) . '/' . $matches[2];
		if ( ! is_file( $path ) || is_link( $path ) || realpath( $path ) !== $path ) {
			throw new RuntimeException( 'Analysis requires unmodified pinned Core source.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI compares scanned source with pinned Git blobs.
		$contents = file_get_contents( $path );
		if ( false === $contents || ! hash_equals( $matches[1], sha1( 'blob ' . strlen( $contents ) . "\0" . $contents ) ) ) {
			throw new RuntimeException( 'Analysis requires unmodified pinned Core source.' );
		}
	}
}

verify_scanned_core_source( $ran_booster_wp_pusher_migrator_analysis_core );

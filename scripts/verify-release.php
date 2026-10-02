<?php

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open,WordPress.PHP.YodaConditions.NotYoda,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone verifier deliberately uses CLI filesystem and process APIs. Naming checks remain enabled.

use RAN\BoosterWpPusherMigrator\Certification;

require_once __DIR__ . '/core-certification.php';

const PACKAGE_ROOT             = 'ran-booster-wp-pusher-migrator/';
const SLUG                     = 'ran-booster-wp-pusher-migrator';
const MAX_ARCHIVE_MEMBERS      = 128;
const MAX_MEMBER_BYTES         = 5_242_880;
const MAX_UNCOMPRESSED_BYTES   = 10_485_760;
const MAX_COMPRESSED_BYTES     = 5_242_880;
const MAX_COMPRESSION_RATIO    = 100;
const CANONICAL_REPOSITORY     = 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator';
const CANONICAL_REPOSITORY_URL = 'https://github.com/' . CANONICAL_REPOSITORY;

function fail( string $message ): never {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

/** @param list<string> $arguments */
function git_output( array $arguments ): string {
	$process = proc_open(
		array_merge( array( 'git' ), $arguments ),
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		fail( 'Could not start Git source inspection.' );
	}
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	if ( 0 !== $status || false === $output ) {
		fail( 'Git source inspection failed: ' . trim( (string) $error ) );
	}

	return $output;
}

function normalize_member_name( string $name ): string {
	if ( '' === $name
		|| str_contains( $name, "\0" )
		|| str_contains( $name, '\\' )
		|| str_starts_with( $name, '/' )
		|| ! str_starts_with( $name, PACKAGE_ROOT ) ) {
		fail( 'Archive contains an unsafe member name.' );
	}

	$is_directory = str_ends_with( $name, '/' );
	$path         = $is_directory ? substr( $name, 0, -1 ) : $name;
	$segments     = explode( '/', $path );
	foreach ( $segments as $segment ) {
		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			fail( 'Archive contains a non-canonical member name.' );
		}
	}

	return implode( '/', $segments ) . ( $is_directory ? '/' : '' );
}

/** @return array<string,string> */
function source_files( string $commit ): array {
	$allowlist = preg_split( '/\R/', git_output( array( 'show', $commit . ':release-contents.txt' ) ) );
	if ( false === $allowlist ) {
		fail( 'Could not parse the release allowlist.' );
	}

	$entries = array();
	$files   = array();
	foreach ( $allowlist as $path ) {
		$path = trim( $path );
		if ( '' === $path || str_starts_with( $path, '#' ) ) {
			continue;
		}
		if ( isset( $entries[ $path ] )
			|| str_starts_with( $path, '/' )
			|| in_array( '', explode( '/', rtrim( $path, '/' ) ), true )
			|| in_array( '.', explode( '/', rtrim( $path, '/' ) ), true )
			|| in_array( '..', explode( '/', rtrim( $path, '/' ) ), true ) ) {
			fail( 'Release allowlist contains a duplicate or unsafe path.' );
		}
		$entries[ $path ] = true;
		$listed           = preg_split( '/\R/', trim( git_output( array( 'ls-tree', '-r', '--name-only', $commit, '--', $path ) ) ) );
		if ( false === $listed || array( '' ) === $listed ) {
			fail( 'Release allowlist entry is missing from the source commit.' );
		}
		foreach ( $listed as $file ) {
			if ( '' === $file ) {
				continue;
			}
			$tree = preg_split( '/\s+/', trim( git_output( array( 'ls-tree', $commit, '--', $file ) ) ), 4 );
			if ( false === $tree || '100644' !== ( $tree[0] ?? '' ) ) {
				fail( 'Release source contains a symlink, non-regular or executable file.' );
			}
			$name = PACKAGE_ROOT . $file;
			if ( isset( $files[ $name ] ) ) {
				fail( 'Release allowlist entries overlap.' );
			}
			$files[ $name ] = git_output( array( 'show', $commit . ':' . $file ) );
		}
	}
	if ( array() === $files ) {
		fail( 'Release allowlist is empty.' );
	}
	ksort( $files, SORT_STRING );

	return $files;
}

/** @param array<string,string> $files @return array<string,true> */
function expected_directories( array $files ): array {
	$directories = array( PACKAGE_ROOT => true );
	foreach ( array_keys( $files ) as $file ) {
		$directory = dirname( $file );
		while ( '.' !== $directory && ! isset( $directories[ $directory . '/' ] ) ) {
			$directories[ $directory . '/' ] = true;
			$directory                       = dirname( $directory );
		}
	}
	ksort( $directories, SORT_STRING );

	return $directories;
}

/** @return array<string,mixed> */
function release_metadata( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail( 'Release manifest is missing.' );
	}
	try {
		$document = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException ) {
		fail( 'Release manifest is invalid JSON.' );
	}
	if ( ! is_array( $document ) ) {
		fail( 'Release manifest is not an object.' );
	}

	return $document;
}

$archive       = $argv[1] ?? '';
$source_commit = $argv[2] ?? '';
$root          = dirname( __DIR__ );
chdir( $root );

if ( 1 !== preg_match( '/\A[0-9a-f]{40}\z/D', $source_commit )
	|| trim( git_output( array( 'rev-parse', $source_commit . '^{commit}' ) ) ) !== $source_commit ) {
	fail( 'Source commit must be an existing full commit ID.' );
}
if ( ! str_starts_with( $archive, '/' ) ) {
	$archive = $root . '/' . $archive;
}
if ( ! is_file( $archive ) ) {
	fail( 'Release archive is missing.' );
}

$source_plugin = git_output( array( 'show', $source_commit . ':' . SLUG . '.php' ) );
preg_match( '/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $source_plugin, $plugin_version );
preg_match( '/^[[:space:]]*\*[[:space:]]*Requires at least:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $source_plugin, $requires_word_press );
preg_match( '/^[[:space:]]*\*[[:space:]]*Requires PHP:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $source_plugin, $requires_php );
preg_match( '/^[[:space:]]*\*[[:space:]]*Tested up to:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $source_plugin, $tested_word_press );
$version = $plugin_version[1] ?? '';
if ( 1 !== preg_match( '/\A[0-9]+\.[0-9]+\.[0-9]+(?:[-.][0-9A-Za-z.-]+)?\z/D', $version )
	|| ! str_contains( $source_plugin, 'Plugin URI: ' . CANONICAL_REPOSITORY_URL )
	|| ! str_contains( $source_plugin, 'Update URI: ' . CANONICAL_REPOSITORY_URL ) ) {
	fail( 'Source plugin identity or version is invalid.' );
}

$composer_path = tempnam( sys_get_temp_dir(), 'ran-migrator-certification-' );
if ( false === $composer_path ) {
	fail( 'Could not create a temporary certification manifest.' );
}
file_put_contents( $composer_path, git_output( array( 'show', $source_commit . ':composer.json' ) ) );
try {
	Certification\read_core_certification( $composer_path );
} catch ( Throwable $error ) {
	unlink( $composer_path );
	fail( $error->getMessage() );
}
unlink( $composer_path );

$archive_name = basename( $archive );
if ( $archive_name !== SLUG . '-' . $version . '.zip' ) {
	fail( 'Archive filename does not match the source version.' );
}
$checksum = $archive . '.sha256';
if ( ! is_file( $checksum ) ) {
	fail( 'Release checksum is missing.' );
}
$archive_hash    = hash_file( 'sha256', $archive );
$expected_digest = $archive_hash . '  ' . $archive_name;
$recorded_digest = rtrim( (string) file_get_contents( $checksum ), "\r\n" );
if ( ! is_string( $archive_hash ) || ! hash_equals( $expected_digest, $recorded_digest ) ) {
	fail( 'Release checksum does not match the archive and basename.' );
}

$archive_size      = filesize( $archive );
$expected_metadata = array(
	'schema'             => 'ran-wordpress-plugin-release',
	'schema_version'     => 1,
	'repository'         => CANONICAL_REPOSITORY,
	'tag'                => 'v' . $version,
	'commit'             => $source_commit,
	'zip'                => $archive_name,
	'plugin_root'        => SLUG,
	'main_file'          => SLUG . '.php',
	'version'            => $version,
	'requires_php'       => $requires_php[1] ?? '',
	'requires_wordpress' => $requires_word_press[1] ?? '',
	'tested_wordpress'   => $tested_word_press[1] ?? '',
	'zip_size'           => $archive_size,
	'zip_sha256'         => $archive_hash,
);
if ( release_metadata( dirname( $archive ) . '/' . substr( $archive_name, 0, -4 ) . '.json' ) !== $expected_metadata ) {
	fail( 'Release manifest does not match the source commit, archive and package identity.' );
}

$expected_files       = source_files( $source_commit );
$expected_directories = expected_directories( $expected_files );
$zip                  = new ZipArchive();
if ( true !== $zip->open( $archive, ZipArchive::RDONLY ) ) {
	fail( 'Release archive could not be opened.' );
}
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native ZipArchive::$numFiles property.
if ( $zip->numFiles < 1 || $zip->numFiles > MAX_ARCHIVE_MEMBERS ) {
	fail( 'Archive member count exceeds the safe bound.' );
}

$seen_raw           = array();
$seen_case_folded   = array();
$seen_normalized    = array();
$actual_files       = array();
$actual_directories = array();
$compressed_bytes   = 0;
$uncompressed_bytes = 0;
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native ZipArchive::$numFiles property.
for ( $index = 0; $index < $zip->numFiles; ++$index ) {
	$stat = $zip->statIndex( $index, ZipArchive::FL_UNCHANGED );
	$name = $zip->getNameIndex( $index, ZipArchive::FL_UNCHANGED );
	if ( false === $stat || false === $name || isset( $seen_raw[ $name ] ) ) {
		fail( 'Archive contains duplicate or unreadable raw members.' );
	}
	$seen_raw[ $name ] = true;
	$case_folded       = strtolower( $name );
	if ( isset( $seen_case_folded[ $case_folded ] ) ) {
		fail( 'Archive contains case-insensitive member collisions.' );
	}
	$seen_case_folded[ $case_folded ] = true;
	$normalized                       = normalize_member_name( $name );
	if ( isset( $seen_normalized[ $normalized ] ) ) {
		fail( 'Archive contains normalized member collisions.' );
	}
	$seen_normalized[ $normalized ] = true;

	$size       = (int) $stat['size'];
	$compressed = (int) $stat['comp_size'];
	$method     = (int) $stat['comp_method'];
	if ( $size < 0 || $compressed < 0 || $size > MAX_MEMBER_BYTES
		|| ! in_array( $method, array( ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE ), true )
		|| 0 !== (int) $stat['encryption_method']
		|| ( 0 < $compressed && $size > $compressed * MAX_COMPRESSION_RATIO )
		|| ( 0 === $compressed && 0 < $size ) ) {
		fail( 'Archive member exceeds the permitted type, encryption, size or ratio bounds.' );
	}
	$compressed_bytes   += $compressed;
	$uncompressed_bytes += $size;
	if ( $compressed_bytes > MAX_COMPRESSED_BYTES || $uncompressed_bytes > MAX_UNCOMPRESSED_BYTES ) {
		fail( 'Archive aggregate size exceeds the safe bound.' );
	}

	$operations = 0;
	$attributes = 0;
	if ( ! $zip->getExternalAttributesIndex( $index, $operations, $attributes, ZipArchive::FL_UNCHANGED ) ) {
		fail( 'Archive member attributes are unreadable.' );
	}
	$mode         = ( $attributes >> 16 ) & 0xffff;
	$is_directory = str_ends_with( $name, '/' );
	if ( $is_directory && ( 0 !== $size || 0 !== $compressed ) ) {
		fail( 'Archive contains a non-empty directory member.' );
	}
	if ( ZipArchive::OPSYS_UNIX === $operations
		&& ( $is_directory ? 0040775 !== $mode && 0040755 !== $mode : 0100664 !== $mode && 0100644 !== $mode ) ) {
		fail( 'Archive contains a symlink, non-regular or executable member.' );
	}
	if ( ZipArchive::OPSYS_UNIX !== $operations && 0 !== $mode ) {
		fail( 'Archive contains untrusted non-Unix mode metadata.' );
	}
	if ( $is_directory ) {
		$actual_directories[ $normalized ] = true;
		continue;
	}

	$content = $zip->getFromIndex( $index, $size, ZipArchive::FL_UNCHANGED );
	if ( false === $content || strlen( $content ) !== $size ) {
		fail( 'Archive member could not be read completely.' );
	}
	$actual_files[ $normalized ] = $content;
}
$zip->close();

ksort( $actual_files, SORT_STRING );
ksort( $actual_directories, SORT_STRING );
if ( array_keys( $actual_files ) !== array_keys( $expected_files )
	|| array_keys( $actual_directories ) !== array_keys( $expected_directories ) ) {
	fail( 'Archive contents do not exactly match the source allowlist.' );
}
foreach ( $expected_files as $name => $content ) {
	if ( ! hash_equals( hash( 'sha256', $content ), hash( 'sha256', $actual_files[ $name ] ) ) ) {
		fail( 'Archive member bytes do not match the source commit.' );
	}
}

$plugin = $actual_files[ PACKAGE_ROOT . 'src/Plugin.php' ] ?? '';
$source = $actual_files[ PACKAGE_ROOT . 'src/WpPusherSource.php' ] ?? '';
if ( ! str_contains( $plugin, 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
	|| ! preg_match( '/REQUIRED_PORTABILITY_API_VERSION\s*=\s*3/', $plugin )
	|| ! preg_match( '/REQUIRED_ADMIN_INTERACTION_API_VERSION\s*=\s*3/', $plugin )
	|| ! str_contains( $plugin, "'ran_booster_portability_ready'" )
	|| ! str_contains( $plugin, "'ran_booster_admin_interaction_ready'" )
	|| str_contains( implode( '', $actual_files ), 'LoggingFacade' )
	|| ! str_contains( $source, "private const VERSION = '3.0.13';" ) ) {
	fail( 'Archive does not preserve the Migrator source and Core API boundaries.' );
}
foreach ( array_keys( $actual_files ) as $name ) {
	if ( preg_match( '#/(tests|vendor|node_modules|\.git|scripts|\.github|dist)/#', $name ) ) {
		fail( 'Release archive contains development-only files.' );
	}
}

$temporary = sys_get_temp_dir() . '/ran-migrator-verify-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $temporary, 0700, true ) ) {
	fail( 'Could not create the syntax-check directory.' );
}
register_shutdown_function(
	static function () use ( $temporary ): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $temporary, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $temporary );
	}
);
foreach ( $actual_files as $name => $content ) {
	if ( ! str_ends_with( $name, '.php' ) ) {
		continue;
	}
	$path = $temporary . '/' . substr( $name, strlen( PACKAGE_ROOT ) );
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0700, true );
	}
	file_put_contents( $path, $content );
	$process = proc_open(
		array( PHP_BINARY, '-l', $path ),
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		fail( 'Could not start PHP syntax verification.' );
	}
	stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	if ( 0 !== proc_close( $process ) ) {
		fail( 'Archive PHP syntax failed: ' . trim( (string) $error ) );
	}
}

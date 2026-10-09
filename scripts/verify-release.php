<?php

declare(strict_types=1);


use RAN\BoosterWpPusherMigrator\Certification;

require_once __DIR__ . '/core-certification.php';

const RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT             = 'ran-booster-wp-pusher-migrator/';
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_SLUG                     = 'ran-booster-wp-pusher-migrator';
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_ARCHIVE_MEMBERS      = 128;
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_MEMBER_BYTES         = 5_242_880;
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_UNCOMPRESSED_BYTES   = 10_485_760;
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_COMPRESSED_BYTES     = 5_242_880;
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_COMPRESSION_RATIO    = 100;
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_CANONICAL_REPOSITORY     = 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator';
const RAN_BOOSTER_WP_PUSHER_MIGRATOR_CANONICAL_REPOSITORY_URL = 'https://github.com/' . RAN_BOOSTER_WP_PUSHER_MIGRATOR_CANONICAL_REPOSITORY;

function ran_booster_wp_pusher_migrator_fail( string $message ): never {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

/** @param list<string> $arguments */
function ran_booster_wp_pusher_migrator_git_output( array $arguments ): string {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	$process = proc_open(
		array_merge( array( 'git' ), $arguments ),
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Could not start Git source inspection.' );
	}
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	fclose( $pipes[1] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	fclose( $pipes[2] );
	$status = proc_close( $process );
	if ( 0 !== $status || false === $output ) {
		ran_booster_wp_pusher_migrator_fail( 'Git source inspection failed: ' . trim( (string) $error ) );
	}

	return $output;
}

function ran_booster_wp_pusher_migrator_normalize_member_name( string $name ): string {
	if ( '' === $name
		|| str_contains( $name, "\0" )
		|| str_contains( $name, '\\' )
		|| str_starts_with( $name, '/' )
		|| ! str_starts_with( $name, RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains an unsafe member name.' );
	}

	$is_directory = str_ends_with( $name, '/' );
	$path         = $is_directory ? substr( $name, 0, -1 ) : $name;
	$segments     = explode( '/', $path );
	foreach ( $segments as $segment ) {
		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			ran_booster_wp_pusher_migrator_fail( 'Archive contains a non-canonical member name.' );
		}
	}

	return implode( '/', $segments ) . ( $is_directory ? '/' : '' );
}

/** @return array<string,string> */
function ran_booster_wp_pusher_migrator_source_files( string $commit ): array {
	$allowlist = preg_split( '/\R/', ran_booster_wp_pusher_migrator_git_output( array( 'show', $commit . ':release-contents.txt' ) ) );
	if ( false === $allowlist ) {
		ran_booster_wp_pusher_migrator_fail( 'Could not parse the release allowlist.' );
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
			ran_booster_wp_pusher_migrator_fail( 'Release allowlist contains a duplicate or unsafe path.' );
		}
		$entries[ $path ] = true;
		$listed           = preg_split( '/\R/', trim( ran_booster_wp_pusher_migrator_git_output( array( 'ls-tree', '-r', '--name-only', $commit, '--', $path ) ) ) );
		if ( false === $listed || array( '' ) === $listed ) {
			ran_booster_wp_pusher_migrator_fail( 'Release allowlist entry is missing from the source commit.' );
		}
		foreach ( $listed as $file ) {
			if ( '' === $file ) {
				continue;
			}
			$tree = preg_split( '/\s+/', trim( ran_booster_wp_pusher_migrator_git_output( array( 'ls-tree', $commit, '--', $file ) ) ), 4 );
			if ( false === $tree || '100644' !== ( $tree[0] ?? '' ) ) {
				ran_booster_wp_pusher_migrator_fail( 'Release source contains a symlink, non-regular or executable file.' );
			}
			$name = RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT . $file;
			if ( isset( $files[ $name ] ) ) {
				ran_booster_wp_pusher_migrator_fail( 'Release allowlist entries overlap.' );
			}
			$files[ $name ] = ran_booster_wp_pusher_migrator_git_output( array( 'show', $commit . ':' . $file ) );
		}
	}
	if ( array() === $files ) {
		ran_booster_wp_pusher_migrator_fail( 'Release allowlist is empty.' );
	}
	ksort( $files, SORT_STRING );

	return $files;
}

/**
 * @param array<string, string> $files
 * @return array<string, true> Archive directory members inferred from file paths.
 */
function ran_booster_wp_pusher_migrator_expected_directories( array $files ): array {
	$directories = array( RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT => true );
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
function ran_booster_wp_pusher_migrator_release_metadata( string $path ): array {
	if ( ! is_file( $path ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Release manifest is missing.' );
	}
	try {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
		$document = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException ) {
		ran_booster_wp_pusher_migrator_fail( 'Release manifest is invalid JSON.' );
	}
	if ( ! is_array( $document ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Release manifest is not an object.' );
	}

	return $document;
}

$ran_booster_wp_pusher_migrator_archive       = $argv[1] ?? '';
$ran_booster_wp_pusher_migrator_source_commit = $argv[2] ?? '';
$ran_booster_wp_pusher_migrator_root          = dirname( __DIR__ );
chdir( $ran_booster_wp_pusher_migrator_root );

if ( 1 !== preg_match( '/\A[0-9a-f]{40}\z/D', $ran_booster_wp_pusher_migrator_source_commit )
	|| trim( ran_booster_wp_pusher_migrator_git_output( array( 'rev-parse', $ran_booster_wp_pusher_migrator_source_commit . '^{commit}' ) ) ) !== $ran_booster_wp_pusher_migrator_source_commit ) {
	ran_booster_wp_pusher_migrator_fail( 'Source commit must be an existing full commit ID.' );
}
if ( ! str_starts_with( $ran_booster_wp_pusher_migrator_archive, '/' ) ) {
	$ran_booster_wp_pusher_migrator_archive = $ran_booster_wp_pusher_migrator_root . '/' . $ran_booster_wp_pusher_migrator_archive;
}
if ( ! is_file( $ran_booster_wp_pusher_migrator_archive ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Release archive is missing.' );
}

$ran_booster_wp_pusher_migrator_source_plugin = ran_booster_wp_pusher_migrator_git_output( array( 'show', $ran_booster_wp_pusher_migrator_source_commit . ':' . RAN_BOOSTER_WP_PUSHER_MIGRATOR_SLUG . '.php' ) );
preg_match( '/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $ran_booster_wp_pusher_migrator_source_plugin, $ran_booster_wp_pusher_migrator_plugin_version );
preg_match( '/^[[:space:]]*\*[[:space:]]*Requires at least:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $ran_booster_wp_pusher_migrator_source_plugin, $ran_booster_wp_pusher_migrator_requires_word_press );
preg_match( '/^[[:space:]]*\*[[:space:]]*Requires PHP:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $ran_booster_wp_pusher_migrator_source_plugin, $ran_booster_wp_pusher_migrator_requires_php );
preg_match( '/^[[:space:]]*\*[[:space:]]*Tested up to:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $ran_booster_wp_pusher_migrator_source_plugin, $ran_booster_wp_pusher_migrator_tested_word_press );
$ran_booster_wp_pusher_migrator_version = $ran_booster_wp_pusher_migrator_plugin_version[1] ?? '';
if ( 1 !== preg_match( '/\A[0-9]+\.[0-9]+\.[0-9]+(?:[-.][0-9A-Za-z.-]+)?\z/D', $ran_booster_wp_pusher_migrator_version )
	|| ! str_contains( $ran_booster_wp_pusher_migrator_source_plugin, 'Plugin URI: ' . RAN_BOOSTER_WP_PUSHER_MIGRATOR_CANONICAL_REPOSITORY_URL )
	|| ! str_contains( $ran_booster_wp_pusher_migrator_source_plugin, 'Update URI: ' . RAN_BOOSTER_WP_PUSHER_MIGRATOR_CANONICAL_REPOSITORY_URL ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Source plugin identity or version is invalid.' );
}

$ran_booster_wp_pusher_migrator_composer_path = tempnam( sys_get_temp_dir(), 'ran-migrator-certification-' );
if ( false === $ran_booster_wp_pusher_migrator_composer_path ) {
	ran_booster_wp_pusher_migrator_fail( 'Could not create a temporary certification manifest.' );
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
file_put_contents( $ran_booster_wp_pusher_migrator_composer_path, ran_booster_wp_pusher_migrator_git_output( array( 'show', $ran_booster_wp_pusher_migrator_source_commit . ':composer.json' ) ) );
try {
	Certification\read_core_certification( $ran_booster_wp_pusher_migrator_composer_path );
} catch ( Throwable $ran_booster_wp_pusher_migrator_error ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	unlink( $ran_booster_wp_pusher_migrator_composer_path );
	ran_booster_wp_pusher_migrator_fail( $ran_booster_wp_pusher_migrator_error->getMessage() );
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
unlink( $ran_booster_wp_pusher_migrator_composer_path );

$ran_booster_wp_pusher_migrator_archive_name = basename( $ran_booster_wp_pusher_migrator_archive );
if ( RAN_BOOSTER_WP_PUSHER_MIGRATOR_SLUG . '-' . $ran_booster_wp_pusher_migrator_version . '.zip' !== $ran_booster_wp_pusher_migrator_archive_name ) {
	ran_booster_wp_pusher_migrator_fail( 'Archive filename does not match the source version.' );
}
$ran_booster_wp_pusher_migrator_checksum = $ran_booster_wp_pusher_migrator_archive . '.sha256';
if ( ! is_file( $ran_booster_wp_pusher_migrator_checksum ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Release checksum is missing.' );
}
$ran_booster_wp_pusher_migrator_archive_hash    = hash_file( 'sha256', $ran_booster_wp_pusher_migrator_archive );
$ran_booster_wp_pusher_migrator_expected_digest = $ran_booster_wp_pusher_migrator_archive_hash . '  ' . $ran_booster_wp_pusher_migrator_archive_name;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
$ran_booster_wp_pusher_migrator_recorded_digest = rtrim( (string) file_get_contents( $ran_booster_wp_pusher_migrator_checksum ), "\r\n" );
if ( ! is_string( $ran_booster_wp_pusher_migrator_archive_hash ) || ! hash_equals( $ran_booster_wp_pusher_migrator_expected_digest, $ran_booster_wp_pusher_migrator_recorded_digest ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Release checksum does not match the archive and basename.' );
}

$ran_booster_wp_pusher_migrator_archive_size      = filesize( $ran_booster_wp_pusher_migrator_archive );
$ran_booster_wp_pusher_migrator_expected_metadata = array(
	'schema'             => 'ran-wordpress-plugin-release',
	'schema_version'     => 1,
	'repository'         => RAN_BOOSTER_WP_PUSHER_MIGRATOR_CANONICAL_REPOSITORY,
	'tag'                => 'v' . $ran_booster_wp_pusher_migrator_version,
	'commit'             => $ran_booster_wp_pusher_migrator_source_commit,
	'zip'                => $ran_booster_wp_pusher_migrator_archive_name,
	'plugin_root'        => RAN_BOOSTER_WP_PUSHER_MIGRATOR_SLUG,
	'main_file'          => RAN_BOOSTER_WP_PUSHER_MIGRATOR_SLUG . '.php',
	'version'            => $ran_booster_wp_pusher_migrator_version,
	'requires_php'       => $ran_booster_wp_pusher_migrator_requires_php[1] ?? '',
	'requires_wordpress' => $ran_booster_wp_pusher_migrator_requires_word_press[1] ?? '',
	'tested_wordpress'   => $ran_booster_wp_pusher_migrator_tested_word_press[1] ?? '',
	'zip_size'           => $ran_booster_wp_pusher_migrator_archive_size,
	'zip_sha256'         => $ran_booster_wp_pusher_migrator_archive_hash,
);
if ( ran_booster_wp_pusher_migrator_release_metadata( dirname( $ran_booster_wp_pusher_migrator_archive ) . '/' . substr( $ran_booster_wp_pusher_migrator_archive_name, 0, -4 ) . '.json' ) !== $ran_booster_wp_pusher_migrator_expected_metadata ) {
	ran_booster_wp_pusher_migrator_fail( 'Release manifest does not match the source commit, archive and package identity.' );
}

$ran_booster_wp_pusher_migrator_expected_files       = ran_booster_wp_pusher_migrator_source_files( $ran_booster_wp_pusher_migrator_source_commit );
$ran_booster_wp_pusher_migrator_expected_directories = ran_booster_wp_pusher_migrator_expected_directories( $ran_booster_wp_pusher_migrator_expected_files );
$ran_booster_wp_pusher_migrator_zip                  = new ZipArchive();
if ( true !== $ran_booster_wp_pusher_migrator_zip->open( $ran_booster_wp_pusher_migrator_archive, ZipArchive::RDONLY ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Release archive could not be opened.' );
}
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native ZipArchive::$numFiles property.
if ( $ran_booster_wp_pusher_migrator_zip->numFiles < 1 || $ran_booster_wp_pusher_migrator_zip->numFiles > RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_ARCHIVE_MEMBERS ) {
	ran_booster_wp_pusher_migrator_fail( 'Archive member count exceeds the safe bound.' );
}

$ran_booster_wp_pusher_migrator_seen_raw           = array();
$ran_booster_wp_pusher_migrator_seen_case_folded   = array();
$ran_booster_wp_pusher_migrator_seen_normalized    = array();
$ran_booster_wp_pusher_migrator_actual_files       = array();
$ran_booster_wp_pusher_migrator_actual_directories = array();
$ran_booster_wp_pusher_migrator_compressed_bytes   = 0;
$ran_booster_wp_pusher_migrator_uncompressed_bytes = 0;
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native ZipArchive::$numFiles property.
for ( $ran_booster_wp_pusher_migrator_index = 0; $ran_booster_wp_pusher_migrator_index < $ran_booster_wp_pusher_migrator_zip->numFiles; ++$ran_booster_wp_pusher_migrator_index ) {
	$ran_booster_wp_pusher_migrator_stat = $ran_booster_wp_pusher_migrator_zip->statIndex( $ran_booster_wp_pusher_migrator_index, ZipArchive::FL_UNCHANGED );
	$ran_booster_wp_pusher_migrator_name = $ran_booster_wp_pusher_migrator_zip->getNameIndex( $ran_booster_wp_pusher_migrator_index, ZipArchive::FL_UNCHANGED );
	if ( false === $ran_booster_wp_pusher_migrator_stat || false === $ran_booster_wp_pusher_migrator_name || isset( $ran_booster_wp_pusher_migrator_seen_raw[ $ran_booster_wp_pusher_migrator_name ] ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains duplicate or unreadable raw members.' );
	}
	$ran_booster_wp_pusher_migrator_seen_raw[ $ran_booster_wp_pusher_migrator_name ] = true;
	$ran_booster_wp_pusher_migrator_case_folded                                      = strtolower( $ran_booster_wp_pusher_migrator_name );
	if ( isset( $ran_booster_wp_pusher_migrator_seen_case_folded[ $ran_booster_wp_pusher_migrator_case_folded ] ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains case-insensitive member collisions.' );
	}
	$ran_booster_wp_pusher_migrator_seen_case_folded[ $ran_booster_wp_pusher_migrator_case_folded ] = true;
	$ran_booster_wp_pusher_migrator_normalized = ran_booster_wp_pusher_migrator_normalize_member_name( $ran_booster_wp_pusher_migrator_name );
	if ( isset( $ran_booster_wp_pusher_migrator_seen_normalized[ $ran_booster_wp_pusher_migrator_normalized ] ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains normalized member collisions.' );
	}
	$ran_booster_wp_pusher_migrator_seen_normalized[ $ran_booster_wp_pusher_migrator_normalized ] = true;

	$ran_booster_wp_pusher_migrator_size       = (int) $ran_booster_wp_pusher_migrator_stat['size'];
	$ran_booster_wp_pusher_migrator_compressed = (int) $ran_booster_wp_pusher_migrator_stat['comp_size'];
	$ran_booster_wp_pusher_migrator_method     = (int) $ran_booster_wp_pusher_migrator_stat['comp_method'];
	if ( $ran_booster_wp_pusher_migrator_size < 0 || $ran_booster_wp_pusher_migrator_compressed < 0 || $ran_booster_wp_pusher_migrator_size > RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_MEMBER_BYTES
		|| ! in_array( $ran_booster_wp_pusher_migrator_method, array( ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE ), true )
		|| 0 !== (int) $ran_booster_wp_pusher_migrator_stat['encryption_method']
		|| ( 0 < $ran_booster_wp_pusher_migrator_compressed && $ran_booster_wp_pusher_migrator_size > $ran_booster_wp_pusher_migrator_compressed * RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_COMPRESSION_RATIO )
		|| ( 0 === $ran_booster_wp_pusher_migrator_compressed && 0 < $ran_booster_wp_pusher_migrator_size ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive member exceeds the permitted type, encryption, size or ratio bounds.' );
	}
	$ran_booster_wp_pusher_migrator_compressed_bytes   += $ran_booster_wp_pusher_migrator_compressed;
	$ran_booster_wp_pusher_migrator_uncompressed_bytes += $ran_booster_wp_pusher_migrator_size;
	if ( $ran_booster_wp_pusher_migrator_compressed_bytes > RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_COMPRESSED_BYTES || $ran_booster_wp_pusher_migrator_uncompressed_bytes > RAN_BOOSTER_WP_PUSHER_MIGRATOR_MAX_UNCOMPRESSED_BYTES ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive aggregate size exceeds the safe bound.' );
	}

	$ran_booster_wp_pusher_migrator_operations = 0;
	$ran_booster_wp_pusher_migrator_attributes = 0;
	if ( ! $ran_booster_wp_pusher_migrator_zip->getExternalAttributesIndex( $ran_booster_wp_pusher_migrator_index, $ran_booster_wp_pusher_migrator_operations, $ran_booster_wp_pusher_migrator_attributes, ZipArchive::FL_UNCHANGED ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive member attributes are unreadable.' );
	}
	$ran_booster_wp_pusher_migrator_mode         = ( $ran_booster_wp_pusher_migrator_attributes >> 16 ) & 0xffff;
	$ran_booster_wp_pusher_migrator_is_directory = str_ends_with( $ran_booster_wp_pusher_migrator_name, '/' );
	if ( $ran_booster_wp_pusher_migrator_is_directory && ( 0 !== $ran_booster_wp_pusher_migrator_size || 0 !== $ran_booster_wp_pusher_migrator_compressed ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains a non-empty directory member.' );
	}
	if ( ZipArchive::OPSYS_UNIX === $ran_booster_wp_pusher_migrator_operations
		&& ( $ran_booster_wp_pusher_migrator_is_directory ? 0040775 !== $ran_booster_wp_pusher_migrator_mode && 0040755 !== $ran_booster_wp_pusher_migrator_mode : 0100664 !== $ran_booster_wp_pusher_migrator_mode && 0100644 !== $ran_booster_wp_pusher_migrator_mode ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains a symlink, non-regular or executable member.' );
	}
	if ( ZipArchive::OPSYS_UNIX !== $ran_booster_wp_pusher_migrator_operations && 0 !== $ran_booster_wp_pusher_migrator_mode ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive contains untrusted non-Unix mode metadata.' );
	}
	if ( $ran_booster_wp_pusher_migrator_is_directory ) {
		$ran_booster_wp_pusher_migrator_actual_directories[ $ran_booster_wp_pusher_migrator_normalized ] = true;
		continue;
	}

	$ran_booster_wp_pusher_migrator_content = $ran_booster_wp_pusher_migrator_zip->getFromIndex( $ran_booster_wp_pusher_migrator_index, $ran_booster_wp_pusher_migrator_size, ZipArchive::FL_UNCHANGED );
	if ( false === $ran_booster_wp_pusher_migrator_content || strlen( $ran_booster_wp_pusher_migrator_content ) !== $ran_booster_wp_pusher_migrator_size ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive member could not be read completely.' );
	}
	$ran_booster_wp_pusher_migrator_actual_files[ $ran_booster_wp_pusher_migrator_normalized ] = $ran_booster_wp_pusher_migrator_content;
}
$ran_booster_wp_pusher_migrator_zip->close();

ksort( $ran_booster_wp_pusher_migrator_actual_files, SORT_STRING );
ksort( $ran_booster_wp_pusher_migrator_actual_directories, SORT_STRING );
if ( array_keys( $ran_booster_wp_pusher_migrator_actual_files ) !== array_keys( $ran_booster_wp_pusher_migrator_expected_files )
	|| array_keys( $ran_booster_wp_pusher_migrator_actual_directories ) !== array_keys( $ran_booster_wp_pusher_migrator_expected_directories ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Archive contents do not exactly match the source allowlist.' );
}
foreach ( $ran_booster_wp_pusher_migrator_expected_files as $ran_booster_wp_pusher_migrator_name => $ran_booster_wp_pusher_migrator_content ) {
	if ( ! hash_equals( hash( 'sha256', $ran_booster_wp_pusher_migrator_content ), hash( 'sha256', $ran_booster_wp_pusher_migrator_actual_files[ $ran_booster_wp_pusher_migrator_name ] ) ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive member bytes do not match the source commit.' );
	}
}

$ran_booster_wp_pusher_migrator_plugin = $ran_booster_wp_pusher_migrator_actual_files[ RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT . 'src/Plugin.php' ] ?? '';
$ran_booster_wp_pusher_migrator_source = $ran_booster_wp_pusher_migrator_actual_files[ RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT . 'src/WpPusherSource.php' ] ?? '';
if ( ! str_contains( $ran_booster_wp_pusher_migrator_plugin, 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
	|| ! preg_match( '/REQUIRED_PORTABILITY_API_VERSION\s*=\s*3/', $ran_booster_wp_pusher_migrator_plugin )
	|| ! preg_match( '/REQUIRED_ADMIN_INTERACTION_API_VERSION\s*=\s*3/', $ran_booster_wp_pusher_migrator_plugin )
	|| ! str_contains( $ran_booster_wp_pusher_migrator_plugin, "'ran_booster_portability_ready'" )
	|| ! str_contains( $ran_booster_wp_pusher_migrator_plugin, "'ran_booster_admin_interaction_ready'" )
	|| str_contains( implode( '', $ran_booster_wp_pusher_migrator_actual_files ), 'LoggingFacade' )
	|| ! str_contains( $ran_booster_wp_pusher_migrator_source, "private const VERSION = '3.0.13';" ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Archive does not preserve the Migrator source and Core API boundaries.' );
}
foreach ( array_keys( $ran_booster_wp_pusher_migrator_actual_files ) as $ran_booster_wp_pusher_migrator_name ) {
	if ( preg_match( '#/(tests|vendor|node_modules|\.git|scripts|\.github|dist)/#', $ran_booster_wp_pusher_migrator_name ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Release archive contains development-only files.' );
	}
}

$ran_booster_wp_pusher_migrator_temporary = sys_get_temp_dir() . '/ran-migrator-verify-' . bin2hex( random_bytes( 8 ) );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
if ( ! mkdir( $ran_booster_wp_pusher_migrator_temporary, 0700, true ) ) {
	ran_booster_wp_pusher_migrator_fail( 'Could not create the syntax-check directory.' );
}
register_shutdown_function(
	static function () use ( $ran_booster_wp_pusher_migrator_temporary ): void {
		$ran_booster_wp_pusher_migrator_iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $ran_booster_wp_pusher_migrator_temporary, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $ran_booster_wp_pusher_migrator_iterator as $ran_booster_wp_pusher_migrator_item ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.WP.AlternativeFunctions.unlink_unlink -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
			$ran_booster_wp_pusher_migrator_item->isDir() ? rmdir( $ran_booster_wp_pusher_migrator_item->getPathname() ) : unlink( $ran_booster_wp_pusher_migrator_item->getPathname() );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
		rmdir( $ran_booster_wp_pusher_migrator_temporary );
	}
);
foreach ( $ran_booster_wp_pusher_migrator_actual_files as $ran_booster_wp_pusher_migrator_name => $ran_booster_wp_pusher_migrator_content ) {
	if ( ! str_ends_with( $ran_booster_wp_pusher_migrator_name, '.php' ) ) {
		continue;
	}
	$ran_booster_wp_pusher_migrator_path = $ran_booster_wp_pusher_migrator_temporary . '/' . substr( $ran_booster_wp_pusher_migrator_name, strlen( RAN_BOOSTER_WP_PUSHER_MIGRATOR_PACKAGE_ROOT ) );
	if ( ! is_dir( dirname( $ran_booster_wp_pusher_migrator_path ) ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
		mkdir( dirname( $ran_booster_wp_pusher_migrator_path ), 0700, true );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	file_put_contents( $ran_booster_wp_pusher_migrator_path, $ran_booster_wp_pusher_migrator_content );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	$ran_booster_wp_pusher_migrator_process = proc_open(
		array( PHP_BINARY, '-l', $ran_booster_wp_pusher_migrator_path ),
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$ran_booster_wp_pusher_migrator_pipes
	);
	if ( ! is_resource( $ran_booster_wp_pusher_migrator_process ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Could not start PHP syntax verification.' );
	}
	stream_get_contents( $ran_booster_wp_pusher_migrator_pipes[1] );
	$ran_booster_wp_pusher_migrator_error = stream_get_contents( $ran_booster_wp_pusher_migrator_pipes[2] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	fclose( $ran_booster_wp_pusher_migrator_pipes[1] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Standalone verifier inspects local archive bytes and subprocess pipes before any WordPress runtime exists.
	fclose( $ran_booster_wp_pusher_migrator_pipes[2] );
	if ( 0 !== proc_close( $ran_booster_wp_pusher_migrator_process ) ) {
		ran_booster_wp_pusher_migrator_fail( 'Archive PHP syntax failed: ' . trim( (string) $ran_booster_wp_pusher_migrator_error ) );
	}
}

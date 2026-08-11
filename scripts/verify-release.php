<?php

declare(strict_types=1);

// phpcs:disable -- Standalone verifier deliberately uses CLI filesystem and process APIs.

use RAN\BoosterWpPusherMigrator\Certification;

require_once __DIR__ . '/core-certification.php';

const PACKAGE_ROOT            = 'ran-booster-wp-pusher-migrator/';
const SLUG                    = 'ran-booster-wp-pusher-migrator';
const MAX_ARCHIVE_MEMBERS     = 128;
const MAX_MEMBER_BYTES        = 5_242_880;
const MAX_UNCOMPRESSED_BYTES  = 10_485_760;
const MAX_COMPRESSED_BYTES    = 5_242_880;
const MAX_COMPRESSION_RATIO   = 100;
const CANONICAL_REPOSITORY    = 'RocketsAreNostalgic/ran-booster-wp-pusher-migrator';
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

	$isDirectory = str_ends_with( $name, '/' );
	$path        = $isDirectory ? substr( $name, 0, -1 ) : $name;
	$segments    = explode( '/', $path );
	foreach ( $segments as $segment ) {
		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			fail( 'Archive contains a non-canonical member name.' );
		}
	}

	return implode( '/', $segments ) . ( $isDirectory ? '/' : '' );
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
			$directory = dirname( $directory );
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

$archive      = $argv[1] ?? '';
$sourceCommit = $argv[2] ?? '';
$root         = dirname( __DIR__ );
chdir( $root );

if ( 1 !== preg_match( '/\A[0-9a-f]{40}\z/D', $sourceCommit )
	|| trim( git_output( array( 'rev-parse', $sourceCommit . '^{commit}' ) ) ) !== $sourceCommit ) {
	fail( 'Source commit must be an existing full commit ID.' );
}
if ( ! str_starts_with( $archive, '/' ) ) {
	$archive = $root . '/' . $archive;
}
if ( ! is_file( $archive ) ) {
	fail( 'Release archive is missing.' );
}

$sourcePlugin = git_output( array( 'show', $sourceCommit . ':' . SLUG . '.php' ) );
preg_match( '/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $sourcePlugin, $pluginVersion );
preg_match( '/^[[:space:]]*\*[[:space:]]*Requires at least:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $sourcePlugin, $requiresWordPress );
preg_match( '/^[[:space:]]*\*[[:space:]]*Requires PHP:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $sourcePlugin, $requiresPhp );
preg_match( '/^[[:space:]]*\*[[:space:]]*Tested up to:[[:space:]]*([^[:space:]]+)[[:space:]]*$/m', $sourcePlugin, $testedWordPress );
$version = $pluginVersion[1] ?? '';
if ( 1 !== preg_match( '/\A[0-9]+\.[0-9]+\.[0-9]+(?:[-.][0-9A-Za-z.-]+)?\z/D', $version )
	|| ! str_contains( $sourcePlugin, 'Plugin URI: ' . CANONICAL_REPOSITORY_URL )
	|| ! str_contains( $sourcePlugin, 'Update URI: ' . CANONICAL_REPOSITORY_URL ) ) {
	fail( 'Source plugin identity or version is invalid.' );
}

$composerPath = tempnam( sys_get_temp_dir(), 'ran-migrator-certification-' );
if ( false === $composerPath ) {
	fail( 'Could not create a temporary certification manifest.' );
}
file_put_contents( $composerPath, git_output( array( 'show', $sourceCommit . ':composer.json' ) ) );
try {
	Certification\read_core_certification( $composerPath );
} catch ( Throwable $error ) {
	unlink( $composerPath );
	fail( $error->getMessage() );
}
unlink( $composerPath );

$archiveName = basename( $archive );
if ( $archiveName !== SLUG . '-' . $version . '.zip' ) {
	fail( 'Archive filename does not match the source version.' );
}
$checksum = $archive . '.sha256';
if ( ! is_file( $checksum ) ) {
	fail( 'Release checksum is missing.' );
}
$archiveHash    = hash_file( 'sha256', $archive );
$expectedDigest = $archiveHash . '  ' . $archiveName;
$recordedDigest = rtrim( (string) file_get_contents( $checksum ), "\r\n" );
if ( ! is_string( $archiveHash ) || ! hash_equals( $expectedDigest, $recordedDigest ) ) {
	fail( 'Release checksum does not match the archive and basename.' );
}

$archiveSize = filesize( $archive );
$expectedMetadata = array(
	'schema'             => 'ran-wordpress-plugin-release',
	'schema_version'     => 1,
	'repository'         => CANONICAL_REPOSITORY,
	'tag'                => 'v' . $version,
	'commit'             => $sourceCommit,
	'zip'                => $archiveName,
	'plugin_root'        => SLUG,
	'main_file'          => SLUG . '.php',
	'version'            => $version,
	'requires_php'       => $requiresPhp[1] ?? '',
	'requires_wordpress' => $requiresWordPress[1] ?? '',
	'tested_wordpress'   => $testedWordPress[1] ?? '',
	'zip_size'           => $archiveSize,
	'zip_sha256'         => $archiveHash,
);
if ( release_metadata( dirname( $archive ) . '/' . substr( $archiveName, 0, -4 ) . '.json' ) !== $expectedMetadata ) {
	fail( 'Release manifest does not match the source commit, archive and package identity.' );
}

$expectedFiles       = source_files( $sourceCommit );
$expectedDirectories = expected_directories( $expectedFiles );
$zip                 = new ZipArchive();
if ( true !== $zip->open( $archive, ZipArchive::RDONLY ) ) {
	fail( 'Release archive could not be opened.' );
}
if ( $zip->numFiles < 1 || $zip->numFiles > MAX_ARCHIVE_MEMBERS ) {
	fail( 'Archive member count exceeds the safe bound.' );
}

$seenRaw           = array();
$seenCaseFolded    = array();
$seenNormalized    = array();
$actualFiles       = array();
$actualDirectories = array();
$compressedBytes   = 0;
$uncompressedBytes = 0;
for ( $index = 0; $index < $zip->numFiles; ++$index ) {
	$stat = $zip->statIndex( $index, ZipArchive::FL_UNCHANGED );
	$name = $zip->getNameIndex( $index, ZipArchive::FL_UNCHANGED );
	if ( false === $stat || false === $name || isset( $seenRaw[ $name ] ) ) {
		fail( 'Archive contains duplicate or unreadable raw members.' );
	}
	$seenRaw[ $name ] = true;
	$caseFolded       = strtolower( $name );
	if ( isset( $seenCaseFolded[ $caseFolded ] ) ) {
		fail( 'Archive contains case-insensitive member collisions.' );
	}
	$seenCaseFolded[ $caseFolded ] = true;
	$normalized = normalize_member_name( $name );
	if ( isset( $seenNormalized[ $normalized ] ) ) {
		fail( 'Archive contains normalized member collisions.' );
	}
	$seenNormalized[ $normalized ] = true;

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
	$compressedBytes   += $compressed;
	$uncompressedBytes += $size;
	if ( $compressedBytes > MAX_COMPRESSED_BYTES || $uncompressedBytes > MAX_UNCOMPRESSED_BYTES ) {
		fail( 'Archive aggregate size exceeds the safe bound.' );
	}

	$operations = 0;
	$attributes = 0;
	if ( ! $zip->getExternalAttributesIndex( $index, $operations, $attributes, ZipArchive::FL_UNCHANGED ) ) {
		fail( 'Archive member attributes are unreadable.' );
	}
	$mode        = ( $attributes >> 16 ) & 0xffff;
	$isDirectory = str_ends_with( $name, '/' );
	if ( $isDirectory && ( 0 !== $size || 0 !== $compressed ) ) {
		fail( 'Archive contains a non-empty directory member.' );
	}
	if ( ZipArchive::OPSYS_UNIX === $operations
		&& ( $isDirectory ? 0040775 !== $mode && 0040755 !== $mode : 0100664 !== $mode && 0100644 !== $mode ) ) {
		fail( 'Archive contains a symlink, non-regular or executable member.' );
	}
	if ( ZipArchive::OPSYS_UNIX !== $operations && 0 !== $mode ) {
		fail( 'Archive contains untrusted non-Unix mode metadata.' );
	}
	if ( $isDirectory ) {
		$actualDirectories[ $normalized ] = true;
		continue;
	}

	$content = $zip->getFromIndex( $index, $size, ZipArchive::FL_UNCHANGED );
	if ( false === $content || strlen( $content ) !== $size ) {
		fail( 'Archive member could not be read completely.' );
	}
	$actualFiles[ $normalized ] = $content;
}
$zip->close();

ksort( $actualFiles, SORT_STRING );
ksort( $actualDirectories, SORT_STRING );
if ( array_keys( $actualFiles ) !== array_keys( $expectedFiles )
	|| array_keys( $actualDirectories ) !== array_keys( $expectedDirectories ) ) {
	fail( 'Archive contents do not exactly match the source allowlist.' );
}
foreach ( $expectedFiles as $name => $content ) {
	if ( ! hash_equals( hash( 'sha256', $content ), hash( 'sha256', $actualFiles[ $name ] ) ) ) {
		fail( 'Archive member bytes do not match the source commit.' );
	}
}

$plugin = $actualFiles[ PACKAGE_ROOT . 'src/Plugin.php' ] ?? '';
$source = $actualFiles[ PACKAGE_ROOT . 'src/WpPusherSource.php' ] ?? '';
if ( ! str_contains( $plugin, 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
	|| ! preg_match( '/REQUIRED_PORTABILITY_API_VERSION\s*=\s*2/', $plugin )
	|| ! preg_match( '/REQUIRED_ADMIN_INTERACTION_API_VERSION\s*=\s*2/', $plugin )
	|| ! str_contains( $plugin, "'ran_booster_portability_ready'" )
	|| ! str_contains( $plugin, "'ran_booster_admin_interaction_ready'" )
	|| str_contains( implode( '', $actualFiles ), 'LoggingFacade' )
	|| ! str_contains( $source, "private const VERSION = '3.0.13';" ) ) {
	fail( 'Archive does not preserve the Migrator source and Core API boundaries.' );
}
foreach ( array_keys( $actualFiles ) as $name ) {
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
foreach ( $actualFiles as $name => $content ) {
	if ( ! str_ends_with( $name, '.php' ) ) {
		continue;
	}
	$path = $temporary . '/' . substr( $name, strlen( PACKAGE_ROOT ) );
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0700, true );
	}
	file_put_contents( $path, $content );
	$process = proc_open( array( PHP_BINARY, '-l', $path ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
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

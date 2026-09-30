<?php

declare(strict_types=1);

// phpcs:disable -- Standalone development guard uses PHPStan internals and CLI filesystem APIs.

use PHPStan\Command\CommandHelper;

$root = dirname( __DIR__ );
chdir( $root );

try {
	if ( 2 !== $argc || ! is_file( $argv[1] ) ) {
		throw new RuntimeException( 'Usage: php scripts/check-analysis-coverage.php <finished-runtime.zip>' );
	}
	$manifest = json_decode( file_get_contents( $root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
	if ( ( $manifest['scripts']['analyze'] ?? null ) !== array(
		'@analysis:setup',
		'phpstan analyze --configuration=phpstan.neon.dist --memory-limit=512M',
	) ) {
		throw new RuntimeException( 'Review coverage discovery when the canonical analyze command changes.' );
	}
	require $root . '/vendor/autoload.php';
	// This is the actual CLI discovery entry point, not an independent NEON/path parser.
	// Review this internal API when intentionally updating the locked PHPStan version.
	if ( '2.2.16' !== Composer\InstalledVersions::getPrettyVersion( 'phpstan/phpstan' ) ) {
		throw new RuntimeException( 'Review coverage discovery for the installed PHPStan version.' );
	}
	// Resolve the PHAR-scoped Symfony namespace without baking its build hash into this script.
	$parameters = ( new ReflectionMethod( CommandHelper::class, 'begin' ) )->getParameters();
	$input_class = str_replace( 'InputInterface', 'ArrayInput', $parameters[0]->getType()->getName() );
	$output_class = str_replace( 'OutputInterface', 'ConsoleOutput', $parameters[1]->getType()->getName() );
	$inception = CommandHelper::begin(
		new $input_class( array() ), new $output_class(), array(), '512M', null,
		array( $root ), $root . '/phpstan.neon.dist', null, null,
		false, false, null, null, false
	);
	// getFiles includes imported/merged paths, extension filters, analyse/scan exclusions
	// and configured stub exclusions. scanDirectories alone never establishes coverage.
	$selected = array_fill_keys( $inception->getFiles()[0], true );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $argv[1], ZipArchive::RDONLY ) ) {
		throw new RuntimeException( 'Cannot open the finished runtime ZIP.' );
	}
	$prefix = 'ran-booster-wp-pusher-migrator/';
	$count = 0;
	$missing = array();
	for ( $index = 0; $index < $zip->numFiles; ++$index ) {
		$name = $zip->getNameIndex( $index );
		if ( ! is_string( $name ) || ! str_starts_with( $name, $prefix )
			|| str_contains( $name, '\\' ) || str_contains( $name, "\0" )
			|| preg_match( '~(?:^|/)(?:\.|\.\.)(?:/|$)|//~', $name ) ) {
			throw new RuntimeException( 'Non-canonical archive member; run archive:verify.' );
		}
		if ( 0 !== strcasecmp( pathinfo( $name, PATHINFO_EXTENSION ), 'php' ) ) {
			continue;
		}
		$relative = substr( $name, strlen( $prefix ) );
		$source = $root . '/' . $relative;
		++$count;
		if ( ! isset( $selected[$source] ) ) {
			$missing[] = $relative;
			continue;
		}
		// Bind discovery to the shipped bytes, not a same-named local substitute.
		if ( ! is_file( $source ) || $zip->getFromIndex( $index ) !== file_get_contents( $source ) ) {
			throw new RuntimeException( 'Shipped PHP differs from selected source: ' . $relative );
		}
	}
	$zip->close();
	if ( array() !== $missing ) {
		throw new RuntimeException( 'Shipped PHP is outside direct PHPStan selection: ' . implode( ', ', $missing ) );
	}
	if ( 0 === $count ) {
		throw new RuntimeException( 'Runtime archive contains no PHP files.' );
	}
	printf( "Analysis coverage: all %d shipped PHP files directly selected by locked PHPStan.\n", $count );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'Analysis coverage failed: ' . $error->getMessage() . "\n" );
	exit( 1 );
}

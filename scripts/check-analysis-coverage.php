<?php

declare(strict_types=1);


use PHPStan\Command\CommandHelper;

$ran_booster_wp_pusher_migrator_root = dirname( __DIR__ );
chdir( $ran_booster_wp_pusher_migrator_root );

try {
	if ( ! isset( $argc, $argv ) ) {
		throw new RuntimeException( 'Analysis coverage requires CLI argument registration.' );
	}
	// Composer forwards analyzer flags to every aggregate step; source mode consumes none.
	$ran_booster_wp_pusher_migrator_source_only = '--source' === ( $argv[1] ?? null );
	$ran_booster_wp_pusher_migrator_development = '--development' === ( $argv[1] ?? null );
	if ( ! $ran_booster_wp_pusher_migrator_source_only && ! $ran_booster_wp_pusher_migrator_development && ( 2 !== $argc || ! is_file( $argv[1] ) ) ) {
		throw new RuntimeException( 'Usage: php scripts/check-analysis-coverage.php --source|--development|finished-runtime.zip (exactly one archive required in archive mode)' );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone CLI guard reads local analysis metadata and emits terminal diagnostics, not HTML.
	$ran_booster_wp_pusher_migrator_manifest = json_decode( file_get_contents( $ran_booster_wp_pusher_migrator_root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
	if ( ( $ran_booster_wp_pusher_migrator_manifest['scripts']['analyze'] ?? null ) !== array( '@analyze:production', '@analyze:development' )
		|| ( $ran_booster_wp_pusher_migrator_manifest['scripts']['analyze:development'] ?? null ) !== array(
			'@analysis:setup',
			'@php scripts/check-analysis-coverage.php --development',
			'phpstan analyze --configuration=phpstan-development.neon.dist --memory-limit=512M',
			'phpstan analyze --configuration=phpstan-real-proofs.neon.dist --memory-limit=512M',
		)
		|| ( $ran_booster_wp_pusher_migrator_manifest['scripts']['analyze:production'] ?? null ) !== array(
			'@analysis:setup',
			'@php scripts/check-analysis-coverage.php --source',
			'phpstan analyze --configuration=phpstan.neon.dist --memory-limit=512M',
		) ) {
		throw new RuntimeException( 'Review coverage discovery when the canonical analyze command changes.' );
	}
	require $ran_booster_wp_pusher_migrator_root . '/vendor/autoload.php';
	// This is the actual CLI discovery entry point, not an independent NEON/path parser.
	// Review this internal API when intentionally updating the locked PHPStan version.
	if ( '2.2.16' !== Composer\InstalledVersions::getPrettyVersion( 'phpstan/phpstan' ) ) {
		throw new RuntimeException( 'Review coverage discovery for the installed PHPStan version.' );
	}
	$ran_booster_wp_pusher_migrator_configurations = $ran_booster_wp_pusher_migrator_development ? array( 'phpstan-development.neon.dist', 'phpstan-real-proofs.neon.dist' ) : array( 'phpstan.neon.dist' );
	$ran_booster_wp_pusher_migrator_selected       = array();
	$ran_booster_wp_pusher_migrator_exemptions     = $ran_booster_wp_pusher_migrator_development ? array( 'vendor', 'node_modules', 'dist', '.git', '.workspaces' ) : array( 'tests', 'scripts', 'vendor', 'node_modules', 'dist', '.git', '.workspaces' );
	foreach ( $ran_booster_wp_pusher_migrator_configurations as $ran_booster_wp_pusher_migrator_configuration ) {
		// Resolve the PHAR-scoped Symfony namespace without baking its build hash into this script.
		// @phpstan-ignore phpstanApi.classConstant (Locked CLI discovery contract deliberately inspects its exact internal entry point.)
		$ran_booster_wp_pusher_migrator_parameters  = ( new ReflectionMethod( CommandHelper::class, 'begin' ) )->getParameters();
		$ran_booster_wp_pusher_migrator_input_type  = $ran_booster_wp_pusher_migrator_parameters[0]->getType();
		$ran_booster_wp_pusher_migrator_output_type = $ran_booster_wp_pusher_migrator_parameters[1]->getType();
		if ( ! $ran_booster_wp_pusher_migrator_input_type instanceof ReflectionNamedType || ! $ran_booster_wp_pusher_migrator_output_type instanceof ReflectionNamedType ) {
			throw new RuntimeException( 'Review changed PHPStan discovery parameter types.' );
		}
		$ran_booster_wp_pusher_migrator_input_class  = str_replace( 'InputInterface', 'ArrayInput', $ran_booster_wp_pusher_migrator_input_type->getName() );
		$ran_booster_wp_pusher_migrator_output_class = str_replace( 'OutputInterface', 'ConsoleOutput', $ran_booster_wp_pusher_migrator_output_type->getName() );
		// @phpstan-ignore phpstanApi.method (Use the locked actual CLI file selection, including imports and stubs.)
		$ran_booster_wp_pusher_migrator_inception = CommandHelper::begin( new $ran_booster_wp_pusher_migrator_input_class( array() ), new $ran_booster_wp_pusher_migrator_output_class(), array(), '512M', null, array( $ran_booster_wp_pusher_migrator_root ), $ran_booster_wp_pusher_migrator_root . '/' . $ran_booster_wp_pusher_migrator_configuration, null, null, false, false, null, null, false );
		// @phpstan-ignore phpstanApi.method (Obtain the locked CLI effective analyzed files rather than approximate discovery.)
		$ran_booster_wp_pusher_migrator_selected += array_fill_keys( $ran_booster_wp_pusher_migrator_inception->getFiles()[0], true );
		// @phpstan-ignore phpstanApi.constructor, phpstanApi.method (Inspect locked NEON structure to fail closed on changed role boundaries.)
		$ran_booster_wp_pusher_migrator_config = ( new PHPStan\DependencyInjection\NeonAdapter( array() ) )->load( $ran_booster_wp_pusher_migrator_root . '/' . $ran_booster_wp_pusher_migrator_configuration );
		// The effective container includes imported suppressions; only the two reviewed
		// defensive POST checks may be excepted, and obsolete entries must still fail.
		$ran_booster_wp_pusher_migrator_reviewed_ignores = $ran_booster_wp_pusher_migrator_development ? array() : array(
			array(
				'identifier' => 'function.alreadyNarrowedType',
				'message'    => '#^Call to function is_array\\(\\) with array<mixed> will always evaluate to true\\.$#',
				'path'       => $ran_booster_wp_pusher_migrator_root . '/src/MigrationRequestController.php',
				'count'      => 2,
			),
		);
		// @phpstan-ignore phpstanApi.method (Read the locked CLI effective container rather than only its top-level configuration.)
		$ran_booster_wp_pusher_migrator_container = $ran_booster_wp_pusher_migrator_inception->getContainer();
		if ( $ran_booster_wp_pusher_migrator_reviewed_ignores !== $ran_booster_wp_pusher_migrator_container->getParameter( 'ignoreErrors' )
			|| true !== $ran_booster_wp_pusher_migrator_container->getParameter( 'reportUnmatchedIgnoredErrors' ) ) {
			throw new RuntimeException( 'Review effective analysis suppressions and their exact occurrence counts.' );
		}
		if ( ! $ran_booster_wp_pusher_migrator_development ) {
			if ( 6 !== ( $ran_booster_wp_pusher_migrator_config['parameters']['level'] ?? null )
				|| array( 'analyseAndScan' => array_map( static fn( string $ran_booster_wp_pusher_migrator_path ): string => $ran_booster_wp_pusher_migrator_path . '/*', array_values( array_diff( $ran_booster_wp_pusher_migrator_exemptions, array( 'vendor' ) ) ) ) ) !== ( $ran_booster_wp_pusher_migrator_config['parameters']['excludePaths'] ?? null )
				|| array( 'vendor/ran-source-core/source/RAN' ) !== ( $ran_booster_wp_pusher_migrator_config['parameters']['scanDirectories'] ?? null )
				|| array( 'tests/phpstan-bootstrap.php' ) !== ( $ran_booster_wp_pusher_migrator_config['parameters']['bootstrapFiles'] ?? null ) ) {
				throw new RuntimeException( 'Review maintained analysis scope and its root-only development exemptions.' );
			}
		} else {
			$ran_booster_wp_pusher_migrator_real     = 'phpstan-real-proofs.neon.dist' === $ran_booster_wp_pusher_migrator_configuration;
			$ran_booster_wp_pusher_migrator_paths    = $ran_booster_wp_pusher_migrator_real ? array( 'tests/installed-candidate', 'tests/source-candidate-behaviour.php' ) : array( 'tests', 'scripts' );
			$ran_booster_wp_pusher_migrator_excluded = $ran_booster_wp_pusher_migrator_real ? array( 'tests/fixtures/PortabilityApi.php', 'tests/fixtures/AdminInteractionApi.php', 'tests/bootstrap.php' ) : array( 'tests/installed-candidate/*', 'tests/source-candidate-behaviour.php' );
			$ran_booster_wp_pusher_migrator_scanned  = $ran_booster_wp_pusher_migrator_real ? array( 'tests/fixtures/analysis', 'src', 'vendor/ran-source-core/source/RAN' ) : array( 'src' );
			if ( 5 !== ( $ran_booster_wp_pusher_migrator_config['parameters']['level'] ?? null )
				|| ( $ran_booster_wp_pusher_migrator_config['parameters']['paths'] ?? null ) !== $ran_booster_wp_pusher_migrator_paths
				|| array( 'analyseAndScan' => $ran_booster_wp_pusher_migrator_excluded ) !== ( $ran_booster_wp_pusher_migrator_config['parameters']['excludePaths'] ?? null )
				|| ( $ran_booster_wp_pusher_migrator_config['parameters']['scanDirectories'] ?? null ) !== $ran_booster_wp_pusher_migrator_scanned
				|| ( $ran_booster_wp_pusher_migrator_real ? array( 'tests/source-candidate-bootstrap.php', 'tests/WpPusherSourceTest.php' ) : array() ) !== ( $ran_booster_wp_pusher_migrator_config['parameters']['scanFiles'] ?? array() )
				|| array( 'tests/phpstan-bootstrap.php' ) !== ( $ran_booster_wp_pusher_migrator_config['parameters']['bootstrapFiles'] ?? null )
				|| array( 'vendor/szepeviktor/phpstan-wordpress/extension.neon' ) !== ( $ran_booster_wp_pusher_migrator_config['includes'] ?? null )
				|| isset( $ran_booster_wp_pusher_migrator_config['parameters']['ignoreErrors'] ) || isset( $ran_booster_wp_pusher_migrator_config['parameters']['fileExtensions'] ) ) {
				throw new RuntimeException( 'Review development analysis level and isolated fixture worlds.' );
			}
		}
	}
	$ran_booster_wp_pusher_migrator_iterator = new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $ran_booster_wp_pusher_migrator_root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $ran_booster_wp_pusher_migrator_entry ) use ( $ran_booster_wp_pusher_migrator_root, $ran_booster_wp_pusher_migrator_exemptions ): bool {
			return ! $ran_booster_wp_pusher_migrator_entry->isDir() || ! in_array( substr( $ran_booster_wp_pusher_migrator_entry->getPathname(), strlen( $ran_booster_wp_pusher_migrator_root ) + 1 ), $ran_booster_wp_pusher_migrator_exemptions, true );
		}
	);
	$ran_booster_wp_pusher_migrator_expected = array();
	foreach ( new RecursiveIteratorIterator( $ran_booster_wp_pusher_migrator_iterator ) as $ran_booster_wp_pusher_migrator_entry ) {
		if ( ! $ran_booster_wp_pusher_migrator_entry->isFile() || ( $ran_booster_wp_pusher_migrator_development && ! preg_match( '~^(?:tests|scripts)/~', substr( $ran_booster_wp_pusher_migrator_entry->getPathname(), strlen( $ran_booster_wp_pusher_migrator_root ) + 1 ) ) ) ) {
			continue;
		}
		if ( 0 === strcasecmp( $ran_booster_wp_pusher_migrator_entry->getExtension(), 'php' ) ) {
			if ( 'php' !== $ran_booster_wp_pusher_migrator_entry->getExtension() ) {
				throw new RuntimeException( 'Unsupported PHP extension must not evade analysis.' );
			}
			$ran_booster_wp_pusher_migrator_expected[] = $ran_booster_wp_pusher_migrator_entry->getPathname();
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone CLI guard reads local analysis metadata and emits terminal diagnostics, not HTML.
			$ran_booster_wp_pusher_migrator_header = file_get_contents( $ran_booster_wp_pusher_migrator_entry->getPathname(), false, null, 0, 512 );
			if ( false === $ran_booster_wp_pusher_migrator_header ) {
				throw new RuntimeException( 'Cannot inspect maintained file for PHP coverage.' );
			}
			$ran_booster_wp_pusher_migrator_extension = strtolower( $ran_booster_wp_pusher_migrator_entry->getExtension() );
			// Documentation/data and declared Bash fixtures may contain literal PHP.
			// Every other suffix, including future template formats, is inspected fully.
			$ran_booster_wp_pusher_migrator_inert = in_array( $ran_booster_wp_pusher_migrator_extension, array( 'md', 'json' ), true )
				|| ( 'sh' === $ran_booster_wp_pusher_migrator_extension && ( str_starts_with( $ran_booster_wp_pusher_migrator_header, "#!/usr/bin/env bash\n" ) || str_starts_with( $ran_booster_wp_pusher_migrator_header, "#!/bin/bash\n" ) ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Inspect potentially executable local source bodies without executing them.
			$ran_booster_wp_pusher_migrator_contents = $ran_booster_wp_pusher_migrator_inert ? $ran_booster_wp_pusher_migrator_header : file_get_contents( $ran_booster_wp_pusher_migrator_entry->getPathname() );
			if ( false === $ran_booster_wp_pusher_migrator_contents ) {
				throw new RuntimeException( 'Cannot inspect maintained file for PHP coverage.' );
			}
			// Only a genuine leading XML declaration is data rather than a possible short PHP tag.
			$ran_booster_wp_pusher_migrator_contents = preg_replace(
				'~\A(?:\xEF\xBB\xBF)?<\?xml[ \t\r\n]+version[ \t\r\n]*=[ \t\r\n]*(?:"1\.[01]"|\'1\.[01]\')(?:[ \t\r\n]+encoding[ \t\r\n]*=[ \t\r\n]*(?:"[A-Za-z][A-Za-z0-9._-]*"|\'[A-Za-z][A-Za-z0-9._-]*\'))?(?:[ \t\r\n]+standalone[ \t\r\n]*=[ \t\r\n]*(?:"(?:yes|no)"|\'(?:yes|no)\'))?[ \t\r\n]*\?>~',
				'',
				$ran_booster_wp_pusher_migrator_contents
			);
			if ( null === $ran_booster_wp_pusher_migrator_contents || 'phtml' === $ran_booster_wp_pusher_migrator_extension || preg_match( $ran_booster_wp_pusher_migrator_inert ? '/^(?:\xEF\xBB\xBF)?(?:#![^\n]*\n)?\s*<\?/i' : '/<\?/i', $ran_booster_wp_pusher_migrator_contents ) ) {
				throw new RuntimeException( 'Nonstandard-extension PHP needs an explicit reviewed analysis decision.' );
			}
		}
	}
	if ( array() === $ran_booster_wp_pusher_migrator_expected || array() !== array_diff( $ran_booster_wp_pusher_migrator_expected, array_keys( $ran_booster_wp_pusher_migrator_selected ) ) || array() !== array_diff( array_keys( $ran_booster_wp_pusher_migrator_selected ), $ran_booster_wp_pusher_migrator_expected ) ) {
		throw new RuntimeException( 'Effective PHPStan selection differs from independently discovered PHP.' );
	}
	if ( $ran_booster_wp_pusher_migrator_source_only || $ran_booster_wp_pusher_migrator_development ) {
		exit( 0 );
	}
	printf( "Analysis coverage: all %d maintained production PHP files directly selected.\n", count( $ran_booster_wp_pusher_migrator_expected ) );
	$ran_booster_wp_pusher_migrator_zip = new ZipArchive();
	if ( true !== $ran_booster_wp_pusher_migrator_zip->open( $argv[1], ZipArchive::RDONLY ) ) {
		throw new RuntimeException( 'Cannot open the finished runtime ZIP.' );
	}
	$ran_booster_wp_pusher_migrator_prefix  = 'ran-booster-wp-pusher-migrator/';
	$ran_booster_wp_pusher_migrator_count   = 0;
	$ran_booster_wp_pusher_migrator_missing = array();
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native ZipArchive::$numFiles property.
	for ( $ran_booster_wp_pusher_migrator_index = 0; $ran_booster_wp_pusher_migrator_index < $ran_booster_wp_pusher_migrator_zip->numFiles; ++$ran_booster_wp_pusher_migrator_index ) {
		$ran_booster_wp_pusher_migrator_name = $ran_booster_wp_pusher_migrator_zip->getNameIndex( $ran_booster_wp_pusher_migrator_index );
		if ( ! is_string( $ran_booster_wp_pusher_migrator_name ) || ! str_starts_with( $ran_booster_wp_pusher_migrator_name, $ran_booster_wp_pusher_migrator_prefix )
			|| str_contains( $ran_booster_wp_pusher_migrator_name, '\\' ) || str_contains( $ran_booster_wp_pusher_migrator_name, "\0" )
			|| preg_match( '~(?:^|/)(?:\.|\.\.)(?:/|$)|//~', $ran_booster_wp_pusher_migrator_name ) ) {
			throw new RuntimeException( 'Non-canonical archive member; run archive:verify.' );
		}
		if ( 0 !== strcasecmp( pathinfo( $ran_booster_wp_pusher_migrator_name, PATHINFO_EXTENSION ), 'php' ) ) {
			continue;
		}
		$ran_booster_wp_pusher_migrator_relative = substr( $ran_booster_wp_pusher_migrator_name, strlen( $ran_booster_wp_pusher_migrator_prefix ) );
		$ran_booster_wp_pusher_migrator_source   = $ran_booster_wp_pusher_migrator_root . '/' . $ran_booster_wp_pusher_migrator_relative;
		++$ran_booster_wp_pusher_migrator_count;
		if ( ! isset( $ran_booster_wp_pusher_migrator_selected[ $ran_booster_wp_pusher_migrator_source ] ) ) {
			$ran_booster_wp_pusher_migrator_missing[] = $ran_booster_wp_pusher_migrator_relative;
			continue;
		}
		// Bind discovery to the shipped bytes, not a same-named local substitute.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone CLI guard reads local analysis metadata and emits terminal diagnostics, not HTML.
		if ( ! is_file( $ran_booster_wp_pusher_migrator_source ) || $ran_booster_wp_pusher_migrator_zip->getFromIndex( $ran_booster_wp_pusher_migrator_index ) !== file_get_contents( $ran_booster_wp_pusher_migrator_source ) ) {
			throw new RuntimeException( 'Shipped PHP differs from selected source: ' . $ran_booster_wp_pusher_migrator_relative );
		}
	}
	$ran_booster_wp_pusher_migrator_zip->close();
	if ( array() !== $ran_booster_wp_pusher_migrator_missing ) {
		throw new RuntimeException( 'Shipped PHP is outside direct PHPStan selection: ' . implode( ', ', $ran_booster_wp_pusher_migrator_missing ) );
	}
	if ( 0 === $ran_booster_wp_pusher_migrator_count ) {
		throw new RuntimeException( 'Runtime archive contains no PHP files.' );
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone CLI guard reads local analysis metadata and emits terminal diagnostics, not HTML.
	printf( "Analysis coverage: all %d shipped PHP files directly selected by locked PHPStan.\n", $ran_booster_wp_pusher_migrator_count );
} catch ( Throwable $ran_booster_wp_pusher_migrator_error ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI guard reads local analysis metadata and emits terminal diagnostics, not HTML.
	fwrite( STDERR, 'Analysis coverage failed: ' . $ran_booster_wp_pusher_migrator_error->getMessage() . "\n" );
	exit( 1 );
}

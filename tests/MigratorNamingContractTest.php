<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests;

use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use ReflectionClass;

final class MigratorNamingContractTest extends TestCase {
	public function test_owned_declarations_preserve_completed_naming_scope(): void {
		foreach ( array( 'Autoloader', 'CandidateFactory', 'MigrationPresenter', 'MigrationRequestController', 'MigrationService', 'Plugin', 'WpPusherPackage', 'WpPusherSource' ) as $name ) {
			$class = new ReflectionClass( 'RAN\\BoosterWpPusherMigrator\\' . $name );
			foreach ( $class->getProperties() as $property ) {
				self::assertMatchesRegularExpression( '/\A[a-z_][a-z0-9_]*\z/', $property->getName() );
			}
			foreach ( $class->getMethods() as $method ) {
				self::assertMatchesRegularExpression( '/\A[a-z_][a-z0-9_]*\z/', $method->getName() );
				foreach ( $method->getParameters() as $parameter ) {
					self::assertMatchesRegularExpression( '/\A[a-z_][a-z0-9_]*\z/', $parameter->getName() );
				}
			}
		}
	}

	public function test_helper_naming_cannot_be_hidden_by_blanket_suppressions(): void {
		$root  = dirname( __DIR__ );
		$files = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
				static fn ( \SplFileInfo $file ): bool => ! $file->isDir() || ! in_array( substr( $file->getPathname(), strlen( $root ) + 1 ), array( '.git', 'vendor', 'node_modules', 'dist', '.workspaces' ), true )
			)
		);
		foreach ( $files as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Inspect comments in owned maintained source without executing it.
			$tokens = token_get_all( file_get_contents( $file->getPathname() ) );
			foreach ( $tokens as $token ) {
				if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					self::assertFalse( self::is_blanket_suppression( $token[1] ), $file->getPathname() . ':' . $token[2] . ' blanket PHPCS suppression' );
				}
			}
		}
	}

	private static function is_blanket_suppression( string $comment ): bool {
		// PHPCS treats modern directives case-insensitively, including doc-comment lines.
		$normalized = preg_replace( '/[\s*\/]+/', ' ', $comment );
		if ( 1 === preg_match( '/@codingStandards(?:ChangeSetting|Ignore)|phpcs:(?:set|disable|enable|ignorefile)/i', $normalized ) ) {
			return true;
		}
		preg_match_all( '/phpcs:ignore\b(.*?)(?=phpcs:|$)/i', $normalized, $directives );
		foreach ( $directives[1] as $directive ) {
			// Only a diagnostic (not its standard/category/sniff), with an occurrence rationale.
			if ( 1 !== preg_match( '/\A\s*[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){3}(?:\s*,\s*[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){3})*\s+--\s+\S.*\z/', $directive ) ) {
				return true;
			}
		}
		return false;
	}

	public function test_standards_profile_cannot_silently_weaken_coverage(): void {
		$root = dirname( __DIR__ );
		$xml  = simplexml_load_file( $root . '/.phpcs.xml.dist' );
		self::assertNotFalse( $xml );
		self::assertSame( array( '.' ), array_map( static fn( \SimpleXMLElement $node ): string => (string) $node, $xml->xpath( './file' ) ) );
		self::assertSame( array( '/vendor/', '/node_modules/', '/.git/', '/.phpunit.cache/', '/.phpcs-cache' ), array_map( static fn( \SimpleXMLElement $node ): string => (string) $node, $xml->xpath( './exclude-pattern' ) ) );
		self::assertSame( array( 'RANWordPressPlugin', 'RANOwnedMethods', 'WordPress.NamingConventions.PrefixAllGlobals' ), array_map( static fn( \SimpleXMLElement $node ): string => (string) $node['ref'], $xml->xpath( './rule' ) ) );
		self::assertSame( array(), $xml->xpath( '//exclude|//severity|//type|//rule//exclude-pattern|//rule//include-pattern' ), 'No rule disabling, recategorization, or path-specific bypasses.' );
		self::assertSame( array( 'ran_booster_wp_pusher_migrator', 'RAN\\BoosterWpPusherMigrator' ), array_map( static fn( \SimpleXMLElement $node ): string => (string) $node['value'], $xml->xpath( './rule/properties/property/element' ) ) );
		self::assertCount( 1, $xml->xpath( '//property' ) );
		self::assertSame( 'prefixes', (string) $xml->rule[2]->properties->property['name'] );
		self::assertSame( 'array', (string) $xml->rule[2]->properties->property['type'] );
		self::assertSame( array( 'minimum_wp_version=7.0', 'testVersion=8.2-' ), array_map( static fn( \SimpleXMLElement $node ): string => (string) $node['name'] . '=' . (string) $node['value'], $xml->xpath( './config' ) ) );
		self::assertSame( array( 'basepath=.', 'colors=', 'extensions=php', 'parallel=4', '=sp' ), array_map( static fn( \SimpleXMLElement $node ): string => (string) $node['name'] . '=' . (string) $node['value'], $xml->xpath( './arg' ) ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Inspect the canonical local command, without executing configuration.
		$manifest = json_decode( file_get_contents( $root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
		self::assertSame( 'phpcs --standard=.phpcs.xml.dist --report=summary', $manifest['scripts']['standards'] );
		self::assertSame( 'phpcbf --standard=.phpcs.xml.dist', $manifest['scripts']['standards:fix'] );
	}

	public function test_prefix_category_guard_keeps_precise_fixture_exceptions(): void {
		foreach ( array( '// phpcs:disable RANOwnedMethods', '// PHPCS:DISABLE WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound', '// phpcs:ignore WordPress.PHP.YodaConditions -- Too broad.', '// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress -- Mixed broad selector.', '// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound', '// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Reason. phpcs:ignore WordPress', '// phpcs:disable Generic.Files.LineLength, WordPress', '// PHPCS:IGNORE WordPress.NamingConventions', '// phpcs:set WordPress.NamingConventions.PrefixAllGlobals prefixes probe', '// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals' ) as $annotation ) {
			self::assertTrue( self::is_blanket_suppression( $annotation ) );
		}
		self::assertTrue( self::is_blanket_suppression( '/* phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals */' ) );
		self::assertFalse( self::is_blanket_suppression( '// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WordPress stand-in.' ) );
	}

	public function test_named_arguments_preserve_candidate_wire_fields(): void {
		$source    = new WpPusherPackage(
			id: 1,
			package: 'fixture/fixture.php',
			repository: 'owner/repository',
			branch: 'main',
			type: 1,
			status: 0,
			ptd: 0,
			host: 'gh',
			is_private: 1,
			subdirectory: null
		);
		$factory   = new CandidateFactory( plugins: static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture' ) ) );
		$fields    = $factory->candidate( source: $source, credential_id: 'profile_123' );
		$candidate = new PortabilityCandidate(
			type: $fields['type'],
			identifier: $fields['identifier'],
			display_name: $fields['display_name'],
			provider_code: $fields['provider'],
			repository: $fields['repository'],
			branch: $fields['branch'],
			subdirectory: $fields['subdirectory'],
			credential_id: $fields['credential_id']
		);
		self::assertSame( 'Fixture', $candidate->display_name );
		self::assertSame( 'gh', $candidate->provider_code );
		self::assertSame( 'profile_123', $candidate->credential_id );
		self::assertSame( array( 'id', 'package', 'repository', 'branch', 'type', 'status', 'ptd', 'host', 'private', 'subdirectory' ), array_keys( $source->to_array() ) );
		self::assertSame( 1, $source->to_array()['private'] );
		self::assertSame( $source->fingerprint(), WpPusherPackage::from_row( $source->to_array() )->fingerprint() );
		$result = new PortabilityApplyResult( status: 'adopted', reason: 'none', message: 'Adopted.', target_verified: true );
		self::assertTrue( $result->target_verified );
		self::assertFalse( ( new \ReflectionClass( $candidate ) )->hasProperty( 'credentialId' ) );
		self::assertFalse( ( new \ReflectionClass( $result ) )->hasProperty( 'targetVerified' ) );
	}
}

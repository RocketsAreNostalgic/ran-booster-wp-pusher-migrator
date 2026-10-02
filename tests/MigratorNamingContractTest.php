<?php

declare(strict_types=1);

namespace Tests;

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
		foreach ( array( 'tests', 'scripts' ) as $directory ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ) . '/' . $directory ) );
			foreach ( $files as $file ) {
				if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Inspect comments in owned development source without executing it.
				$tokens = token_get_all( file_get_contents( $file->getPathname() ) );
				foreach ( $tokens as $token ) {
					if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
						self::assertFalse( self::is_blanket_suppression( $token[1] ), $file->getPathname() . ':' . $token[2] . ' blanket PHPCS suppression' );
					}
				}
			}
		}
	}

	private static function is_blanket_suppression( string $comment ): bool {
		// PHPCS annotations are case-insensitive; doc comments can split the directive over lines.
		$normalized = preg_replace( '/[\s*\/]+/', ' ', $comment );
		return 1 === preg_match( '/(?:@?phpcs:ignorefile\b|@codingStandardsIgnore(?:File|Start|Line)\b|@?phpcs:(?:disable|ignore)(?=\s*(?:--|$)))/i', trim( $normalized ) );
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
			private: 1,
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
		$result = new PortabilityApplyResult( status: 'adopted', reason: 'none', message: 'Adopted.', target_verified: true );
		self::assertTrue( $result->target_verified );
		self::assertFalse( property_exists( $candidate, 'credentialId' ) );
		self::assertFalse( property_exists( $result, 'targetVerified' ) );
	}
}

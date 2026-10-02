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

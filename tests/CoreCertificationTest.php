<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname( __DIR__ ) . '/scripts/core-certification.php';

final class CoreCertificationTest extends TestCase {
	public function test_composer_owns_the_only_machine_readable_core_certification_tuple(): void {
		$manifest = dirname( __DIR__ ) . '/composer.json';
		$tuple    = \RAN\BoosterWpPusherMigrator\Certification\read_core_certification( $manifest );
		$quality  = file_get_contents( dirname( __DIR__ ) . '/.github/workflows/quality.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
		$builder  = file_get_contents( dirname( __DIR__ ) . '/scripts/build-release.sh' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
		$verifier = file_get_contents( dirname( __DIR__ ) . '/scripts/verify-release.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
		self::assertIsString( $quality );
		self::assertIsString( $builder );
		self::assertIsString( $verifier );
		self::assertSame( array( 'tag', 'commit' ), array_keys( $tuple ) );
		self::assertMatchesRegularExpression( '/\Av[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?\z/', $tuple['tag'] );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{40}\z/', $tuple['commit'] );
		self::assertStringContainsString( 'php scripts/core-certification.php read composer.json', $quality );
		self::assertStringContainsString( 'ref: ${{ needs.runtime-archive.outputs.core-commit }}', $quality );
		self::assertStringContainsString( 'core-certification.php verify migrator/composer.json core', $quality );
		self::assertStringContainsString( 'git show "${source_commit}:composer.json"', $builder );
		self::assertStringContainsString( 'read_core_certification( $composer_path )', $verifier );
		self::assertStringNotContainsString( $tuple['commit'], $quality );
		self::assertStringNotContainsString( $tuple['tag'], $quality );
	}

	public function test_certification_reader_rejects_missing_malformed_and_extra_tuple_fields(): void {
		$fixtures = array(
			'not-json',
			'{}',
			'{"extra":[]}',
			'{"extra":{"ran-booster-core-certification":[]}}',
			'{"extra":{"ran-booster-core-certification":{"tag":"v1.2.3"}}}',
			'{"extra":{"ran-booster-core-certification":{"commit":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}}}',
			'{"extra":{"ran-booster-core-certification":{"tag":"v1.2.3","commit":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","branch":"main"}}}',
			'{"extra":{"ran-booster-core-certification":{"tag":"1.2.3","commit":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}}}',
			'{"extra":{"ran-booster-core-certification":{"tag":"v01.2.3","commit":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}}}',
			'{"extra":{"ran-booster-core-certification":{"tag":"v1.2.3","commit":"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"}}}',
			'{"extra":{"ran-booster-core-certification":{"tag":"v1.2.3","commit":123}}}',
		);

		foreach ( $fixtures as $fixture ) {
			$path = $this->temporary_manifest( $fixture );
			try {
				\RAN\BoosterWpPusherMigrator\Certification\read_core_certification( $path );
				self::fail( 'Malformed Core certification metadata must fail closed: ' . $fixture );
			} catch ( InvalidArgumentException ) {
				self::addToAssertionCount( 1 );
			} finally {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes a test-owned temporary file.
			}
		}
	}

	public function test_certification_checkout_rejects_contradictory_head_or_tag_resolution(): void {
		$tuple = \RAN\BoosterWpPusherMigrator\Certification\read_core_certification( dirname( __DIR__ ) . '/composer.json' );
		\RAN\BoosterWpPusherMigrator\Certification\assert_core_certification_checkout( $tuple, $tuple['commit'], $tuple['commit'] );
		self::addToAssertionCount( 1 );
		$other_commit = str_repeat( '0' === $tuple['commit'][0] ? '1' : '0', 40 );
		foreach ( array(
			array( $other_commit, $tuple['commit'], 'HEAD' ),
			array( $tuple['commit'], $other_commit, 'tag' ),
		) as $case ) {
			list( $head, $tag_commit, $expected ) = $case;
			try {
				\RAN\BoosterWpPusherMigrator\Certification\assert_core_certification_checkout( $tuple, $head, $tag_commit );
				self::fail( 'Contradictory Core certification must fail closed.' );
			} catch ( RuntimeException $error ) {
				self::assertStringContainsString( $expected, $error->getMessage() );
			}
		}
	}

	public function test_public_evidence_names_the_certified_tag_without_duplicating_its_commit(): void {
		$tuple = \RAN\BoosterWpPusherMigrator\Certification\read_core_certification( dirname( __DIR__ ) . '/composer.json' );
		foreach ( array( 'README.md', 'RELEASE.md', 'docs/phase-0-source-certification.md' ) as $relative_path ) {
			$copy = file_get_contents( dirname( __DIR__ ) . '/' . $relative_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
			self::assertIsString( $copy );
			self::assertStringContainsString( $tuple['tag'], $copy, $relative_path );
			self::assertStringNotContainsString( $tuple['commit'], $copy, $relative_path );
			self::assertStringContainsString( 'installed', strtolower( $copy ), $relative_path );
		}
	}

	private function temporary_manifest( string $contents ): string {
		$path = tempnam( sys_get_temp_dir(), 'ran-migrator-certification-' );
		self::assertIsString( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes a test-owned temporary fixture.
		self::assertNotFalse( file_put_contents( $path, $contents ) );

		return $path;
	}
}

<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname( __DIR__ ) . '/scripts/core-certification.php';

final class CoreCertificationTest extends TestCase {
	public function testComposerOwnsTheOnlyMachineReadableCoreCertificationTuple(): void {
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
		self::assertStringContainsString( 'read_core_certification( $composerPath )', $verifier );
		self::assertStringNotContainsString( $tuple['commit'], $quality );
		self::assertStringNotContainsString( $tuple['tag'], $quality );
	}

	public function testCertificationReaderRejectsMissingMalformedAndExtraTupleFields(): void {
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
			$path = $this->temporaryManifest( $fixture );
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

	public function testCertificationCheckoutRejectsContradictoryHeadOrTagResolution(): void {
		$tuple = \RAN\BoosterWpPusherMigrator\Certification\read_core_certification( dirname( __DIR__ ) . '/composer.json' );
		\RAN\BoosterWpPusherMigrator\Certification\assert_core_certification_checkout( $tuple, $tuple['commit'], $tuple['commit'] );
		self::addToAssertionCount( 1 );
		$otherCommit = str_repeat( '0' === $tuple['commit'][0] ? '1' : '0', 40 );
		foreach ( array(
			array( $otherCommit, $tuple['commit'], 'HEAD' ),
			array( $tuple['commit'], $otherCommit, 'tag' ),
		) as $case ) {
			list( $head, $tagCommit, $expected ) = $case;
			try {
				\RAN\BoosterWpPusherMigrator\Certification\assert_core_certification_checkout( $tuple, $head, $tagCommit );
				self::fail( 'Contradictory Core certification must fail closed.' );
			} catch ( RuntimeException $error ) {
				self::assertStringContainsString( $expected, $error->getMessage() );
			}
		}
	}

	public function testPublicEvidenceNamesTheCertifiedTagWithoutDuplicatingItsCommit(): void {
		$tuple = \RAN\BoosterWpPusherMigrator\Certification\read_core_certification( dirname( __DIR__ ) . '/composer.json' );
		foreach ( array( 'README.md', 'RELEASE.md', 'docs/phase-0-source-certification.md' ) as $relativePath ) {
			$copy = file_get_contents( dirname( __DIR__ ) . '/' . $relativePath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
			self::assertIsString( $copy );
			self::assertStringContainsString( $tuple['tag'], $copy, $relativePath );
			self::assertStringNotContainsString( $tuple['commit'], $copy, $relativePath );
			self::assertStringContainsString( 'installed', strtolower( $copy ), $relativePath );
		}
	}

	private function temporaryManifest( string $contents ): string {
		$path = tempnam( sys_get_temp_dir(), 'ran-migrator-certification-' );
		self::assertIsString( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes a test-owned temporary fixture.
		self::assertNotFalse( file_put_contents( $path, $contents ) );

		return $path;
	}
}

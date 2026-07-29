<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RuntimeException;

final class CandidateFactoryTest extends TestCase {

	public function testMapsInstalledPluginWithoutInventingStableIdentity(): void {
		$candidate = $this->factory()->candidate( $this->package() );

		self::assertSame(
			array(
				'type'          => 'plugin',
				'identifier'    => 'fixture/fixture.php',
				'display_name'  => 'Fixture Plugin',
				'provider'      => 'github',
				'repository'    => 'RocketsAreNostalgic/booster-fixture-plugin',
				'branch'        => 'main',
				'subdirectory'  => null,
				'credential_id' => null,
			),
			$candidate
		);
		self::assertArrayNotHasKey( 'provider_repository_id', $candidate );
	}

	public function testMapsThemeAndLegacyEmptyBranch(): void {
		$candidate = $this->factory()->candidate(
			$this->package(
				array(
					'package'      => 'booster-fixture-theme',
					'repository'   => 'RocketsAreNostalgic/booster-fixture-theme',
					'branch'       => '',
					'type'         => '2',
					'host'         => 'bb',
					'subdirectory' => 'packages/theme',
				)
			),
			'profile_123'
		);

		self::assertSame( 'theme', $candidate['type'] );
		self::assertSame( 'Fixture Theme', $candidate['display_name'] );
		self::assertSame( 'bitbucket', $candidate['provider'] );
		self::assertSame( 'master', $candidate['branch'] );
		self::assertSame( 'packages/theme', $candidate['subdirectory'] );
		self::assertSame( 'profile_123', $candidate['credential_id'] );
	}

	#[DataProvider( 'unsupportedCandidateProvider' )]
	public function testRejectsUnsupportedOrMissingCandidate(
		WpPusherPackage $package,
		array $plugins,
		bool $themeExists,
		?string $credentialId
	): void {
		$this->expectException( RuntimeException::class );
		$this->factory( $plugins, $themeExists )->candidate( $package, $credentialId );
	}

	/** @return iterable<string, array{WpPusherPackage, array<string, array<string, string>>, bool, string|null}> */
	public static function unsupportedCandidateProvider(): iterable {
		yield 'gitlab' => array( self::staticPackage( array( 'host' => 'gl' ) ), array(), true, null );
		yield 'missing plugin' => array( self::staticPackage(), array(), true, null );
		yield 'missing theme' => array(
			self::staticPackage(
				array(
					'type'    => '2',
					'package' => 'missing',
				)
			),
			array(),
			false,
			null,
		);
		yield 'private without replacement credential' => array(
			self::staticPackage( array( 'private' => '1' ) ),
			array( 'fixture/fixture.php' => array( 'Name' => 'Fixture' ) ),
			true,
			null,
		);
		yield 'invalid credential id' => array( self::staticPackage(), array( 'fixture/fixture.php' => array( 'Name' => 'Fixture' ) ), true, '../secret' );
	}

	/** @param array<string, array<string, string>>|null $plugins */
	private function factory( ?array $plugins = null, bool $themeExists = true ): CandidateFactory {
		$plugins ??= array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) );

		return new CandidateFactory(
			static fn (): array => $plugins,
			static fn ( string $stylesheet ): object => new FakeTheme( $stylesheet, $themeExists )
		);
	}

	/** @param array<string, mixed> $overrides */
	private function package( array $overrides = array() ): WpPusherPackage {
		return self::staticPackage( $overrides );
	}

	/** @param array<string, mixed> $overrides */
	private static function staticPackage( array $overrides = array() ): WpPusherPackage {
		return WpPusherPackage::fromRow(
			array_merge(
				array(
					'id'           => '1',
					'package'      => 'fixture/fixture.php',
					'repository'   => 'RocketsAreNostalgic/booster-fixture-plugin',
					'branch'       => 'main',
					'type'         => '1',
					'status'       => '1',
					'ptd'          => '0',
					'host'         => 'gh',
					'private'      => '0',
					'subdirectory' => null,
				),
				$overrides
			)
		);
	}
}

final class FakeTheme {

	public function __construct( private string $stylesheet, private bool $exists ) {
	}

	public function exists(): bool {
		return $this->exists;
	}

	public function get( string $field ): string {
		return 'Name' === $field && 'booster-fixture-theme' === $this->stylesheet ? 'Fixture Theme' : '';
	}
}

<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RuntimeException;

final class CandidateFactoryTest extends TestCase {

	public function test_maps_installed_plugin_without_inventing_stable_identity(): void {
		$candidate = $this->factory()->candidate( $this->package() );

		self::assertSame(
			array(
				'type'          => 'plugin',
				'identifier'    => 'fixture/fixture.php',
				'display_name'  => 'Fixture Plugin',
				'provider'      => 'gh',
				'repository'    => 'RocketsAreNostalgic/booster-fixture-plugin',
				'branch'        => 'main',
				'subdirectory'  => null,
				'credential_id' => null,
			),
			$candidate
		);
		self::assertArrayNotHasKey( 'provider_repository_id', $candidate );
	}

	public function test_maps_theme_and_legacy_empty_branch(): void {
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
		self::assertSame( 'bb', $candidate['provider'] );
		self::assertSame( 'master', $candidate['branch'] );
		self::assertSame( 'packages/theme', $candidate['subdirectory'] );
		self::assertSame( 'profile_123', $candidate['credential_id'] );
	}

	public function test_maps_public_bitbucket_plugin_without_credential(): void {
		$candidate = $this->factory()->candidate(
			$this->package(
				array(
					'host'       => 'bb',
					'repository' => 'fixture-workspace/public-plugin',
					'branch'     => 'stable',
				)
			)
		);

		self::assertSame(
			array(
				'type'          => 'plugin',
				'identifier'    => 'fixture/fixture.php',
				'display_name'  => 'Fixture Plugin',
				'provider'      => 'bb',
				'repository'    => 'fixture-workspace/public-plugin',
				'branch'        => 'stable',
				'subdirectory'  => null,
				'credential_id' => null,
			),
			$candidate
		);
	}

	public function test_maps_private_bitbucket_plugin_with_replacement_credential(): void {
		$candidate = $this->factory()->candidate(
			$this->package(
				array(
					'host'         => 'bb',
					'private'      => '1',
					'repository'   => 'fixture-workspace/private-plugin',
					'subdirectory' => 'packages/plugin',
				)
			),
			'bitbucket_profile'
		);

		self::assertSame( 'bb', $candidate['provider'] );
		self::assertSame( 'fixture-workspace/private-plugin', $candidate['repository'] );
		self::assertSame( 'packages/plugin', $candidate['subdirectory'] );
		self::assertSame( 'bitbucket_profile', $candidate['credential_id'] );
	}

	public function test_rejects_git_lab_with_user_visible_message(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'GitLab WP Pusher packages are not supported.' );

		$this->factory()->candidate( $this->package( array( 'host' => 'gl' ) ) );
	}

	#[DataProvider( 'unsupported_candidate_provider' )]
	public function test_rejects_unsupported_or_missing_candidate(
		WpPusherPackage $package,
		array $plugins,
		bool $theme_exists,
		?string $credential_id
	): void {
		$this->expectException( RuntimeException::class );
		$this->factory( $plugins, $theme_exists )->candidate( $package, $credential_id );
	}

	/** @return iterable<string, array{WpPusherPackage, array<string, array<string, string>>, bool, string|null}> */
	public static function unsupported_candidate_provider(): iterable {
		yield 'missing plugin' => array( self::static_package(), array(), true, null );
		yield 'missing theme' => array(
			self::static_package(
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
			self::static_package( array( 'private' => '1' ) ),
			array( 'fixture/fixture.php' => array( 'Name' => 'Fixture' ) ),
			true,
			null,
		);
		yield 'invalid credential id' => array( self::static_package(), array( 'fixture/fixture.php' => array( 'Name' => 'Fixture' ) ), true, '../secret' );
	}

	/** @param array<string, array<string, string>>|null $plugins */
	private function factory( ?array $plugins = null, bool $theme_exists = true ): CandidateFactory {
		$plugins ??= array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) );

		return new CandidateFactory(
			static fn (): array => $plugins,
			static fn ( string $stylesheet ): object => new FakeTheme( $stylesheet, $theme_exists )
		);
	}

	/** @param array<string, mixed> $overrides */
	private function package( array $overrides = array() ): WpPusherPackage {
		return self::static_package( $overrides );
	}

	/** @param array<string, mixed> $overrides */
	private static function static_package( array $overrides = array() ): WpPusherPackage {
		return WpPusherPackage::from_row(
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

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
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

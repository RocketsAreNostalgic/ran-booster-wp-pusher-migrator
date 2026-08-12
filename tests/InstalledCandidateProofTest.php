<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class InstalledCandidateProofTest extends TestCase {
	private const CORE_SHA = '1ac974014231b84694a2b0c04bd5bc27c61d5cc362d467539ec1aea0d4fdf8cd';

	private const WP_PUSHER_SHA = '4f1533b9b946afdf9d699ea54279ea236b7e25f3d3fc9182bb53cec295a52208';

	private string $driver;

	private string $probe;

	private string $recorder;

	protected function setUp(): void {
		$root           = __DIR__ . '/installed-candidate/';
		$this->driver   = $this->read( $root . 'migrator-installed-proof.sh' );
		$this->probe    = $this->read( $root . 'migrator-installed-probe.php' );
		$this->recorder = $this->read( $root . 'migrator-installed-recorder.php' );
	}

	public function testDriverPinsEveryPublishedInputAndCallerSelectedCandidateIdentity(): void {
		self::assertStringContainsString( "expected_migrator_version='0.1.0-beta.6'", $this->driver );
		self::assertStringContainsString( "expected_core_version='1.0.0-beta.15'", $this->driver );
		self::assertStringContainsString( self::CORE_SHA, $this->driver );
		self::assertStringContainsString( self::WP_PUSHER_SHA, $this->driver );
		self::assertStringContainsString( 'RAN_MIGRATOR_SOURCE_COMMIT', $this->driver );
		self::assertStringContainsString( 'status --porcelain=v1 --untracked-files=all', $this->driver );
		self::assertStringContainsString( 'scripts/verify-release.sh" "$migrator_archive" "$migrator_commit"', $this->driver );
		self::assertStringContainsString( 'diff -qr "$recovery/extracted-migrator/ran-booster-wp-pusher-migrator"', $this->driver );
	}

	public function testDriverOwnsARecoverableDatabasePhysicalAndSparseOptionBoundary(): void {
		self::assertStringContainsString( 'mysqldump_binary', $this->driver );
		self::assertStringContainsString( '--single-transaction', $this->driver );
		self::assertStringContainsString( 'DROP DATABASE', $this->driver );
		self::assertStringContainsString( 'mysql_server_cli < "$sql_snapshot"', $this->driver );
		self::assertStringContainsString( 'cmp -s "$sql_snapshot" "$recovery/post-cleanup.sql"', $this->driver );
		self::assertStringContainsString( "'db-identity'", $this->probe );
		foreach ( array( 'snapshot_directory "$core_dir" core', 'snapshot_directory "$migrator_dir" migrator', 'snapshot_directory "$wppusher_dir" wppusher' ) as $snapshot ) {
			self::assertStringContainsString( $snapshot, $this->driver );
		}
		self::assertStringContainsString( 'serialized_base64', $this->probe );
		self::assertStringContainsString( 'serialize( ran_migrator_active_snapshot() )', $this->probe );
		self::assertStringContainsString( 'cleanup is uncertain; retained recovery data', $this->driver );
	}

	public function testNoNetworkBoundaryIsFailClosedAndCredentialsRemainAbsent(): void {
		self::assertStringContainsString( 'advanced-cache.php db.php', $this->driver );
		self::assertStringContainsString( 'no existing top-level must-use plugin', $this->driver );
		self::assertStringContainsString( 'GITHUB_TOKEN', $this->driver );
		self::assertStringContainsString( 'BITBUCKET_TOKEN', $this->driver );
		self::assertStringContainsString( 'PHP_INT_MAX', $this->recorder );
		self::assertStringContainsString( 'array_key_last( $callbacks )', $this->probe );
		self::assertStringContainsString( 'A source/refusal case attempted provider or network contact.', $this->probe );
		self::assertStringNotContainsString( 'curl ', $this->driver );
		self::assertStringNotContainsString( 'provider_repository_id', $this->probe );
	}

	public function testInstalledCasesCoverBothOrdersAndExactSourceRefusals(): void {
		self::assertStringContainsString( 'for load_order in core-first addon-first', $this->driver );
		self::assertStringContainsString( "'wppusher/wppusher.php'", $this->probe );
		self::assertStringContainsString( "'Version' => '3.0.12'", $this->probe );
		self::assertStringContainsString( 'Choose an existing Booster credential profile', $this->probe );
		self::assertStringContainsString( 'Stale exact cleanup unexpectedly deleted', $this->probe );
		self::assertStringContainsString( 'The retained WP Pusher package schema is unsupported.', $this->probe );
		self::assertStringContainsString( "'workspace/core-public'", $this->probe );
		self::assertStringContainsString( "'RocketsAreNostalgic/theme-fixture'", $this->probe );
		self::assertStringContainsString( 'admin_enqueue_scripts|20|enqueueAssets|1', $this->probe );
	}

	private function read( string $path ): string {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
		self::assertIsString( $contents );

		return $contents;
	}
}

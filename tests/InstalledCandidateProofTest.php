<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class InstalledCandidateProofTest extends TestCase {
	private const CORE_SHA = 'e373d0127d676eb70f2dcd5e96eb016df3af07fbb61e5d2d481e2efb47660faa';

	private const WP_PUSHER_SHA = '4f1533b9b946afdf9d699ea54279ea236b7e25f3d3fc9182bb53cec295a52208';

	private string $driver;

	private string $probe;

	private string $recorder;

	/** @var list<string> */
	private array $temporaryDirectories = array();

	protected function setUp(): void {
		$root           = __DIR__ . '/installed-candidate/';
		$this->driver   = $this->read( $root . 'migrator-installed-proof.sh' );
		$this->probe    = $this->read( $root . 'migrator-installed-probe.php' );
		$this->recorder = $this->read( $root . 'migrator-installed-recorder.php' );
	}

	public function testDriverPinsEveryPublishedInputAndCallerSelectedCandidateIdentity(): void {
		self::assertStringContainsString( "expected_migrator_version='0.1.0-beta.7'", $this->driver );
		self::assertStringContainsString( "expected_core_version='1.0.0-beta.22'", $this->driver );
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
		self::assertStringContainsString( 'sql_snapshot_canonical="$recovery/baseline.canonical.sql"', $this->driver );
		self::assertStringContainsString( 'cmp -s "$sql_snapshot_canonical" "$post_cleanup_sql_canonical"', $this->driver );
		self::assertStringContainsString( "inert_theme_slug='ran-migrator-proof-inert'", $this->driver );
		self::assertStringContainsString( 'The active theme must be the exact non-child inert proof fixture.', $this->driver );
		self::assertStringContainsString( 'find "$active_theme" -type f -name', $this->driver );
		self::assertStringContainsString( 'diff -qr "$inert_theme_source" "$active_theme"', $this->driver );
		self::assertSame( 2, substr_count( $this->driver, 'find "$active_theme" -type l' ) );
		self::assertSame( 2, substr_count( $this->driver, 'find "$active_theme" -type f -name' ) );
		self::assertSame( 2, substr_count( $this->driver, 'diff -qr "$inert_theme_source" "$active_theme"' ) );
		self::assertStringContainsString( "'db-identity'", $this->probe );
		foreach ( array( 'snapshot_directory "$core_dir" core', 'snapshot_directory "$migrator_dir" migrator', 'snapshot_directory "$wppusher_dir" wppusher' ) as $snapshot ) {
			self::assertStringContainsString( $snapshot, $this->driver );
		}
		self::assertStringContainsString( 'serialized_base64', $this->probe );
		self::assertStringContainsString( 'serialize( ran_migrator_active_snapshot() )', $this->probe );
		self::assertStringContainsString( 'cleanup is uncertain; retained recovery data', $this->driver );
		self::assertStringContainsString( 'proof failed; retained diagnostic data', $this->driver );
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

	public function testDatabaseCanonicalizationOnlyRemovesRedundantColumnCharsetSpelling(): void {
		$directory = $this->temporaryDirectory();
		$cases     = array(
			'redundant'                   => array(
				"CREATE TABLE `t` (\n  `c` text COLLATE utf8mb4_unicode_520_ci\n) DEFAULT CHARSET=latin1;\n",
				"CREATE TABLE `t` (\n  `c` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci\n) DEFAULT CHARSET=latin1;\n",
				true,
			),
			'nonredundant-column-charset' => array(
				"CREATE TABLE `t` (\n  `c` text CHARACTER SET utf8mb4\n) DEFAULT CHARSET=latin1;\n",
				"CREATE TABLE `t` (\n  `c` text\n) DEFAULT CHARSET=latin1;\n",
				false,
			),
			'table-default'               => array(
				") DEFAULT CHARSET=utf8mb4;\n",
				") DEFAULT CHARSET=latin1;\n",
				false,
			),
			'index'                       => array(
				"  KEY `one` (`c`)\n",
				"  KEY `two` (`c`)\n",
				false,
			),
			'data'                        => array(
				"INSERT INTO `t` VALUES ('one CHARACTER SET utf8mb4');\n",
				"INSERT INTO `t` VALUES ('two CHARACTER SET utf8mb4');\n",
				false,
			),
		);

		foreach ( $cases as $name => $case ) {
			list( $baseline, $restored, $equal ) = $case;
			$left                                = $directory . '/' . $name . '-left.sql';
			$right                               = $directory . '/' . $name . '-right.sql';
			file_put_contents( $left, $baseline ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private behavioral fixture.
			file_put_contents( $right, $restored ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private behavioral fixture.
			$status = $this->runReadback(
				sprintf(
					'canonicalize_database_export %s %s; canonicalize_database_export %s %s; cmp -s %s %s',
					escapeshellarg( $left ),
					escapeshellarg( "$left.out" ),
					escapeshellarg( $right ),
					escapeshellarg( "$right.out" ),
					escapeshellarg( "$left.out" ),
					escapeshellarg( "$right.out" )
				)
			);
			self::assertSame( $equal ? 0 : 1, $status, $name );
		}
	}

	private function runReadback( string $command ): int {
		$helper = __DIR__ . '/installed-candidate/migrator-installed-readback.sh';
		$output = array();
		$status = 0;
		exec( 'bash -c ' . escapeshellarg( 'source ' . escapeshellarg( $helper ) . '; ' . $command ), $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Private behavioral fixture.

		return $status;
	}

	private function temporaryDirectory(): string {
		$directory = sys_get_temp_dir() . '/migrator-proof-' . bin2hex( random_bytes( 8 ) );
		mkdir( $directory, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private behavioral fixture.
		$this->assertDirectoryExists( $directory );
		$this->temporaryDirectories[] = $directory;

		return $directory;
	}

	protected function tearDown(): void {
		foreach ( $this->temporaryDirectories as $directory ) {
			$paths = glob( $directory . '/*' );
			foreach ( false === $paths ? array() : $paths as $path ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private behavioral fixture.
			}
			rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private behavioral fixture.
		}
		$this->temporaryDirectories = array();
		parent::tearDown();
	}

	private function read( string $path ): string {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source contract.
		self::assertIsString( $contents );

		return $contents;
	}
}

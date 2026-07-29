<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RuntimeException;

final class WpPusherSourceTest extends TestCase {

	public function testReadsOnlyExactInactiveSource(): void {
		$database = new FakeDatabase();
		$source   = $this->source( $database );

		$packages = $source->packages();

		self::assertCount( 1, $packages );
		self::assertSame( 'fixture/fixture.php', $packages[0]->package );
		self::assertMatchesRegularExpression( '/\Av1:[a-f0-9]{64}\z/', $packages[0]->fingerprint() );
		self::assertStringContainsString( 'LIMIT 129', implode( ' ', $database->queries ) );
	}

	public function testReportsOptionNamesWithoutReadingValues(): void {
		$database              = new FakeDatabase();
		$database->optionNames = array( 'gh_token', 'wppusher_license_key', 'unknown_secret' );

		$presence = $this->source( $database )->optionPresence();

		self::assertTrue( $presence['gh_token'] );
		self::assertTrue( $presence['wppusher_license_key'] );
		self::assertFalse( $presence['bb_pass'] );
		self::assertStringNotContainsString( 'option_value', $database->queries[0] );
		self::assertStringNotContainsString( 'SECRET-CANARY', implode( ' ', $database->queries ) );
	}

	#[DataProvider( 'unsupportedEnvironmentProvider' )]
	public function testRejectsUnsupportedEnvironment(
		string $version,
		array $active,
		array $networkActive,
		bool $multisite
	): void {
		$this->expectException( RuntimeException::class );
		$this->source( new FakeDatabase(), $version, $active, $networkActive, $multisite )->packages();
	}

	/** @return iterable<string, array{string, array<int, string>, array<string, int>, bool}> */
	public static function unsupportedEnvironmentProvider(): iterable {
		yield 'older version' => array( '3.0.12', array(), array(), false );
		yield 'newer version' => array( '3.0.14', array(), array(), false );
		yield 'site active' => array( '3.0.13', array( WpPusherSource::PLUGIN ), array(), false );
		yield 'network active' => array( '3.0.13', array(), array( WpPusherSource::PLUGIN => 1 ), false );
		yield 'multisite' => array( '3.0.13', array(), array(), true );
	}

	public function testRejectsSchemaDriftAndDuplicateIdentities(): void {
		$schemaDrift = new FakeDatabase();
		array_pop( $schemaDrift->schema );
		try {
			$this->source( $schemaDrift )->packages();
			self::fail( 'Schema drift was accepted.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		}

		$duplicates       = new FakeDatabase();
		$duplicates->rows = array( $duplicates->rows[0], array_merge( $duplicates->rows[0], array( 'id' => '2' ) ) );
		$this->expectException( RuntimeException::class );
		$this->source( $duplicates )->packages();
	}

	public function testAcceptsMySqlEightWithoutIntegerDisplayWidth(): void {
		$database                    = new FakeDatabase();
		$database->schema[0]['Type'] = 'mediumint';

		self::assertCount( 1, $this->source( $database )->packages() );
	}

	public function testRejectsMalformedRowsAndInventoryOverBound(): void {
		$malformed                          = new FakeDatabase();
		$malformed->rows[0]['subdirectory'] = '../secret';
		try {
			$this->source( $malformed )->packages();
			self::fail( 'Malformed row was accepted.' );
		} catch ( \InvalidArgumentException ) {
			self::assertTrue( true );
		}

		$overBound       = new FakeDatabase();
		$overBound->rows = array_fill( 0, 129, $overBound->rows[0] );
		$this->expectException( RuntimeException::class );
		$this->source( $overBound )->packages();
	}

	public function testDeletesOnlyExactUnchangedRowAndPreservesNullDistinction(): void {
		$database = new FakeDatabase();
		$source   = $this->source( $database );
		$package  = $source->packages()[0];

		self::assertTrue( $source->deleteExact( $package ) );
		self::assertStringContainsString( '`subdirectory` IS NULL', implode( ' ', $database->queries ) );
		self::assertSame( array(), $source->packages() );
	}

	public function testChangedOrUnmatchedDeleteLeavesSourceRow(): void {
		$database                    = new FakeDatabase();
		$source                      = $this->source( $database );
		$package                     = $source->packages()[0];
		$database->rows[0]['branch'] = 'changed';

		self::assertFalse( $source->deleteExact( $package ) );
		self::assertCount( 1, $database->rows );

		$database               = new FakeDatabase();
		$database->affectedRows = 0;
		$source                 = $this->source( $database );
		self::assertFalse( $source->deleteExact( $source->packages()[0] ) );
		self::assertCount( 1, $database->rows );
	}

	public function testCleanupRequiresNoRowsAndPreservesLicenseAndUnknownOptions(): void {
		$database              = new FakeDatabase();
		$database->optionNames = array( 'gh_token', 'wppusher_license_key', 'unknown_secret' );
		$source                = $this->source( $database );

		self::assertFalse( $source->deleteUnusedOptions() );
		self::assertContains( 'gh_token', $database->optionNames );

		$database->rows = array();
		self::assertTrue( $source->deleteUnusedOptions() );
		self::assertNotContains( 'gh_token', $database->optionNames );
		self::assertContains( 'wppusher_license_key', $database->optionNames );
		self::assertContains( 'unknown_secret', $database->optionNames );
	}

	public function testDropsOnlyExactLockedFreshlyEmptyTable(): void {
		$database       = new FakeDatabase();
		$database->rows = array();
		$source         = $this->source( $database );

		self::assertTrue( $source->dropEmptyPackageTable() );
		self::assertFalse( $source->packageTablePresent() );
		self::assertStringContainsString( 'LOCK TABLES', implode( ' ', $database->queries ) );
		self::assertStringContainsString( 'SELECT COUNT(*)', implode( ' ', $database->queries ) );
		self::assertStringContainsString( 'UNLOCK TABLES', implode( ' ', $database->queries ) );
	}

	public function testTableCleanupRejectsRowsSchemaDriftAndLockFailure(): void {
		$database = new FakeDatabase();
		self::assertFalse( $this->source( $database )->dropEmptyPackageTable() );
		self::assertTrue( $database->tableExists );

		$database       = new FakeDatabase();
		$database->rows = array();
		array_pop( $database->schema );
		try {
			$this->source( $database )->dropEmptyPackageTable();
			self::fail( 'Schema drift was accepted.' );
		} catch ( RuntimeException ) {
			self::assertTrue( $database->tableExists );
			self::assertStringContainsString( 'UNLOCK TABLES', implode( ' ', $database->queries ) );
		}

		$database             = new FakeDatabase();
		$database->rows       = array();
		$database->lockResult = false;
		self::assertFalse( $this->source( $database )->dropEmptyPackageTable() );
		self::assertTrue( $database->tableExists );
	}

	private function source(
		FakeDatabase $database,
		string $version = '3.0.13',
		array $active = array(),
		array $networkActive = array(),
		bool $multisite = false
	): WpPusherSource {
		return new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => $version ) ),
			static fn (): array => $active,
			static fn (): array => $networkActive,
			static fn (): bool => $multisite,
			static function ( string $option ) use ( $database ): bool {
				$before                = count( $database->optionNames );
				$database->optionNames = array_values( array_diff( $database->optionNames, array( $option ) ) );

				return $before !== count( $database->optionNames );
			}
		);
	}
}

final class FakeDatabase {

	public string $prefix  = 'wp_';
	public string $options = 'wp_options';

	/** @var list<string> */
	public array $queries = array();

	/** @var list<string> */
	public array $optionNames = array();

	/** @var list<array<string, string>> */
	public array $schema;

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $affectedRows     = 1;
	public bool $tableExists     = true;
	public int|false $lockResult = 0;

	public function __construct() {
		$types        = array(
			'id'           => 'mediumint(9)',
			'package'      => 'varchar(255)',
			'repository'   => 'varchar(255)',
			'branch'       => 'varchar(255)',
			'type'         => 'int',
			'status'       => 'int',
			'ptd'          => 'int',
			'host'         => 'varchar(10)',
			'private'      => 'int',
			'subdirectory' => 'varchar(255)',
		);
		$this->schema = array_map(
			static fn ( string $field, string $type ): array => array(
				'Field' => $field,
				'Type'  => $type,
			),
			array_keys( $types ),
			$types
		);
		$this->rows   = array(
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
		);
	}

	/** @return list<array<string, mixed>> */
	public function get_results( string $query, string $output ): array {
		unset( $output );
		$this->queries[] = $query;

		return str_starts_with( $query, 'SHOW COLUMNS' ) ? ( $this->tableExists ? $this->schema : array() ) : $this->rows;
	}

	public function prepare( string $query, mixed ...$values ): string {
		$this->queries[] = $query . ' :: ' . count( $values );

		return $query;
	}

	/** @return list<string> */
	public function get_col( string $query ): array {
		$this->queries[] = $query;

		return $this->optionNames;
	}

	public function get_var( string $query ): string|int|null {
		$this->queries[] = $query;
		if ( str_starts_with( $query, 'SHOW TABLES' ) ) {
			return $this->tableExists ? $this->prefix . 'wppusher_packages' : null;
		}

		return count( $this->rows );
	}

	public function query( string $query ): int|false {
		$this->queries[] = $query;
		if ( str_starts_with( $query, 'LOCK TABLES' ) ) {
			return $this->lockResult;
		}
		if ( str_starts_with( $query, 'DROP TABLE' ) ) {
			$this->tableExists = false;

			return 1;
		}
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' ) && 1 === $this->affectedRows ) {
			$this->rows = array();
		}

		return $this->affectedRows;
	}
}

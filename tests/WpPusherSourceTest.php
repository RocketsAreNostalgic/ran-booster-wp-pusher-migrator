<?php

declare(strict_types=1);

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The Composer test autoloader owns this existing fixture namespace; keep its test discovery identity.
namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RuntimeException;

final class WpPusherSourceTest extends TestCase {

	public function test_reads_only_exact_inactive_source(): void {
		$database = new FakeDatabase();
		$source   = $this->source( $database );

		$packages = $source->packages();

		self::assertCount( 1, $packages );
		self::assertSame( 'fixture/fixture.php', $packages[0]->package );
		self::assertMatchesRegularExpression( '/\Av1:[a-f0-9]{64}\z/', $packages[0]->fingerprint() );
		self::assertStringContainsString( 'LIMIT 129', implode( ' ', $database->queries ) );
	}

	public function test_reports_option_names_without_reading_values(): void {
		$database               = new FakeDatabase();
		$database->option_names = array( 'gh_token', 'wppusher_license_key', 'unknown_secret' );

		$presence = $this->source( $database )->option_presence();

		self::assertTrue( $presence['gh_token'] );
		self::assertTrue( $presence['wppusher_license_key'] );
		self::assertFalse( $presence['bb_pass'] );
		self::assertStringNotContainsString( 'option_value', $database->queries[0] );
		self::assertStringNotContainsString( 'SECRET-CANARY', implode( ' ', $database->queries ) );
	}

	#[DataProvider( 'unsupported_environment_provider' )]
	public function test_rejects_unsupported_environment(
		string $version,
		array $active,
		array $network_active,
		bool $multisite
	): void {
		$this->expectException( RuntimeException::class );
		$this->source( new FakeDatabase(), $version, $active, $network_active, $multisite )->packages();
	}

	/** @return iterable<string, array{string, array<int, string>, array<string, int>, bool}> */
	public static function unsupported_environment_provider(): iterable {
		yield 'older version' => array( '3.0.12', array(), array(), false );
		yield 'newer version' => array( '3.0.14', array(), array(), false );
		yield 'site active' => array( '3.0.13', array( WpPusherSource::PLUGIN ), array(), false );
		yield 'network active' => array( '3.0.13', array(), array( WpPusherSource::PLUGIN => 1 ), false );
		yield 'multisite' => array( '3.0.13', array(), array(), true );
	}

	public function test_rejects_schema_drift_and_duplicate_identities(): void {
		$schema_drift = new FakeDatabase();
		array_pop( $schema_drift->schema );
		try {
			$this->source( $schema_drift )->packages();
			self::fail( 'Schema drift was accepted.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		}

		$duplicates       = new FakeDatabase();
		$duplicates->rows = array( $duplicates->rows[0], array_merge( $duplicates->rows[0], array( 'id' => '2' ) ) );
		$this->expectException( RuntimeException::class );
		$this->source( $duplicates )->packages();
	}

	public function test_accepts_my_sql_eight_without_integer_display_width(): void {
		$database                    = new FakeDatabase();
		$database->schema[0]['Type'] = 'mediumint';

		self::assertCount( 1, $this->source( $database )->packages() );
	}

	public function test_reports_only_an_exact_supported_retained_package_table(): void {
		$database = new FakeDatabase();
		self::assertTrue( $this->source( $database )->supported_package_table_present() );

		$database->table_exists = false;
		self::assertFalse( $this->source( $database )->supported_package_table_present() );

		$database                    = new FakeDatabase();
		$database->schema[0]['Type'] = 'bigint';
		$this->expectException( RuntimeException::class );
		$this->source( $database )->supported_package_table_present();
	}

	public function test_rejects_malformed_rows_and_inventory_over_bound(): void {
		$malformed                          = new FakeDatabase();
		$malformed->rows[0]['subdirectory'] = '../secret';
		try {
			$this->source( $malformed )->packages();
			self::fail( 'Malformed row was accepted.' );
		} catch ( \InvalidArgumentException ) {
			self::assertTrue( true );
		}

		$over_bound       = new FakeDatabase();
		$over_bound->rows = array_fill( 0, 129, $over_bound->rows[0] );
		$this->expectException( RuntimeException::class );
		$this->source( $over_bound )->packages();
	}

	public function test_deletes_only_exact_unchanged_row_and_preserves_null_distinction(): void {
		$database = new FakeDatabase();
		$source   = $this->source( $database );
		$package  = $source->packages()[0];

		self::assertTrue( $source->delete_exact( $package ) );
		self::assertStringContainsString( '`subdirectory` IS NULL', implode( ' ', $database->queries ) );
		self::assertSame( array(), $source->packages() );
	}

	public function test_changed_or_unmatched_delete_leaves_source_row(): void {
		$database                    = new FakeDatabase();
		$source                      = $this->source( $database );
		$package                     = $source->packages()[0];
		$database->rows[0]['branch'] = 'changed';
		$database->affected_rows     = 0;

		self::assertFalse( $source->delete_exact( $package ) );
		self::assertCount( 1, $database->rows );

		$database                = new FakeDatabase();
		$database->affected_rows = 0;
		$source                  = $this->source( $database );
		self::assertFalse( $source->delete_exact( $source->packages()[0] ) );
		self::assertCount( 1, $database->rows );
	}

	private function source(
		FakeDatabase $database,
		string $version = '3.0.13',
		array $active = array(),
		array $network_active = array(),
		bool $multisite = false
	): WpPusherSource {
		return new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => $version ) ),
			static fn (): array => $active,
			static fn (): array => $network_active,
			static fn (): bool => $multisite
		);
	}
}

final class FakeDatabase {

	public string $prefix  = 'wp_';
	public string $options = 'wp_options';

	/** @var list<string> */
	public array $queries = array();

	/** @var list<string> */
	public array $option_names = array();

	/** @var list<array<string, string>> */
	public array $schema;

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $affected_rows = 1;
	public bool $table_exists = true;

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

		return str_starts_with( $query, 'SHOW COLUMNS' ) ? ( $this->table_exists ? $this->schema : array() ) : $this->rows;
	}

	public function prepare( string $query, mixed ...$values ): string {
		$this->queries[] = $query . ' :: ' . count( $values );

		return $query;
	}

	/** @return list<string> */
	public function get_col( string $query ): array {
		$this->queries[] = $query;

		return $this->option_names;
	}

	public function get_var( string $query ): string|int|null {
		$this->queries[] = $query;
		if ( str_starts_with( $query, 'SHOW TABLES' ) ) {
			return $this->table_exists ? $this->prefix . 'wppusher_packages' : null;
		}

		return count( $this->rows );
	}

	public function query( string $query ): int|false {
		$this->queries[] = $query;
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' ) && 1 === $this->affected_rows ) {
			$this->rows = array();
		}

		return $this->affected_rows;
	}
}

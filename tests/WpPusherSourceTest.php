<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RAN\BoosterWpPusherMigrator\WpPusherInventory;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RAN\BoosterWpPusherMigrator\WpPusherRejectedPackage;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RAN\BoosterWpPusherMigrator\WpPusherSourceException;
use RuntimeException;

final class WpPusherSourceTest extends TestCase {

	#[DataProvider( 'compatible_version_provider' )]
	public function test_reads_validated_inactive_source_across_plugin_versions( string $version ): void {
		$database = new FakeDatabase();
		$source   = $this->source( $database, $version );

		$packages = $source->packages();

		self::assertCount( 1, $packages );
		self::assertSame( 'fixture/fixture.php', $packages[0]->package );
		self::assertMatchesRegularExpression( '/\Av1:[a-f0-9]{64}\z/', $packages[0]->fingerprint() );
		self::assertStringContainsString( 'LIMIT 129', implode( ' ', $database->queries ) );
	}

	/** @return iterable<string, array{string}> */
	public static function compatible_version_provider(): iterable {
		yield 'earlier version with compatible seeded data' => array( '3.0.12' );
		yield 'historical version with compatible seeded data' => array( '3.0.13' );
		yield 'current version with compatible seeded data' => array( '3.0.18' );
		yield 'different version with compatible seeded data' => array( '4.0.0' );
	}

	public function test_accepts_folded_table_name_on_case_insensitive_server(): void {
		$database                 = new FakeDatabase();
		$database->prefix         = 'Wp_';
		$database->reported_table = 'wp_wppusher_packages';
		$database->table_mode     = '1';

		self::assertCount( 1, $this->source( $database )->packages() );
	}

	public function test_ignores_differently_cased_table_on_case_sensitive_server(): void {
		$database                 = new FakeDatabase();
		$database->prefix         = 'Wp_';
		$database->reported_table = 'wp_wppusher_packages';

		self::assertSame( array(), $this->source( $database )->packages() );
	}

	/** @param array<string, array<string, mixed>> $plugins */
	#[DataProvider( 'absent_plugin_provider' )]
	public function test_absent_plugin_reports_not_installed_without_reading_database( array $plugins ): void {
		$database = new FakeDatabase();
		$source   = new WpPusherSource(
			$database,
			static fn (): array => $plugins,
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);

		$inventory = $source->inventory();

		self::assertSame( WpPusherInventory::STATUS_NOT_INSTALLED, $inventory->status );
		self::assertFalse( $inventory->is_installed() );
		self::assertTrue( $inventory->is_empty() );
		self::assertSame( array(), $source->packages() );
		self::assertFalse( $source->supported_package_table_present() );
		self::assertFalse( $source->option_presence()['gh_token'] );
		self::assertSame( array(), $database->queries );
	}

	/** @return iterable<string, array{array<string, array<string, mixed>>}> */
	public static function absent_plugin_provider(): iterable {
		yield 'not installed' => array( array() );
		yield 'different basename' => array( array( 'other/wppusher.php' => array( 'Version' => '3.0.18' ) ) );
	}

	/** @param array<string, array<string, mixed>> $plugins */
	#[DataProvider( 'invalid_plugin_metadata_provider' )]
	public function test_requires_recognized_installed_plugin_before_reading_data( array $plugins ): void {
		$database = new FakeDatabase();
		$source   = new WpPusherSource(
			$database,
			static fn (): array => $plugins,
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);

		try {
			$source->packages();
			self::fail( 'Unrecognized installed WP Pusher metadata was accepted.' );
		} catch ( WpPusherSourceException $failure ) {
			self::assertSame( WpPusherSourceException::REASON_INVALID_PLUGIN_METADATA, $failure->reason );
			self::assertFalse( $failure->is_database_error );
			self::assertSame( 'An installed WP Pusher plugin with valid version metadata is required.', $failure->getMessage() );
		}
		self::assertSame( array(), $database->queries );
	}

	/** @return iterable<string, array{array<string, array<string, mixed>>}> */
	public static function invalid_plugin_metadata_provider(): iterable {
		foreach ( array( null, '', '  ', 3018, false, array() ) as $index => $version ) {
			yield 'invalid version ' . $index => array( array( WpPusherSource::PLUGIN => array( 'Version' => $version ) ) );
		}
	}

	public function test_inactive_plugin_with_missing_or_empty_table_reports_empty_inventory(): void {
		$no_table               = new FakeDatabase();
		$no_table->table_exists = false;
		$inventory              = $this->source( $no_table )->inventory();
		self::assertSame( WpPusherInventory::STATUS_EMPTY, $inventory->status );
		self::assertTrue( $inventory->is_installed() );
		self::assertTrue( $inventory->is_empty() );

		$empty_table       = new FakeDatabase();
		$empty_table->rows = array();
		$inventory         = $this->source( $empty_table )->inventory();
		self::assertSame( WpPusherInventory::STATUS_EMPTY, $inventory->status );
		self::assertTrue( $inventory->is_installed() );
		self::assertTrue( $inventory->is_empty() );
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

	/**
	 * @param array<int, string> $active
	 * @param array<string, int> $network_active
	 */
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
			self::addToAssertionCount( 1 );
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

	public function test_accepts_maria_db_integer_display_widths(): void {
		$database = new FakeDatabase();
		foreach ( array( 4, 5, 6, 8 ) as $index ) {
			$database->schema[ $index ]['Type'] = 'int(11)';
		}

		self::assertCount( 1, $this->source( $database )->packages() );
		self::assertTrue( $this->source( $database )->supported_package_table_present() );
	}

	#[DataProvider( 'unsupported_integer_type_provider' )]
	public function test_rejects_changed_integer_semantics( string $type ): void {
		$database                    = new FakeDatabase();
		$database->schema[4]['Type'] = $type;

		$this->expectException( RuntimeException::class );
		$this->source( $database )->packages();
	}

	/** @return iterable<string, array{string}> */
	public static function unsupported_integer_type_provider(): iterable {
		yield 'unsigned' => array( 'int(11) unsigned' );
		yield 'zerofill' => array( 'int(11) unsigned zerofill' );
		yield 'smaller integer' => array( 'smallint(6)' );
		yield 'larger integer' => array( 'bigint(20)' );
		yield 'text' => array( 'varchar(11)' );
		yield 'malformed' => array( 'int(11) garbage' );
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
		$malformed                        = new FakeDatabase();
		$malformed->rows[0]['repository'] = 'not-a-repo';
		try {
			$this->source( $malformed )->packages();
			self::fail( 'Malformed row was accepted.' );
		} catch ( WpPusherSourceException $failure ) {
			self::assertSame( WpPusherSourceException::REASON_MALFORMED_PACKAGE_ROW, $failure->reason );
			self::assertFalse( $failure->is_database_error );
		}

		$over_bound       = new FakeDatabase();
		$over_bound->rows = array_fill( 0, 129, $over_bound->rows[0] );
		try {
			$this->source( $over_bound )->packages();
			self::fail( 'Over-bound inventory was accepted.' );
		} catch ( WpPusherSourceException $failure ) {
			self::assertSame( WpPusherSourceException::REASON_INVENTORY_LIMIT_EXCEEDED, $failure->reason );
			self::assertFalse( $failure->is_database_error );
		}
	}

	/** @return iterable<string, array{string}> */
	public static function invalid_subdirectory_provider(): iterable {
		yield 'parent traversal' => array( '../x' );
		yield 'leading slash' => array( '/x' );
		yield 'backslash' => array( 'a\\b' );
		yield 'double slash' => array( 'a//b' );
		yield 'dot segment' => array( './x' );
		yield 'nested dot segment' => array( 'a/./b' );
		yield 'trailing double slash' => array( 'plugins/foo//' );
	}

	#[DataProvider( 'invalid_subdirectory_provider' )]
	public function test_isolates_invalid_subdirectory_row_while_keeping_valid_rows_migratable( string $invalid_subdirectory ): void {
		$database       = new FakeDatabase();
		$bad_row        = array_merge(
			$database->rows[0],
			array(
				'id'           => '1',
				'package'      => 'bad/bad.php',
				'subdirectory' => $invalid_subdirectory,
			)
		);
		$good_row       = array_merge(
			$database->rows[0],
			array(
				'id'           => '2',
				'package'      => 'good/good.php',
				'subdirectory' => 'plugins/foo/',
			)
		);
		$database->rows = array( $bad_row, $good_row );

		$inventory = $this->source( $database )->inventory();

		self::assertSame( WpPusherInventory::STATUS_HAS_PACKAGES, $inventory->status );
		self::assertCount( 2, $inventory->entries );
		self::assertInstanceOf( WpPusherRejectedPackage::class, $inventory->entries[0] );
		self::assertSame( 'bad/bad.php', $inventory->entries[0]->package );
		self::assertSame( $invalid_subdirectory, $inventory->entries[0]->raw_subdirectory );
		self::assertSame( WpPusherRejectedPackage::REASON_INVALID_SUBDIRECTORY, $inventory->entries[0]->reason );
		self::assertInstanceOf( WpPusherPackage::class, $inventory->entries[1] );
		self::assertSame( 'good/good.php', $inventory->entries[1]->package );
		self::assertSame( 'plugins/foo', $inventory->entries[1]->subdirectory );
		self::assertSame( 'plugins/foo/', $inventory->entries[1]->raw_subdirectory );
		self::assertCount( 1, $inventory->packages() );
		self::assertCount( 1, $inventory->rejected() );

		$this->expectException( InvalidArgumentException::class );
		WpPusherPackage::from_row( $bad_row );
	}

	public function test_duplicate_identity_check_still_applies_across_valid_and_rejected_rows(): void {
		$database       = new FakeDatabase();
		$rejected_row   = array_merge(
			$database->rows[0],
			array(
				'id'           => '1',
				'subdirectory' => '../x',
			)
		);
		$duplicate_row  = array_merge(
			$database->rows[0],
			array(
				'id'           => '2',
				'subdirectory' => null,
			)
		);
		$database->rows = array( $rejected_row, $duplicate_row );

		try {
			$this->source( $database )->inventory();
			self::fail( 'Duplicate identity across rejected and valid row was accepted.' );
		} catch ( WpPusherSourceException $failure ) {
			self::assertSame( WpPusherSourceException::REASON_DUPLICATE_PACKAGES, $failure->reason );
			self::assertFalse( $failure->is_database_error );
		}
	}

	/** @return iterable<string, array{string, string}> */
	public static function stored_subdirectory_cleanup_provider(): iterable {
		yield 'trailing slash' => array( 'plugins/foo/', 'plugins/foo' );
		yield 'no trailing slash' => array( 'plugins/foo', 'plugins/foo' );
		yield 'empty string' => array( '', '' );
	}

	#[DataProvider( 'stored_subdirectory_cleanup_provider' )]
	public function test_delete_exact_matches_raw_stored_subdirectory_and_rejects_mutated_row(
		string $stored_subdirectory,
		string $normalized_subdirectory
	): void {
		$database                          = new FakeDatabase();
		$database->rows[0]['subdirectory'] = $stored_subdirectory;
		$source                            = $this->source( $database );
		$package                           = $source->packages()[0];

		self::assertSame( $normalized_subdirectory, $package->subdirectory );
		self::assertSame( $stored_subdirectory, $package->raw_subdirectory );
		self::assertSame( $stored_subdirectory, $package->to_array()['subdirectory'] );
		self::assertSame( $package->fingerprint(), WpPusherPackage::from_row( $package->to_array() )->fingerprint() );

		// Mutate the stored row before cleanup: exact match must not delete it.
		$database->rows[0]['subdirectory'] = $stored_subdirectory . 'mutated';
		self::assertFalse( $source->delete_exact( $package ) );
		self::assertCount( 1, $database->rows );
		self::assertSame( $stored_subdirectory, $database->last_prepared_values[9] ?? null );

		// Restore exact stored value: exact match deletes the row.
		$database->rows[0]['subdirectory'] = $stored_subdirectory;
		self::assertTrue( $source->delete_exact( $package ) );
		self::assertSame( array(), $database->rows );
	}

	public function test_database_query_errors_report_closed_code_without_sql_text(): void {
		$database             = new FakeDatabase();
		$database->last_error = "You have an error in your SQL syntax near 'SELECT `id` FROM `wp_wppusher_packages`'";

		try {
			$this->source( $database )->inventory();
			self::fail( 'Database last_error was ignored.' );
		} catch ( WpPusherSourceException $failure ) {
			self::assertSame( WpPusherSourceException::REASON_DB_QUERY_FAILED, $failure->reason );
			self::assertTrue( $failure->is_database_error );
			self::assertStringNotContainsString( 'SELECT', $failure->getMessage() );
			self::assertStringNotContainsString( 'wppusher_packages', $failure->getMessage() );
		}

		$throwing = new class() {
			public string $prefix = 'wp_';

			public function prepare( string $query, mixed ...$values ): string {
				unset( $query, $values );
				throw new RuntimeException( 'SQLSTATE[42000]: SELECT * FROM wp_wppusher_packages WHERE id = 1' );
			}

			public function get_var( string $query ): mixed {
				unset( $query );
				return null;
			}
		};

		try {
			$this->source( $throwing )->inventory();
			self::fail( 'Thrown database exception was ignored.' );
		} catch ( WpPusherSourceException $failure ) {
			self::assertSame( WpPusherSourceException::REASON_DB_QUERY_FAILED, $failure->reason );
			self::assertTrue( $failure->is_database_error );
			self::assertStringNotContainsString( 'SQLSTATE', $failure->getMessage() );
			self::assertStringNotContainsString( 'SELECT', $failure->getMessage() );
		}
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

	public function test_magic_database_keeps_property_reads_and_method_order(): void {
		$inner  = new FakeDatabase();
		$proxy  = new class( $inner ) {
			/** @var list<string> */
			public array $calls = array();

			public function __construct( private FakeDatabase $inner ) {
			}

			public function __get( string $name ): mixed {
				return $this->inner->$name;
			}

			/** @param array<int|string, mixed> $arguments */
			public function __call( string $name, array $arguments ): mixed {
				$this->calls[] = $name;
				return $this->inner->$name( ...$arguments );
			}
		};
		$source = $this->source( $proxy );
		self::assertCount( 1, $source->packages() );
		self::assertFalse( $source->option_presence()['gh_token'] );
		$package      = WpPusherPackage::from_row( $inner->rows[0] );
		$proxy->calls = array();
		self::assertTrue( $source->delete_exact( $package ) );
		self::assertSame( array( 'prepare', 'query' ), $proxy->calls );
		self::assertStringContainsString( '`branch` = %s', implode( ' ', $inner->queries ) );
		self::assertSame( array(), $inner->rows );
	}

	public function test_unavailable_query_does_not_prepare_cleanup(): void {
		foreach ( array(
			new class() {
				public string $prefix = 'wp_';
				public int $prepared  = 0;

				public function prepare( string $query ): string {
					++$this->prepared;
					return $query;
				}
			},
			new class() {
				public string $prefix = 'wp_';
				public int $prepared  = 0;

				public function prepare( string $query ): string {
					++$this->prepared;
					return $query;
				}

				public function call_private_query(): int {
					return $this->query( 'private query proof' );
				}

				private function query( string $query ): int {
					unset( $query );
					throw new RuntimeException( 'A private query method cannot be called.' );
				}
			},
		) as $database ) {
			$package = WpPusherPackage::from_row( ( new FakeDatabase() )->rows[0] );
			try {
				$this->source( $database )->delete_exact( $package );
				self::fail( 'Unavailable cleanup method was accepted.' );
			} catch ( RuntimeException $failure ) {
				self::assertSame( 'The WP Pusher cleanup database is unavailable.', $failure->getMessage() );
			}
			self::assertSame( 0, $database->prepared );
			if ( method_exists( $database, 'call_private_query' ) ) {
				try {
					$database->call_private_query();
				} catch ( RuntimeException $failure ) {
					self::assertSame( 'A private query method cannot be called.', $failure->getMessage() );
				}
			}
		}
	}

	public function test_magic_dispatch_alone_cannot_supply_a_missing_options_property(): void {
		$database = new class() {
			public string $prefix = 'wp_';
			public int $calls     = 0;

			/** @param array<int|string, mixed> $arguments */
			public function __call( string $name, array $arguments ): mixed {
				unset( $name, $arguments );
				++$this->calls;
				return array();
			}
		};
		try {
			$this->source( $database )->option_presence();
			self::fail( 'Missing options property was accepted.' );
		} catch ( RuntimeException $failure ) {
			self::assertSame( 'The WordPress options table is unavailable.', $failure->getMessage() );
		}
		self::assertSame( 0, $database->calls );
	}

	public function test_private_or_uninitialized_options_are_not_readable(): void {
		foreach ( array(
			new class() {
				private string $options = 'private_options';

				public function fixture_options(): string {
					return $this->options;
				}
			},
			new class() {
				public string $options;
			},
		) as $database ) {
			try {
				$this->source( $database )->option_presence();
				self::fail( 'Inaccessible options property was accepted.' );
			} catch ( RuntimeException $failure ) {
				self::assertSame( 'The WordPress options table is unavailable.', $failure->getMessage() );
			}
			if ( method_exists( $database, 'fixture_options' ) ) {
				self::assertSame( 'private_options', $database->fixture_options() );
			}
		}
	}

	public function test_public_null_options_retains_existing_empty_table_coercion(): void {
		$database = new class() {
			public mixed $options = null;
			public string $sql    = '';

			public function prepare( string $query, mixed ...$values ): string {
				unset( $values );
				$this->sql = $query;
				return $query;
			}

			/** @return list<string> */
			public function get_col( string $query ): array {
				unset( $query );
				return array();
			}
		};
		self::assertFalse( $this->source( $database )->option_presence()['gh_token'] );
		self::assertStringContainsString( 'FROM ``', $database->sql );
	}

	/**
	 * @param array<int, string> $active
	 * @param array<string, int> $network_active
	 */
	private function source(
		object $database,
		string $version = '3.0.18',
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

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
final class FakeDatabase {

	public string $prefix     = 'wp_';
	public string $options    = 'wp_options';
	public string $last_error = '';

	/** @var list<string> */
	public array $queries = array();

	/** @var array<int|string, mixed> */
	public array $last_prepared_values = array();

	/** @var list<string> */
	public array $option_names = array();

	/** @var list<array<string, string>> */
	public array $schema;

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $affected_rows      = 1;
	public bool $table_exists      = true;
	public ?string $reported_table = null;
	public string $table_mode      = '0';

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
		$this->last_prepared_values = $values;
		$this->queries[]            = $query . ' :: ' . count( $values );

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
			return $this->table_exists ? ( $this->reported_table ?? $this->prefix . 'wppusher_packages' ) : null;
		}
		if ( 'SELECT @@lower_case_table_names' === $query ) {
			return $this->table_mode;
		}

		return count( $this->rows );
	}

	public function query( string $query ): int {
		$this->queries[] = $query;
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' ) && 1 === $this->affected_rows ) {
			$matched   = 0;
			$remaining = array();
			foreach ( $this->rows as $row ) {
				if ( $this->row_matches_delete( $query, $row ) ) {
					++$matched;
					continue;
				}
				$remaining[] = $row;
			}
			$this->rows = $remaining;

			return $matched;
		}

		return $this->affected_rows;
	}

	/** @param array<string, mixed> $row */
	private function row_matches_delete( string $query, array $row ): bool {
		$values = $this->last_prepared_values;
		if ( (string) ( $row['id'] ?? '' ) !== (string) ( $values[0] ?? '' )
			|| (string) ( $row['package'] ?? '' ) !== (string) ( $values[1] ?? '' )
			|| (string) ( $row['repository'] ?? '' ) !== (string) ( $values[2] ?? '' )
			|| (string) ( $row['branch'] ?? '' ) !== (string) ( $values[3] ?? '' )
			|| (string) ( $row['type'] ?? '' ) !== (string) ( $values[4] ?? '' )
			|| (string) ( $row['status'] ?? '' ) !== (string) ( $values[5] ?? '' )
			|| (string) ( $row['ptd'] ?? '' ) !== (string) ( $values[6] ?? '' )
			|| (string) ( $row['host'] ?? '' ) !== (string) ( $values[7] ?? '' )
			|| (string) ( $row['private'] ?? '' ) !== (string) ( $values[8] ?? '' ) ) {
			return false;
		}
		if ( str_contains( $query, '`subdirectory` IS NULL' ) ) {
			return null === ( $row['subdirectory'] ?? null );
		}

		if ( array_key_exists( 9, $values ) && ( $row['subdirectory'] ?? null ) === $values[9] ) {
			return true;
		}

		return false;
	}
}

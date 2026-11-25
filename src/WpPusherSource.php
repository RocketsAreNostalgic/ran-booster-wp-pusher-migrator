<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use Closure;
use InvalidArgumentException;
use Throwable;

/** Validated retained WP Pusher data and package-record cleanup boundary. */
final class WpPusherSource {

	public const PLUGIN = 'wppusher/wppusher.php';

	private const COLUMNS = array(
		'id'           => 'mediumint',
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

	public const OPTIONS = array(
		'gh_token',
		'bb_token',
		'bb_user',
		'bb_pass',
		'gl_private_token',
		'gl_base_url',
		'wppusher_token',
		'wppusher_license_key',
		'pusher_logging_enabled',
		'hide-wppusher-welcome',
	);

	private object $database;

	/** @var Closure():array<string, array<string, mixed>> */
	private Closure $plugins;

	/** @var Closure():array<int, string> */
	private Closure $active_plugins;

	/** @var Closure():array<string, mixed> */
	private Closure $network_active_plugins;

	/** @var Closure():bool */
	private Closure $multisite;

	/**
	 * @param null|callable():array<string, array<string, mixed>> $plugins
	 * @param null|callable():array<int, string>                  $active_plugins
	 * @param null|callable():array<string, mixed>                $network_active_plugins
	 * @param null|callable():bool                                $multisite
	 */
	public function __construct(
		?object $database = null,
		?callable $plugins = null,
		?callable $active_plugins = null,
		?callable $network_active_plugins = null,
		?callable $multisite = null
	) {
		global $wpdb;

		$this->database               = $database ?? $wpdb;
		$this->plugins                = Closure::fromCallable( $plugins ?? self::plugins( ... ) );
		$this->active_plugins         = Closure::fromCallable( $active_plugins ?? static fn (): array => (array) get_option( 'active_plugins', array() ) );
		$this->network_active_plugins = Closure::fromCallable( $network_active_plugins ?? static fn (): array => (array) get_site_option( 'active_sitewide_plugins', array() ) );
		$this->multisite              = Closure::fromCallable( $multisite ?? static fn (): bool => is_multisite() );
	}

	/** @return list<WpPusherPackage> */
	public function packages(): array {
		return $this->inventory()->packages();
	}

	public function inventory(): WpPusherInventory {
		if ( ! $this->is_supported_installation() ) {
			return WpPusherInventory::not_installed();
		}
		if ( ! $this->package_table_exists() ) {
			return WpPusherInventory::empty_inventory();
		}
		$table = $this->table();
		$this->assert_package_table_schema( $table );

		$columns = implode( '`, `', array_keys( self::COLUMNS ) );
		if ( ! is_callable( array( $this->database, 'get_results' ) ) ) {
			throw WpPusherSourceException::db_reader_unavailable();
		}
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table and constant column allowlist.
			$rows = $this->database->get_results( "SELECT `{$columns}` FROM `{$table}` ORDER BY `id` ASC LIMIT 129", ARRAY_A );
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();
		if ( ! is_array( $rows ) ) {
			throw WpPusherSourceException::db_query_failed();
		}
		if ( count( $rows ) > 128 ) {
			throw WpPusherSourceException::inventory_limit_exceeded();
		}

		$entries = array();
		$seen    = array();
		foreach ( $rows as $row ) {
			$row_data = is_array( $row ) ? $row : array();
			try {
				$entry = WpPusherPackage::from_row( $row_data );
			} catch ( InvalidArgumentException $failure ) {
				$entry = WpPusherPackage::rejected_subdirectory_row( $row_data );
				if ( null === $entry ) {
					throw WpPusherSourceException::malformed_package_row( $failure );
				}
			}
			$identity = $entry->type . ':' . $entry->package;
			if ( isset( $seen[ $identity ] ) ) {
				throw WpPusherSourceException::duplicate_packages();
			}
			$seen[ $identity ] = true;
			$entries[]         = $entry;
		}

		return WpPusherInventory::from_entries( $entries );
	}

	/** @return array<string, bool> */
	public function option_presence(): array {
		if ( ! $this->is_supported_installation() ) {
			return array_fill_keys( self::OPTIONS, false );
		}
		$getter_accessible = is_callable( array( $this->database, '__get' ) );
		$getter_declared   = method_exists( $this->database, '__get' );
		if ( ! array_key_exists( 'options', get_object_vars( $this->database ) )
			&& ( ! $getter_declared || ! $getter_accessible ) ) {
			throw WpPusherSourceException::db_option_reader_unavailable( 'The WordPress options table is unavailable.' );
		}
		/** @var object{options: mixed} $database Public or magic property reads retain their actual values. */
		$database      = $this->database;
		$options_table = (string) $database->options;
		$placeholders  = implode( ', ', array_fill( 0, count( self::OPTIONS ), '%s' ) );
		if ( ! is_callable( array( $this->database, 'prepare' ) ) ) {
			throw WpPusherSourceException::db_option_reader_unavailable();
		}
		if ( ! is_callable( array( $this->database, 'get_col' ) ) ) {
			throw WpPusherSourceException::db_option_reader_unavailable();
		}
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact wpdb options table and generated placeholders.
			$sql   = $this->database->prepare( "SELECT `option_name` FROM `{$options_table}` WHERE `option_name` IN ({$placeholders})", ...self::OPTIONS );
			$names = $this->database->get_col( $sql );
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();
		if ( ! is_array( $names ) ) {
			throw WpPusherSourceException::db_query_failed();
		}
		$found = array_fill_keys( array_intersect( self::OPTIONS, $names ), true );

		return array_map( static fn ( string $name ): bool => isset( $found[ $name ] ), array_combine( self::OPTIONS, self::OPTIONS ) );
	}

	public function supported_package_table_present(): bool {
		if ( ! $this->is_supported_installation() ) {
			return false;
		}
		if ( ! $this->package_table_exists() ) {
			return false;
		}

		$table = $this->table();
		$this->assert_package_table_schema( $table );

		return true;
	}

	public function supported_package_rows_present(): bool {
		if ( ! $this->supported_package_table_present() ) {
			return false;
		}
		if ( ! is_callable( array( $this->database, 'get_var' ) ) ) {
			throw WpPusherSourceException::db_table_reader_unavailable();
		}

		$table = $this->table();
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table derived from wpdb prefix.
			$found = $this->database->get_var( "SELECT 1 FROM `{$table}` LIMIT 1" );
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();

		if ( null === $found ) {
			return false;
		}
		if ( 1 !== $found && '1' !== $found ) {
			throw WpPusherSourceException::db_query_failed();
		}

		return true;
	}

	private function assert_package_table_schema( string $table ): void {
		if ( ! is_callable( array( $this->database, 'get_results' ) ) ) {
			throw WpPusherSourceException::db_schema_reader_unavailable();
		}
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table derived from wpdb prefix.
			$schema = $this->database->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();
		if ( ! is_array( $schema ) ) {
			throw WpPusherSourceException::db_query_failed();
		}
		$this->assert_schema( $schema );
	}

	/** @phpstan-impure */
	public function delete_exact( WpPusherPackage $expected ): bool {
		$table  = $this->table();
		$query  = "DELETE FROM `{$table}` WHERE `id` = %d AND CAST( `package` AS BINARY ) = CAST( %s AS BINARY ) AND CAST( `repository` AS BINARY ) = CAST( %s AS BINARY ) AND CAST( `branch` AS BINARY ) = CAST( %s AS BINARY ) AND `type` = %d AND `status` = %d AND `ptd` = %d AND CAST( `host` AS BINARY ) = CAST( %s AS BINARY ) AND `private` = %d";
		$values = array(
			$expected->id,
			$expected->package,
			$expected->repository,
			$expected->branch,
			$expected->type,
			$expected->status,
			$expected->ptd,
			$expected->host,
			$expected->is_private,
		);
		if ( null === $expected->raw_subdirectory ) {
			$query .= ' AND `subdirectory` IS NULL';
		} else {
			$query   .= ' AND CAST( `subdirectory` AS BINARY ) = CAST( %s AS BINARY )';
			$values[] = $expected->raw_subdirectory;
		}

		// PHP resolves the outer query method before evaluating its prepare argument.
		if ( ! is_callable( array( $this->database, 'query' ) ) || ! is_callable( array( $this->database, 'prepare' ) ) ) {
			throw WpPusherSourceException::db_cleanup_unavailable();
		}
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately from constant columns and exact values.
			$affected = $this->database->query( $this->database->prepare( $query, ...$values ) );
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();

		return 1 === $affected;
	}

	private function is_supported_installation(): bool {
		if ( ( $this->multisite )() ) {
			throw WpPusherSourceException::multisite();
		}
		$plugins = ( $this->plugins )();
		if ( ! array_key_exists( self::PLUGIN, $plugins ) ) {
			return false;
		}
		$version = $plugins[ self::PLUGIN ]['Version'] ?? null;
		if ( ! is_string( $version ) || '' === trim( $version ) ) {
			throw WpPusherSourceException::invalid_plugin_metadata();
		}
		// Plugin versions identify the installation; schema and records determine compatibility.
		if ( in_array( self::PLUGIN, ( $this->active_plugins )(), true )
			|| array_key_exists( self::PLUGIN, ( $this->network_active_plugins )() ) ) {
			throw WpPusherSourceException::plugin_active();
		}

		return true;
	}

	/** @param array<mixed> $schema Database rows remain untrusted until validation. */
	private function assert_schema( array $schema ): void {
		if ( count( self::COLUMNS ) !== count( $schema ) ) {
			throw WpPusherSourceException::unsupported_schema();
		}

		$actual = array();
		foreach ( $schema as $column ) {
			if ( ! isset( $column['Field'], $column['Type'] )
				|| ! is_string( $column['Field'] )
				|| ! is_string( $column['Type'] ) ) {
				throw WpPusherSourceException::unsupported_schema();
			}
			$type                       = strtolower( $column['Type'] );
			$actual[ $column['Field'] ] = match ( $type ) {
				'mediumint(9)' => 'mediumint',
				'int(11)'      => 'int',
				default        => $type,
			};
		}
		if ( self::COLUMNS !== $actual ) {
			throw WpPusherSourceException::unsupported_schema();
		}
	}

	private function table(): string {
		$getter_accessible = is_callable( array( $this->database, '__get' ) );
		$getter_declared   = method_exists( $this->database, '__get' );
		if ( ! array_key_exists( 'prefix', get_object_vars( $this->database ) )
			&& ( ! $getter_declared || ! $getter_accessible ) ) {
			throw WpPusherSourceException::db_prefix_invalid();
		}
		/** @var object{prefix: mixed} $database Public or magic property reads retain their actual values. */
		$database = $this->database;
		$prefix   = (string) $database->prefix;
		if ( '' === $prefix || 1 !== preg_match( '/\A[A-Za-z0-9_]+\z/D', $prefix ) ) {
			throw WpPusherSourceException::db_prefix_invalid();
		}

		return $prefix . 'wppusher_packages';
	}

	private function package_table_exists(): bool {
		$table = $this->table();
		$like  = addcslashes( $table, '\\_%' );
		if ( ! is_callable( array( $this->database, 'prepare' ) ) ) {
			throw WpPusherSourceException::db_table_reader_unavailable();
		}
		if ( ! is_callable( array( $this->database, 'get_var' ) ) ) {
			throw WpPusherSourceException::db_table_reader_unavailable();
		}
		try {
			$sql   = $this->database->prepare( 'SHOW TABLES LIKE %s', $like );
			$found = $this->database->get_var( $sql );
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();
		if ( $table === $found ) {
			return true;
		}
		if ( ! is_string( $found ) || 0 !== strcasecmp( $table, $found ) ) {
			return false;
		}

		try {
			if ( ! is_callable( array( $this->database, 'get_var' ) ) ) {
				throw WpPusherSourceException::db_table_reader_unavailable();
			}
			$mode = $this->database->get_var( 'SELECT @@lower_case_table_names' );
		} catch ( WpPusherSourceException $failure ) {
			throw $failure;
		} catch ( Throwable $failure ) {
			throw WpPusherSourceException::db_query_failed( $failure );
		}
		$this->assert_no_database_error();

		return match ( $mode ) {
			1, '1' => strtolower( $table ) === $found,
			2, '2' => true,
			default => false,
		};
	}

	private function assert_no_database_error(): void {
		$properties = get_object_vars( $this->database );
		if ( array_key_exists( 'last_error', $properties )
			&& is_string( $properties['last_error'] )
			&& '' !== trim( $properties['last_error'] ) ) {
			throw WpPusherSourceException::db_query_failed();
		}
	}

	/** @return array<string, array<string, mixed>> */
	private static function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return (array) get_plugins();
	}
}

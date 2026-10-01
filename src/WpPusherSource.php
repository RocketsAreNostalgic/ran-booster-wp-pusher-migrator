<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use Closure;
use RuntimeException;

/** Exact-version WP Pusher source and package-record cleanup boundary. */
final class WpPusherSource {

	public const PLUGIN = 'wppusher/wppusher.php';

	private const VERSION = '3.0.13';

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
		$this->assert_supported();
		if ( ! $this->package_table_exists() ) {
			return array();
		}
		$table = $this->table();
		$this->assert_package_table_schema( $table );

		$columns = implode( '`, `', array_keys( self::COLUMNS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table and constant column allowlist.
		$rows = $this->database->get_results( "SELECT `{$columns}` FROM `{$table}` ORDER BY `id` ASC LIMIT 129", ARRAY_A );
		if ( ! is_array( $rows ) || count( $rows ) > 128 ) {
			throw new RuntimeException( 'The retained WP Pusher package inventory is unsupported.' );
		}

		$packages = array();
		$seen     = array();
		foreach ( $rows as $row ) {
			$package  = WpPusherPackage::from_row( is_array( $row ) ? $row : array() );
			$identity = $package->type . ':' . $package->package;
			if ( isset( $seen[ $identity ] ) ) {
				throw new RuntimeException( 'The retained WP Pusher package inventory contains duplicates.' );
			}
			$seen[ $identity ] = true;
			$packages[]        = $package;
		}

		return $packages;
	}

	/** @return array<string, bool> */
	public function option_presence(): array {
		$this->assert_supported();
		$options_table = (string) $this->database->options;
		$placeholders  = implode( ', ', array_fill( 0, count( self::OPTIONS ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact wpdb options table and generated placeholders.
		$sql   = $this->database->prepare( "SELECT `option_name` FROM `{$options_table}` WHERE `option_name` IN ({$placeholders})", ...self::OPTIONS );
		$names = $this->database->get_col( $sql );
		$found = is_array( $names ) ? array_fill_keys( array_intersect( self::OPTIONS, $names ), true ) : array();

		return array_map( static fn ( string $name ): bool => isset( $found[ $name ] ), array_combine( self::OPTIONS, self::OPTIONS ) );
	}

	public function supported_package_table_present(): bool {
		$this->assert_supported();
		if ( ! $this->package_table_exists() ) {
			return false;
		}

		$table = $this->table();
		$this->assert_package_table_schema( $table );

		return true;
	}

	private function assert_package_table_schema( string $table ): void {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table derived from wpdb prefix.
		$schema = $this->database->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		$this->assert_schema( is_array( $schema ) ? $schema : array() );
	}

	public function delete_exact( WpPusherPackage $expected ): bool {
		$table  = $this->table();
		$query  = "DELETE FROM `{$table}` WHERE `id` = %d AND `package` = %s AND `repository` = %s AND `branch` = %s AND `type` = %d AND `status` = %d AND `ptd` = %d AND `host` = %s AND `private` = %d";
		$values = array(
			$expected->id,
			$expected->package,
			$expected->repository,
			$expected->branch,
			$expected->type,
			$expected->status,
			$expected->ptd,
			$expected->host,
			$expected->private,
		);
		if ( null === $expected->subdirectory ) {
			$query .= ' AND `subdirectory` IS NULL';
		} else {
			$query   .= ' AND `subdirectory` = %s';
			$values[] = $expected->subdirectory;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately from constant columns and exact values.
		return 1 === $this->database->query( $this->database->prepare( $query, ...$values ) );
	}

	private function assert_supported(): void {
		if ( ( $this->multisite )() ) {
			throw new RuntimeException( 'WP Pusher migration supports single-site WordPress only.' );
		}
		$plugins = ( $this->plugins )();
		if ( ! isset( $plugins[ self::PLUGIN ]['Version'] )
			|| self::VERSION !== $plugins[ self::PLUGIN ]['Version'] ) {
			throw new RuntimeException( 'Only retained WP Pusher 3.0.13 data is supported.' );
		}
		if ( in_array( self::PLUGIN, ( $this->active_plugins )(), true )
			|| array_key_exists( self::PLUGIN, ( $this->network_active_plugins )() ) ) {
			throw new RuntimeException( 'Deactivate WP Pusher before assessing retained packages.' );
		}
	}

	/** @param list<array<string, mixed>> $schema */
	private function assert_schema( array $schema ): void {
		if ( count( self::COLUMNS ) !== count( $schema ) ) {
			throw new RuntimeException( 'The retained WP Pusher package schema is unsupported.' );
		}

		$actual = array();
		foreach ( $schema as $column ) {
			if ( ! isset( $column['Field'], $column['Type'] )
				|| ! is_string( $column['Field'] )
				|| ! is_string( $column['Type'] ) ) {
				throw new RuntimeException( 'The retained WP Pusher package schema is unsupported.' );
			}
			$type                       = strtolower( $column['Type'] );
			$actual[ $column['Field'] ] = 'mediumint(9)' === $type ? 'mediumint' : $type;
		}
		if ( self::COLUMNS !== $actual ) {
			throw new RuntimeException( 'The retained WP Pusher package schema is unsupported.' );
		}
	}

	private function table(): string {
		$prefix = (string) $this->database->prefix;
		if ( '' === $prefix || 1 !== preg_match( '/\A[A-Za-z0-9_]+\z/D', $prefix ) ) {
			throw new RuntimeException( 'The WordPress database prefix is invalid.' );
		}

		return $prefix . 'wppusher_packages';
	}

	private function package_table_exists(): bool {
		$table = $this->table();
		$like  = addcslashes( $table, '\\_%' );
		$sql   = $this->database->prepare( 'SHOW TABLES LIKE %s', $like );

		return $table === $this->database->get_var( $sql );
	}

	/** @return array<string, array<string, mixed>> */
	private static function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return (array) get_plugins();
	}
}

<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use Closure;
use RuntimeException;

/** Read-only exact-version WP Pusher source boundary. */
final class WpPusherSource {

	public const PLUGIN = 'wppusher/wppusher.php';

	private const VERSION = '3.0.13';

	private const COLUMNS = array(
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
	private Closure $activePlugins;

	/** @var Closure():array<string, mixed> */
	private Closure $networkActivePlugins;

	/** @var Closure():bool */
	private Closure $multisite;

	/**
	 * @param null|callable():array<string, array<string, mixed>> $plugins
	 * @param null|callable():array<int, string>                  $activePlugins
	 * @param null|callable():array<string, mixed>                $networkActivePlugins
	 * @param null|callable():bool                                $multisite
	 */
	public function __construct(
		?object $database = null,
		?callable $plugins = null,
		?callable $activePlugins = null,
		?callable $networkActivePlugins = null,
		?callable $multisite = null
	) {
		global $wpdb;

		$this->database             = $database ?? $wpdb;
		$this->plugins              = Closure::fromCallable( $plugins ?? self::plugins( ... ) );
		$this->activePlugins        = Closure::fromCallable( $activePlugins ?? static fn (): array => (array) get_option( 'active_plugins', array() ) );
		$this->networkActivePlugins = Closure::fromCallable( $networkActivePlugins ?? static fn (): array => (array) get_site_option( 'active_sitewide_plugins', array() ) );
		$this->multisite            = Closure::fromCallable( $multisite ?? static fn (): bool => is_multisite() );
	}

	/** @return list<WpPusherPackage> */
	public function packages(): array {
		$this->assertSupported();
		$table = $this->table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table derived from wpdb prefix.
		$schema = $this->database->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		$this->assertSchema( is_array( $schema ) ? $schema : array() );

		$columns = implode( '`, `', array_keys( self::COLUMNS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact validated table and constant column allowlist.
		$rows = $this->database->get_results( "SELECT `{$columns}` FROM `{$table}` ORDER BY `id` ASC LIMIT 129", ARRAY_A );
		if ( ! is_array( $rows ) || count( $rows ) > 128 ) {
			throw new RuntimeException( 'The retained WP Pusher package inventory is unsupported.' );
		}

		$packages = array();
		$seen     = array();
		foreach ( $rows as $row ) {
			$package  = WpPusherPackage::fromRow( is_array( $row ) ? $row : array() );
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
	public function optionPresence(): array {
		$this->assertSupported();
		$optionsTable = (string) $this->database->options;
		$placeholders = implode( ', ', array_fill( 0, count( self::OPTIONS ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Exact wpdb options table and generated placeholders.
		$sql   = $this->database->prepare( "SELECT `option_name` FROM `{$optionsTable}` WHERE `option_name` IN ({$placeholders})", ...self::OPTIONS );
		$names = $this->database->get_col( $sql );
		$found = is_array( $names ) ? array_fill_keys( array_intersect( self::OPTIONS, $names ), true ) : array();

		return array_map( static fn ( string $name ): bool => isset( $found[ $name ] ), array_combine( self::OPTIONS, self::OPTIONS ) );
	}

	private function assertSupported(): void {
		if ( ( $this->multisite )() ) {
			throw new RuntimeException( 'WP Pusher migration supports single-site WordPress only.' );
		}
		$plugins = ( $this->plugins )();
		if ( ! isset( $plugins[ self::PLUGIN ]['Version'] )
			|| self::VERSION !== $plugins[ self::PLUGIN ]['Version'] ) {
			throw new RuntimeException( 'Only retained WP Pusher 3.0.13 data is supported.' );
		}
		if ( in_array( self::PLUGIN, ( $this->activePlugins )(), true )
			|| array_key_exists( self::PLUGIN, ( $this->networkActivePlugins )() ) ) {
			throw new RuntimeException( 'Deactivate WP Pusher before assessing retained packages.' );
		}
	}

	/** @param list<array<string, mixed>> $schema */
	private function assertSchema( array $schema ): void {
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
			$actual[ $column['Field'] ] = strtolower( $column['Type'] );
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

	/** @return array<string, array<string, mixed>> */
	private static function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return (array) get_plugins();
	}
}

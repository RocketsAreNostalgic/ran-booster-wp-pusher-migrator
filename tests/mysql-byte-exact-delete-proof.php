<?php
/** Real disposable MySQL 8.4 regression proof for byte-exact delete_exact(). */
declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests\MysqlByteExactDeleteProof;

use PDO;
use RAN\BoosterWpPusherMigrator\Autoloader;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use ReflectionClass;
use RuntimeException;

if ( ! defined( 'ARRAY_A' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Supply the exact WordPress or Core capability constant consumed by the host-contract fixture.
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Supply the exact WordPress or Core capability constant consumed by the host-contract fixture.
	define( 'ABSPATH', '/tmp/mysql-byte-exact-proof-wordpress/' );
}

require_once dirname( __DIR__ ) . '/src/Autoloader.php';
Autoloader::register();

function expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function required_env( string $name ): string {
	$value = getenv( $name );
	if ( false === $value || '' === $value ) {
		throw new RuntimeException( 'Missing required MySQL proof environment variable: ' . $name );
	}

	return $value;
}

function connect_pdo(): PDO {
	$database = required_env( 'MYSQL_DATABASE' );
	$user     = required_env( 'MYSQL_USER' );
	$password = getenv( 'MYSQL_PASSWORD' );
	$password = false === $password ? '' : $password;
	$socket   = getenv( 'MYSQL_SOCKET' );
	if ( false !== $socket && '' !== $socket ) {
		$dsn = sprintf( 'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $database );
	} else {
		$host = required_env( 'MYSQL_HOST' );
		$port = getenv( 'MYSQL_PORT' );
		$port = false === $port || '' === $port ? '3306' : $port;
		$dsn  = sprintf( 'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database );
	}

	return new PDO(
		$dsn,
		$user,
		$password,
		array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_STRINGIFY_FETCHES  => true,
		)
	);
}

function run_proof(): void {
	$source_file = ( new ReflectionClass( WpPusherSource::class ) )->getFileName();
	expect(
		dirname( __DIR__ ) . '/src/WpPusherSource.php' === $source_file,
		'Proof must load the current checkout src/WpPusherSource.php.'
	);

	$pdo = connect_pdo();

	$database = new class( $pdo ) {
		public string $prefix     = 'wp_';
		public string $last_error = '';

		public function __construct( private PDO $pdo ) {
		}

		public function prepare( string $query, mixed ...$values ): string {
			$index = 0;
			$sql   = preg_replace_callback(
				'/%(?:d|s)/',
				function ( array $matches ) use ( &$index, $values ): string {
					if ( ! array_key_exists( $index, $values ) ) {
						throw new RuntimeException( 'Missing prepare placeholder value.' );
					}
					$value = $values[ $index ];
					++$index;
					if ( '%d' === $matches[0] ) {
						return (string) (int) $value;
					}
					$quoted = $this->pdo->quote( (string) $value );
					if ( false === $quoted ) {
						throw new RuntimeException( 'PDO could not quote string argument.' );
					}

					return $quoted;
				},
				$query
			);
			if ( null === $sql || count( $values ) !== $index ) {
				throw new RuntimeException( 'Prepared query placeholder count mismatch.' );
			}

			return $sql;
		}

		public function query( string $query ): int {
			$this->last_error = '';
			$affected         = $this->pdo->exec( $query );
			if ( false === $affected ) {
				throw new RuntimeException( 'PDO exec failed.' );
			}

			return $affected;
		}

		/** @return list<array<string, mixed>> */
		public function get_results( string $query, string $output = ARRAY_A ): array {
			unset( $output );
			$this->last_error = '';
			$statement        = $this->pdo->query( $query );
			if ( false === $statement ) {
				throw new RuntimeException( 'PDO query failed.' );
			}

			/** @var list<array<string, mixed>> $rows */
			$rows = $statement->fetchAll( PDO::FETCH_ASSOC );

			return $rows;
		}

		public function get_var( string $query ): mixed {
			$this->last_error = '';
			$statement        = $this->pdo->query( $query );
			if ( false === $statement ) {
				throw new RuntimeException( 'PDO query failed.' );
			}
			$value = $statement->fetchColumn( 0 );

			return false === $value ? null : $value;
		}
	};

	$table = $database->prefix . 'wppusher_packages';
	$pdo->exec( "DROP TABLE IF EXISTS `{$table}`" );
	$pdo->exec(
		"CREATE TABLE `{$table}` ("
		. '`id` mediumint NOT NULL,'
		. '`package` varchar(255) NOT NULL,'
		. '`repository` varchar(255) NOT NULL,'
		. '`branch` varchar(255) NOT NULL,'
		. '`type` int NOT NULL,'
		. '`status` int NOT NULL,'
		. '`ptd` int NOT NULL,'
		. '`host` varchar(10) NOT NULL,'
		. '`private` int NOT NULL,'
		. '`subdirectory` varchar(255) NULL,'
		. 'PRIMARY KEY (`id`)'
		. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
	);

	$insert = $pdo->prepare(
		"INSERT INTO `{$table}` (`id`, `package`, `repository`, `branch`, `type`, `status`, `ptd`, `host`, `private`, `subdirectory`)"
		. ' VALUES (:id, :package, :repository, :branch, :type, :status, :ptd, :host, :private, :subdirectory)'
	);
	$insert->execute(
		array(
			':id'           => 1,
			':package'      => 'fixture/fixture.php',
			':repository'   => 'RocketsAreNostalgic/booster-fixture-plugin',
			':branch'       => 'main',
			':type'         => 1,
			':status'       => 1,
			':ptd'          => 0,
			':host'         => 'gh',
			':private'      => 0,
			':subdirectory' => 'plugins/foo/',
		)
	);

	// Control: confirm utf8mb4_unicode_ci treats plugins/foo/ and plugins/Foo/ as equal under plain =.
	$control = $pdo->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `id` = 1 AND `subdirectory` = :sub" );
	$control->execute( array( ':sub' => 'plugins/Foo/' ) );
	expect(
		'1' === (string) $control->fetchColumn( 0 ),
		'Control failed: plain = under utf8mb4_unicode_ci did not match plugins/Foo/ against plugins/foo/.'
	);

	$source   = new WpPusherSource(
		$database,
		static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.18' ) ),
		static fn (): array => array(),
		static fn (): array => array(),
		static fn (): bool => false
	);
	$packages = $source->packages();
	expect( 1 === count( $packages ), 'Expected exactly one seeded row.' );
	$original = $packages[0];
	expect( 'plugins/foo' === $original->subdirectory, 'Expected normalized subdirectory plugins/foo.' );
	expect( 'plugins/foo/' === $original->raw_subdirectory, 'Expected raw subdirectory plugins/foo/.' );

	$mutations = array(
		array( 'subdirectory', 'plugins/Foo/', 'plugins/foo/' ),
		array( 'subdirectory', 'plugins/foo/ ', 'plugins/foo/' ),
		array( 'package', 'Fixture/fixture.php', 'fixture/fixture.php' ),
		array( 'package', 'fixture/fixture.php ', 'fixture/fixture.php' ),
		array( 'repository', 'RocketsAreNostalgic/Booster-Fixture-Plugin', 'RocketsAreNostalgic/booster-fixture-plugin' ),
		array( 'repository', 'RocketsAreNostalgic/booster-fixture-plugin ', 'RocketsAreNostalgic/booster-fixture-plugin' ),
		array( 'branch', 'Main', 'main' ),
		array( 'branch', 'main ', 'main' ),
		array( 'host', 'GH', 'gh' ),
		array( 'host', 'gh ', 'gh' ),
	);

	foreach ( $mutations as $mutation ) {
		list( $column, $mutated_value, $original_value ) = $mutation;
		$update = $pdo->prepare( "UPDATE `{$table}` SET `{$column}` = :value WHERE `id` = 1" );
		$update->execute( array( ':value' => $mutated_value ) );

		expect(
			! $source->delete_exact( $original ),
			sprintf( 'delete_exact() unexpectedly deleted mutated %s (%s).', $column, $mutated_value )
		);

		$check = $pdo->query( "SELECT HEX(`{$column}`) FROM `{$table}` WHERE `id` = 1" );
		if ( false === $check ) {
			throw new RuntimeException( 'Could not inspect surviving mutated row.' );
		}
		expect(
			strtoupper( bin2hex( $mutated_value ) ) === strtoupper( (string) $check->fetchColumn( 0 ) ),
			sprintf( 'Mutated %s row did not survive intact in MySQL.', $column )
		);

		$update->execute( array( ':value' => $original_value ) );
	}

	// Exact original bytes must delete the row.
	expect( $source->delete_exact( $original ), 'delete_exact() failed to delete the exact matching row.' );
	$after_first = $pdo->query( "SELECT COUNT(*) FROM `{$table}` WHERE `id` = 1" );
	expect( false !== $after_first && '0' === (string) $after_first->fetchColumn( 0 ), 'Exact matching row remained after delete_exact().' );

	// NULL vs empty-string distinction.
	$insert->execute(
		array(
			':id'           => 2,
			':package'      => 'null-sub/plugin.php',
			':repository'   => 'RocketsAreNostalgic/null-sub-plugin',
			':branch'       => 'main',
			':type'         => 1,
			':status'       => 1,
			':ptd'          => 0,
			':host'         => 'gh',
			':private'      => 0,
			':subdirectory' => null,
		)
	);
	$null_packages = $source->packages();
	expect( 1 === count( $null_packages ), 'Expected one NULL-subdirectory row.' );
	$null_original = $null_packages[0];
	expect( null === $null_original->subdirectory && null === $null_original->raw_subdirectory, 'Expected NULL raw and normalized subdirectory.' );

	$pdo->exec( "UPDATE `{$table}` SET `subdirectory` = '' WHERE `id` = 2" );
	expect(
		! $source->delete_exact( $null_original ),
		'delete_exact() with NULL subdirectory unexpectedly deleted empty-string row.'
	);
	$empty_check = $pdo->query( "SELECT `subdirectory` FROM `{$table}` WHERE `id` = 2" );
	expect( false !== $empty_check && '' === $empty_check->fetchColumn( 0 ), 'Empty-string row did not survive NULL delete_exact().' );

	$pdo->exec( "UPDATE `{$table}` SET `subdirectory` = NULL WHERE `id` = 2" );
	expect(
		$source->delete_exact( $null_original ),
		'delete_exact() with NULL subdirectory failed to delete matching NULL row.'
	);
	$after_second = $pdo->query( "SELECT COUNT(*) FROM `{$table}` WHERE `id` = 2" );
	expect( false !== $after_second && '0' === (string) $after_second->fetchColumn( 0 ), 'NULL subdirectory row remained after delete_exact().' );

	$pdo->exec( "DROP TABLE IF EXISTS `{$table}`" );
	echo "PASS MySQL utf8mb4_unicode_ci byte-exact delete_exact() proof.\n";
}

run_proof();

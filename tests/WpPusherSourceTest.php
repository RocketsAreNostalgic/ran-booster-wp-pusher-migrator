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
		self::assertStringContainsString( 'LIMIT 129', $database->queries[1] );
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
	public array $optionNames = array();

	/** @var list<array<string, string>> */
	public array $schema;

	/** @var list<array<string, mixed>> */
	public array $rows;

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

		return str_starts_with( $query, 'SHOW COLUMNS' ) ? $this->schema : $this->rows;
	}

	public function prepare( string $query, string ...$values ): string {
		unset( $values );

		return $query;
	}

	/** @return list<string> */
	public function get_col( string $query ): array {
		$this->queries[] = $query;

		return $this->optionNames;
	}
}

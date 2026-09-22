<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use InvalidArgumentException;
use JsonException;

/** Exact validated projection of one retained WP Pusher package row. */
final readonly class WpPusherPackage {

	/** @param array<string, mixed> $row */
	public static function fromRow( array $row ): self {
		$expected = array(
			'id',
			'package',
			'repository',
			'branch',
			'type',
			'status',
			'ptd',
			'host',
			'private',
			'subdirectory',
		);
		if ( array_keys( $row ) !== $expected ) {
			throw new InvalidArgumentException( 'The WP Pusher package row is malformed.' );
		}

		return new self(
			self::positiveInteger( $row['id'] ),
			self::boundedString( $row['package'] ),
			self::repository( $row['repository'] ),
			self::boundedString( $row['branch'], true ),
			self::oneOfIntegers( $row['type'], array( 1, 2 ) ),
			self::booleanInteger( $row['status'] ),
			self::booleanInteger( $row['ptd'] ),
			self::oneOfStrings( $row['host'], array( 'gh', 'bb', 'gl' ) ),
			self::booleanInteger( $row['private'] ),
			self::subdirectory( $row['subdirectory'] )
		);
	}

	public function __construct(
		public int $id,
		public string $package,
		public string $repository,
		public string $branch,
		public int $type,
		public int $status,
		public int $ptd,
		public string $host,
		public int $private,
		public ?string $subdirectory
	) {
	}

	public function fingerprint(): string {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Canonical local fingerprint.
			$json = json_encode( $this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
		} catch ( JsonException ) {
			throw new InvalidArgumentException( 'The WP Pusher package could not be fingerprinted.' );
		}

		return 'v1:' . hash( 'sha256', $json );
	}

	/** @return array{id:int,package:string,repository:string,branch:string,type:int,status:int,ptd:int,host:string,private:int,subdirectory:string|null} */
	public function toArray(): array {
		return array(
			'id'           => $this->id,
			'package'      => $this->package,
			'repository'   => $this->repository,
			'branch'       => $this->branch,
			'type'         => $this->type,
			'status'       => $this->status,
			'ptd'          => $this->ptd,
			'host'         => $this->host,
			'private'      => $this->private,
			'subdirectory' => $this->subdirectory,
		);
	}

	private static function positiveInteger( mixed $value ): int {
		$integer = filter_var( $value, FILTER_VALIDATE_INT );
		if ( false === $integer || $integer < 1 ) {
			throw new InvalidArgumentException( 'The WP Pusher package identifier is invalid.' );
		}

		return $integer;
	}

	/** @param list<int> $allowed */
	private static function oneOfIntegers( mixed $value, array $allowed ): int {
		$integer = filter_var( $value, FILTER_VALIDATE_INT );
		if ( false === $integer || ! in_array( $integer, $allowed, true ) ) {
			throw new InvalidArgumentException( 'The WP Pusher package field is unsupported.' );
		}

		return $integer;
	}

	private static function booleanInteger( mixed $value ): int {
		return self::oneOfIntegers( $value, array( 0, 1 ) );
	}

	/** @param list<string> $allowed */
	private static function oneOfStrings( mixed $value, array $allowed ): string {
		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'The WP Pusher provider is unsupported.' );
		}

		return $value;
	}

	private static function boundedString( mixed $value, bool $allowEmpty = false ): string {
		if ( ! is_string( $value )
			|| strlen( $value ) > 255
			|| ( ! $allowEmpty && '' === $value )
			|| 1 === preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			throw new InvalidArgumentException( 'The WP Pusher package field is invalid.' );
		}

		return $value;
	}

	private static function repository( mixed $value ): string {
		$repository = self::boundedString( $value );
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/D', $repository ) ) {
			throw new InvalidArgumentException( 'The WP Pusher repository is invalid.' );
		}

		return $repository;
	}

	private static function subdirectory( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$path = self::boundedString( $value, true );
		if ( '' === $path ) {
			return '';
		}
		if ( str_starts_with( $path, '/' )
			|| str_contains( $path, '\\' )
			|| in_array( '..', explode( '/', $path ), true )
			|| 1 !== preg_match( '/\A[A-Za-z0-9._\/-]+\z/D', $path ) ) {
			throw new InvalidArgumentException( 'The WP Pusher subdirectory is invalid.' );
		}

		return trim( $path, '/' );
	}
}

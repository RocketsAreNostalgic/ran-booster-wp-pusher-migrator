<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use InvalidArgumentException;
use JsonException;

/** Exact validated projection of one retained WP Pusher package row. */
final readonly class WpPusherPackage {

	/** @param array<string, mixed> $row */
	public static function from_row( array $row ): self {
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

		$raw_subdirectory = $row['subdirectory'];
		$subdirectory     = self::subdirectory( $raw_subdirectory );

		return new self(
			self::positive_integer( $row['id'] ),
			self::bounded_string( $row['package'] ),
			self::repository( $row['repository'] ),
			self::bounded_string( $row['branch'], true ),
			self::one_of_integers( $row['type'], array( 1, 2 ) ),
			self::boolean_integer( $row['status'] ),
			self::boolean_integer( $row['ptd'] ),
			self::one_of_strings( $row['host'], array( 'gh', 'bb', 'gl' ) ),
			self::boolean_integer( $row['private'] ),
			$subdirectory,
			is_string( $raw_subdirectory ) ? $raw_subdirectory : null
		);
	}

	/**
	 * Build a rejected-row record when a retained row is valid in every field
	 * except its stored subdirectory string.
	 *
	 * @param array<string, mixed> $row
	 */
	public static function rejected_subdirectory_row( array $row ): ?WpPusherRejectedPackage {
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
		if ( array_keys( $row ) !== $expected || ! is_string( $row['subdirectory'] ) ) {
			return null;
		}

		try {
			$id         = self::positive_integer( $row['id'] );
			$package    = self::bounded_string( $row['package'] );
			$repository = self::repository( $row['repository'] );
			self::bounded_string( $row['branch'], true );
			$type = self::one_of_integers( $row['type'], array( 1, 2 ) );
			self::boolean_integer( $row['status'] );
			self::boolean_integer( $row['ptd'] );
			self::one_of_strings( $row['host'], array( 'gh', 'bb', 'gl' ) );
			self::boolean_integer( $row['private'] );
		} catch ( InvalidArgumentException ) {
			return null;
		}

		try {
			self::subdirectory( $row['subdirectory'] );

			return null;
		} catch ( InvalidArgumentException ) {
			return new WpPusherRejectedPackage(
				$id,
				$package,
				$repository,
				$type,
				$row['subdirectory'],
				WpPusherRejectedPackage::REASON_INVALID_SUBDIRECTORY
			);
		}
	}

	public ?string $raw_subdirectory;

	public function __construct(
		public int $id,
		public string $package,
		public string $repository,
		public string $branch,
		public int $type,
		public int $status,
		public int $ptd,
		public string $host,
		public int $is_private,
		public ?string $subdirectory,
		?string $raw_subdirectory = null
	) {
		$this->raw_subdirectory = $raw_subdirectory ?? $subdirectory;
	}

	public function fingerprint(): string {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Canonical local fingerprint.
			$json = json_encode( $this->to_array(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
		} catch ( JsonException ) {
			throw new InvalidArgumentException( 'The WP Pusher package could not be fingerprinted.' );
		}

		return 'v1:' . hash( 'sha256', $json );
	}

	/** @return array{id:int,package:string,repository:string,branch:string,type:int,status:int,ptd:int,host:string,private:int,subdirectory:string|null} */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'package'      => $this->package,
			'repository'   => $this->repository,
			'branch'       => $this->branch,
			'type'         => $this->type,
			'status'       => $this->status,
			'ptd'          => $this->ptd,
			'host'         => $this->host,
			'private'      => $this->is_private,
			'subdirectory' => $this->raw_subdirectory,
		);
	}

	private static function positive_integer( mixed $value ): int {
		$integer = filter_var( $value, FILTER_VALIDATE_INT );
		if ( false === $integer || $integer < 1 ) {
			throw new InvalidArgumentException( 'The WP Pusher package identifier is invalid.' );
		}

		return $integer;
	}

	/** @param list<int> $allowed */
	private static function one_of_integers( mixed $value, array $allowed ): int {
		$integer = filter_var( $value, FILTER_VALIDATE_INT );
		if ( false === $integer || ! in_array( $integer, $allowed, true ) ) {
			throw new InvalidArgumentException( 'The WP Pusher package field is unsupported.' );
		}

		return $integer;
	}

	private static function boolean_integer( mixed $value ): int {
		return self::one_of_integers( $value, array( 0, 1 ) );
	}

	/** @param list<string> $allowed */
	private static function one_of_strings( mixed $value, array $allowed ): string {
		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'The WP Pusher provider is unsupported.' );
		}

		return $value;
	}

	private static function bounded_string( mixed $value, bool $allow_empty = false ): string {
		if ( ! is_string( $value )
			|| strlen( $value ) > 255
			|| ( ! $allow_empty && '' === $value )
			|| 1 === preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			throw new InvalidArgumentException( 'The WP Pusher package field is invalid.' );
		}

		return $value;
	}

	private static function repository( mixed $value ): string {
		$repository = self::bounded_string( $value );
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/D', $repository ) ) {
			throw new InvalidArgumentException( 'The WP Pusher repository is invalid.' );
		}

		return $repository;
	}

	private static function subdirectory( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$path = self::bounded_string( $value, true );
		if ( '' === $path ) {
			return '';
		}
		if ( 1 !== preg_match( '/\A[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*\/?\z/D', $path ) ) {
			throw new InvalidArgumentException( 'The WP Pusher subdirectory is invalid.' );
		}

		$normalized = str_ends_with( $path, '/' ) ? substr( $path, 0, -1 ) : $path;
		$segments   = explode( '/', $normalized );
		if ( in_array( '.', $segments, true ) || in_array( '..', $segments, true ) ) {
			throw new InvalidArgumentException( 'The WP Pusher subdirectory is invalid.' );
		}

		return $normalized;
	}
}

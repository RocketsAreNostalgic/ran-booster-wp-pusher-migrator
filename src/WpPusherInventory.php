<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

/** Result of reading retained WP Pusher packages without conflating absence with failure. */
final readonly class WpPusherInventory {

	public const STATUS_NOT_INSTALLED = 'not_installed';
	public const STATUS_EMPTY         = 'empty';
	public const STATUS_HAS_PACKAGES  = 'has_packages';

	/**
	 * @param list<WpPusherPackage|WpPusherRejectedPackage> $entries Retained rows in table order.
	 */
	public function __construct(
		public string $status,
		public array $entries
	) {
	}

	public static function not_installed(): self {
		return new self( self::STATUS_NOT_INSTALLED, array() );
	}

	public static function empty_inventory(): self {
		return new self( self::STATUS_EMPTY, array() );
	}

	/**
	 * @param list<WpPusherPackage|WpPusherRejectedPackage> $entries Retained rows in table order.
	 */
	public static function from_entries( array $entries ): self {
		if ( array() === $entries ) {
			return self::empty_inventory();
		}

		return new self( self::STATUS_HAS_PACKAGES, $entries );
	}

	public function is_installed(): bool {
		return self::STATUS_NOT_INSTALLED !== $this->status;
	}

	public function is_empty(): bool {
		return array() === $this->entries;
	}

	/** @return list<WpPusherPackage> */
	public function packages(): array {
		$packages = array();
		foreach ( $this->entries as $entry ) {
			if ( $entry instanceof WpPusherPackage ) {
				$packages[] = $entry;
			}
		}

		return $packages;
	}

	/** @return list<WpPusherRejectedPackage> */
	public function rejected(): array {
		$rejected = array();
		foreach ( $this->entries as $entry ) {
			if ( $entry instanceof WpPusherRejectedPackage ) {
				$rejected[] = $entry;
			}
		}

		return $rejected;
	}
}

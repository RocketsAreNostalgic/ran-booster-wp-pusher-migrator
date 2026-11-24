<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

/** Validated retained WP Pusher row that cannot be migrated due to an invalid subdirectory. */
final readonly class WpPusherRejectedPackage {

	public const REASON_INVALID_SUBDIRECTORY = 'invalid_subdirectory';

	public function __construct(
		public int $id,
		public string $package,
		public string $repository,
		public int $type,
		public string $raw_subdirectory,
		public string $reason = self::REASON_INVALID_SUBDIRECTORY
	) {
	}

	public function display_subdirectory(): string {
		$sanitized = preg_replace( '/[\x00-\x1F\x7F]+/', '', $this->raw_subdirectory );
		if ( null === $sanitized ) {
			$sanitized = '';
		}
		if ( strlen( $sanitized ) > 255 ) {
			$sanitized = substr( $sanitized, 0, 255 );
		}

		return $sanitized;
	}
}

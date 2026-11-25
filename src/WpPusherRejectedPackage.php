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
		if ( '' !== $sanitized && 1 !== preg_match( '//u', $sanitized ) ) {
			$matched   = preg_match_all(
				'/[\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE-\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}/',
				$sanitized,
				$matches
			);
			$sanitized = false !== $matched && $matched > 0 ? implode( '', $matches[0] ) : '';
		}
		if ( strlen( $sanitized ) > 255 ) {
			$length = 255;
			while ( $length > 0 && 0x80 === ( ord( $sanitized[ $length ] ) & 0xC0 ) ) {
				--$length;
			}
			$sanitized = substr( $sanitized, 0, $length );
		}

		return $sanitized;
	}
}

<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RuntimeException;
use Throwable;

/** Closed failure classification for retained WP Pusher source reads and cleanup. */
final class WpPusherSourceException extends RuntimeException {

	public const REASON_MULTISITE                    = 'multisite';
	public const REASON_INVALID_PLUGIN_METADATA      = 'invalid_plugin_metadata';
	public const REASON_PLUGIN_ACTIVE                = 'plugin_active';
	public const REASON_UNSUPPORTED_SCHEMA           = 'unsupported_schema';
	public const REASON_INVENTORY_LIMIT_EXCEEDED     = 'inventory_limit_exceeded';
	public const REASON_DUPLICATE_PACKAGES           = 'duplicate_packages';
	public const REASON_MALFORMED_PACKAGE_ROW        = 'malformed_package_row';
	public const REASON_DB_PREFIX_INVALID            = 'db_prefix_invalid';
	public const REASON_DB_TABLE_READER_UNAVAILABLE  = 'db_table_reader_unavailable';
	public const REASON_DB_SCHEMA_READER_UNAVAILABLE = 'db_schema_reader_unavailable';
	public const REASON_DB_READER_UNAVAILABLE        = 'db_reader_unavailable';
	public const REASON_DB_OPTION_READER_UNAVAILABLE = 'db_option_reader_unavailable';
	public const REASON_DB_CLEANUP_UNAVAILABLE       = 'db_cleanup_unavailable';
	public const REASON_DB_QUERY_FAILED              = 'db_query_failed';

	private function __construct(
		public readonly string $reason,
		public readonly bool $is_database_error,
		string $message,
		?Throwable $previous = null
	) {
		parent::__construct( $message, 0, $previous );
	}

	public static function multisite(): self {
		return new self(
			self::REASON_MULTISITE,
			false,
			'WP Pusher migration supports single-site WordPress only.'
		);
	}

	public static function invalid_plugin_metadata(): self {
		return new self(
			self::REASON_INVALID_PLUGIN_METADATA,
			false,
			'An installed WP Pusher plugin with valid version metadata is required.'
		);
	}

	public static function plugin_active(): self {
		return new self(
			self::REASON_PLUGIN_ACTIVE,
			false,
			'Deactivate WP Pusher before assessing retained packages.'
		);
	}

	public static function unsupported_schema(): self {
		return new self(
			self::REASON_UNSUPPORTED_SCHEMA,
			false,
			'The retained WP Pusher package schema is unsupported.'
		);
	}

	public static function inventory_limit_exceeded(): self {
		return new self(
			self::REASON_INVENTORY_LIMIT_EXCEEDED,
			false,
			'The retained WP Pusher package inventory is unsupported.'
		);
	}

	public static function duplicate_packages(): self {
		return new self(
			self::REASON_DUPLICATE_PACKAGES,
			false,
			'The retained WP Pusher package inventory contains duplicates.'
		);
	}

	public static function malformed_package_row( ?Throwable $previous = null ): self {
		return new self(
			self::REASON_MALFORMED_PACKAGE_ROW,
			false,
			'The retained WP Pusher package row is malformed.',
			$previous
		);
	}

	public static function db_prefix_invalid(): self {
		return new self(
			self::REASON_DB_PREFIX_INVALID,
			true,
			'The WordPress database prefix is invalid.'
		);
	}

	public static function db_table_reader_unavailable(): self {
		return new self(
			self::REASON_DB_TABLE_READER_UNAVAILABLE,
			true,
			'The WP Pusher package table reader is unavailable.'
		);
	}

	public static function db_schema_reader_unavailable(): self {
		return new self(
			self::REASON_DB_SCHEMA_READER_UNAVAILABLE,
			true,
			'The WP Pusher schema reader is unavailable.'
		);
	}

	public static function db_reader_unavailable(): self {
		return new self(
			self::REASON_DB_READER_UNAVAILABLE,
			true,
			'The WP Pusher database reader is unavailable.'
		);
	}

	public static function db_option_reader_unavailable( string $message = 'The WP Pusher option reader is unavailable.' ): self {
		return new self(
			self::REASON_DB_OPTION_READER_UNAVAILABLE,
			true,
			$message
		);
	}

	public static function db_cleanup_unavailable(): self {
		return new self(
			self::REASON_DB_CLEANUP_UNAVAILABLE,
			true,
			'The WP Pusher cleanup database is unavailable.'
		);
	}

	public static function db_query_failed( ?Throwable $previous = null ): self {
		return new self(
			self::REASON_DB_QUERY_FAILED,
			true,
			'The WP Pusher database query failed.',
			$previous
		);
	}
}

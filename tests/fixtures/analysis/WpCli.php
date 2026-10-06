<?php
/**
 * Analysis-only signatures, never loaded by the test or installed runtime.
 * @see https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-line/
 * @see https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-success/
 */

declare(strict_types=1);

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Exact external WP-CLI class identity for its documented output signatures.
final class WP_CLI {
	public static function line( string $message = '' ): void {
		unset( $message );
	}
	public static function success( string $message ): void {
		unset( $message );
	}
}

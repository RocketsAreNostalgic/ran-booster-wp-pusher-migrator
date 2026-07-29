<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

/** Minimal runtime-only autoloader. */
final class Autoloader {

	public static function register(): void {
		spl_autoload_register(
			static function ( string $class ): void {
				$prefix = __NAMESPACE__ . '\\';
				if ( ! str_starts_with( $class, $prefix ) ) {
					return;
				}

				$relative = substr( $class, strlen( $prefix ) );
				if ( false === $relative || 1 !== preg_match( '/\A[A-Za-z][A-Za-z0-9\\\\]*\z/D', $relative ) ) {
					return;
				}

				$file = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_file( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}

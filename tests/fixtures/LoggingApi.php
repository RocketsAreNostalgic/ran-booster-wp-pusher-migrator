<?php

declare(strict_types=1);

namespace RAN\AddOn\Logging;

use Throwable;

class LoggingFacade {

	/** @param array<string, mixed> $context */
	public function log( string $message, array $context = array() ): void {
		unset( $message, $context );
	}

	/** @param array<string, mixed> $context */
	public function logException( string $message, Throwable $exception, array $context = array() ): void {
		unset( $message, $exception, $context );
	}
}

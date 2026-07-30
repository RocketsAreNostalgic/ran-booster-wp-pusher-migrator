<?php

declare(strict_types=1);

final class WpDieException extends RuntimeException {

	/** @param array<string, mixed> $args */
	public function __construct(
		public readonly string $dieMessage,
		public readonly string $dieTitle,
		public readonly array $args
	) {
		parent::__construct( $dieMessage );
	}
}

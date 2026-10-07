<?php

declare(strict_types=1);

final class Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception extends RuntimeException {

	/** @param array<string, mixed> $args */
	public function __construct(
		public readonly string $die_message,
		public readonly string $die_title,
		public readonly array $args
	) {
		parent::__construct( $die_message );
	}
}

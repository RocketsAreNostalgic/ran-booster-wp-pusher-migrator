<?php

declare(strict_types=1);

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

require_once __DIR__ . '/fixtures/PortabilityApi.php';
require_once dirname( __DIR__ ) . '/src/Autoloader.php';
\RAN\BoosterWpPusherMigrator\Autoloader::register();

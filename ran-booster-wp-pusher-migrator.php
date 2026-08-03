<?php
/**
 * Plugin Name: RAN Booster WP Pusher Migrator
 * Plugin URI: https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator
 * Description: Migrates retained WP Pusher 3.0.13 package ownership into RAN Booster.
 * x-release-please-start-version
 * Version: 0.1.0-beta.3
 * x-release-please-end
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Requires Plugins: ran-booster
 * Tested up to: 7.0
 * Author: Rockets Are Nostalgic
 * License: GPL-2.0-or-later
 * Text Domain: ran-booster-wp-pusher-migrator
 * Update URI: https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';

\RAN\BoosterWpPusherMigrator\Autoloader::register();
\RAN\BoosterWpPusherMigrator\Plugin::register();

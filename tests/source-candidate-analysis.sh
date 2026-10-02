#!/usr/bin/env bash
# An explicit alternate exact-source audit; canonical composer check uses the manifest source tuple.
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"
export RAN_MIGRATOR_SOURCE_CORE=${1:?canonical Core source path required}
export RAN_MIGRATOR_SOURCE_CORE_SHA=${2:?exact Core source commit required}
migrator_sha=${3:?exact Migrator source commit required}
php -r 'require "tests/source-candidate-bootstrap.php"; \RAN\BoosterWpPusherMigrator\SourceCandidate\verify_source(getcwd(), $argv[1], ["src/", "views/", "index.php", "ran-booster-wp-pusher-migrator.php", "phpstan.neon.dist", "composer.lock"]);' "$migrator_sha"
configuration=$(mktemp "$project_root/.source-candidate-XXXXXX.neon")
trap 'rm -f "$configuration"' EXIT
php -r '
$config = file_get_contents("phpstan.neon.dist");
$core = getenv("RAN_MIGRATOR_SOURCE_CORE");
if (strpbrk($core, "\r\n\t\"\\") !== false) { throw new RuntimeException("Unsupported Core path."); }
foreach ([
 "vendor/ran-source-core/source/RAN" => "\"" . $core . "/RAN\"",
 "tests/phpstan-bootstrap.php" => "tests/source-candidate-bootstrap.php",
 "vendor/ran-phpstan-cache" => "vendor/ran-source-candidate-cache",
] as $from => $to) {
 if (substr_count($config, $from) !== 1) { throw new RuntimeException("Source analysis configuration drift."); }
 $config = str_replace($from, $to, $config);
}
file_put_contents($argv[1], $config);
' "$configuration"
php vendor/bin/phpstan analyze --configuration="$configuration" --memory-limit=512M --no-progress
printf 'SOURCE-ONLY analysis passed for Core %s; released-host certification remains separate.\n' "$RAN_MIGRATOR_SOURCE_CORE_SHA"

#!/usr/bin/env bash
# Separate release gate: immutable tagged Core must support the current production surface.
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"
bash scripts/prepare-certified-analysis-core.sh
configuration=$(mktemp "$project_root/.certified-core-XXXXXX.neon")
trap 'rm -f "$configuration"' EXIT
php -r '
$config = file_get_contents("phpstan.neon.dist");
foreach ([
 "vendor/ran-source-core/source/RAN" => "vendor/ran-certified-core/source/RAN",
 "tests/phpstan-bootstrap.php" => "tests/certified-core-bootstrap.php",
 "vendor/ran-phpstan-cache" => "vendor/ran-certified-phpstan-cache",
] as $from => $to) {
 if (substr_count($config, $from) !== 1) { throw new RuntimeException("Certified analysis configuration drift."); }
 $config = str_replace($from, $to, $config);
}
file_put_contents($argv[1], $config);
' "$configuration"
php vendor/bin/phpstan analyze --configuration="$configuration" --memory-limit=512M --no-progress

#!/usr/bin/env bash
# Verify the real pinned Core facade/DTO behavior; no PHPUnit Core doubles.
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"
export RAN_MIGRATOR_SOURCE_CORE="$(realpath vendor/ran-source-core/source)"
export RAN_MIGRATOR_SOURCE_CORE_SHA="$(php scripts/core-source.php composer.json)"
# Verify every consumed manifest and source blob before Composer reads the checkout.
php tests/source-candidate-bootstrap.php
# Keep dependency artifacts outside the byte-verified source checkout.
export RAN_MIGRATOR_SOURCE_CORE_VENDOR="$project_root/vendor/ran-source-core/dependencies"
COMPOSER_VENDOR_DIR="$RAN_MIGRATOR_SOURCE_CORE_VENDOR" composer --working-dir="$RAN_MIGRATOR_SOURCE_CORE" install --no-dev --no-scripts --no-plugins --no-interaction --prefer-dist --no-progress
php tests/source-candidate-behaviour.php "$project_root" candidate "$(git rev-parse HEAD)"

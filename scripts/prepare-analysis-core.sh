#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"

# Validation precedes using the manifest's exact tag as a Git argument.
php scripts/core-certification.php read composer.json >/dev/null
core_tag="$(php -r '$manifest = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR); echo $manifest["extra"]["ran-booster-core-certification"]["tag"];')"
core_path='vendor/ran-certified-core/source'
if [[ ! -e "$core_path" ]]; then
	mkdir -p "$(dirname "$core_path")"
	git clone --depth 1 --branch "$core_tag" -- \
		https://github.com/RocketsAreNostalgic/ran-booster.git "$core_path"
fi

# Existing caches are verified, never silently repinned or overwritten.
php tests/phpstan-bootstrap.php

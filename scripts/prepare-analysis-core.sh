#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"

# The source tuple is separate from the immutable released-host certification.
core_commit="$(php scripts/core-source.php composer.json)"
core_path='vendor/ran-source-core/source'
if [[ ! -e "$core_path" ]]; then
	mkdir -p "$core_path"
	git -C "$core_path" init --quiet
	git -C "$core_path" remote add origin https://github.com/RocketsAreNostalgic/ran-booster.git
	git -C "$core_path" fetch --depth 1 origin "$core_commit"
	git -C "$core_path" checkout --detach "$core_commit"
fi
# Existing caches must match; never silently reset or substitute another revision.
php tests/phpstan-bootstrap.php

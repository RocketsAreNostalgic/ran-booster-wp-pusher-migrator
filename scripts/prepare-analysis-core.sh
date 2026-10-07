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

# Prepare the immutable historical receiver once; ordinary checks reuse it offline.
historical_commit=862396ec07594a4dada0991f66446f30346a7d44
historical_path='vendor/ran-historical-migrator/source'
if [[ ! -e "$historical_path" ]]; then
	mkdir -p "$historical_path"
	git -C "$historical_path" init --quiet
	git -C "$historical_path" fetch --quiet --depth 1 https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator.git "$historical_commit"
	git -C "$historical_path" checkout --quiet --detach "$historical_commit"
fi
[[ "$(git --no-replace-objects -C "$historical_path" rev-parse HEAD)" == "$historical_commit" ]] || { printf 'Historical Migrator cache identity differs.\n' >&2; exit 1; }
for file in WpPusherSource WpPusherPackage; do
	[[ -f "$historical_path/src/$file.php" && ! -L "$historical_path/src/$file.php" ]] || { printf 'Historical Migrator cache bytes differ.\n' >&2; exit 1; }
	[[ "$(git hash-object --no-filters "$historical_path/src/$file.php")" == "$(git --no-replace-objects -C "$historical_path" rev-parse "$historical_commit:src/$file.php")" ]] || { printf 'Historical Migrator cache bytes differ.\n' >&2; exit 1; }
done

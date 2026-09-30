#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
git -C "$project_root" archive HEAD | tar -x -C "$fixture"
cp "$project_root/scripts/check-analysis-coverage.php" "$fixture/scripts/"
ln -s "$project_root/vendor" "$fixture/vendor"
cd "$fixture"
git init -q
git config user.name 'Coverage fixture'
git config user.email 'coverage@example.invalid'
git add .
git commit -qm 'test: baseline package'
archive="dist/ran-booster-wp-pusher-migrator-$(php -r 'echo json_decode(file_get_contents("package.json"), true)["version"];').zip"

check() {
	php scripts/check-analysis-coverage.php "$archive"
}
reject() {
	if check > "$fixture/result.log" 2>&1; then
		printf 'Coverage incorrectly accepted %s.\n' "$1" >&2
		exit 1
	fi
	grep -Fq "$2" "$fixture/result.log"
}

bash scripts/build-release.sh "$(git rev-parse HEAD)" >/dev/null
check

# Build a real allowlisted ZIP with a new shipped PHP file outside analysis roots.
printf '<?php\n// Coverage fixture.\n' > assets/uncovered.php
git add assets/uncovered.php
git commit -qm 'test: newly shipped uncovered PHP'
bash scripts/build-release.sh "$(git rev-parse HEAD)" >/dev/null
reject 'new shipped PHP' 'outside direct PHPStan selection: assets/uncovered.php'

# scanDirectories supplies symbols but must not count as direct analysis.
printf 'parameters:\n\tscanDirectories:\n\t\t- assets\n' > coverage-import.neon
sed -i '/^includes:/a\	- coverage-import.neon' phpstan.neon.dist
reject 'scan-only PHP' 'outside direct PHPStan selection: assets/uncovered.php'

# Imported paths are supported; exclusions use the locked engine's semantics.
printf 'parameters:\n\tpaths:\n\t\t- assets\n' > coverage-import.neon
check
for exclusion in analyse analyseAndScan; do
	printf 'parameters:\n\tpaths:\n\t\t- assets\n\texcludePaths:\n\t\t%s:\n\t\t\t- assets/*.php\n' "$exclusion" > coverage-import.neon
	reject "imported $exclusion exclusion" 'outside direct PHPStan selection: assets/uncovered.php'
done
printf 'parameters:\n\tpaths:\n\t\t- assets\n\texcludePaths:\n\t\t- assets/*.php\n' > coverage-import.neon
reject 'legacy exclusion form' 'outside direct PHPStan selection: assets/uncovered.php'

printf 'parameters:\n\tpaths:\n\t\t- assets\n\tstubFiles:\n\t\t- assets/uncovered.php\n' > coverage-import.neon
reject 'stub-only PHP' 'outside direct PHPStan selection: assets/uncovered.php'

printf 'parameters:\n\tpaths:\n\t\t- assets\n\tfileExtensions!:\n\t\t- inc\n' > coverage-import.neon
reject 'extension filter' 'outside direct PHPStan selection:'
printf 'parameters:\n\tpaths:\n\t\t- assets\n' > coverage-import.neon
check
printf '// Different local bytes.\n' >> assets/uncovered.php
reject 'substituted source bytes' 'Shipped PHP differs from selected source: assets/uncovered.php'
git restore assets/uncovered.php
check
printf 'Analysis coverage contract: shipped drift, imports, exclusions, stubs, extensions and byte binding passed.\n'

#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
git -C "$project_root" archive HEAD | tar -x -C "$fixture"
cp "$project_root/scripts/check-analysis-coverage.php" "$fixture/scripts/"
cp "$project_root/phpstan.neon.dist" "$project_root/composer.json" "$fixture/"
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

# The public archive command may not silently fall back to source-only coverage.
archive_arguments_rejected() {
	if composer --no-interaction --no-plugins analysis:coverage -- "$@" > "$fixture/arguments.log" 2>&1; then
		printf 'Archive coverage accepted missing or excess archive arguments.\n' >&2
		exit 1
	fi
	grep -Fq 'exactly one archive required in archive mode' "$fixture/arguments.log"
}
archive_arguments_rejected
archive_arguments_rejected ''
archive_arguments_rejected "$fixture/missing.zip"
archive_arguments_rejected "$archive" "$archive"
composer --no-interaction --no-plugins analysis:coverage -- "$archive"
composer --no-interaction --no-plugins analysis:coverage -- --source
composer --no-interaction --no-plugins analysis:coverage -- --development
printf '' > "$fixture/empty.zip"
if composer --no-interaction --no-plugins analysis:coverage -- "$fixture/empty.zip" > "$fixture/arguments.log" 2>&1; then exit 1; fi
grep -Fq 'Cannot open the finished runtime ZIP.' "$fixture/arguments.log"

# Maintained sources cannot silently escape before packaging admits them.
cp phpstan.neon.dist coverage-baseline.neon
mkdir -p new-product/contracts src/tests
for path in root-contract.php new-product/contracts/split.php; do
	printf '<?php\n// Unpackaged production fixture.\n' > "$path"
	reject 'unpackaged production path' 'Effective PHPStan selection differs'
	printf 'parameters:\n\tpaths:\n\t\t- %s\n' "$path" > coverage-import.neon
	sed -i '/^includes:/a\	- coverage-import.neon' phpstan.neon.dist
	check
	cp coverage-baseline.neon phpstan.neon.dist
	rm "$path" coverage-import.neon
done
printf '<?php\n' > src/tests/runtime-contract.php
check
rm src/tests/runtime-contract.php
for path in NewContract.PHP contract-tool contract.inc; do
	printf '#!/usr/bin/env php\n<?PHP\n' > "$path"
	if [[ "$path" == *.PHP ]]; then
		reject 'uppercase extension' 'Unsupported PHP extension'
	else
		reject 'unsupported PHP spelling' 'Nonstandard-extension PHP'
	fi
	rm "$path"
done
for header in '<?PHP' '<?='; do
	for path in contract-tool contract.inc; do
		printf '%s\n' "$header" > "$path"
		reject 'nonstandard extension' 'Nonstandard-extension PHP'
		rm "$path"
	done
done
sed -i '/- views$/d' phpstan.neon.dist
reject 'shrunken selection' 'Effective PHPStan selection differs'
cp coverage-baseline.neon phpstan.neon.dist
sed -i 's/analyseAndScan:/analyse:/' phpstan.neon.dist
reject 'development scan leakage policy' 'Review maintained analysis scope'
cp coverage-baseline.neon phpstan.neon.dist

# Build a real allowlisted ZIP with a new shipped PHP file outside analysis roots.
printf '<?php\n// Coverage fixture.\n' > assets/uncovered.php
git add assets/uncovered.php
git commit -qm 'test: newly shipped uncovered PHP'
bash scripts/build-release.sh "$(git rev-parse HEAD)" >/dev/null
reject 'new shipped PHP' 'Effective PHPStan selection differs'

# scanDirectories supplies symbols but must not count as direct analysis.
printf 'parameters:\n\tscanDirectories:\n\t\t- assets\n' > coverage-import.neon
sed -i '/^includes:/a\	- coverage-import.neon' phpstan.neon.dist
reject 'scan-only PHP' 'Effective PHPStan selection differs'

# Imported paths are supported; exclusions use the locked engine's semantics.
printf 'parameters:\n\tpaths:\n\t\t- assets\n' > coverage-import.neon
check
for exclusion in analyse analyseAndScan; do
	printf 'parameters:\n\tpaths:\n\t\t- assets\n\texcludePaths:\n\t\t%s:\n\t\t\t- assets/*.php\n' "$exclusion" > coverage-import.neon
	reject "imported $exclusion exclusion" 'Effective PHPStan selection differs'
done
printf 'parameters:\n\tpaths:\n\t\t- assets\n\texcludePaths:\n\t\t- assets/*.php\n' > coverage-import.neon
reject 'legacy exclusion form' 'Effective PHPStan selection differs'

printf 'parameters:\n\tpaths:\n\t\t- assets\n\tstubFiles:\n\t\t- assets/uncovered.php\n' > coverage-import.neon
reject 'stub-only PHP' 'Effective PHPStan selection differs'

printf 'parameters:\n\tpaths:\n\t\t- assets\n\tfileExtensions!:\n\t\t- inc\n' > coverage-import.neon
reject 'extension filter' 'Effective PHPStan selection differs'
printf 'parameters:\n\tpaths:\n\t\t- assets\n' > coverage-import.neon
check
printf '// Different local bytes.\n' >> assets/uncovered.php
reject 'substituted source bytes' 'Shipped PHP differs from selected source: assets/uncovered.php'
git restore assets/uncovered.php
check
printf 'Analysis coverage contract: shipped drift, imports, exclusions, stubs, extensions and byte binding passed.\n'

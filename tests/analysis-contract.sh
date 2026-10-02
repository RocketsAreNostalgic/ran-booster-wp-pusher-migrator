#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT

mkdir -p "$fixture/scripts" "$fixture/tests"
cp -R src views "$fixture/"
cp composer.json phpstan.neon.dist index.php ran-booster-wp-pusher-migrator.php "$fixture/"
cp scripts/core-certification.php scripts/core-source.php scripts/prepare-analysis-core.sh "$fixture/scripts/"
cp tests/phpstan-bootstrap.php "$fixture/tests/"
ln -s "$project_root/vendor" "$fixture/vendor"

analyze() {
	composer --no-interaction --no-plugins --working-dir="$fixture" analyze -- --no-progress --error-format=json
}

analyze > "$fixture/result.json"

# Prove recursive src/view selection, both root files, return checks and real Core symbols.
for path in src/AnalysisNegative.php views/analysis-negative.php index.php ran-booster-wp-pusher-migrator.php; do
	if [[ -f "$fixture/$path" ]]; then
		cp "$fixture/$path" "$fixture/original.php"
	else
		printf '<?php\n' > "$fixture/$path"
	fi
	cat >> "$fixture/$path" <<'PHP'

function ran_booster_wp_pusher_migrator_analysis_negative( \RAN\AddOn\Portability\PortabilityFacade $facade ): int {
	$facade->ran_missing_analysis_method();
	return 'not an integer';
}
PHP
	if analyze > "$fixture/result.json" 2> "$fixture/error.log"; then
		printf 'Analysis accepted negative fixture %s.\n' "$path" >&2
		exit 1
	fi
	php -r '
		$result = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
		$messages = $result["files"][$argv[2]]["messages"] ?? array();
		$identifiers = array_column($messages, "identifier");
		if (0 !== $result["totals"]["errors"]
			|| !in_array("return.type", $identifiers, true)
			|| !in_array("method.notFound", $identifiers, true)) {
			fwrite(STDERR, "Negative fixture did not report both the return and certified Core method errors.\n");
			exit(1);
		}
	' "$fixture/result.json" "$fixture/$path"
	if [[ -f "$fixture/original.php" ]]; then
		mv "$fixture/original.php" "$fixture/$path"
	else
		rm "$fixture/$path"
	fi
done

analyze > "$fixture/result.json"

# Verify a disposable Core cache fails closed on altered bytes or certification.
guard="$fixture/core-guard"
mkdir -p "$guard/scripts" "$guard/tests" "$guard/vendor/ran-source-core"
cp composer.json "$guard/"
cp scripts/core-certification.php scripts/core-source.php "$guard/scripts/"
cp tests/phpstan-bootstrap.php "$guard/tests/"
git clone --quiet --shared -- "$project_root/vendor/ran-source-core/source" "$guard/vendor/ran-source-core/source"
php "$guard/tests/phpstan-bootstrap.php"
printf '\n# Analysis negative control\n' >> "$guard/vendor/ran-source-core/source/.gitignore"
if php "$guard/tests/phpstan-bootstrap.php" > "$fixture/guard.log" 2>&1; then
	printf 'Analysis accepted altered Core source.\n' >&2
	exit 1
fi
grep -Fq 'Analysis requires unmodified pinned Core source.' "$fixture/guard.log"
git -C "$guard/vendor/ran-source-core/source" restore -- .gitignore
printf 'RAN/AnalysisIgnored.php\n' >> "$guard/vendor/ran-source-core/source/.git/info/exclude"
printf '<?php\nclass RanIgnoredAnalysisDeclaration {}\n' > "$guard/vendor/ran-source-core/source/RAN/AnalysisIgnored.php"
test -z "$(git -C "$guard/vendor/ran-source-core/source" status --porcelain --untracked-files=all)"
if php "$guard/tests/phpstan-bootstrap.php" > "$fixture/guard.log" 2>&1; then
	printf 'Analysis accepted ignored Core declarations.\n' >&2
	exit 1
fi
grep -Fq 'Analysis requires unmodified pinned Core source.' "$fixture/guard.log"
rm "$guard/vendor/ran-source-core/source/RAN/AnalysisIgnored.php"

# Git status must not certify bytes hidden by assume-unchanged or sparse-checkout flags.
core_cache="$guard/vendor/ran-source-core/source"
core_file="$(git -C "$core_cache" ls-files 'RAN/*.php' | sed -n '1p')"
test -n "$core_file"
git -C "$core_cache" update-index --assume-unchanged -- "$core_file"
printf '\n// Hidden altered declaration\n' >> "$core_cache/$core_file"
test -z "$(git -C "$core_cache" status --porcelain --untracked-files=all --ignored)"
if php "$guard/tests/phpstan-bootstrap.php" > "$fixture/guard.log" 2>&1; then
	printf 'Analysis accepted assume-unchanged Core bytes.\n' >&2
	exit 1
fi
grep -Fq 'Analysis requires unmodified pinned Core source.' "$fixture/guard.log"
git -C "$core_cache" update-index --no-assume-unchanged -- "$core_file"
git -C "$core_cache" restore -- "$core_file"
git -C "$core_cache" update-index --skip-worktree -- "$core_file"
rm "$core_cache/$core_file"
test -z "$(git -C "$core_cache" status --porcelain --untracked-files=all --ignored)"
if php "$guard/tests/phpstan-bootstrap.php" > "$fixture/guard.log" 2>&1; then
	printf 'Analysis accepted missing skip-worktree Core source.\n' >&2
	exit 1
fi
grep -Fq 'Analysis requires unmodified pinned Core source.' "$fixture/guard.log"
git -C "$core_cache" update-index --no-skip-worktree -- "$core_file"
git -C "$core_cache" restore -- "$core_file"
php "$guard/tests/phpstan-bootstrap.php"

# Replacement refs must not substitute a different tree behind the certified commit.
certified_head="$(git --no-replace-objects -C "$core_cache" rev-parse HEAD)"
printf '\n// Replacement-tree negative control\n' >> "$core_cache/$core_file"
git -C "$core_cache" add -- "$core_file"
replacement_tree="$(git -C "$core_cache" write-tree)"
replacement_commit="$(git -C "$core_cache" -c user.name='Analysis contract' -c user.email='analysis@example.invalid' commit-tree "$replacement_tree" -p "$certified_head" <<< 'Analysis replacement negative control')"
git -C "$core_cache" replace "$certified_head" "$replacement_commit"
git -C "$core_cache" reset --hard HEAD >/dev/null
test "$certified_head" = "$(git -C "$core_cache" rev-parse HEAD)"
test -z "$(git -C "$core_cache" status --porcelain --untracked-files=all --ignored)"
if php "$guard/tests/phpstan-bootstrap.php" > "$fixture/guard.log" 2>&1; then
	printf 'Analysis accepted a replacement Core tree.\n' >&2
	exit 1
fi
grep -Fq 'Analysis requires unmodified pinned Core source.' "$fixture/guard.log"
git -C "$core_cache" replace -d "$certified_head" >/dev/null
git -C "$core_cache" reset --hard "$certified_head" >/dev/null
php "$guard/tests/phpstan-bootstrap.php"

php -r '
	$path = $argv[1];
	$manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	$manifest["extra"]["ran-booster-core-source"]["commit"] = str_repeat("0", 40);
	file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
' "$guard/composer.json"
if php "$guard/tests/phpstan-bootstrap.php" > "$fixture/guard.log" 2>&1; then
	printf 'Analysis accepted a mismatched Core certification.\n' >&2
	exit 1
fi
grep -Fq 'Checked-out Core does not match the pinned source commit and tree.' "$fixture/guard.log"
printf 'Analysis contract passed: clean source, four selected-path negatives, certified Core method/return checks, altered/ignored/index-hidden/replacement-tree/mismatched Core refusals.\n'

#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT

mkdir -p "$fixture/scripts" "$fixture/tests" "$fixture/new-product/contracts" "$fixture/src/tests"
cp -R src views "$fixture/"
cp composer.json phpstan.neon.dist index.php ran-booster-wp-pusher-migrator.php "$fixture/"
cp scripts/core-certification.php scripts/core-source.php scripts/prepare-analysis-core.sh scripts/check-analysis-coverage.php "$fixture/scripts/"
cp tests/phpstan-bootstrap.php "$fixture/tests/"
ln -s "$project_root/vendor" "$fixture/vendor"

analyze() {
	composer --no-interaction --no-plugins --working-dir="$fixture" analyze:production -- --no-progress --error-format=json
}

analyze > "$fixture/result.json"
if php -d register_argc_argv=0 "$fixture/scripts/check-analysis-coverage.php" --source > "$fixture/arguments.log" 2>&1; then exit 1; fi
grep -q 'Analysis coverage requires CLI argument registration.' "$fixture/arguments.log"
if grep -q 'PHP Warning' "$fixture/arguments.log"; then exit 1; fi
sed -i 's/level: 8/level: 7/' "$fixture/phpstan.neon.dist"
if analyze > "$fixture/level.log" 2>&1; then exit 1; fi
grep -q 'Review maintained analysis scope' "$fixture/level.log"
cp phpstan.neon.dist "$fixture/phpstan.neon.dist"

# The Level 8 gate must diagnose nullable arguments, beyond Level 7 cleanliness.
printf '<?php\nfunction ran_booster_wp_pusher_migrator_nullable_product(?string $value): string { return strtolower($value); }\n' > "$fixture/src/nullable-product.php"
if analyze > "$fixture/nullable-product.json" 2>&1; then exit 1; fi
grep -q 'argument.type' "$fixture/nullable-product.json"
(cd "$fixture" && php vendor/bin/phpstan analyze -c phpstan.neon.dist --level=7 --no-progress --error-format=json) > "$fixture/nullable-product-seven.json"
rm "$fixture/src/nullable-product.php"

# Reflection retains native parameter enforcement for the existing locked CLI call.
cp "$fixture/scripts/check-analysis-coverage.php" "$fixture/original-coverage.php"
sed -i "s/\$ran_booster_wp_pusher_migrator_output, array(), '512M'/\$ran_booster_wp_pusher_migrator_output, 'invalid paths', '512M'/" "$fixture/scripts/check-analysis-coverage.php"
if php "$fixture/scripts/check-analysis-coverage.php" --source > "$fixture/wrong-discovery-argument.log" 2>&1; then exit 1; fi
grep -q 'CommandHelper::begin' "$fixture/wrong-discovery-argument.log"
grep -q 'Argument #3' "$fixture/wrong-discovery-argument.log"
grep -q 'must be of type array' "$fixture/wrong-discovery-argument.log"
cp "$fixture/original-coverage.php" "$fixture/scripts/check-analysis-coverage.php"
rm "$fixture/original-coverage.php"

# Unknown template suffixes are executable candidates, not a named inclusion list.
for path in src/coverage-template.phtml src/coverage-command views/coverage-template.tpl src/coverage-template.custom; do
	for shape in tag html bom-long-echo; do
		php -r '$source = $argv[2] === "tag" ? "<?php function ran_migrator_template(): int { return \"invalid\"; }" : ($argv[2] === "html" ? "<main>template</main><?php function ran_migrator_template(): int { return \"invalid\"; }" : "\xEF\xBB\xBF<main>" . str_repeat("x",8192) . "</main><?= ran_missing_template(); ?>");file_put_contents($argv[1],$source);' "$fixture/$path" "$shape"
		if analyze > "$fixture/template.log" 2>&1; then printf 'Template escaped: %s/%s\n' "$path" "$shape" >&2; exit 1; fi
		grep -q 'Nonstandard-extension PHP needs an explicit reviewed analysis decision' "$fixture/template.log"
		rm "$fixture/$path"
	done
done
printf '# Example\n<main><?php example(); ?></main>\n' > "$fixture/coverage-example.md"
printf '{"example":"<main><?php example(); ?></main>"}\n' > "$fixture/coverage-example.json"
analyze > "$fixture/inert.json"
rm "$fixture/coverage-example.md" "$fixture/coverage-example.json"

# Preserve the accepted two-count POST exception while rejecting imported additions.
printf '<?php\nfunction ran_booster_wp_pusher_migrator_suppression_probe(): int { return "invalid"; }\n' > "$fixture/src/CoverageSuppressionProbe.php"
if (cd "$fixture" && php "$project_root/vendor/bin/phpstan" analyze -c phpstan.neon.dist --no-progress --error-format=json) > "$fixture/suppression-before.json" 2> "$fixture/suppression-before.log"; then exit 1; fi
grep -q 'return.type' "$fixture/suppression-before.json"
cat > "$fixture/coverage-ignore.neon" <<'NEON'
parameters:
	ignoreErrors:
		-
			identifier: return.type
			path: src/CoverageSuppressionProbe.php
			reportUnmatched: false
NEON
sed -i '/^includes:/a\	- coverage-ignore.neon' "$fixture/phpstan.neon.dist"
(cd "$fixture" && php "$project_root/vendor/bin/phpstan" analyze -c phpstan.neon.dist --no-progress --error-format=json) > "$fixture/suppression-after.json" 2> "$fixture/suppression-after.log"
php -r '$report=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($report["totals"]["errors"]!==0||$report["totals"]["file_errors"]!==0){exit(1);}' "$fixture/suppression-after.json"
if analyze > "$fixture/suppression-guard.log" 2>&1; then exit 1; fi
grep -q 'Review effective analysis suppressions' "$fixture/suppression-guard.log"
cp phpstan.neon.dist "$fixture/phpstan.neon.dist"
rm "$fixture/coverage-ignore.neon" "$fixture/src/CoverageSuppressionProbe.php"
for mutation in count path unmatched; do
	case "$mutation" in
		count) sed -i 's/count: 2/count: 3/' "$fixture/phpstan.neon.dist" ;;
		path) sed -i 's#path: src/MigrationRequestController.php#path: src/*#' "$fixture/phpstan.neon.dist" ;;
		unmatched) printf '\treportUnmatchedIgnoredErrors: false\n' >> "$fixture/phpstan.neon.dist" ;;
	esac
	if analyze > "$fixture/suppression-guard.log" 2>&1; then exit 1; fi
	grep -q 'Review effective analysis suppressions' "$fixture/suppression-guard.log"
	cp phpstan.neon.dist "$fixture/phpstan.neon.dist"
done
analyze > "$fixture/restored-suppressions.json"
printf 'Template and suppression controls passed: unknown bodies rejected; inert examples and exact two-count exception retained; imported suppression and scope changes rejected.\n'

# Prove recursive src/view selection, both root files, return checks and real Core symbols.
for path in src/AnalysisNegative.php views/analysis-negative.php index.php ran-booster-wp-pusher-migrator.php src/tests/runtime.php; do
	if [[ -f "$fixture/$path" ]]; then
		cp "$fixture/$path" "$fixture/scripts/original.php"
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
	if [[ -f "$fixture/scripts/original.php" ]]; then
		mv "$fixture/scripts/original.php" "$fixture/$path"
	else
		rm "$fixture/$path"
	fi
done

analyze > "$fixture/result.json"

# New roots/split classes fail the canonical command until explicitly admitted.
for path in root-contract.php new-product/contracts/split.php; do
	printf '<?php\nran_migrator_missing_contract();\n' > "$fixture/$path"
	if analyze > "$fixture/new-root.log" 2>&1; then exit 1; fi
	grep -q 'Effective PHPStan selection differs' "$fixture/new-root.log"
	sed -i "/^\tpaths:/a\\\t\t- $path" "$fixture/phpstan.neon.dist"
	if analyze > "$fixture/negative.json" 2> "$fixture/negative.log"; then exit 1; fi
	grep -q 'function.notFound' "$fixture/negative.json"
	grep -q 'ran_migrator_missing_contract' "$fixture/negative.json"
	rm "$fixture/$path"
	cp phpstan.neon.dist "$fixture/phpstan.neon.dist"
done
mv "$fixture/src/Autoloader.php" "$fixture/new-product/Autoloader.php"
if analyze > "$fixture/moved.log" 2>&1; then exit 1; fi
grep -q 'Effective PHPStan selection differs' "$fixture/moved.log"
sed -i '/^\tpaths:/a\\		- new-product' "$fixture/phpstan.neon.dist"
analyze > "$fixture/moved.json"
mv "$fixture/new-product/Autoloader.php" "$fixture/src/Autoloader.php"
cp phpstan.neon.dist "$fixture/phpstan.neon.dist"

# Real symbol-scanning controls: a development-only constant cannot satisfy a
# production reference, even if a configuration tries to scan fixture declarations.
printf '<?php\nconst RAN_MIGRATOR_FIXTURE_ONLY = 1;\n' > "$fixture/tests/development.php"
printf '<?php\necho RAN_MIGRATOR_FIXTURE_ONLY;\n' > "$fixture/src/scan-isolation.php"
sed -i '/^\tscanDirectories:/a\\		- tests' "$fixture/phpstan.neon.dist"
direct_analyze() { ( cd "$fixture" && php vendor/bin/phpstan analyze --configuration=phpstan.neon.dist --no-progress --error-format=json ); }
if direct_analyze > "$fixture/isolation.json" 2> "$fixture/isolation.log"; then exit 1; fi
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);foreach($r["files"][$argv[2]]["messages"]??[] as $m){if(($m["identifier"]??"")==="constant.notFound"&&str_contains($m["message"],"RAN_MIGRATOR_FIXTURE_ONLY")){exit(0);}}exit(1);' "$fixture/isolation.json" "$fixture/src/scan-isolation.php"
sed -i 's/analyseAndScan:/analyse:/' "$fixture/phpstan.neon.dist"
direct_analyze > "$fixture/leaked.json"
cp phpstan.neon.dist "$fixture/phpstan.neon.dist"
rm "$fixture/src/scan-isolation.php" "$fixture/tests/development.php"

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
printf 'Analysis contract passed: clean source, five selected-path negatives, new/split/moved include-or-fail and fixture isolation, certified Core method/return checks, altered/ignored/index-hidden/replacement-tree/mismatched Core refusals.\n'

bash tests/source-behaviour-manifest-contract.sh

# All maintained development PHP is selected across its actual fixture worlds.
development_fixture="$fixture/development"
mkdir -p "$development_fixture"
cp -R "$project_root/src" "$project_root/views" "$project_root/tests" "$project_root/scripts" "$development_fixture/"
cp "$project_root/composer.json" "$project_root/phpstan-development.neon.dist" "$project_root/phpstan-real-proofs.neon.dist" "$development_fixture/"
ln -s "$project_root/vendor" "$development_fixture/vendor"
development() { composer --no-interaction --no-plugins --working-dir="$development_fixture" analyze:development -- --no-progress --error-format=json; }
development > "$fixture/development-clean.log"
for path in tests/future-contract.php scripts/future-helper.php tests/installed-candidate/future-proof.php; do
    printf '<?php\nran_migrator_missing_development_contract();\n' > "$development_fixture/$path"
    if development > "$fixture/development-negative.log" 2>&1; then exit 1; fi
    grep -q 'ran_migrator_missing_development_contract' "$fixture/development-negative.log"
    grep -q 'function.notFound' "$fixture/development-negative.log"
    rm "$development_fixture/$path"
done
# Each isolated world must reject its own new nullable source only at Level 8.
for pair in 'phpstan-development.neon.dist:tests/future-nullable.php' 'phpstan-real-proofs.neon.dist:tests/installed-candidate/future-nullable.php'; do
    configuration=${pair%%:*}
    path=${pair#*:}
    printf '<?php\nfunction ran_booster_wp_pusher_migrator_nullable_development(?string $value): string { return strtolower($value); }\n' > "$development_fixture/$path"
    if development > "$fixture/development-nullable.json" 2>&1; then exit 1; fi
    grep -q 'argument.type' "$fixture/development-nullable.json"
    (cd "$development_fixture" && php vendor/bin/phpstan analyze -c "$configuration" --level=7 --no-progress --error-format=json) > "$fixture/development-nullable-seven.json"
    rm "$development_fixture/$path"
done
for configuration in phpstan-development.neon.dist phpstan-real-proofs.neon.dist; do
    sed -i 's/level: 8/level: 7/' "$development_fixture/$configuration"
    if development > "$fixture/development-negative.log" 2>&1; then exit 1; fi
    grep -q 'Review development analysis level' "$fixture/development-negative.log"
    cp "$project_root/$configuration" "$development_fixture/$configuration"
done
# A selected test registered as a stub is omitted by the actual CLI and rejected.
printf '\tstubFiles:\n\t\t- tests/bootstrap.php\n' >> "$development_fixture/phpstan-development.neon.dist"
if development > "$fixture/development-negative.log" 2>&1; then exit 1; fi
grep -q 'Effective PHPStan selection differs' "$fixture/development-negative.log"
cp "$project_root/phpstan-development.neon.dist" "$development_fixture/phpstan-development.neon.dist"
# Exact existing internal-API exceptions must not suppress a new neighboring call.
printf '<?php\nnew PHPStan\\DependencyInjection\\NeonAdapter([]);\n' > "$development_fixture/scripts/future-helper.php"
if development > "$fixture/development-negative.log" 2>&1; then exit 1; fi
grep -q 'phpstanApi.constructor' "$fixture/development-negative.log"
rm "$development_fixture/scripts/future-helper.php"
# The new result-type API allowance cannot cover a neighboring class check.
printf '<?php\nfunction ran_booster_wp_pusher_migrator_future_internal(object $value): bool { return $value instanceof PHPStan\\Command\\InceptionResult; }\n' > "$development_fixture/scripts/future-helper.php"
if development > "$fixture/development-result-api.log" 2>&1; then exit 1; fi
grep -q 'phpstanApi.class' "$fixture/development-result-api.log"
rm "$development_fixture/scripts/future-helper.php"
# The new exact effective-container API annotation leaves the next call diagnosed.
sed -i '/_inception->getContainer();/a\\		$ran_booster_wp_pusher_migrator_inception->getContainer();' "$development_fixture/scripts/check-analysis-coverage.php"
if development > "$fixture/development-container-api.log" 2>&1; then exit 1; fi
grep -q 'phpstanApi.method' "$fixture/development-container-api.log"
cp "$project_root/scripts/check-analysis-coverage.php" "$development_fixture/scripts/check-analysis-coverage.php"
# Both development worlds keep unknown templates and imported suppressions visible.
for path in tests/coverage-template.tpl scripts/coverage-template.custom; do
    printf '<main>template</main><?= ran_missing_development_template(); ?>\n' > "$development_fixture/$path"
    if development > "$fixture/development-template.log" 2>&1; then exit 1; fi
    grep -q 'Nonstandard-extension PHP needs an explicit reviewed analysis decision' "$fixture/development-template.log"
    rm "$development_fixture/$path"
done
printf '#!/usr/bin/env bash\nprintf '\''<main><?php fixture(); ?></main>'\''\n' > "$development_fixture/scripts/coverage-example.sh"
development > "$fixture/bash-example.log" 2>&1
sed -i '1d' "$development_fixture/scripts/coverage-example.sh"
if development > "$fixture/bash-example.log" 2>&1; then exit 1; fi
grep -q 'Nonstandard-extension PHP needs an explicit reviewed analysis decision' "$fixture/bash-example.log"
rm "$development_fixture/scripts/coverage-example.sh"
for configuration in phpstan-development.neon.dist phpstan-real-proofs.neon.dist; do
    printf 'parameters:\n\tignoreErrors:\n\t\t-\n\t\t\tidentifier: return.type\n\t\t\treportUnmatched: false\n' > "$development_fixture/development-ignore.neon"
    sed -i '/^includes:/a\\	- development-ignore.neon' "$development_fixture/$configuration"
    if development > "$fixture/development-ignore.log" 2>&1; then exit 1; fi
    grep -q 'Review effective analysis suppressions' "$fixture/development-ignore.log"
    cp "$project_root/$configuration" "$development_fixture/$configuration"
    rm "$development_fixture/development-ignore.neon"
done
# Each historical-member waiver leaves its immediately preceding statement checked.
for occurrence in 0 1 2 3; do
    php -r '
        $path = $argv[1];
        $occurrence = (int) $argv[2];
        $outside = array(
            "expect( \$ran_booster_wp_pusher_migrator_result->ran_missing_outside_property, \"Outside historical waiver.\" );\n",
            "\t\"outside_negative\" => \$ran_booster_wp_pusher_migrator_review->candidate->ran_missing_outside_method(),\n",
            "\t\"outside_negative\" => \$ran_booster_wp_pusher_migrator_service->ran_missing_outside_method(),\n",
            "\t\"outside_negative\" => \$ran_booster_wp_pusher_migrator_service->ran_missing_outside_method(),\n",
        );
        $index = 0;
        $source = preg_replace_callback("/^[^\n]*@phpstan-ignore[^\n]*\n/m", function ($match) use (&$index, $occurrence, $outside) {
            return ($index++ === $occurrence ? $outside[$occurrence] : "") . $match[0];
        }, file_get_contents($path));
        if (4 !== $index) { exit(1); }
        file_put_contents($path, $source);
    ' "$development_fixture/tests/source-candidate-behaviour.php" "$occurrence"
    if development > "$fixture/development-historical-negative.log" 2>&1; then exit 1; fi
    grep -q 'ran_missing_outside_' "$fixture/development-historical-negative.log"
    if [[ "$occurrence" = 0 ]]; then
        grep -q 'property.notFound' "$fixture/development-historical-negative.log"
    else
        grep -q 'method.notFound' "$fixture/development-historical-negative.log"
    fi
    cp "$project_root/tests/source-candidate-behaviour.php" "$development_fixture/tests/source-candidate-behaviour.php"
done
# The installed proof is deliberately bound to beta.7, not current Migrator.
# Execute its actual stale-cleanup expression against that immutable receiver.
historical="$fixture/historical-migrator"
historical_commit=862396ec07594a4dada0991f66446f30346a7d44
historical_cache="$project_root/vendor/ran-historical-migrator/source"
mkdir -p "$historical"
test "$(git --no-replace-objects -C "$historical_cache" rev-parse HEAD)" = "$historical_commit"
# Prepared setup must use verified cached bytes without any network command.
setup_probe="$fixture/prepared-setup"
mkdir -p "$setup_probe/scripts" "$setup_probe/tests" "$setup_probe/vendor/ran-historical-migrator" "$setup_probe/bin"
cp "$project_root/composer.json" "$setup_probe/"
cp "$project_root/scripts/prepare-analysis-core.sh" "$project_root/scripts/core-source.php" "$project_root/scripts/core-certification.php" "$setup_probe/scripts/"
cp "$project_root/tests/phpstan-bootstrap.php" "$setup_probe/tests/"
ln -s "$project_root/vendor/ran-source-core" "$setup_probe/vendor/ran-source-core"
git clone --quiet --shared "$historical_cache" "$setup_probe/vendor/ran-historical-migrator/source"
actual_git="$(command -v git)"
printf '#!/usr/bin/env bash\nfor arg in "$@"; do case "$arg" in fetch|clone|pull|ls-remote) echo "Unexpected network Git command" >&2; exit 97;; esac; done\nexec %q "$@"\n' "$actual_git" > "$setup_probe/bin/git"
chmod +x "$setup_probe/bin/git"
PATH="$setup_probe/bin:$PATH" bash "$setup_probe/scripts/prepare-analysis-core.sh"
historical_probe="$setup_probe/vendor/ran-historical-migrator/source"
git -C "$historical_probe" update-index --assume-unchanged src/WpPusherSource.php
printf '\n// Hidden historical cache alteration.\n' >> "$historical_probe/src/WpPusherSource.php"
if PATH="$setup_probe/bin:$PATH" bash "$setup_probe/scripts/prepare-analysis-core.sh" > "$fixture/historical-cache-negative.log" 2>&1; then exit 1; fi
grep -Fq 'Historical Migrator cache bytes differ.' "$fixture/historical-cache-negative.log"
for file in WpPusherSource WpPusherPackage; do
    git --no-replace-objects -C "$historical_cache" show "$historical_commit:src/$file.php" > "$historical/$file.php"
done
historical_cleanup() {
    php -r '
        require $argv[1] . "/WpPusherSource.php";
        require $argv[1] . "/WpPusherPackage.php";
        $database = new class {
            public string $prefix = "wp_";
            public int $queries = 0;
            public function prepare(string $query, mixed ...$values): string {
                if ($values[3] !== "old-branch" || !str_contains($query, "`branch` = %s")) {
                    throw new RuntimeException("Historical cleanup lost its exact-row branch predicate.");
                }
                return $query;
            }
            public function query(string $query): int { ++$this->queries; return 0; }
        };
        $ran_booster_wp_pusher_migrator_source = new RAN\BoosterWpPusherMigrator\WpPusherSource($database);
        $ran_booster_wp_pusher_migrator_old = new RAN\BoosterWpPusherMigrator\WpPusherPackage(1, "fixture/plugin.php", "owner/repository", "old-branch", 1, 0, 0, "gh", 0, null);
        $probe = file_get_contents($argv[2]);
        if (1 !== preg_match("/if \( (\\x24ran_booster_wp_pusher_migrator_source->\\w+\( \\x24ran_booster_wp_pusher_migrator_old \)) \) \{/", $probe, $match)) {
            throw new RuntimeException("Historical cleanup expression missing or ambiguous.");
        }
        if (false !== eval("return " . $match[1] . ";") || 1 !== $database->queries) {
            throw new RuntimeException("Historical stale cleanup did not preserve refusal.");
        }
    ' "$historical" "$1"
}
historical_cleanup "$project_root/tests/installed-candidate/migrator-installed-probe.php"
sed 's/->deleteExact(/->delete_exact(/' "$project_root/tests/installed-candidate/migrator-installed-probe.php" > "$fixture/current-spelling-probe.php"
if historical_cleanup "$fixture/current-spelling-probe.php" > "$fixture/historical-negative.log" 2>&1; then exit 1; fi
grep -q 'undefined method.*delete_exact' "$fixture/historical-negative.log"
# The one historical allowance cannot hide an immediately adjacent missing call.
sed -i '/@phpstan-ignore method.notFound/i\	$ran_booster_wp_pusher_migrator_source->ran_missing_outside_historical_cleanup();' "$development_fixture/tests/installed-candidate/migrator-installed-probe.php"
if development > "$fixture/development-installed-negative.log" 2>&1; then exit 1; fi
grep -q 'ran_missing_outside_historical_cleanup' "$fixture/development-installed-negative.log"
grep -q 'method.notFound' "$fixture/development-installed-negative.log"
cp "$project_root/tests/installed-candidate/migrator-installed-probe.php" "$development_fixture/tests/installed-candidate/migrator-installed-probe.php"
printf 'Historical beta.7 cleanup receiver and exact exception boundary passed.\n'

# The real CLI profile must see real Core's method, never the PHPUnit stand-in.
printf '<?php\n/** @return array<string, string|null> */\nfunction ran_booster_wp_pusher_migrator_real_world(\\RAN\\AddOn\\Portability\\PortabilityCandidate $candidate): array { return $candidate->to_array(); }\n' > "$development_fixture/tests/installed-candidate/world-proof.php"
development > "$fixture/development-world.log"
sed -i '/- tests\/fixtures\/PortabilityApi.php$/d' "$development_fixture/phpstan-real-proofs.neon.dist"
printf 'parameters:\n\tpaths:\n\t\t- tests/installed-candidate\n\t\t- tests/source-candidate-behaviour.php\n\t\t- tests/fixtures/PortabilityApi.php\n' > "$development_fixture/world-import.neon"
sed -i '/^includes:/a\\	- world-import.neon' "$development_fixture/phpstan-real-proofs.neon.dist"
if (cd "$development_fixture" && php vendor/bin/phpstan analyze -c phpstan-real-proofs.neon.dist --no-progress --error-format=json) > "$fixture/development-world-negative.log" 2>&1; then exit 1; fi
grep -q 'to_array' "$fixture/development-world-negative.log"
grep -q 'method.notFound' "$fixture/development-world-negative.log"
if development > "$fixture/development-negative.log" 2>&1; then exit 1; fi
grep -q 'Review development analysis level' "$fixture/development-negative.log"
printf 'Development analysis contract passed: future roots, minimum levels, effective stub omission, historical waiver neighbors and actual Core/fixture separation.\n'

#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
work_root=$(mktemp -d "${TMPDIR:-/tmp}/ran-migrator-standards-test.XXXXXX")
trap 'rm -rf "$work_root"' EXIT
fixture="$work_root/source tree"
mkdir "$fixture"

# Copy working-tree bytes, including tracked edits, without dependencies or Git metadata.
git -C "$repo_root" ls-files -z > "$work_root/tracked-files"
while IFS= read -r -d '' path; do
	# Unstaged deletions remain in the index but are absent from the working tree.
	if [[ -e "$repo_root/$path" || -L "$repo_root/$path" ]]; then
		printf '%s\0' "$path"
	fi
done < "$work_root/tracked-files" > "$work_root/files"
tar -C "$repo_root" --null -T "$work_root/files" -cf - | tar -C "$fixture" -xf -
ln -s "$repo_root/vendor" "$fixture/vendor"

run_command() {
	composer --no-interaction --no-plugins --working-dir="$fixture" "$1" > "$work_root/output" 2>&1
}
fail() {
	printf 'standards contract: %s\n' "$*" >&2
	cat "$work_root/output" >&2
	exit 1
}
snapshot() {
	(cd "$fixture" && xargs -0 shasum -a 256 -- < "$work_root/files") > "$1"
}
fix() {
	local status=0
	run_command standards:fix || status=$?
	# PHPCBF uses 1 for successfully fixed errors, 2/3 for incomplete/error outcomes.
	if (( status > 1 )); then
		fail "fixer returned $status"
	fi
}

run_command standards || fail 'clean source does not pass standards'
snapshot "$work_root/before"
for pass in 1 2; do
	fix
	snapshot "$work_root/after"
	cmp -s "$work_root/before" "$work_root/after" || fail "clean fixer pass $pass changed tracked bytes"
done

# Mutate an existing selected root file, not a path supplied as a CLI override.
sed 's/Silence is golden\.$/Silence is golden.  /' "$fixture/index.php" > "$work_root/mutated-index.php"
mv "$work_root/mutated-index.php" "$fixture/index.php"
if run_command standards; then
	fail 'trailing-whitespace fixture escaped the configured checking scope'
fi
fix
run_command standards || fail 'fixed fixture does not pass standards'
snapshot "$work_root/after"
cmp -s "$work_root/before" "$work_root/after" || fail 'fixer did not restore the exact clean source'
fix
snapshot "$work_root/after"
cmp -s "$work_root/before" "$work_root/after" || fail 'second fixture fixer pass changed tracked bytes'

# Completed runtime naming is blocking through the same canonical standards gate.
cp "$fixture/src/WpPusherPackage.php" "$work_root/clean-package.php"
sed 's/function fingerprint(/function fingerprintProbe(/' "$work_root/clean-package.php" > "$fixture/src/WpPusherPackage.php"
if run_command standards; then
	fail 'owned camelCase method escaped runtime naming enforcement'
fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/src/WpPusherPackage.php" >> "$work_root/output" 2>&1 || true
grep -q 'MethodNameInvalid' "$work_root/output" || fail 'method control failed for an unrelated reason'
cp "$work_root/clean-package.php" "$fixture/src/WpPusherPackage.php"
sed 's/public int $id/public int $packageId/' "$work_root/clean-package.php" > "$fixture/src/WpPusherPackage.php"
if run_command standards; then
	fail 'owned promoted camelCase property escaped runtime naming enforcement'
fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/src/WpPusherPackage.php" >> "$work_root/output" 2>&1 || true
grep -q 'NotSnakeCase' "$work_root/output" || fail 'property control failed for an unrelated reason'
cp "$work_root/clean-package.php" "$fixture/src/WpPusherPackage.php"
run_command standards || fail 'restored naming controls do not pass standards'

# Reserved parameter checking must remain active after removing the global waiver.
sed 's/public int $is_private/public int $private/' "$work_root/clean-package.php" > "$fixture/src/WpPusherPackage.php"
if run_command standards; then
	fail 'reserved owned parameter escaped standards enforcement'
fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/src/WpPusherPackage.php" >> "$work_root/output" 2>&1 || true
grep -q 'NoReservedKeywordParameterNames.privateFound' "$work_root/output" || fail 'reserved parameter failed for an unrelated reason'
cp "$work_root/clean-package.php" "$fixture/src/WpPusherPackage.php"
run_command standards || fail 'restored reserved parameter control does not pass'

# Exercise actual naming rules where WPCS skips inherited methods and narrowed CLI style annotations.
cp "$fixture/tests/CandidateFactoryTest.php" "$work_root/clean-test.php"
sed 's/function static_package(/function staticPackageProbe(/' "$work_root/clean-test.php" > "$fixture/tests/CandidateFactoryTest.php"
if run_command standards; then
	fail 'owned inherited helper method escaped naming enforcement'
fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/tests/CandidateFactoryTest.php" >> "$work_root/output" 2>&1 || true
grep -q 'RANOwnedMethods' "$work_root/output" || fail 'helper method failed for an unrelated reason'
cp "$work_root/clean-test.php" "$fixture/tests/CandidateFactoryTest.php"
cp "$fixture/scripts/verify-release.php" "$work_root/clean-verifier.php"
sed 's/$ran_booster_wp_pusher_migrator_archive_name/$ran_booster_wp_pusher_migrator_archiveNameProbe/g' "$work_root/clean-verifier.php" > "$fixture/scripts/verify-release.php"
if run_command standards; then
	fail 'owned CLI variable escaped naming enforcement through a style suppression'
fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/scripts/verify-release.php" >> "$work_root/output" 2>&1 || true
grep -q 'VariableNotSnakeCase' "$work_root/output" || fail 'CLI variable failed for an unrelated reason'
cp "$work_root/clean-verifier.php" "$fixture/scripts/verify-release.php"
run_command standards || fail 'restored helper naming controls do not pass'

# PHPCS accepts these annotation variants. The independent token guard must reject
# each even when the actual shared method sniff is completely suppressed.
for annotation in '// phpcs:disable RANOwnedMethods' '// phpcs:disable RANOwnedMethods.NamingConventions' '// phpcs:ignoreFile' '// PHPCS:IGNOREFILEsuffix' $'/* phpcs:disable\n */' '// PHPCS:DISABLE' '// @codingStandardsIgnoreStart' '// @codingStandardsIgnoreFile' '// @codingStandardsIgnoreLine'; do
	printf '<?php\n%s\nclass NamingGuardProbe { public function badMethod() {} }\n' "$annotation" > "$fixture/tests/NamingGuardProbe.php"
	"$repo_root/vendor/bin/phpcs" --standard=RANOwnedMethods -q "$fixture/tests/NamingGuardProbe.php" > "$work_root/output" 2>&1 || fail 'negative annotation no longer suppresses the actual shared sniff'
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_helper_naming_cannot_be_hidden_by_blanket_suppressions > "$work_root/output" 2>&1; then
		fail 'blanket PHPCS annotation escaped the independent token guard'
	fi
	grep -q 'blanket PHPCS suppression' "$work_root/output" || fail 'blanket annotation failed for an unrelated reason'
done
for directive in 'phpcs:set' '@codingStandardsChangeSetting'; do
	printf '<?php\n// %s WordPress.NamingConventions.PrefixAllGlobals prefixes unowned\nfunction unowned_probe() {}\n' "$directive" > "$fixture/tests/NamingGuardProbe.php"
	"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --sniffs=WordPress.NamingConventions.PrefixAllGlobals -q "$fixture/tests/NamingGuardProbe.php" > "$work_root/output" 2>&1 || fail 'inline property control no longer suppresses the real checker'
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_helper_naming_cannot_be_hidden_by_blanket_suppressions > "$work_root/output" 2>&1; then fail 'inline property change escaped token guard'; fi
	grep -qi 'blanket PHPCS suppression' "$work_root/output" || fail 'inline property control failed for an unrelated reason'
done
rm "$fixture/tests/NamingGuardProbe.php"

# The same token policy covers maintained production and root files.
for relative in src/NamingGuardProbe.php views/naming-guard-probe.php naming-guard-probe.php src/tests/NamingGuardProbe.php; do
	mkdir -p "$(dirname "$fixture/$relative")"
	printf '<?php\n// phpcs:disable RANOwnedMethods\nclass NamingGuardProbe { public function badMethod() {} }\n' > "$fixture/$relative"
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_helper_naming_cannot_be_hidden_by_blanket_suppressions > "$work_root/output" 2>&1; then
		fail "ancestor suppression escaped the maintained-source token guard: $relative"
	fi
	grep -q 'blanket PHPCS suppression' "$work_root/output" || fail 'production annotation failed for an unrelated reason'
	rm "$fixture/$relative"
done

run_command standards || fail 'restored annotation controls do not pass'

# Test/template local variables must not exempt new global declarations.
for relative in tests/PrefixProbe.php views/prefix-probe.php src/PrefixProbe.php src/tests/PrefixProbe.php src/views/PrefixProbe.php prefix-probe.php; do
	mkdir -p "$(dirname "$fixture/$relative")"
	printf '<?php\nfunction unowned_probe() {} class UnownedProbe {} const UNOWNED_PROBE = 1; $local_value = 1;\n' > "$fixture/$relative"
	if run_command standards; then
		fail "unprefixed declaration escaped scope: $relative"
	fi
	"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --sniffs=WordPress.NamingConventions.PrefixAllGlobals --report=full -s "$fixture/$relative" > "$work_root/prefix-output" 2>&1 || true
	for kind in Function Class Constant Variable; do
		grep -q "NonPrefixed${kind}Found" "$work_root/prefix-output" || fail "missing prefix diagnostic for $kind in $relative"
	done
	rm "$fixture/$relative"
done
run_command standards || fail 'restored prefix controls do not pass'

# Existing exception sites must reject the same diagnostic immediately outside.
for relative in scripts/verify-release.php scripts/check-analysis-coverage.php tests/ReleaseArchiveTest.php tests/installed-candidate/migrator-installed-probe.php; do
	cp "$fixture/$relative" "$work_root/exception-clean.php"
	printf '\nfile_get_contents( __FILE__ );\n' >> "$fixture/$relative"
	if run_command standards; then fail "adjacent local-file call escaped narrow exception: $relative"; fi
	"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/$relative" > "$work_root/output" 2>&1 || true
	grep -q 'file_get_contents_file_get_contents' "$work_root/output" || fail 'adjacent local-file control failed for another reason'
	cp "$work_root/exception-clean.php" "$fixture/$relative"
done
for relative in scripts/verify-release.php tests/fixtures/PortabilityApi.php; do
	cp "$fixture/$relative" "$work_root/exception-clean.php"
	printf '\nfunction unrelated_future_function(): void {}\nconst UNRELATED_FUTURE_CONSTANT = 1;\nclass UnrelatedFutureClass {}\n' >> "$fixture/$relative"
	if run_command standards; then fail "adjacent declarations escaped narrow exception: $relative"; fi
	"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/$relative" > "$work_root/output" 2>&1 || true
	if [[ "$relative" == scripts/* ]]; then
		for kind in Function Class Constant; do grep -q "NonPrefixed${kind}Found" "$work_root/output" || fail "missing adjacent $kind diagnostic"; done
	else
		grep -q 'OneObjectStructurePerFile.MultipleFound' "$work_root/output" || fail 'adjacent colocated class escaped its declaration-local allowance'
	fi
	cp "$work_root/exception-clean.php" "$fixture/$relative"
done
cp "$fixture/views/source-card.php" "$work_root/exception-clean.php"
printf '\n<?php $unowned_adjacent_value = 1; ?>\n' >> "$fixture/views/source-card.php"
if run_command standards; then fail 'adjacent template variable escaped caller-bound row exception'; fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/views/source-card.php" > "$work_root/output" 2>&1 || true
grep -q 'NonPrefixedVariableFound' "$work_root/output" || fail 'template control failed for another reason'
cp "$work_root/exception-clean.php" "$fixture/views/source-card.php"

# Exact-diagnostic spans and sniff-wide line ignores are still broad exceptions.
for annotation in '// PHPCS:DISABLE RANOwnedMethods.NamingConventions.ValidMethodName.NotSnakeCase' '// phpcs:ignore RANOwnedMethods.NamingConventions.ValidMethodName -- Broad sniff ignore.'; do
	printf '<?php\n%s\nclass NamingGuardProbe { public function badMethod() {} }\n' "$annotation" > "$fixture/tests/NamingGuardProbe.php"
	"$repo_root/vendor/bin/phpcs" --standard=RANOwnedMethods -q "$fixture/tests/NamingGuardProbe.php" > "$work_root/output" 2>&1 || fail 'narrow-looking broad annotation no longer suppresses real checker'
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_helper_naming_cannot_be_hidden_by_blanket_suppressions > "$work_root/output" 2>&1; then fail 'narrow-looking broad annotation escaped token guard'; fi
	grep -q 'blanket PHPCS suppression' "$work_root/output" || fail 'broad annotation control failed for another reason'
done
rm "$fixture/tests/NamingGuardProbe.php"

# A green standards command cannot establish coverage if its rule is disabled.
cp "$fixture/.phpcs.xml.dist" "$work_root/clean-profile.xml"
for weakening in '<rule ref="WordPress.NamingConventions.PrefixAllGlobals"><severity>0</severity></rule>' '<rule ref="WordPress"><exclude name="WordPress.NamingConventions.PrefixAllGlobals"/></rule>' '<rule ref="Generic.Files.OneObjectStructurePerFile"><exclude-pattern>/tests/</exclude-pattern></rule>' '<arg name="sniffs" value="Generic.Files.LineLength"/>'; do
	WEAKENING="$weakening" php -r '$path=$argv[1]; file_put_contents($path,str_replace("</ruleset>",getenv("WEAKENING")."\n</ruleset>",file_get_contents($argv[2])));' "$fixture/.phpcs.xml.dist" "$work_root/clean-profile.xml"
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_standards_profile_cannot_silently_weaken_coverage > "$work_root/output" 2>&1; then fail 'XML weakening escaped the independent profile guard'; fi
	grep -q 'FAILURES!' "$work_root/output" || fail 'XML weakening control failed for an unrelated reason'
done
cp "$work_root/clean-profile.xml" "$fixture/.phpcs.xml.dist"
# PHPCS accepts conditional attributes on rules/properties/elements. Neither phase
# may silently lose a mandatory rule while the XML still names it.
cat > "$fixture/src/ConditionalGuardProbe.php" <<'PHP'
<?php
namespace RAN\BoosterWpPusherMigrator;

class ConditionalGuardProbe extends \stdClass {
	public function camelCase(): void {}
}
PHP
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/src/ConditionalGuardProbe.php" > "$work_root/output" 2>&1 || true
grep -q 'RANOwnedMethods.NamingConventions.ValidMethodName.NotSnakeCase' "$work_root/output" || fail 'conditional control did not start with the actual owned-method diagnostic'
for attribute in 'phpcbf-only="true"' 'phpcs-only="false"'; do
	CONDITIONAL_ATTRIBUTE="$attribute" php -r '$path=$argv[1]; file_put_contents($path,str_replace("<rule ref=\"RANOwnedMethods\"/>","<rule ref=\"RANOwnedMethods\" ".getenv("CONDITIONAL_ATTRIBUTE")."/>",file_get_contents($argv[2])));' "$fixture/.phpcs.xml.dist" "$work_root/clean-profile.xml"
	"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/src/ConditionalGuardProbe.php" > "$work_root/output" 2>&1 || fail 'conditional control did not pass the actual checker'
	if grep -q 'RANOwnedMethods.NamingConventions.ValidMethodName.NotSnakeCase' "$work_root/output"; then fail 'conditional control no longer hides the real checker diagnostic'; fi
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_standards_profile_cannot_silently_weaken_coverage > "$work_root/output" 2>&1; then fail 'conditional rule escaped the independent profile guard'; fi
	grep -q 'No command-conditional rules' "$work_root/output" || fail 'conditional rule failed for another reason'
done
cp "$work_root/clean-profile.xml" "$fixture/.phpcs.xml.dist"
rm "$fixture/src/ConditionalGuardProbe.php"

run_command standards || fail 'restored exception controls do not pass'

printf 'PASS actual standards/check-fix commands reject and restore the fixture; repeated fixes preserve tracked bytes\n'

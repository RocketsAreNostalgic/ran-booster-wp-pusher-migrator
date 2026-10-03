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
sed 's/$archive_name/$archiveNameProbe/g' "$work_root/clean-verifier.php" > "$fixture/scripts/verify-release.php"
if run_command standards; then
	fail 'owned CLI variable escaped naming enforcement through a style suppression'
fi
"$repo_root/vendor/bin/phpcs" --standard="$fixture/.phpcs.xml.dist" --report=full -s "$fixture/scripts/verify-release.php" >> "$work_root/output" 2>&1 || true
grep -q 'VariableNotSnakeCase' "$work_root/output" || fail 'CLI variable failed for an unrelated reason'
cp "$work_root/clean-verifier.php" "$fixture/scripts/verify-release.php"
run_command standards || fail 'restored helper naming controls do not pass'

# PHPCS accepts these annotation variants. The independent token guard must reject
# each even when the actual shared method sniff is completely suppressed.
for annotation in '// phpcs:ignoreFile' $'/* phpcs:disable\n */' '// PHPCS:DISABLE' '// @codingStandardsIgnoreStart' '// @codingStandardsIgnoreFile' '// @codingStandardsIgnoreLine'; do
	printf '<?php\n%s\nclass NamingGuardProbe { public function badMethod() {} }\n' "$annotation" > "$fixture/tests/NamingGuardProbe.php"
	"$repo_root/vendor/bin/phpcs" --standard=RANOwnedMethods -q "$fixture/tests/NamingGuardProbe.php" > "$work_root/output" 2>&1 || fail 'negative annotation no longer suppresses the actual shared sniff'
	if composer --no-interaction --no-plugins --working-dir="$fixture" test -- --filter test_helper_naming_cannot_be_hidden_by_blanket_suppressions > "$work_root/output" 2>&1; then
		fail 'blanket PHPCS annotation escaped the independent token guard'
	fi
	grep -q 'blanket PHPCS suppression' "$work_root/output" || fail 'blanket annotation failed for an unrelated reason'
done
rm "$fixture/tests/NamingGuardProbe.php"
run_command standards || fail 'restored annotation controls do not pass'

printf 'PASS actual standards/check-fix commands reject and restore the fixture; repeated fixes preserve tracked bytes\n'

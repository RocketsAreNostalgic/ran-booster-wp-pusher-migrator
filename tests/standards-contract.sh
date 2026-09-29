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

printf 'PASS actual standards/check-fix commands reject and restore the fixture; repeated fixes preserve tracked bytes\n'

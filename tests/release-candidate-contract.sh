#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
validator="$repo_root/scripts/validate-release-candidate.sh"
work_root=$(mktemp -d "${TMPDIR:-/tmp}/ran-migrator-release-candidate-test.XXXXXX")
trap 'rm -rf "$work_root"' EXIT

fail() {
	printf 'release candidate contract: %s\n' "$*" >&2
	exit 1
}

for required_tool in git jq; do
	command -v "$required_tool" >/dev/null || fail "required tool is unavailable: $required_tool"
done

seed="$work_root/seed"
mkdir -p "$seed"
git -C "$seed" init --quiet
git -C "$seed" config user.name 'Validator Test'
git -C "$seed" config user.email 'validator@example.invalid'
printf '{".":"1.2.3"}\n' > "$seed/.release-please-manifest.json"
cat > "$seed/CHANGELOG.md" <<'EOF'
# Changelog

## [1.2.3](https://example.invalid/v1.2.3) (2026-01-01)

Accepted history.
EOF
cat > "$seed/package.json" <<'EOF'
{"name":"fixture","version":"1.2.3","private":true}
EOF
cat > "$seed/ran-booster-wp-pusher-migrator.php" <<'EOF'
<?php
/**
 * Plugin Name: Migrator Validator Fixture
 * Version: 1.2.3
 */
EOF
git -C "$seed" add .
git -C "$seed" commit --quiet -m 'chore: seed release fixture'

prepare_valid() {
	local name=$1
	case_dir="$work_root/$name"
	git clone --quiet "$seed" "$case_dir"
	git -C "$case_dir" config user.name 'Validator Test'
	git -C "$case_dir" config user.email 'validator@example.invalid'
	printf '{".":"1.2.4"}\n' > "$case_dir/.release-please-manifest.json"
	jq '.version = "1.2.4"' "$case_dir/package.json" > "$case_dir/package.next.json"
	mv "$case_dir/package.next.json" "$case_dir/package.json"
	sed -i.bak -E 's/Version: 1\.2\.3/Version: 1.2.4/' "$case_dir/ran-booster-wp-pusher-migrator.php"
	rm -f "$case_dir/ran-booster-wp-pusher-migrator.php.bak"
	{
		printf '# Changelog\n\n'
		printf '## [1.2.4](https://example.invalid/v1.2.4) (2026-01-02)\n\nGenerated release.\n\n'
		git -C "$case_dir" show HEAD:CHANGELOG.md | sed -n '3,$p'
	} > "$case_dir/CHANGELOG.md"
	git -C "$case_dir" add .
	git -C "$case_dir" commit --quiet -m 'chore(main): release 1.2.4'
	base_sha=$(git -C "$case_dir" rev-parse HEAD^)
	head_sha=$(git -C "$case_dir" rev-parse HEAD)
}

amend_case() {
	git -C "$case_dir" add -A
	git -C "$case_dir" commit --quiet --amend --no-edit
	head_sha=$(git -C "$case_dir" rev-parse HEAD)
}

expect_invalid() {
	local name=$1
	if (cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha") >/dev/null 2>&1; then
		fail "$name should fail"
	fi
}

prepare_valid valid
(cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha") >/dev/null 	|| fail 'valid generated candidate should pass'

prepare_valid multi-commit
git -C "$case_dir" commit --quiet --allow-empty -m 'chore: unexpected second commit'
head_sha=$(git -C "$case_dir" rev-parse HEAD)
(cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha") >/dev/null \
	|| fail 'content-identical multi-commit candidate should pass'

prepare_valid merge-history
release_tree=$(git -C "$case_dir" rev-parse HEAD^{tree})
side_sha=$(printf 'side history\n' | git -C "$case_dir" commit-tree "$base_sha^{tree}" -p "$base_sha")
head_sha=$(printf 'same release tree\n' | git -C "$case_dir" commit-tree "$release_tree" -p "$base_sha" -p "$side_sha")
(cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha") >/dev/null \
	|| fail 'content-identical two-parent candidate should pass'

prepare_valid divergent-equivalent-history
head_sha=$(printf 'same tree without shared history\n' | git -C "$case_dir" commit-tree HEAD^{tree})
(cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha") >/dev/null \
	|| fail 'final product comparison must not require ancestry'

# The old dispatch used the immediate parent X, hiding runtime changes in B..X.
prepare_valid hidden-runtime-commit
release_tree=$(git -C "$case_dir" rev-parse HEAD^{tree})
git -C "$case_dir" reset --quiet --hard "$base_sha"
printf '<?php // unexpected runtime\n' > "$case_dir/unexpected.php"
git -C "$case_dir" add .
git -C "$case_dir" commit --quiet -m 'unexpected runtime before release'
runtime_sha=$(git -C "$case_dir" rev-parse HEAD)
git -C "$case_dir" checkout "$release_tree" -- .release-please-manifest.json CHANGELOG.md package.json ran-booster-wp-pusher-migrator.php
git -C "$case_dir" commit --quiet -m 'generated release after runtime change'
head_sha=$(git -C "$case_dir" rev-parse HEAD)
(cd "$case_dir" && bash "$validator" "$runtime_sha" "$head_sha") >/dev/null \
	|| fail 'fixture must demonstrate the old immediate-parent blind spot'
expect_invalid hidden-runtime-commit

prepare_valid missing-new-base-content
release_sha=$head_sha
git -C "$case_dir" checkout --quiet --detach "$base_sha"
printf 'new accepted config\n' > "$case_dir/config.txt"
git -C "$case_dir" add .
git -C "$case_dir" commit --quiet -m 'base advances'
base_sha=$(git -C "$case_dir" rev-parse HEAD)
head_sha=$release_sha
expect_invalid missing-new-base-content

prepare_valid extra-file
printf 'unexpected\n' > "$case_dir/unexpected.txt"
amend_case
expect_invalid extra-file

prepare_valid manifest-mismatch
printf '{".":"1.2.5"}\n' > "$case_dir/.release-please-manifest.json"
amend_case
expect_invalid manifest-mismatch

prepare_valid package-non-version-edit
jq '.description = "unexpected"' "$case_dir/package.json" > "$case_dir/package.next.json"
mv "$case_dir/package.next.json" "$case_dir/package.json"
amend_case
expect_invalid package-non-version-edit

prepare_valid plugin-non-version-edit
printf '\n// Unexpected bootstrap edit.\n' >> "$case_dir/ran-booster-wp-pusher-migrator.php"
amend_case
expect_invalid plugin-non-version-edit

prepare_valid changelog-deletion
sed -i.bak '/Accepted history\./d' "$case_dir/CHANGELOG.md"
rm -f "$case_dir/CHANGELOG.md.bak"
amend_case
expect_invalid changelog-deletion

bash "$repo_root/tests/release-candidate-base-contract.sh"
printf 'Product release-candidate contract passed.\n'

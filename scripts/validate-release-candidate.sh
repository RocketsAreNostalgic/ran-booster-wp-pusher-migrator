#!/usr/bin/env bash
set -euo pipefail

fail() {
	printf 'validate-release-candidate: %s\n' "$*" >&2
	exit 1
}

[[ $# -eq 2 ]] || fail 'expected <base-commit> <release-commit>.'
base_commit=$(git rev-parse --verify "$1^{commit}") \
	|| fail 'base commit is unavailable.'
release_commit=$(git rev-parse --verify "$2^{commit}") \
	|| fail 'release commit is unavailable.'

git merge-base --is-ancestor "$base_commit" "$release_commit" \
	|| fail 'release commit does not descend from its pull-request base.'
read -r candidate_sha candidate_parent extra_parent \
	< <(git rev-list --parents -n 1 "$release_commit")
[[ "$candidate_sha" == "$release_commit" \
	&& "$candidate_parent" == "$base_commit" \
	&& -z "$extra_parent" ]] \
	|| fail 'release candidate must be the single generated commit directly above its pull-request base.'

expected_changes=$(printf '%s\n' \
	$'M\t.release-please-manifest.json' \
	$'M\tCHANGELOG.md' \
	$'M\tpackage.json' \
	$'M\tran-booster-wp-pusher-migrator.php')
actual_changes=$(git diff --name-status "$base_commit" "$release_commit" -- | LC_ALL=C sort -k2)
[[ "$actual_changes" == "$expected_changes" ]] \
	|| fail 'release candidate must change exactly the four generated release files.'

manifest_version() {
	git show "$1:.release-please-manifest.json" \
		| jq -er 'select(type == "object" and keys == ["."] and (.["."] | type) == "string") | .["."]'
}

package_version() {
	git show "$1:package.json" \
		| jq -er 'select(type == "object" and (.version | type) == "string") | .version'
}

plugin_version() {
	git show "$1:ran-booster-wp-pusher-migrator.php" \
		| sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p'
}

base_version=$(manifest_version "$base_commit") \
	|| fail 'base manifest has an invalid shape.'
release_version=$(manifest_version "$release_commit") \
	|| fail 'release manifest has an invalid shape.'
[[ "$release_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] \
	|| fail 'release version is not valid semver.'
[[ "$release_version" != "$base_version" ]] \
	|| fail 'release version did not change.'
[[ "$(package_version "$base_commit")" == "$base_version" ]] \
	|| fail 'base package version does not match the base manifest.'
[[ "$(plugin_version "$base_commit")" == "$base_version" ]] \
	|| fail 'base plugin header does not match the base manifest.'
[[ "$(package_version "$release_commit")" == "$release_version" ]] \
	|| fail 'release package version does not match the release manifest.'
[[ "$(plugin_version "$release_commit")" == "$release_version" ]] \
	|| fail 'release plugin header does not match the release manifest.'

diff -u \
	<(git show "$base_commit:package.json" | jq -S 'del(.version)') \
	<(git show "$release_commit:package.json" | jq -S 'del(.version)') \
	>/dev/null \
	|| fail 'package manifest changed beyond the generated version value.'

diff -u \
	<(git show "$base_commit:ran-booster-wp-pusher-migrator.php" \
		| sed -E 's/^([[:space:]]*\*[[:space:]]*Version:).*/\1 __RELEASE_VERSION__/') \
	<(git show "$release_commit:ran-booster-wp-pusher-migrator.php" \
		| sed -E 's/^([[:space:]]*\*[[:space:]]*Version:).*/\1 __RELEASE_VERSION__/') \
	>/dev/null \
	|| fail 'plugin bootstrap changed beyond the generated Version value.'

read -r changelog_additions changelog_deletions changelog_path \
	< <(git diff --numstat "$base_commit" "$release_commit" -- CHANGELOG.md)
[[ "$changelog_path" == 'CHANGELOG.md' \
	&& "$changelog_additions" =~ ^[1-9][0-9]*$ \
	&& "$changelog_deletions" == 0 ]] \
	|| fail 'changelog must preserve all accepted history and add the new release entry.'
release_changelog=$(git show "$release_commit:CHANGELOG.md")
grep -Fq "## [${release_version}](" <<< "$release_changelog" \
	|| fail 'changelog does not contain the proposed release heading.'

release_heading_line=$(grep -nF -m1 "## [${release_version}](" <<< "$release_changelog" | cut -d: -f1)
first_heading_line=$(grep -nE -m1 '^## \[[0-9]+\.[0-9]+\.[0-9]+' <<< "$release_changelog" | cut -d: -f1)
[[ -n "$release_heading_line" && "$release_heading_line" == "$first_heading_line" ]] \
	|| fail 'new release entry must remain the first version section in the changelog.'

printf 'Validated Release Please candidate %s at %s.\n' "$release_version" "$release_commit"

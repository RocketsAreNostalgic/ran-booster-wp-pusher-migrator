#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
validator="$repo_root/scripts/validate-release-candidate.sh"
identity_validator="$repo_root/scripts/validate-release-candidate-identity.sh"
run_selector="$repo_root/scripts/has-trusted-release-candidate-run.sh"
candidate_fetcher="$repo_root/scripts/fetch-release-candidate-ref.sh"
work_root=$(mktemp -d "${TMPDIR:-/tmp}/ran-migrator-release-candidate-test.XXXXXX")
trap 'rm -rf "$work_root"' EXIT

fail() {
	printf 'release candidate contract: %s\n' "$*" >&2
	exit 1
}

for required_tool in git jq; do
	command -v "$required_tool" >/dev/null \
		|| fail "required tool is unavailable: $required_tool"
done

fake_bin="$work_root/fake-bin"
fetch_log="$work_root/fetch.log"
mkdir -p "$fake_bin"
cat > "$fake_bin/git" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
{
	printf '%s\n' "${GIT_CONFIG_COUNT:-}"
	printf '%s\n' "${GIT_CONFIG_KEY_0:-}"
	printf '%s\n' "${GIT_CONFIG_VALUE_0:-}"
	printf '%s\n' "$*"
	printf '%s\n' "${GH_TOKEN:-}"
} > "$FETCH_LOG"
EOF
chmod +x "$fake_bin/git"

test_token='candidate-fetch-token'
GH_TOKEN="$test_token" FETCH_LOG="$fetch_log" PATH="$fake_bin:$PATH" \
	bash "$candidate_fetcher" origin refs/pull/26/head
expected_authorization=$(printf 'x-access-token:%s' "$test_token" | base64 | tr -d '\r\n')
[[ "$(sed -n '1p' "$fetch_log")" == 1 \
	&& "$(sed -n '2p' "$fetch_log")" == http.https://github.com/.extraheader \
	&& "$(sed -n '3p' "$fetch_log")" == "AUTHORIZATION: basic ${expected_authorization}" \
	&& "$(sed -n '4p' "$fetch_log")" == 'fetch --no-tags origin refs/pull/26/head' \
	&& -z "$(sed -n '5p' "$fetch_log")" \
	&& "$(wc -l < "$fetch_log" | tr -d ' ')" == 5 ]] \
	|| fail 'candidate fetch did not use the exact ephemeral Git authentication contract'
rm "$fetch_log"
if GH_TOKEN= FETCH_LOG="$fetch_log" PATH="$fake_bin:$PATH" \
	bash "$candidate_fetcher" origin refs/pull/26/head >/dev/null 2>&1; then
	fail 'candidate fetch accepted an empty GitHub token'
fi
[[ ! -e "$fetch_log" ]] || fail 'candidate fetch invoked Git without a GitHub token'

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
	if ( cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha" ) >/dev/null 2>&1; then
		fail "$name should fail"
	fi
}

prepare_valid valid
( cd "$case_dir" && bash "$validator" "$base_sha" "$head_sha" ) >/dev/null \
	|| fail 'valid candidate should pass'

prepare_valid multi-commit
git -C "$case_dir" commit --quiet --allow-empty -m 'chore: unexpected second commit'
head_sha=$(git -C "$case_dir" rev-parse HEAD)
expect_invalid multi-commit

prepare_valid merge-commit
candidate_tree=$(git -C "$case_dir" rev-parse "${head_sha}^{tree}")
base_tree=$(git -C "$case_dir" rev-parse "${base_sha}^{tree}")
second_parent=$(printf 'unexpected second parent\n' \
	| git -C "$case_dir" commit-tree "$base_tree" -p "$base_sha")
head_sha=$(printf 'merge-shaped release candidate\n' \
	| git -C "$case_dir" commit-tree "$candidate_tree" -p "$base_sha" -p "$second_parent")
expect_invalid merge-commit

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

identity_base=1111111111111111111111111111111111111111
identity_head=2222222222222222222222222222222222222222
identity=$(jq -nc \
	--arg base "$identity_base" \
	--arg head "$identity_head" \
	'{data:{repository:{pullRequest:{commits:{nodes:[{commit:{
		oid:$head,
		parents:{nodes:[{oid:$base}]},
		signature:{isValid:true,state:"VALID",signer:{login:"web-flow"}},
		author:{email:"41898282+github-actions[bot]@users.noreply.github.com",user:{login:"github-actions[bot]"}},
		committer:{email:"noreply@github.com"}
	}}]}}}}}')
bash "$identity_validator" "$identity_base" "$identity_head" <<< "$identity"
for mutation in \
	'.data.repository.pullRequest.commits.nodes[0].commit.signature.isValid = false' \
	'.data.repository.pullRequest.commits.nodes[0].commit.parents.nodes[0].oid = "3333333333333333333333333333333333333333"' \
	'.data.repository.pullRequest.commits.nodes[0].commit.author.user.login = "maintainer"'; do
	if bash "$identity_validator" "$identity_base" "$identity_head" <<< "$(jq "$mutation" <<< "$identity")"; then
		fail "invalid identity was accepted: $mutation"
	fi
done

workflow_id=42
branch=release-please--branches--main--components--ran-booster-wp-pusher-migrator
repository=RocketsAreNostalgic/ran-booster-wp-pusher-migrator
run=$(jq -nc \
	--arg branch "$branch" \
	--arg repository "$repository" \
	--arg sha "$identity_head" \
	--argjson workflow_id "$workflow_id" \
	'{workflow_runs:[{
		workflow_id:$workflow_id,
		path:".github/workflows/quality.yml",
		event:"workflow_dispatch",
		head_branch:$branch,
		head_sha:$sha,
		head_repository:{full_name:$repository},
		repository:{full_name:$repository},
		actor:{login:"github-actions[bot]"},
		triggering_actor:{login:"github-actions[bot]"},
		status:"queued",
		conclusion:null
	}]}')
bash "$run_selector" "$workflow_id" "$branch" "$identity_head" "$repository" <<< "$run"
bash "$run_selector" "$workflow_id" "$branch" "$identity_head" "$repository" \
	<<< "$(jq '.workflow_runs[0].status = "completed" | .workflow_runs[0].conclusion = "success"' <<< "$run")"
for mutation in \
	'.workflow_runs[0].status = "completed" | .workflow_runs[0].conclusion = "failure"' \
	'.workflow_runs[0].event = "pull_request"' \
	'.workflow_runs[0].actor.login = "maintainer"' \
	'.workflow_runs[0].head_repository.full_name = "contributor/fork"'; do
	if bash "$run_selector" "$workflow_id" "$branch" "$identity_head" "$repository" \
		<<< "$(jq "$mutation" <<< "$run")"; then
		fail "untrusted run suppressed dispatch: $mutation"
	fi
done

printf 'Release candidate, identity, and trusted-run contracts passed.\n'

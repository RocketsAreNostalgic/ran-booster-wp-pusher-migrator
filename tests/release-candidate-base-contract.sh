#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
resolver="$repo_root/scripts/release-candidate-base.sh"
work_root=$(mktemp -d "${TMPDIR:-/tmp}/ran-migrator-candidate-base-test.XXXXXX")
trap 'rm -rf "$work_root"' EXIT
fail() { printf 'candidate base contract: %s\n' "$*" >&2; exit 1; }

mkdir "$work_root/bin"
cat > "$work_root/bin/gh" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
[[ "$*" == "api --paginate --slurp repos/${GITHUB_REPOSITORY}/pulls?state=open&base=main&per_page=100" ]]
[[ "${API_FAILURE:-false}" == false ]] || exit 1
cat "$PR_PAGES"
MOCK
chmod +x "$work_root/bin/gh"
export PATH="$work_root/bin:$PATH"
export GITHUB_REPOSITORY='RocketsAreNostalgic/ran-booster-wp-pusher-migrator'
export GITHUB_EVENT_PATH="$work_root/event.json"
export PR_PAGES="$work_root/pages.json"
export RAN_SOURCE_SHA='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
base_sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
branch='release-please--branches--main--components--ran-booster-wp-pusher-migrator'
export GITHUB_REF="refs/heads/$branch"

jq -n --arg repo "$GITHUB_REPOSITORY" --arg branch "$branch" \
	--arg base "$base_sha" --arg head "$RAN_SOURCE_SHA" \
	'{state:"open", user:{login:"github-actions[bot]"},
	base:{ref:"main", sha:$base, repo:{full_name:$repo}},
	head:{ref:$branch, sha:$head, repo:{full_name:$repo}}}' > "$work_root/pr.json"

prepare() {
	jq '{pull_request:.}' "$work_root/pr.json" > "$GITHUB_EVENT_PATH"
	# Unrelated PR on page one; the canonical candidate is on page two.
	jq '[[. | .head.ref = "ordinary"], [.]]' "$work_root/pr.json" > "$PR_PAGES"
}
expect_base() {
	[[ "$(bash "$resolver")" == "$base_sha" ]] || fail "$1 returned the wrong base"
}
expect_invalid() {
	if bash "$resolver" > "$work_root/output" 2>/dev/null; then fail "$1 should fail"; fi
	[[ ! -s "$work_root/output" ]] || fail "$1 leaked a usable base"
}

prepare
export GITHUB_EVENT_NAME=pull_request
# PR events use their immutable snapshot, without needing API access.
export API_FAILURE=true
expect_base 'PR snapshot'
export API_FAILURE=false
export GITHUB_EVENT_NAME=workflow_dispatch
expect_base 'paginated dispatch'
export API_FAILURE=true
expect_invalid 'API failure'
export API_FAILURE=false

for mutation in \
	'.head.sha = "cccccccccccccccccccccccccccccccccccccccc"' \
	'.head.repo.full_name = "other/fork"' \
	'.base.repo.full_name = "other/repository"' \
	'.base.ref = "other"' \
	'.state = "closed"' \
	'.user.login = "someone"' \
	'.base.sha = "main"' \
	'del(.base.sha)'; do
	jq "$mutation" "$work_root/pr.json" > "$work_root/changed.json"
	jq '{pull_request:.}' "$work_root/changed.json" > "$GITHUB_EVENT_PATH"
	jq '[[.]]' "$work_root/changed.json" > "$PR_PAGES"
	export GITHUB_EVENT_NAME=pull_request
	expect_invalid "PR $mutation"
	export GITHUB_EVENT_NAME=workflow_dispatch
	expect_invalid "dispatch $mutation"
done

prepare
printf '[[]]\n' > "$PR_PAGES"
expect_invalid 'missing proposal'
jq '[[., .]]' "$work_root/pr.json" > "$PR_PAGES"
expect_invalid 'ambiguous proposal'
printf 'not JSON\n' > "$PR_PAGES"
expect_invalid 'malformed API response'
prepare
export RAN_SOURCE_SHA=main
expect_invalid 'non-SHA event identity'
export RAN_SOURCE_SHA='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'

export GITHUB_EVENT_NAME=pull_request
jq '{pull_request:(. | .head.ref = "ordinary")}' "$work_root/pr.json" > "$GITHUB_EVENT_PATH"
[[ -z "$(bash "$resolver")" ]] || fail 'ordinary PR should not be release-validated'
printf '{}\n' > "$GITHUB_EVENT_PATH"
expect_invalid 'malformed PR event'
export GITHUB_EVENT_NAME=push
[[ -z "$(bash "$resolver")" ]] || fail 'main push should not be release-validated'
export GITHUB_EVENT_NAME=workflow_dispatch GITHUB_REF=refs/heads/main API_FAILURE=true
[[ -z "$(bash "$resolver")" ]] || fail 'ordinary dispatch should not query candidate identity'

printf 'Canonical release PR base contract passed.\n'

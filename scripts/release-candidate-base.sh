#!/usr/bin/env bash
set -euo pipefail

# Return the canonical release PR base, or nothing for ordinary Quality runs.
# This adapter only reads identity; shared Profile B owns release lifecycle.
fail() {
	printf 'release-candidate-base: %s\n' "$*" >&2
	exit 1
}

release_branch='release-please--branches--main--components--ran-booster-wp-pusher-migrator'
case "$GITHUB_EVENT_NAME" in
	pull_request)
		event_branch=$(jq -er '.pull_request.head.ref | select(type == "string" and length > 0)' "$GITHUB_EVENT_PATH") \
			|| fail 'pull-request event has no head branch.'
		[[ "$event_branch" == "$release_branch" ]] || exit 0
		candidates=$(jq -c '[.pull_request]' "$GITHUB_EVENT_PATH")
		;;
	workflow_dispatch)
		[[ "$GITHUB_REF" == "refs/heads/$release_branch" ]] || exit 0
		# Pagination is required: an unrelated first page must not hide a proposal.
		pages=$(gh api --paginate --slurp "repos/${GITHUB_REPOSITORY}/pulls?state=open&base=main&per_page=100")
		candidates=$(jq -ce --arg branch "$release_branch" \
			'add | [.[] | select(.head.ref == $branch)]' <<< "$pages")
		;;
	*) exit 0 ;;
esac

[[ "$RAN_SOURCE_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'invalid event source SHA.'
# Require exactly one proposal before checking its identity; do not hide ambiguity
# by filtering out a wrong author, repository or moving head first.
base_commit=$(jq -er \
	--arg repository "$GITHUB_REPOSITORY" \
	--arg branch "$release_branch" \
	--arg sha "$RAN_SOURCE_SHA" \
	'if length != 1 then error("expected exactly one release proposal") else .[0] end
	 | select(.state == "open"
		and .user.login == "github-actions[bot]"
		and .base.ref == "main"
		and .base.repo.full_name == $repository
		and .head.repo.full_name == $repository
		and .head.ref == $branch
		and .head.sha == $sha)
	 | .base.sha | select(type == "string" and test("^[0-9a-f]{40}$"))' \
	<<< "$candidates") || fail 'canonical release PR identity is missing, ambiguous or no longer matches the event.'

printf '%s\n' "$base_commit"

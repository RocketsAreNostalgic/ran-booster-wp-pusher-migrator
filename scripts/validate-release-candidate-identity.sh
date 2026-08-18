#!/usr/bin/env bash
set -euo pipefail

[[ $# -eq 2 ]] || exit 2
base_sha=$1
head_sha=$2
[[ "$base_sha" =~ ^[0-9a-f]{40}$ ]] || exit 2
[[ "$head_sha" =~ ^[0-9a-f]{40}$ ]] || exit 2

jq -e \
	--arg base "$base_sha" \
	--arg bot 'github-actions[bot]' \
	--arg bot_email '41898282+github-actions[bot]@users.noreply.github.com' \
	--arg committer_email 'noreply@github.com' \
	--arg head "$head_sha" \
	--arg signer web-flow \
	'.data.repository.pullRequest.commits.nodes
	| length == 1
	and .[0].commit.oid == $head
	and (.[0].commit.parents.nodes | length == 1 and .[0].oid == $base)
	and .[0].commit.signature.isValid == true
	and .[0].commit.signature.state == "VALID"
	and .[0].commit.signature.signer.login == $signer
	and .[0].commit.author.user.login == $bot
	and .[0].commit.author.email == $bot_email
	and .[0].commit.committer.email == $committer_email' >/dev/null

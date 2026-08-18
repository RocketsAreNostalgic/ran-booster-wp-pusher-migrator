#!/usr/bin/env bash
set -euo pipefail

remote=${1:?Git remote is required.}
refspec=${2:?Git refspec is required.}
token=${GH_TOKEN:?GH_TOKEN is required for the candidate fetch.}
authorization=$(printf 'x-access-token:%s' "$token" | base64 | tr -d '\r\n')

env -u GH_TOKEN \
	GIT_CONFIG_COUNT=1 \
	GIT_CONFIG_KEY_0=http.https://github.com/.extraheader \
	GIT_CONFIG_VALUE_0="AUTHORIZATION: basic ${authorization}" \
	git fetch --no-tags "$remote" "$refspec"

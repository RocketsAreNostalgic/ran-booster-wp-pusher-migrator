#!/usr/bin/env bash
set -euo pipefail

export TZ=UTC

project_root="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
cd "$project_root"

slug="ran-booster-wp-pusher-migrator"
source_commit=${1:?full source commit is required}
if [[ ! "$source_commit" =~ ^[0-9a-f]{40}$ ]] \
	|| ! git cat-file -e "${source_commit}^{commit}" 2>/dev/null \
	|| [[ "$(git rev-parse "${source_commit}^{commit}")" != "$source_commit" ]]; then
	printf 'Source commit must be an existing full commit ID.\n' >&2
	exit 1
fi

main_file="$slug.php"
header="$(git show "${source_commit}:${main_file}")"
version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' <<< "$header")"
requires_wordpress="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Requires at least:[[:space:]]*([^[:space:]]+).*/\1/p' <<< "$header")"
requires_php="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Requires PHP:[[:space:]]*([^[:space:]]+).*/\1/p' <<< "$header")"
tested_wordpress="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Tested up to:[[:space:]]*([^[:space:]]+).*/\1/p' <<< "$header")"

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.-]+)?$ ]]; then
	printf 'Plugin header has an invalid release version: %s\n' "$version" >&2
	exit 1
fi
for compatibility_version in "$requires_wordpress" "$requires_php" "$tested_wordpress"; do
	if [[ ! "$compatibility_version" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
		printf 'Plugin compatibility version is invalid: %s\n' "$compatibility_version" >&2
		exit 1
	fi
done

manifest="$(mktemp)"
trap 'rm -f "$manifest"' EXIT
git show "${source_commit}:composer.json" > "$manifest"
php scripts/core-certification.php read "$manifest" >/dev/null

entries=()
declare -A seen_entries=()
while IFS= read -r entry || [[ -n "$entry" ]]; do
	[[ -z "$entry" || "$entry" == \#* ]] && continue
	if [[ "$entry" == /* || "$entry" == *//* || "$entry" == . || "$entry" == ./* || "$entry" == */./* || "$entry" == */. || "$entry" == .. || "$entry" == ../* || "$entry" == */../* || "$entry" == */.. ]]; then
		printf 'Release allowlist contains an unsafe path: %s\n' "$entry" >&2
		exit 1
	fi
	if [[ -n "${seen_entries[$entry]+yes}" ]]; then
		printf 'Release allowlist contains a duplicate entry: %s\n' "$entry" >&2
		exit 1
	fi
	seen_entries[$entry]=1
	if ! git cat-file -e "${source_commit}:${entry}" 2>/dev/null; then
		printf 'Release allowlist entry is missing from source commit: %s\n' "$entry" >&2
		exit 1
	fi
	entries+=( "$entry" )
done < <(git show "${source_commit}:release-contents.txt")
if (( ${#entries[@]} == 0 )); then
	printf 'Release allowlist is empty.\n' >&2
	exit 1
fi

mkdir -p dist
archive="dist/${slug}-${version}.zip"
checksum="${archive}.sha256"
metadata="dist/${slug}-${version}.json"
rm -f "$archive" "$checksum" "$metadata"
git archive \
	--format=zip \
	--prefix="${slug}/" \
	--output="$archive" \
	"$source_commit" \
	-- "${entries[@]}"

( cd dist && shasum -a 256 "$(basename "$archive")" ) > "$checksum"
archive_hash="$(shasum -a 256 "$archive" | awk '{ print $1 }')"
archive_size="$(wc -c < "$archive" | tr -d '[:space:]')"

# The single-quoted program is PHP and must not be expanded by the shell.
# shellcheck disable=SC2016
php -r '
	$metadata = array(
		"schema"             => "ran-wordpress-plugin-release",
		"schema_version"     => 1,
		"repository"         => "RocketsAreNostalgic/ran-booster-wp-pusher-migrator",
		"tag"                => "v" . $argv[1],
		"commit"             => $argv[2],
		"zip"                => $argv[3],
		"plugin_root"        => "ran-booster-wp-pusher-migrator",
		"main_file"          => "ran-booster-wp-pusher-migrator.php",
		"version"            => $argv[1],
		"requires_php"       => $argv[4],
		"requires_wordpress" => $argv[5],
		"tested_wordpress"   => $argv[6],
		"zip_size"           => (int) $argv[7],
		"zip_sha256"         => $argv[8],
	);
	echo json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
' \
	"$version" \
	"$source_commit" \
	"$(basename "$archive")" \
	"$requires_php" \
	"$requires_wordpress" \
	"$tested_wordpress" \
	"$archive_size" \
	"$archive_hash" > "$metadata"

bash scripts/verify-release.sh "$archive" "$source_commit"
printf '%s\n%s\n%s\n' "$project_root/$archive" "$project_root/$checksum" "$project_root/$metadata"

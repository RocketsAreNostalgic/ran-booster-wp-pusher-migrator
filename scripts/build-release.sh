#!/usr/bin/env bash
set -euo pipefail

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
slug="ran-booster-wp-pusher-migrator"
main_file="$project_root/$slug.php"
allowlist="$project_root/release-contents.txt"
version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "$main_file")"
requires_wordpress="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Requires at least:[[:space:]]*([^[:space:]]+).*/\1/p' "$main_file")"
requires_php="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Requires PHP:[[:space:]]*([^[:space:]]+).*/\1/p' "$main_file")"
tested_wordpress="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Tested up to:[[:space:]]*([^[:space:]]+).*/\1/p' "$main_file")"
commit="$(git -C "$project_root" rev-parse --verify 'HEAD^{commit}')"

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
if [[ ! "$commit" =~ ^[0-9a-f]{40}$ ]]; then
	printf 'Release commit is not a full Git SHA-1: %s\n' "$commit" >&2
	exit 1
fi

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
stage_root="$stage/$slug"
mkdir -p "$stage_root" "$project_root/dist"

while IFS= read -r entry || [[ -n "$entry" ]]; do
	[[ -z "$entry" || "$entry" == \#* ]] && continue
	source="$project_root/$entry"
	if [[ ! -e "$source" ]]; then
		printf 'Release allowlist entry is missing: %s\n' "$entry" >&2
		exit 1
	fi
	if find "$source" -type l -print -quit | grep -q .; then
		printf 'Release allowlist entry contains a symlink: %s\n' "$entry" >&2
		exit 1
	fi
	cp -R "$source" "$stage_root/$entry"
done < "$allowlist"

archive="$project_root/dist/$slug-$version.zip"
checksum="$archive.sha256"
metadata="$project_root/dist/$slug-$version.json"
rm -f "$archive" "$checksum" "$metadata"
find "$stage" -exec touch -h -t 198001010000 {} +
(
	cd "$stage"
	find "$slug" -print | LC_ALL=C sort | zip -Xq "$archive" -@
)

( cd "$project_root/dist" && shasum -a 256 "$(basename "$archive")" ) > "$checksum"
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
	"$commit" \
	"$(basename "$archive")" \
	"$requires_php" \
	"$requires_wordpress" \
	"$tested_wordpress" \
	"$archive_size" \
	"$archive_hash" > "$metadata"

bash "$project_root/scripts/verify-release.sh" "$archive"
printf '%s\n%s\n%s\n' "$archive" "$checksum" "$metadata"

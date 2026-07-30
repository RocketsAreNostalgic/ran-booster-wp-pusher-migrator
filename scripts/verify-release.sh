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
archive="${1:-$project_root/dist/$slug-$version.zip}"

[[ -f "$archive" ]] || { printf 'Release archive not found: %s\n' "$archive" >&2; exit 1; }
unzip -tq "$archive" >/dev/null
checksum="$archive.sha256"
[[ -f "$checksum" ]] || { printf 'Release checksum not found: %s\n' "$checksum" >&2; exit 1; }
archive_name="$(basename "$archive")"
expected_checksum="$(shasum -a 256 "$archive" | awk -v name="$archive_name" '{ print $1 "  " name }')"
if [[ "$(< "$checksum")" != "$expected_checksum" ]]; then
	printf 'Release checksum must contain only the archive basename.\n' >&2
	exit 1
fi
( cd "$(dirname "$archive")" && shasum -a 256 -c "$(basename "$checksum")" ) >/dev/null

archive_hash="$(shasum -a 256 "$archive" | awk '{ print $1 }')"
archive_size="$(wc -c < "$archive" | tr -d '[:space:]')"
metadata="$(dirname "$archive")/${archive_name%.zip}.json"
[[ -f "$metadata" ]] || { printf 'Release manifest not found: %s\n' "$metadata" >&2; exit 1; }

# The single-quoted program is PHP and must not be expanded by the shell.
# shellcheck disable=SC2016
php -r '
	$document = json_decode( file_get_contents( $argv[1] ), true, 512, JSON_THROW_ON_ERROR );
	$expected = array(
		"schema"             => "ran-wordpress-plugin-release",
		"schema_version"     => 1,
		"repository"         => "RocketsAreNostalgic/ran-booster-wp-pusher-migrator",
		"tag"                => "v" . $argv[2],
		"commit"             => $argv[3],
		"zip"                => $argv[4],
		"plugin_root"        => "ran-booster-wp-pusher-migrator",
		"main_file"          => "ran-booster-wp-pusher-migrator.php",
		"version"            => $argv[2],
		"requires_php"       => $argv[5],
		"requires_wordpress" => $argv[6],
		"tested_wordpress"   => $argv[7],
		"zip_size"           => (int) $argv[8],
		"zip_sha256"         => $argv[9],
	);
	if ( $document !== $expected ) {
		fwrite( STDERR, "Release manifest does not match the archive and package identity.\n" );
		exit( 1 );
	}
' \
	"$metadata" \
	"$version" \
	"$commit" \
	"$archive_name" \
	"$requires_php" \
	"$requires_wordpress" \
	"$tested_wordpress" \
	"$archive_size" \
	"$archive_hash"

actual="$(mktemp)"
expected="$(mktemp)"
inspection="$(mktemp -d)"
trap 'rm -f "$actual" "$expected"; rm -rf "$inspection"' EXIT

unzip -Z1 "$archive" | sed '/\/$/d' | LC_ALL=C sort -u > "$actual"
if grep -Ev "^${slug}/" "$actual" >/dev/null; then
	printf 'Release archive has files outside its plugin root.\n' >&2
	exit 1
fi

while IFS= read -r entry || [[ -n "$entry" ]]; do
	[[ -z "$entry" || "$entry" == \#* ]] && continue
	find "$project_root/$entry" -type f -print | sed "s#^$project_root/#$slug/#"
done < "$allowlist" | LC_ALL=C sort -u > "$expected"

if ! diff -u "$expected" "$actual"; then
	printf 'Release archive differs from the runtime allowlist.\n' >&2
	exit 1
fi

unzip -q "$archive" -d "$inspection"
while IFS= read -r file; do
	relative="${file#"$slug/"}"
	if ! cmp -s "$project_root/$relative" "$inspection/$file"; then
		printf 'Release archive file differs from reviewed source: %s\n' "$relative" >&2
		exit 1
	fi
done < "$actual"

header="$inspection/$slug/$slug.php"
archive_version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "$header")"
if [[ "$archive_version" != "$version" ]]; then
	printf 'Release archive plugin header does not match version %s.\n' "$version" >&2
	exit 1
fi
if ! grep -Fq ' * Update URI: https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator' "$header"; then
	printf 'Release archive does not declare its canonical GitHub Update URI.\n' >&2
	exit 1
fi

plugin="$inspection/$slug/src/Plugin.php"
if ! grep -Fq 'RAN_BOOSTER_PORTABILITY_API_VERSION' "$plugin" \
	|| ! grep -Fq 'RAN_BOOSTER_LOGGING_API_VERSION' "$plugin" \
	|| ! grep -Fq "'ran_booster_portability_ready'" "$plugin" \
	|| ! grep -Fq "'ran_booster_portability_render_migration_modes'" "$plugin" \
	|| ! grep -Fq "'ran_booster_portability_render_migration_flows'" "$plugin" \
	|| ! grep -Fq "'ran_booster_overview_render_migration_prompt'" "$plugin"; then
	printf 'Release archive does not contain the required Portability and Logging boundary.\n' >&2
	exit 1
fi
source="$inspection/$slug/src/WpPusherSource.php"
if ! grep -Fq "private const VERSION = '3.0.13';" "$source"; then
	printf 'Release archive does not preserve the exact WP Pusher source version.\n' >&2
	exit 1
fi

if grep -E '/(tests|vendor|node_modules|\.git|scripts|\.github|dist)/' "$actual" >/dev/null; then
	printf 'Release archive contains development-only files.\n' >&2
	exit 1
fi

while IFS= read -r file; do
	php -l "$file" >/dev/null
done < <(find "$inspection/$slug" -type f -name '*.php' -print)

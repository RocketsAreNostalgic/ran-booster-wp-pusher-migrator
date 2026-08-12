#!/usr/bin/env bash

set -euo pipefail

fail() {
	printf 'migrator-installed-proof: %s\n' "$*" >&2
	exit 1
}

[[ "${RAN_MIGRATOR_PROOF_DISPOSABLE:-}" == 1 ]] \
	|| fail 'Set RAN_MIGRATOR_PROOF_DISPOSABLE=1 only for an isolated disposable WordPress site.'

wordpress_input=${RAN_MIGRATOR_WORDPRESS_PATH:?RAN_MIGRATOR_WORDPRESS_PATH is required}
expected_site_url=${RAN_MIGRATOR_EXPECTED_SITE_URL:?RAN_MIGRATOR_EXPECTED_SITE_URL is required}
wp_user=${RAN_MIGRATOR_WP_USER:?RAN_MIGRATOR_WP_USER is required}
core_archive=${RAN_MIGRATOR_CORE_ARCHIVE:?RAN_MIGRATOR_CORE_ARCHIVE is required}
migrator_archive=${RAN_MIGRATOR_ARCHIVE:?RAN_MIGRATOR_ARCHIVE is required}
migrator_sha=${RAN_MIGRATOR_SHA256:?RAN_MIGRATOR_SHA256 is required}
migrator_source_input=${RAN_MIGRATOR_SOURCE_PATH:?RAN_MIGRATOR_SOURCE_PATH is required}
migrator_commit=${RAN_MIGRATOR_SOURCE_COMMIT:?RAN_MIGRATOR_SOURCE_COMMIT is required}
wppusher_archive=${RAN_MIGRATOR_WP_PUSHER_ARCHIVE:?RAN_MIGRATOR_WP_PUSHER_ARCHIVE is required}
mysql_socket=${RAN_MIGRATOR_MYSQL_SOCKET:?RAN_MIGRATOR_MYSQL_SOCKET is required}
mysql_database=${RAN_MIGRATOR_MYSQL_DATABASE:?RAN_MIGRATOR_MYSQL_DATABASE is required}
mysql_binary=${RAN_MIGRATOR_MYSQL_BIN:-mysql}
mysqldump_binary=${RAN_MIGRATOR_MYSQLDUMP_BIN:-mysqldump}
wp_binary=${RAN_MIGRATOR_WP_CLI_BIN:-wp}
php_binary=${RAN_MIGRATOR_WP_CLI_PHP:-}
php_ini=${RAN_MIGRATOR_WP_CLI_PHP_INI:-}

readonly expected_migrator_version='0.1.0-beta.6'
readonly expected_core_version='1.0.0-beta.15'
readonly expected_core_sha='1ac974014231b84694a2b0c04bd5bc27c61d5cc362d467539ec1aea0d4fdf8cd'
readonly expected_wppusher_sha='4f1533b9b946afdf9d699ea54279ea236b7e25f3d3fc9182bb53cec295a52208'

[[ "$migrator_commit" =~ ^[0-9a-f]{40}$ ]] || fail 'The caller-selected beta.6 source commit must be a full lowercase SHA.'
[[ "$migrator_sha" =~ ^[0-9a-f]{64}$ ]] || fail 'The retained beta.6 archive SHA-256 is invalid.'
[[ "$expected_site_url" =~ ^https?://[^[:space:]]+$ ]] || fail 'The expected site URL is invalid.'
[[ "$mysql_database" =~ ^[A-Za-z0-9_]+$ ]] || fail 'The disposable database name is invalid.'
[[ "$mysql_socket" == /* && -S "$mysql_socket" ]] || fail 'The MySQL boundary must be an existing absolute local socket.'
[[ -z "${MYSQL_PWD:-}" && -z "${GITHUB_TOKEN:-}" && -z "${BITBUCKET_TOKEN:-}" && -z "${WP_PUSHER_TOKEN:-}" ]] \
	|| fail 'Credential-bearing environment variables must be absent.'

canonical_directory() {
	local path=$1
	[[ -d "$path" && ! -L "$path" ]] || return 1
	(CDPATH='' cd -- "$path" && pwd -P)
}

canonical_file() {
	local path=$1
	[[ -f "$path" && ! -L "$path" ]] || return 1
	local directory
	directory=$(canonical_directory "$(dirname -- "$path")") || return 1
	printf '%s/%s\n' "$directory" "$(basename -- "$path")"
}

wordpress=$(canonical_directory "$wordpress_input") || fail 'The WordPress path is missing, linked or inaccessible.'
[[ "${wordpress_input%/}" == "$wordpress" ]] || fail 'RAN_MIGRATOR_WORDPRESS_PATH must be the canonical physical ABSPATH.'
migrator_source=$(canonical_directory "$migrator_source_input") || fail 'The Migrator source checkout is missing or linked.'
[[ "${migrator_source_input%/}" == "$migrator_source" ]] || fail 'RAN_MIGRATOR_SOURCE_PATH must be canonical and physical.'
core_archive=$(canonical_file "$core_archive") || fail 'The Core archive is missing, linked or noncanonical.'
migrator_archive=$(canonical_file "$migrator_archive") || fail 'The Migrator archive is missing, linked or noncanonical.'
wppusher_archive=$(canonical_file "$wppusher_archive") || fail 'The WP Pusher archive is missing, linked or noncanonical.'

wp_content="$wordpress/wp-content"
plugins="$wp_content/plugins"
mu_plugins="$wp_content/mu-plugins"
marker="$wordpress/.ran-booster-disposable-test-site"
core_dir="$plugins/ran-booster"
migrator_dir="$plugins/ran-booster-wp-pusher-migrator"
wppusher_dir="$plugins/wppusher"

[[ -d "$wp_content" && ! -L "$wp_content" && -d "$plugins" && ! -L "$plugins" ]] \
	|| fail 'WP_CONTENT_DIR or its plugins directory is not physical.'
[[ "$(canonical_directory "$wp_content")" == "$wp_content" ]] || fail 'WP_CONTENT_DIR is not canonical.'
[[ -f "$marker" && ! -L "$marker" && "$(< "$marker")" == 'RAN Booster disposable test site' ]] \
	|| fail 'The exact disposable-site marker is missing or unsafe.'
for dropin in advanced-cache.php db.php db-error.php install.php maintenance.php object-cache.php php-error.php fatal-error-handler.php sunrise.php; do
	[[ ! -e "$wp_content/$dropin" && ! -L "$wp_content/$dropin" ]] \
		|| fail "The no-network lane refuses executable pre-MU drop-in: $dropin"
done
[[ -d "$migrator_source/.git" || -f "$migrator_source/.git" ]] || fail 'The Migrator source is not a Git checkout.'
[[ "$(git -C "$migrator_source" rev-parse HEAD)" == "$migrator_commit" ]] || fail 'The verifier checkout is not the exact caller-selected beta.6 head.'
[[ -z "$(git -C "$migrator_source" status --porcelain=v1 --untracked-files=all)" ]] || fail 'The verifier checkout is not clean.'

sha256_file() {
	shasum -a 256 "$1" | awk '{ print $1 }'
}

[[ "$(sha256_file "$core_archive")" == "$expected_core_sha" ]] || fail 'The Core archive is not the immutable beta.15 asset.'
[[ "$(sha256_file "$migrator_archive")" == "$migrator_sha" ]] || fail 'The retained Migrator archive digest is wrong.'
[[ "$(sha256_file "$wppusher_archive")" == "$expected_wppusher_sha" ]] || fail 'The WP Pusher archive is not the exact 3.0.13 fixture.'
unzip -tqq "$core_archive"
unzip -tqq "$migrator_archive"
unzip -tqq "$wppusher_archive"
bash "$migrator_source/scripts/verify-release.sh" "$migrator_archive" "$migrator_commit"

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
probe="$script_dir/migrator-installed-probe.php"
recorder_source="$script_dir/migrator-installed-recorder.php"
[[ -f "$probe" && -f "$recorder_source" ]] || fail 'The installed proof helpers are unavailable.'

wp_cli() {
	local command=()
	if [[ -n "$php_binary" ]]; then
		[[ "$php_binary" == /* && -x "$php_binary" ]] || fail 'RAN_MIGRATOR_WP_CLI_PHP must be an absolute executable.'
		[[ "$wp_binary" == /* && -f "$wp_binary" ]] || fail 'Use an absolute WP-CLI script with RAN_MIGRATOR_WP_CLI_PHP.'
		[[ -z "$php_ini" || ( "$php_ini" == /* && -f "$php_ini" ) ]] || fail 'RAN_MIGRATOR_WP_CLI_PHP_INI must be an absolute file.'
		command=( "$php_binary" )
		[[ -z "$php_ini" ]] || command+=( -c "$php_ini" )
		command+=( "$wp_binary" )
	else
		command=( "$wp_binary" )
	fi
	command+=( "--path=$wordpress" --quiet )
	"${command[@]}" "$@"
}

mysql_cli() {
	"$mysql_binary" --protocol=SOCKET --socket="$mysql_socket" --database="$mysql_database" --batch --skip-column-names "$@"
}

mysql_server_cli() {
	"$mysql_binary" --protocol=SOCKET --socket="$mysql_socket" --batch --skip-column-names "$@"
}

dump_database() {
	local destination=$1
	"$mysqldump_binary" --protocol=SOCKET --socket="$mysql_socket" --single-transaction --skip-lock-tables \
		--skip-comments --default-character-set=utf8mb4 --databases "$mysql_database" > "$destination"
}

export RAN_MIGRATOR_EXPECTED_ABSPATH="$wordpress"
export RAN_MIGRATOR_EXPECTED_WP_CONTENT_DIR="$wp_content"
export RAN_MIGRATOR_EXPECTED_SITE_URL="$expected_site_url"

[[ "$(wp_cli --skip-plugins --skip-themes option get siteurl | tail -n 1)" == "$expected_site_url" ]] \
	|| fail 'The disposable WordPress URL is not the caller-authorized site.'
[[ "$(wp_cli --skip-plugins --skip-themes user get "$wp_user" --field=ID | tail -n 1)" =~ ^[1-9][0-9]*$ ]] \
	|| fail 'The caller-selected WordPress proof user is unavailable.'
external_db_identity=$(mysql_cli --execute="SELECT CONCAT(DATABASE(), '|', @@hostname, '|', @@port, '|', @@socket)")
export RAN_MIGRATOR_PROOF_MODE=db-identity
wordpress_db_identity=$(wp_cli --skip-plugins --skip-themes eval-file "$probe" | tail -n 1)
[[ "$external_db_identity" == "$wordpress_db_identity" && "${external_db_identity%%|*}" == "$mysql_database" ]] \
	|| fail 'WordPress and the recovery client do not address the same exact database server.'

recovery=$(mktemp -d /private/tmp/ran-migrator-proof-recovery.XXXXXX)
chmod 0700 "$recovery"
active_snapshot="$recovery/active-plugins.json"
sql_snapshot="$recovery/baseline.sql"
recorder="$mu_plugins/0000000000-ran-migrator-installed-proof.php"
mutation_started=0
proof_completed=0
mu_dir_created=0

export RAN_MIGRATOR_ACTIVE_SNAPSHOT="$active_snapshot"

snapshot_directory() {
	local source=$1 name=$2
	if [[ -L "$source" ]]; then
		fail "Refusing to snapshot linked plugin path: $source"
	fi
	if [[ -d "$source" ]]; then
		if find "$source" -type l -print -quit | grep -q .; then
			fail "Refusing a plugin tree containing links: $source"
		fi
		cp -Rp "$source" "$recovery/baseline-$name"
		diff -qr "$source" "$recovery/baseline-$name" >/dev/null
		printf 'present\n' > "$recovery/baseline-$name.state"
	elif [[ -e "$source" ]]; then
		fail "The baseline plugin path is not a physical directory: $source"
	else
		printf 'absent\n' > "$recovery/baseline-$name.state"
	fi
}

restore_directory() {
	local target=$1 name=$2 failed=0
	[[ ! -L "$target" ]] || return 1
	if [[ -d "$target" ]]; then
		[[ ! -e "$recovery/proof-$name" ]] || return 1
		mv "$target" "$recovery/proof-$name" || failed=1
	elif [[ -e "$target" ]]; then
		failed=1
	fi
	if [[ "$(< "$recovery/baseline-$name.state")" == present ]]; then
		[[ -d "$recovery/baseline-$name" && ! -e "$target" ]] || return 1
		cp -Rp "$recovery/baseline-$name" "$target" || failed=1
		diff -qr "$recovery/baseline-$name" "$target" >/dev/null || failed=1
	else
		[[ ! -e "$target" && ! -L "$target" ]] || failed=1
	fi
	return "$failed"
}

cleanup() {
	local status=$? cleanup_failed=0
	trap - EXIT HUP INT TERM
	set +e
	if (( mutation_started )); then
		restore_directory "$migrator_dir" migrator || cleanup_failed=1
		restore_directory "$core_dir" core || cleanup_failed=1
		restore_directory "$wppusher_dir" wppusher || cleanup_failed=1
		[[ -s "$sql_snapshot" ]] || cleanup_failed=1
		if (( 0 == cleanup_failed )); then
			mysql_server_cli --execute="DROP DATABASE \`$mysql_database\`" || cleanup_failed=1
			(( 0 != cleanup_failed )) || mysql_server_cli < "$sql_snapshot" || cleanup_failed=1
		fi
		if [[ -f "$recorder" && ! -L "$recorder" && "$(sha256_file "$recorder")" == "$(sha256_file "$recorder_source")" ]]; then
			rm -- "$recorder" || cleanup_failed=1
		elif [[ -e "$recorder" || -L "$recorder" ]]; then
			cleanup_failed=1
		fi
		if (( mu_dir_created )); then
			rmdir "$mu_plugins" 2>/dev/null || cleanup_failed=1
		fi
		export RAN_MIGRATOR_PROOF_MODE=compare-active
		wp_cli --skip-plugins --skip-themes eval-file "$probe" || cleanup_failed=1
		[[ "$(wp_cli --skip-plugins --skip-themes option get siteurl | tail -n 1)" == "$expected_site_url" ]] || cleanup_failed=1
		[[ "$(sha256_file "$sql_snapshot")" == "$(< "$recovery/baseline.sql.sha256")" ]] || cleanup_failed=1
		dump_database "$recovery/post-cleanup.sql" || cleanup_failed=1
		cmp -s "$sql_snapshot" "$recovery/post-cleanup.sql" || cleanup_failed=1
		export RAN_MIGRATOR_PROOF_MODE=db-identity
		[[ "$(wp_cli --skip-plugins --skip-themes eval-file "$probe" | tail -n 1)" == "$external_db_identity" ]] || cleanup_failed=1
	fi
	if (( cleanup_failed )); then
		printf 'migrator-installed-proof: cleanup is uncertain; retained recovery data at %s\n' "$recovery" >&2
		status=1
	else
		case "$recovery" in
			/private/tmp/ran-migrator-proof-recovery.*) rm -rf -- "$recovery" ;;
			*) status=1 ;;
		esac
	fi
	if (( 0 == status && proof_completed )); then
		printf 'Migrator beta.6 installed-candidate proof and exact cleanup passed (%s).\n' "$migrator_sha"
	fi
	exit "$status"
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM

export RAN_MIGRATOR_PROOF_MODE=snapshot-active
wp_cli --skip-plugins --skip-themes eval-file "$probe"
snapshot_directory "$core_dir" core
snapshot_directory "$migrator_dir" migrator
snapshot_directory "$wppusher_dir" wppusher
dump_database "$sql_snapshot"
[[ -s "$sql_snapshot" ]] || fail 'The full SQL recovery baseline is empty.'
sha256_file "$sql_snapshot" > "$recovery/baseline.sql.sha256"

if [[ ! -d "$mu_plugins" ]]; then
	mutation_started=1
	mu_dir_created=1
	mkdir "$mu_plugins"
fi
[[ -d "$mu_plugins" && ! -L "$mu_plugins" && ! -e "$recorder" && ! -L "$recorder" ]] \
	|| fail 'The owned no-network recorder path is unavailable.'
if find "$mu_plugins" -maxdepth 1 -type f -name '*.php' -print -quit | grep -q .; then
	fail 'The proof requires no existing top-level must-use plugin.'
fi

mutation_started=1
cp "$recorder_source" "$recorder"
chmod 0644 "$recorder"
[[ "$(find "$mu_plugins" -maxdepth 1 -type f -name '*.php' -print)" == "$recorder" ]] \
	|| fail 'The no-network recorder is not the only top-level must-use plugin.'

wp_cli --skip-plugins --skip-themes plugin install "$core_archive" --force
wp_cli --skip-plugins --skip-themes plugin install "$migrator_archive" --force
wp_cli --skip-plugins --skip-themes plugin install "$wppusher_archive" --force

mkdir "$recovery/extracted-core" "$recovery/extracted-migrator" "$recovery/extracted-wppusher"
unzip -q "$core_archive" -d "$recovery/extracted-core"
unzip -q "$migrator_archive" -d "$recovery/extracted-migrator"
unzip -q "$wppusher_archive" -d "$recovery/extracted-wppusher"
diff -qr "$recovery/extracted-core/ran-booster" "$core_dir" >/dev/null || fail 'The installed Core tree differs from beta.15.'
diff -qr "$recovery/extracted-migrator/ran-booster-wp-pusher-migrator" "$migrator_dir" >/dev/null || fail 'The installed Migrator tree differs from the retained beta.6 artifact.'
diff -qr "$recovery/extracted-wppusher/wppusher" "$wppusher_dir" >/dev/null || fail 'The installed WP Pusher tree differs from the exact 3.0.13 fixture.'

export RAN_MIGRATOR_PROOF_MODE=seed-fixture
wp_cli --skip-plugins --skip-themes eval-file "$probe"
export RAN_MIGRATOR_PROOF_MODE=source-cases
wp_cli --skip-plugins --skip-themes eval-file "$probe"

for load_order in core-first addon-first; do
	export RAN_MIGRATOR_LOAD_ORDER="$load_order"
	export RAN_MIGRATOR_PROOF_MODE=set-order
	wp_cli --skip-plugins --skip-themes eval-file "$probe"
	export RAN_MIGRATOR_PROOF_MODE=compatible
	wp_cli --user="$wp_user" eval-file "$probe"
done

proof_completed=1

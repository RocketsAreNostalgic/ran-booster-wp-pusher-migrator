#!/usr/bin/env bash
set -euo pipefail

fail() { printf 'current-source-fixture: %s\n' "$*" >&2; exit 1; }
[[ "${RAN_MIGRATOR_PROOF_DISPOSABLE:-}" == 1 ]] || fail 'Explicit disposable-site authorization is required.'
root=${RAN_MIGRATOR_WORDPRESS_PATH:?}
site=${RAN_MIGRATOR_EXPECTED_SITE_URL:?}
wp=${RAN_MIGRATOR_WP_CLI_BIN:-wp}
wp_user=${RAN_MIGRATOR_WP_USER:?Explicit administrator user ID or login is required}
phase=${1:?Expected setup, seed, readback or cleanup}
source_dir=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
plugins="$root/wp-content/plugins"
marker="$root/.ran-booster-disposable-test-site"
[[ "$root" == /* && -d "$root" && "$(realpath "$root")" == "$root" ]] || fail 'Use canonical physical ABSPATH.'
[[ "$site" =~ ^http://localhost(:[0-9]+)?(/[^[:space:]]*)?$ ]] || fail 'Use a localhost HTTP site.'
[[ -f "$marker" && ! -L "$marker" && "$(< "$marker")" == 'RAN Booster disposable test site' ]] || fail 'Missing exact site marker.'
[[ "$(realpath "$plugins")" == "$plugins" ]] || fail 'Plugin directory must be physical.'
cli() { "$wp" "--path=$root" "--user=$wp_user" "$@"; }
[[ "$(cli option get siteurl --skip-plugins --skip-themes)" == "$site" ]] || fail 'Site URL mismatch.'

RAN_MIGRATOR_FIXTURE_PHASE=preflight cli eval-file "$source_dir/tests/installed-candidate/current-source-fixture.php"
archive=${RAN_MIGRATOR_ARCHIVE:?}
pusher_archive=${RAN_MIGRATOR_WP_PUSHER_ARCHIVE:?}
[[ -f "$archive" && ! -L "$archive" && -f "$pusher_archive" && ! -L "$pusher_archive" ]] || fail 'Regular caller-provided archives are required.'
[[ "$(sha256sum "$pusher_archive" | cut -d' ' -f1)" == 245b029e3ad0758ac817be0a8af715efd03c143fc66aadb1527abdec0daa681c ]] || fail 'WP Pusher archive differs from verified official 3.0.18 fixture.'
bash "$source_dir/scripts/verify-release.sh" "$archive" ef225f13134bbca33bf910fd1ed5941f42ec9c46
scratch=$(mktemp -d)
trap 'rm -rf -- "$scratch"' EXIT
unzip -q "$archive" -d "$scratch/migrator"
unzip -q "$pusher_archive" -d "$scratch/pusher"
pusher_version=$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "$scratch/pusher/wppusher/wppusher.php")
[[ "$pusher_version" == 3.0.18 ]] || fail 'WP Pusher archive header version mismatch.'
if [[ "$phase" == setup ]]; then
  for plugin in wppusher ran-booster-wp-pusher-migrator; do
    [[ ! -e "$plugins/$plugin" && ! -L "$plugins/$plugin" ]] || fail "Refusing existing destination $plugin."
  done
  cli plugin install "$pusher_archive"
  cli plugin install "$archive"
  [[ "$(cli plugin get wppusher --field=version)" == 3.0.18 ]] || fail 'WP Pusher version mismatch.'
  ! cli plugin is-active wppusher || fail 'WP Pusher must stay inactive.'
  cli plugin activate ran-booster-wp-pusher-migrator
  phase=readback
fi
for plugin in wppusher ran-booster-wp-pusher-migrator; do
  [[ "$(realpath "$plugins/$plugin")" == "$plugins/$plugin" ]] || fail 'Installed plugin directory is linked.'
  [[ -z "$(find "$plugins/$plugin" -type l -print -quit)" ]] || fail 'Installed plugin contains a link.'
done
diff -qr "$scratch/migrator/ran-booster-wp-pusher-migrator" "$plugins/ran-booster-wp-pusher-migrator" || fail 'Installed Migrator bytes differ from exact compatibility source archive.'
diff -qr "$scratch/pusher/wppusher" "$plugins/wppusher" || fail 'Installed WP Pusher bytes differ from retained fixture.'
case "$phase" in seed|readback|cleanup) ;; *) fail 'Unknown phase.' ;; esac
export RAN_MIGRATOR_FIXTURE_PHASE="$phase"
cli eval-file "$source_dir/tests/installed-candidate/current-source-fixture.php"

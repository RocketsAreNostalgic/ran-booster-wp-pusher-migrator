#!/usr/bin/env bash
# Exercise the actual behavior wrapper: hidden manifest tampering must stop before Composer.
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/project/tests" "$fixture/project/scripts" "$fixture/project/vendor/ran-source-core" "$fixture/bin"
cp "$project_root/composer.json" "$fixture/project/"
cp "$project_root/scripts/core-source.php" "$project_root/scripts/core-certification.php" "$fixture/project/scripts/"
cp "$project_root/tests/pinned-source-behaviour.sh" "$project_root/tests/source-candidate-bootstrap.php" "$fixture/project/tests/"
git clone --quiet --shared "$project_root/vendor/ran-source-core/source" "$fixture/project/vendor/ran-source-core/source"
core="$fixture/project/vendor/ran-source-core/source"
export RAN_COMPOSER_PROBE="$fixture/composer-invoked"
cat > "$fixture/bin/composer" <<'COMPOSER'
#!/usr/bin/env bash
printf 'invoked\n' > "$RAN_COMPOSER_PROBE"
exit 99
COMPOSER
chmod +x "$fixture/bin/composer"
run_wrapper() {
	PATH="$fixture/bin:$PATH" bash "$fixture/project/tests/pinned-source-behaviour.sh" > "$fixture/result.log" 2>&1
}
assert_clean_reaches_composer() {
	local status=0
	run_wrapper || status=$?
	test "$status" -eq 99
	test -f "$RAN_COMPOSER_PROBE"
	rm "$RAN_COMPOSER_PROBE"
}
assert_clean_reaches_composer
git -C "$core" update-index --assume-unchanged composer.json
php -r '
$path = $argv[1];
$manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$manifest["autoload"]["files"] = ["unreviewed-autoload.php"];
file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
' "$core/composer.json"
test -z "$(git -C "$core" status --porcelain -- composer.json)"
if run_wrapper; then
	printf 'Behavior accepted an index-hidden Core manifest change.\n' >&2
	exit 1
fi
grep -Fq 'Source candidate declaration bytes differ from the commit.' "$fixture/result.log"
test ! -e "$RAN_COMPOSER_PROBE"
git -C "$core" update-index --no-assume-unchanged composer.json
git -C "$core" restore --source=HEAD -- composer.json
assert_clean_reaches_composer
printf 'Source behavior contract passed: hidden Core manifest changes rejected before Composer; restored source admitted.\n'

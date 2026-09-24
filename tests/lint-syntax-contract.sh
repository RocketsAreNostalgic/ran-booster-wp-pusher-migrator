#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
work_root=$(mktemp -d "${TMPDIR:-/tmp}/ran-migrator-syntax-test.XXXXXX")
trap 'rm -rf "$work_root"' EXIT
fixture="$work_root/source tree"
mkdir -p "$fixture/scripts" "$fixture/nested" "$fixture/vendor" "$fixture/node_modules" "$fixture/.git"
cp "$repo_root/composer.json" "$fixture/composer.json"
cp "$repo_root/scripts/lint-php.sh" "$fixture/scripts/lint-php.sh"

lint() {
	composer --no-interaction --no-plugins --working-dir="$fixture" "$1" > "$work_root/output" 2>&1
}
fail() {
	printf 'syntax contract: %s\n' "$*" >&2
	cat "$work_root/output" >&2
	exit 1
}

printf '<?php echo "valid";\n' > "$fixture/nested/space quote'"$'\n''name.php'
for excluded in vendor node_modules .git; do
	printf '<?php invalid (\n' > "$fixture/$excluded/ignored.php"
done
lint lint:syntax || fail 'valid unusual filename or dependency exclusion failed'
lint lint:php || fail 'existing CI alias failed'

# A later valid file must not mask the invalid file's exit status.
printf '<?php invalid (\n' > "$fixture/invalid file.php"
printf '<?php echo "valid";\n' > "$fixture/z-valid.php"
if lint lint:syntax; then
	fail 'invalid PHP returned success'
fi
if lint lint:php; then
	fail 'existing CI alias masked invalid PHP'
fi
rm "$fixture/invalid file.php"
lint lint:syntax || fail 'clean fixture did not recover'

# Inject a discovery failure independently of PHP parser failures.
mkdir "$work_root/bin"
printf '#!/usr/bin/env bash\nexit 23\n' > "$work_root/bin/find"
chmod +x "$work_root/bin/find"
if PATH="$work_root/bin:$PATH" lint lint:syntax; then
	fail 'file discovery failure returned success'
fi
printf 'PASS syntax command rejects invalid PHP and discovery failures; preserves filenames, exclusions and CI alias\n'

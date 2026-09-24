#!/usr/bin/env bash
set -euo pipefail

# NUL delimiters preserve filenames; pipefail also preserves discovery errors.
find . -path ./vendor -prune -o -path ./node_modules -prune -o -path ./.git -prune \
	-o -name '*.php' -print0 |
	while IFS= read -r -d '' file; do
		php -l "$file"
	done

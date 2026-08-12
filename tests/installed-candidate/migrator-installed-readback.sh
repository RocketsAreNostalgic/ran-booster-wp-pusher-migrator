#!/usr/bin/env bash

canonicalize_database_export() {
	# An explicit utf8mb4 collation already implies the utf8mb4 charset. MySQL
	# may add that redundant spelling after import. Restrict normalization to
	# column definitions and retain the collation that carries the semantics.
	# The SQL backticks are literal syntax.
	# shellcheck disable=SC2016
	sed -E '/^  `[^`]+` / s/ CHARACTER SET utf8mb4 (COLLATE utf8mb4_[[:alnum:]_]+)/ \1/g' "$1" > "$2"
}

#!/usr/bin/env bash

create_canonical_fixture_dir() {
	local raw_dir
	local canonical_dir
	raw_dir="$(mktemp -d)"
	if [ -z "$raw_dir" ] || [ ! -d "$raw_dir" ]; then
		printf 'Failed to create temporary fixture directory.\n' >&2
		return 1
	fi
	canonical_dir="$(CDPATH='' cd -- "$raw_dir" && pwd -P)"
	if [ -z "$canonical_dir" ] || [ ! -d "$canonical_dir" ]; then
		printf 'Failed to resolve temporary fixture directory.\n' >&2
		return 1
	fi
	printf '%s\n' "$canonical_dir"
}

file_replace_literal() {
	php -r '
		$file = $argv[1];
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			exit( 1 );
		}
		$updated = str_replace( $argv[2], $argv[3], $contents, $count );
		if ( 0 === $count || false === file_put_contents( $file, $updated ) ) {
			exit( 1 );
		}
	' "$1" "$2" "$3"
}

file_insert_line() {
	php -r '
		$file = $argv[1];
		$mode = $argv[2];
		$pattern = $argv[3];
		$insert = $argv[4];
		$contents = file_get_contents( $file );
		if ( false === $contents || ! in_array( $mode, array( "after", "before" ), true ) ) {
			exit( 1 );
		}
		$trailing = str_ends_with( $contents, "\n" ) ? "\n" : "";
		$lines = explode( "\n", substr( $contents, 0, strlen( $contents ) - strlen( $trailing ) ) );
		$out = array();
		$matched = 0;
		foreach ( $lines as $line ) {
			if ( 1 === preg_match( $pattern, $line ) ) {
				++$matched;
				if ( "before" === $mode ) {
					$out[] = $insert;
				}
				$out[] = $line;
				if ( "after" === $mode ) {
					$out[] = $insert;
				}
				continue;
			}
			$out[] = $line;
		}
		if ( 0 === $matched || false === file_put_contents( $file, implode( "\n", $out ) . $trailing ) ) {
			exit( 1 );
		}
	' "$1" "$2" "$3" "$4"
}

file_delete_matching_lines() {
	php -r '
		$file = $argv[1];
		$pattern = $argv[2];
		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			exit( 1 );
		}
		$trailing = str_ends_with( $contents, "\n" ) ? "\n" : "";
		$lines = explode( "\n", substr( $contents, 0, strlen( $contents ) - strlen( $trailing ) ) );
		$out = array();
		$matched = 0;
		foreach ( $lines as $line ) {
			if ( 1 === preg_match( $pattern, $line ) ) {
				++$matched;
				continue;
			}
			$out[] = $line;
		}
		if ( 0 === $matched || false === file_put_contents( $file, implode( "\n", $out ) . $trailing ) ) {
			exit( 1 );
		}
	' "$1" "$2"
}

file_delete_first_line() {
	php -r '
		$file = $argv[1];
		$contents = file_get_contents( $file );
		if ( false === $contents || "" === $contents ) {
			exit( 1 );
		}
		$pos = strpos( $contents, "\n" );
		$updated = false === $pos ? "" : substr( $contents, $pos + 1 );
		if ( false === file_put_contents( $file, $updated ) ) {
			exit( 1 );
		}
	' "$1"
}

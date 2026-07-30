#!/usr/bin/env bash
set -euo pipefail

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
fixture_root="$project_root/tests/fixtures/local-wp-pusher-plugins"
site_public="${1:-}"
mysql_socket="${2:-}"
expected_site_url="${3:-}"
table_prefix="${4:-wp_}"

if [[ -z "$site_public" || -z "$mysql_socket" || -z "$expected_site_url" ]]; then
	printf 'Usage: %s <wordpress-public-dir> <mysql-socket> <expected-site-url> [table-prefix]\n' "$0" >&2
	exit 64
fi
if [[ ! -f "$site_public/wp-config.php" ]]; then
	printf 'WordPress public directory was not found: %s\n' "$site_public" >&2
	exit 66
fi
if [[ ! -S "$mysql_socket" ]]; then
	printf 'MySQL socket was not found: %s\n' "$mysql_socket" >&2
	exit 66
fi
if [[ ! "$table_prefix" =~ ^[A-Za-z0-9_]+$ ]]; then
	printf 'The table prefix must contain only letters, numbers, and underscores.\n' >&2
	exit 64
fi

plugins_dir="$site_public/wp-content/plugins"
slugs=(
	booster-migration-alpha
	booster-migration-bravo
	booster-migration-charlie
	booster-migration-delta
	booster-migration-echo
	booster-migration-foxtrot
	booster-migration-bitbucket
	booster-migration-gitlab
)

for slug in "${slugs[@]}"; do
	source_path="$fixture_root/$slug"
	target_path="$plugins_dir/$slug"
	if [[ ! -f "$source_path/$slug.php" ]]; then
		printf 'Fixture source was not found: %s\n' "$source_path/$slug.php" >&2
		exit 66
	fi
	if [[ -L "$target_path" ]]; then
		if [[ "$(readlink "$target_path")" != "$source_path" ]]; then
			printf 'Refusing to replace an unrelated symlink: %s\n' "$target_path" >&2
			exit 73
		fi
	elif [[ -e "$target_path" ]]; then
		printf 'Refusing to replace an existing plugin path: %s\n' "$target_path" >&2
		exit 73
	fi
done

actual_site_url="$(
	mysql --protocol=socket --socket="$mysql_socket" --user=root --password=root \
		--batch --skip-column-names local \
		-e "SELECT option_value FROM \`${table_prefix}options\` WHERE option_name = 'siteurl' LIMIT 1"
)"
if [[ "$actual_site_url" != "$expected_site_url" ]]; then
	printf 'Refusing to seed an unexpected site. Expected %s, found %s.\n' "$expected_site_url" "$actual_site_url" >&2
	exit 73
fi

actual_schema="$(
	mysql --protocol=socket --socket="$mysql_socket" --user=root --password=root \
		--batch --skip-column-names local \
		-e "SELECT GROUP_CONCAT(CONCAT(column_name, ':', data_type) ORDER BY ordinal_position SEPARATOR ',') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '${table_prefix}wppusher_packages'"
)"
expected_schema='id:mediumint,package:varchar,repository:varchar,branch:varchar,type:int,status:int,ptd:int,host:varchar,private:int,subdirectory:varchar'
if [[ "$actual_schema" != "$expected_schema" ]]; then
	printf 'Refusing to seed an unsupported WP Pusher package schema.\n' >&2
	exit 73
fi

for slug in "${slugs[@]}"; do
	source_path="$fixture_root/$slug"
	target_path="$plugins_dir/$slug"
	if [[ ! -e "$target_path" ]]; then
		ln -s "$source_path" "$target_path"
	fi
done

mysql --protocol=socket --socket="$mysql_socket" --user=root --password=root local <<SQL
INSERT INTO \`${table_prefix}wppusher_packages\`
	(\`package\`, \`repository\`, \`branch\`, \`type\`, \`status\`, \`ptd\`, \`host\`, \`private\`, \`subdirectory\`)
SELECT
	CONCAT(slug, '/', slug, '.php'),
	repository,
	'main',
	1,
	1,
	0,
	host,
	0,
	NULL
FROM (
	SELECT
		'booster-migration-alpha' AS slug,
		'RocketsAreNostalgic/booster-fixture-plugin' AS repository,
		'gh' AS host
	UNION ALL SELECT 'booster-migration-bravo', 'RocketsAreNostalgic/booster-fixture-plugin', 'gh'
	UNION ALL SELECT 'booster-migration-charlie', 'RocketsAreNostalgic/booster-fixture-plugin', 'gh'
	UNION ALL SELECT 'booster-migration-delta', 'RocketsAreNostalgic/booster-fixture-plugin', 'gh'
	UNION ALL SELECT 'booster-migration-echo', 'RocketsAreNostalgic/booster-fixture-plugin', 'gh'
	UNION ALL SELECT 'booster-migration-foxtrot', 'RocketsAreNostalgic/booster-fixture-plugin', 'gh'
	UNION ALL SELECT 'booster-migration-bitbucket', 'fixture-workspace/booster-migration-bitbucket', 'bb'
	UNION ALL SELECT 'booster-migration-gitlab', 'fixture-group/booster-migration-gitlab', 'gl'
) AS fixtures
WHERE NOT EXISTS (
	SELECT 1
	FROM \`${table_prefix}wppusher_packages\` AS existing
	WHERE existing.\`type\` = 1
		AND existing.\`package\` = CONCAT(fixtures.slug, '/', fixtures.slug, '.php')
);
SQL

printf 'WP Pusher migration fixtures are installed and reseeded.\n'

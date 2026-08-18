# Contributing

Use [GitHub Issues](https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator/issues)
for a reproducible defect or to discuss a bounded change before opening a pull
request. Do not use a public issue or pull request for a vulnerability; follow
[SECURITY.md](SECURITY.md) instead.

Keep the bridge removable and limited to WP Pusher 3.0.13. Changes must preserve
its read-only source inspection, exact Core API guards, Disabled adoption,
fresh verification before source-row deletion, and refusal to import credentials
or contact providers.

## Local checks

The development baseline is PHP 8.2, Node.js 24, and pnpm 11.

```sh
composer install --no-interaction --prefer-dist
pnpm install --frozen-lockfile
composer validate --strict --no-check-publish
pnpm check
composer check
```

Use a Conventional Commit title. Do not edit the release version,
`.release-please-manifest.json`, or generated changelog entry in an ordinary
change; Release Please owns those files. Do not commit dependency directories,
release archives, credentials, database exports, logs, or private site data.

By submitting a contribution, you agree that it may be distributed under this
project's GPL-2.0-or-later license.

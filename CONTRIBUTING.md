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

`composer lint:syntax` recursively parses PHP outside root `vendor`,
`node_modules`, and `.git`. Parser and file-discovery failures return nonzero.
`composer lint:php` forwards to it for the existing CI caller.
`composer test:syntax-contract` exercises invalid PHP, unusual filenames,
dependency exclusions and discovery failures in a disposable directory; it
also runs in `composer check`.

`composer standards` checks the repository's `.phpcs.xml.dist` ruleset;
`composer standards:fix` applies PHPCBF using the same rules and file scope.
PHPCBF returns 1 when it successfully fixes violations; run `composer standards`
again to confirm the result. Review formatting changes before committing.
`composer test:standards-contract` checks the actual commands in a disposable
copy: clean source, two byte-stable fixer passes, and a trailing-whitespace
negative fixture that must fail checking and be restored by the fixer. It runs
in `composer check` and requires Git and the installed development dependencies.

Use a Conventional Commit title. Do not edit the release version,
`.release-please-manifest.json`, or generated changelog entry in an ordinary
change; Release Please owns those files. Do not commit dependency directories,
release archives, credentials, database exports, logs, or private site data.

By submitting a contribution, you agree that it may be distributed under this
project's GPL-2.0-or-later license.

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

`composer analyze` runs blocking PHPStan level 6 with PHP 8.2 and WordPress 7.0
signatures. It directly selects all current production PHP: recursive `src/`
and `views/`, `index.php`, and the plugin entry point (15 files). Tests, fixtures
and release scripts retain their other checks; this gate does not claim to
analyse them. There is no blanket baseline or production exclusion.

Analysis discovers the real Core declarations at the tag/commit certified in
`composer.json`, without executing Core or loading the unit-test doubles. The
first `composer analyze` (or explicit `composer analysis:setup`) needs Git and
network access to prepare `vendor/ran-certified-core/source`. Subsequent runs
verify the cached tag, HEAD and clean worktree and can run offline. A dirty or
stale cache fails; inspect it and remove that disposable directory before
preparing it again. Never alter the certification tuple just to make analysis
pass. Core API constants are dynamic for the runtime compatibility guards;
two precisely matched, counted POST-guard diagnostics remain excepted under
#42 because other plugins can mutate the request global.

`composer test:analysis-contract` checks clean source and injects bad return and
missing certified-Core method calls into a new source file, a new template,
and each root PHP file in a disposable copy. It runs in `composer check`.
The required repository Quality lane uses that complete aggregate on PRs and
main; runtime archive and certified-Core checks remain separate required jobs.

`composer analysis:coverage -- <finished-runtime.zip>` compares every shipped
PHP file (including PHP added beneath `assets/`) with the locked PHPStan CLI's
actual direct file selection and exact local source bytes. Run `composer
analysis:setup` first. Imported configuration, exclusion globs, extension filters
and stub exclusions use PHPStan's own semantics; scan-only declarations do not
count. The internal discovery API is explicitly qualified for locked PHPStan
2.2.16 and must be reviewed on a future upgrade. This proves selection, not a
second analysis pass or the absence of narrowly reviewed diagnostic exceptions.

Required Quality downloads the existing single-build archive, checks its recorded
digest and source metadata, and runs the guard after `composer check`; it does
not rebuild release bytes. `composer test:analysis-coverage-contract` runs in
`composer check` using a disposable Git repository and the real release builder.
It proves clean-package acceptance, rejection of newly shipped uncovered PHP,
imported selection/exclusions, scan-only/stub/extension behavior and byte binding.

Level 7 remains a later boundary-typing slice: its measured findings concern
candidate provider narrowing, the injected database seam and template facade
types. Adoption at level 6 does not waive that remaining work.

Use a Conventional Commit title. Do not edit the release version,
`.release-please-manifest.json`, or generated changelog entry in an ordinary
change; Release Please owns those files. Do not commit dependency directories,
release archives, credentials, database exports, logs, or private site data.

By submitting a contribution, you agree that it may be distributed under this
project's GPL-2.0-or-later license.

## Portability naming candidate

The owned runtime naming cohort uses snake_case, including promoted properties
and named parameters. Keep foreign Admin Interaction API 2 identifiers and all
serialized/database/UI keys unchanged. Tests and CLI helper naming debt remain
excluded from this cohort; see `docs/migrator-owned-naming-inventory.md`.

The retained Core beta.22 tuple is historical API 2 provenance. Ordinary
`composer check` and certified-host gates remain mandatory and cannot qualify
this API 3 candidate until a matching immutable Core release is selected and
certified. Separate source-candidate proof is preparation evidence only; it
does not replace those gates or release #43's installed-site acceptance.

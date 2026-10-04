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

Analysis discovers real Core declarations at the exact commit/tree in
`extra.ran-booster-core-source`, without executing Core or loading unit-test
doubles. This reviewed source tuple is separate from the released-host
`ran-booster-core-certification` tag/commit. `composer analysis:setup` fetches
only the pinned commit into `vendor/ran-source-core/source`; cached HEAD, tree,
tracked bytes and clean worktree must match, including ignored/index-hidden
changes. An existing wrong cache fails rather than being silently replaced.

Canonical `composer check` uses that source tuple and runs the real facade/DTO
behavior proof, including source fingerprints, nonce payloads and verified-only
cleanup. Source Quality and exact ZIP coverage do not certify an immutable host.
`composer analyze:certified` separately prepares the immutable tagged Core and
analyzes the same production selection against its real declarations. Release
candidate Quality and the existing shared Release Please caller require it;
the caller binds that proof to the successful same-repository push/main Quality
head. The selected immutable beta.31 tuple supplies both required API3 surfaces.
Installed acceptance remains required.

Core API constants are dynamic for the runtime compatibility guards;
two precisely matched, counted POST-guard diagnostics remain excepted under
#42 because other plugins can mutate the request global.

`composer test:analysis-contract` checks clean source and injects bad return and
missing pinned-Core method calls into a new source file, a new template,
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
and named parameters. Keep shared Admin Interaction API 3 snake_case identifiers and all
serialized/database/UI keys unchanged. Owned tests and CLI helpers now join the enforced cohort, including inherited
test methods through `RANOwnedMethods`. PHPUnit lifecycle overrides and native
ZipArchive properties retain precise explained exceptions. Four broad helper
suppressions are replaced by named CLI/hostile-fixture exceptions; a token-aware
regression prohibits blanket suppressions. Actual-command negative controls
reject a camelCase inherited helper and a CLI variable. See `docs/migrator-owned-naming-inventory.md`.

The Core `v1.0.0-beta.31` tuple records the automated released-host baseline.
Canonical `composer check` still qualifies the independently pinned API3 source;
`composer analyze:certified` uses the actual immutable released Core declarations.
Neither source CI nor that automated host gate replaces release #43's exact
candidate installed-site acceptance.

## Next-beta shared-standard adoption

Development tooling now requires `ran/coding-standards` `^1.0.1`, locked to
published v1.0.1 at `0248066be3f4f9476ef7095d888657001488a3de`. Only that
locked package changes. The shared profile deliberately disables
`WordPress.Security.EscapeOutput.ExceptionNotEscaped`: throwing an exception
is not rendering output. Three redundant fixture directives are removed or
narrowed without changing fixture behavior or output-escaping checks.

Whole-tree checking, PHPStan level 6, the source Core tuple and immutable
beta.31 certification are preserved. This package adoption does not establish
full organisation #128 acceptance: the reserved-parameter waiver, retained
helper exceptions, SQL receiver-recognition limitation and suppression-guard
coverage still require their bounded dispositions. Release #43 continues to
require exact-candidate manual acceptance; source checks do not discharge it.

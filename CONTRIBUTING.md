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

`composer analyze` runs blocking PHPStan level 8 with PHP 8.2 and WordPress 7.0
signatures. An independent repository-root population gate runs before PHPStan:
new production files, split/moved classes and new directories must be directly
selected or the canonical command fails. Existing recursive `src`, `views` and
the two root entrypoints retain their inference scope (currently 15 PHP files).
Root `tests`, `scripts`, `vendor`, `node_modules`, `dist`, `.git` and `.workspaces`
are explicit development/dependency/output roles; nested production directories
with those names remain maintained. Root development fixtures are excluded from
analysis and symbol scanning; dependency bodies are not directly analyzed.

A raw `paths: .` expansion also scanned pinned Core entrypoints and test doubles,
changing API-constant inference and WordPress signatures. Preserve the reviewed
production roots plus the exact Core RAN scan instead. The independent population
gate makes this include-or-fail: an unadmitted root file cannot silently pass,
and explicit new selection is verified against actual PHPStan CLI discovery.
No production exclusion, baseline, gate-level or dependency change is introduced.

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
actual direct file selection and exact local source bytes. An independent recursive
inventory first checks every maintained production PHP file, even if packaging
has not yet admitted it. Nonstandard PHP extensions, case variants and PHP header
files cannot silently disappear from that population. Run `composer
analysis:setup` first. Imported configuration, exclusion globs, extension filters
and stub exclusions use PHPStan's own semantics; scan-only declarations do not
count. The internal discovery API is explicitly qualified for locked PHPStan
2.2.16 and must be reviewed on a future upgrade. This proves selection, not a
second analysis pass or the absence of narrowly reviewed diagnostic exceptions.

Required Quality downloads the existing single-build archive, checks its recorded
digest and source metadata, and runs the guard after `composer check`; it does
not rebuild release bytes. `composer test:analysis-coverage-contract` runs in
`composer check` using a disposable Git repository and the real release builder.
It proves include-or-fail before packaging, new root/nested/role-collision
coverage, root development scan isolation, imported selection/exclusions,
scan-only/stub/extension behavior and finished-archive byte binding. The analysis
contract independently proves real diagnostics in new and moved production paths.
The coverage guard also protects the existing pinned Core scan/bootstrap tuple.

All three maintained analysis profiles enforce Level 8. The production and
development symbol worlds remain separate, with the same automatic coverage
and precise reviewed exceptions.

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
ZipArchive properties retain precise explained exceptions. Broad helper suppressions are replaced by occurrence-local CLI/hostile-fixture
exceptions; a token-aware regression prohibits all disable/enable spans and
standard/category/sniff-wide ignores, regardless of case. Actual-command negative controls
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

Whole-tree checking, PHPStan level 8, the source Core tuple and immutable
beta.31 certification are preserved. This package adoption does not establish
full organisation #128 acceptance: retained
helper exceptions, SQL receiver-recognition limitation and suppression-guard
coverage still require their bounded dispositions. Release #43 continues to
require exact-candidate manual acceptance; source checks do not discharge it.

### Reserved parameter names

The reserved-keyword parameter rule applies across the entire PHP tree. Owned
Autoloader parameters use `$class_name`; the owned `WpPusherPackage` constructor
and property use `$is_private`. The named test caller and all known in-repository
readers move together. Inspection of maintained Core, Provider and Bitbucket
found no consumers of this Migrator DTO; this is a known-consumer inventory,
not a guarantee about unknown third-party code.

The retained WP Pusher row key remains `private`, including `to_array()`, source
comparisons, SQL arguments and fingerprint bytes. WordPress `get_option()` and
`get_site_option()` test stand-ins use `$default_value`, matching the genuine
WordPress declarations in locked `php-stubs/wordpress-stubs`. No reserved-name
exception remains. The actual-command standards contract proves an owned
reserved parameter is rejected.

### Global declaration prefix boundary

Owned CLI globals, six release-verifier functions and nine constants use the package
prefix. Private test globals (`ran_booster_wp_pusher_test_*`) and installed recorder
keys (`ran_migrator_proof_*`) now use `ran_booster_wp_pusher_migrator_test_*` and
`ran_booster_wp_pusher_migrator_proof_*`; producers and consumers move together.
WordPress globals, environment inputs, wire keys and fixture identities remain unchanged.
The source-row template's computed local is prefixed. Only the source-card `foreach`
`$row` binding needs an occurrence-local variable exception: source-row consumes that
exact renderer-supplied variable. All template PHP and literal HTML remain equivalent
under the documented local rename; rendered behavior is covered by the view tests.

Existing occurrence-local diagnostic annotations preserve genuine Core interception
namespaces, WordPress function stand-ins, capability constants, PHPUnit lifecycle
methods and native ZipArchive properties. The former tests-directory
`OneObjectStructurePerFile` exemption is replaced by 17 exact class-declaration
annotations in eight files. They preserve fixture load units; a newly appended class
in the same fixture must fail. Native filesystem/process/JSON calls in the CLI
verifier and archive tests retain only the exact diagnostic at each invocation.
The standalone coverage helper's terminal output is not HTML. The disposable
installed probe's local file operations and serialized active_plugins recovery
format retain exact occurrence exceptions; class-disabled decoding, digest/round-trip
checks and all caller-bound security fences remain in force. Unused SQL, Yoda and
global-override waivers are removed; three pure-value comparisons use Yoda operand order.

`MigratorNamingContractTest` rejects all PHPCS disable/enable spans, inline settings,
legacy ignore directives, case variants and ignores without an exact diagnostic and
reason. The profile guard pins recursive PHP scope, mandatory standards, prefixes,
canonical commands and configuration; excludes, severity/type weakening and narrowed
CLI sniff selection fail. PHPCS/PHPCBF-only attributes are rejected on every
element, including rules, properties and array elements. `tests/standards-contract.sh` checks real adjacent file
operations, declarations, fixture classes and template variables, plus actual checker
suppression followed by independent guard rejection. Repeated fixes must preserve bytes.

These retained exception groups are proposed for independent review of the exact
candidate under #65/#128; local green checks or this rationale do not establish
acceptance. The historical installed driver still requires its retained beta.7 /
Core beta.22 archives and an authorized disposable site. Its source-contract checks
do not replace that run, the native installed matrix or release #43 manual acceptance.


## Maintained PHP analysis (#65 / #128)

`composer analyze` runs `analyze:production` (Level 8 and pinned-Core
inference) and `analyze:development` (Level 8 in each isolated world). Recursive `tests` and `scripts`
are included by default. The installed-candidate directory and the existing
paired source-candidate entrypoint are analyzed in their separate real-Core
world. Other development files use the PHPUnit fixture world. The union covers
all 34 development PHP files, including the new minimal analysis-only WP-CLI
output signature fixture. No maintained file exemption or baseline is introduced.
The existing effective CLI discovery guard protects both populations, exact
commands, required levels, fixture isolation and post-discovery stub removal.
Future tests/scripts and installed proofs enter analysis automatically; existing
production include-or-fail and finished-ZIP proofs remain required.

Five precise internal-API notifications in the coverage helper acknowledge its
existing deliberate dependency on locked PHPStan 2.2.16 CLI/NEON internals. Four
local historical-member annotations in the paired behavior entrypoint preserve
API 2 camelCase calls/properties alongside API 3 snake_case; the immutable
baseline/current behavioral proof still executes both and compares evidence.
No broad missing-method/property category or file exclusion is added. The new
WP-CLI declaration models only documented `line`/`success` output signatures,
is never loaded at runtime, and does not model application behavior.

The installed proof retains `deleteExact` because its immutable beta.7 receiver
exposes that historical method. One exact analysis annotation accounts for this
legacy call while the development profile sees current declarations. The actual
beta.7 receiver regression executes the probe call and rejects replacing it with
current `delete_exact`; an adjacent unknown call still fails analysis. This
source-level regression does not supply installed/manual acceptance.
`composer analysis:setup` prepares that immutable historical receiver once under
`vendor/`; subsequent checks verify and reuse its bytes offline. The public
`composer analysis:coverage -- <zip>` requires exactly one existing ZIP, while
the explicit `--source` and `--development` modes remain separate inventory checks.
Remaining cleanup removes nullsafe accesses only after prior
assertions have proven non-null, narrows test-double returns to their actual
values, preserves absent legacy-property checks via Reflection, and records
expected exception assertions without tautological `assertTrue(true)` calls.
Runtime source, public API, credentials/security fences and dependencies are unchanged.

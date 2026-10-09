# RAN Booster WP Pusher Migrator

This is a removable, one-source migration bridge, not a second package manager.
Keep the WP Pusher 3.0.13 reader strict and read-only until Core has freshly
verified a Disabled adopted target. Never import legacy credentials, enable
deployment, contact providers, delete plugin files, or run WP Pusher uninstall.

This source candidate uses Core Portability API 3 and Admin Interaction
API 3 request-local boundaries. Automated released-host certification selects Core
`v1.0.0-beta.31`; exact-candidate installed/manual acceptance remains required.
Core publishes no logging capability to this add-on.
Do not duplicate Core identity, repository resolution, adoption, or
package mutation logic.

Preserve the PHP 8.2 baseline, the `RAN\BoosterWpPusherMigrator` namespace,
the small custom runtime autoloader, and the absence of activation,
deactivation, or uninstall hooks. Keep PSR-4 class filenames. Owned runtime methods, properties, parameters and
local variables use snake_case. Use the matching Admin Interaction API 3 snake_case
signatures; owned test doubles and CLI helpers also use snake_case. PHPUnit lifecycle overrides and native ZipArchive properties retain exact explained exceptions. Keep the exact runtime API and facade checks even though the
plugin header declares `Requires Plugins: ran-booster`: the header cannot
guarantee that the loaded Booster generation is compatible.

The plugin ships its small CSS files directly and has no JavaScript or compiled
asset pipeline. Keep pnpm limited to WordPress CSS linting and formatting unless
the runtime assets genuinely outgrow that model.

## Quality profile and ownership

`ran/coding-standards` owns the shared PHP/WordPress coding ancestry only. This
repository continues to own its WordPress 7.0 and PHP 8.2 support floors,
`RAN\BoosterWpPusherMigrator` prefix/identity policy, source paths, receiver-specific foreign signatures,
narrow test/helper foreign exceptions, and every Core/release-specific contract.
The opt-in `RANOwnedMethods` rule checks owned methods even in inherited classes.
Blanket test/helper suppressions are prohibited by a token-aware regression;
CLI filesystem/hostile-fixture exceptions name specific diagnostics.

`@rocketsarenostalgic/quality-config` owns the upstream WordPress Stylelint and
Prettier ancestry. This repository continues to own the `assets/**/*.css` scope,
its selector and empty-line Stylelint exceptions, ignore policy, and the explicit
absence of JavaScript or a compiled frontend pipeline.

`composer check` and `pnpm check` are the ordinary local quality contracts. The
canonical PHP check/fix pair is `composer standards` / `composer standards:fix`,
using the same `.phpcs.xml.dist` rules and scope. `composer check` includes the
disposable actual-command standards regression and repeated-fix stability proof.
`composer analyze` blocks at PHPStan level 8 with an independent repository-root
production population gate before analysis. Existing `src`, `views` and root
entrypoint inference is preserved; a new production path must be selected or
fail the canonical command before analysis. Root tests/scripts, dependencies,
build output and workspaces have explicit roles; same-named nested production
directories do not inherit those exemptions. Tests/scripts are excluded from
symbol scanning too. Preserve the exact Core RAN scan instead of scanning its
entrypoint and test doubles through a broader vendor discovery root.
Its first run prepares the exact reviewed Core source commit/tree under `vendor/`;
the same setup prepares the exact historical beta.7 receiver used by the existing
proof. Later runs verify the same consumed bytes offline. Use `composer analysis:setup`
for explicit preparation. Never substitute PHPUnit doubles or a floating Core
checkout. `composer analyze:certified` separately verifies the immutable tagged
Core and analyzes current production against it; release-candidate Quality and
the Release Please caller require that gate. Green source Quality alone is not
released-host or installed acceptance. Two counted POST-guard diagnostics are excepted in configuration under
#42; retain their defensive behavior. Analysis includes actual-command negative
controls through `composer test:analysis-contract` in `composer check`.
`composer test:analysis-coverage-contract` also proves finished-ZIP coverage drift
in a disposable repository. Required Quality applies `composer analysis:coverage
-- <zip>` to the same verified archive produced by Runtime archive, using locked
PHPStan CLI discovery (imports/exclusions/stubs included), with exact source-byte
comparison. Preserve this single-build boundary and review the internal discovery
API on any separately authorized PHPStan upgrade.

The single-build runtime archive, exact certified-Core API proof, release-candidate
validation, installed/readback evidence, and immutable-release publication
rules remain repository-owned specialist gates and must not be weakened to fit
the organisation baseline.

Run `pnpm check` and `composer check` before handoff. Use Conventional Commits
and follow [RELEASE.md](RELEASE.md) as the single source of truth for
versioning, packaging, release assets, and disposable-site evidence.

## External AI agent prohibition

Do not invoke, delegate work to, tag, enable, or otherwise use Blacksmith [code]smith,
`@codesmith-bot`, Blacksmith Autofix, Blacksmith CI Tuning, Blacksmith Testbox agents,
or any other Blacksmith AI/agent feature.

Blacksmith may be used only as infrastructure for ordinary GitHub Actions runners where
the repository workflow explicitly specifies a Blacksmith runner.

Do not click or trigger "Enable autofix", do not ask [code]smith to investigate or repair
CI, and do not call Blacksmith agent/MCP/CLI/API features that perform AI inference.

If CI fails, inspect GitHub Actions logs directly and diagnose/fix the failure yourself.

This prohibition is a cost-control requirement and must not be overridden by convenience,
CI failure, review comments, or suggestions from GitHub/Blacksmith UI.

All disable/enable spans, standard/category/sniff-wide ignores and inline
`phpcs:set` changes are rejected by the maintained-source token guard, including
comma-list and case variants. Retained exceptions name an exact diagnostic at
the smallest practical occurrence with a reason; they require independent review
of the exact candidate. The profile guard rejects scope/rule/severity weakening and PHPCS/PHPCBF-only
attributes on any element; the checker and fixer must enforce the same rules.
Green checks and an author-written rationale do not establish exception acceptance.
See the current disposition and preserved contracts in CONTRIBUTING.md and
docs/migrator-owned-naming-inventory.md.


## Maintained development analysis

`composer analyze` now retains the production level-8 profile and requires two
level-8 development worlds. Recursive tests/scripts discovery is the default;
installed-candidate proofs and the paired source-candidate behavior entrypoint
use real pinned Core declarations instead of PHPUnit Core doubles. The existing
coverage guard compares their effective union with independently discovered
maintained development PHP, verifies levels and role boundaries, and rejects
missing/stub-only files. No maintained PHP file is exempt and no baseline is added.

Keep the small analysis-only WP-CLI output declaration out of execution/autoload.
Its two documented foreign signatures are checked as maintained PHP. Exact local
PHPStan API annotations acknowledge locked CLI internals; four historical-member
annotations preserve the existing immutable API-2/API-3 behavioral comparison.
The installed beta.7 probe separately retains its exact historical `deleteExact`
call with a local annotation and real historical-receiver regression.
They are not permission for adjacent diagnostics or new exceptions. Retain the
actual pinned-generation behavioral proof and the real-Core/fixture separation
negative; do not load synthetic Core declarations into production inference.

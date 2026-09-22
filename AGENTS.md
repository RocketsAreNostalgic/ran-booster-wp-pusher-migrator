# RAN Booster WP Pusher Migrator

This is a removable, one-source migration bridge, not a second package manager.
Keep the WP Pusher 3.0.13 reader strict and read-only until Core has freshly
verified a Disabled adopted target. Never import legacy credentials, enable
deployment, contact providers, delete plugin files, or run WP Pusher uninstall.

Use Core Portability API 2 and Admin Interaction API 2 at their published
request-local boundaries. Core publishes no logging capability to this add-on.
Do not duplicate Core identity, repository resolution, adoption, or
package mutation logic.

Preserve the PHP 8.2 baseline, the `RAN\BoosterWpPusherMigrator` namespace,
the small custom runtime autoloader, and the absence of activation,
deactivation, or uninstall hooks. The PSR-4 class filenames and camelCase
OOP/API identifiers intentionally follow the Booster family; their PHPCS
exceptions are compatibility conventions, not permission to weaken unrelated
WordPress rules. Keep the exact runtime API and facade checks even though the
plugin header declares `Requires Plugins: ran-booster`: the header cannot
guarantee that the loaded Booster generation is compatible.

The plugin ships its small CSS files directly and has no JavaScript or compiled
asset pipeline. Keep pnpm limited to WordPress CSS linting and formatting unless
the runtime assets genuinely outgrow that model.

## Quality profile and ownership

`ran/coding-standards` owns the shared PHP/WordPress coding ancestry only. This
repository continues to own its WordPress 7.0 and PHP 8.2 support floors,
`RAN\BoosterWpPusherMigrator` prefix/identity policy, source paths, existing camelCase
exceptions, template/test exclusions, and every Core/release-specific contract.
The remaining naming exceptions still require contract-level review; they do
not establish full WordPress naming compliance.

`@rocketsarenostalgic/quality-config` owns the upstream WordPress Stylelint and
Prettier ancestry. This repository continues to own the `assets/**/*.css` scope,
its selector and empty-line Stylelint exceptions, ignore policy, and the explicit
absence of JavaScript or a compiled frontend pipeline.

`composer check` and `pnpm check` are the ordinary local quality contracts. The
single-build runtime archive, exact certified-Core API proof, release-candidate
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

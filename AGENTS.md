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

Run `pnpm check` and `composer check` before handoff. Use Conventional Commits
and follow [RELEASE.md](RELEASE.md) as the single source of truth for
versioning, packaging, release assets, and disposable-site evidence.

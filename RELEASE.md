# Prerelease guide

Release Please opens Alpha release pull requests from Conventional Commits. It
owns the plugin-header version, `CHANGELOG.md`, and
`.release-please-manifest.json`. A `fix` commit advances the patch prerelease
line, a `feat` advances the minor prerelease line, and a breaking-change marker
advances the pre-1.0 minor line.

The `0.0.0` manifest value is an unreleased bootstrap boundary. The first
release proposal must be `0.1.0-alpha.1` and must update the manifest, changelog,
and annotated plugin header together. Do not edit those generated changes or
add a manual `Release-As` footer.

## Release gate

Before merging a release pull request:

1. Run `composer validate --strict --no-check-publish` and `composer check`.
2. Build twice from clean checkouts and confirm matching ZIP SHA-256 hashes.
3. Inspect the allowlisted ZIP: one
   `ran-booster-wp-pusher-migrator/` root; no tests, tools, workflows, scripts,
   dependency directory, or repository metadata.
4. Install the ZIP beside the exact released Booster generation exposing
   Portability API 1 and Logging API 1 in a disposable single-site WordPress
   install.
5. Exercise inactive and active WP Pusher, wrong version/schema, unsupported
   providers, public/private plugin and theme rows, stale review/apply data,
   conflicting managed targets, partial cleanup recovery, and both plugin load
   orders.
6. Confirm every adopted target is freshly verified and Disabled before exact
   source-row deletion. Verify optional cleanup preserves the license key,
   unknown options, plugin files, and remote webhooks.

Merging the reviewed Release Please pull request creates the `v<version>` tag
and prerelease GitHub release. The same workflow checks out that exact tag,
repeats the full Composer gate, proves a second deterministic build, and
attaches the ZIP, portable checksum, and updater manifest. A release is
incomplete until all three verified assets are attached.

GitHub's generated source archives are not installable plugin packages. This
repository does not publish to WordPress.org, deploy a site, delete WP Pusher,
or contact a provider.

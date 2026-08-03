# Prerelease guide

Release Please opens Beta release pull requests from Conventional Commits. It
owns the synchronized plugin-header and `package.json` versions,
`CHANGELOG.md`, and `.release-please-manifest.json`. A `fix` commit advances the
patch prerelease line, a `feat` advances the minor prerelease line, and a
breaking-change marker advances the pre-1.0 minor line.

The `0.0.0` manifest value is the intentional boundary for rebooting the
prerelease line after retiring the Alpha releases. The first proposal from this
boundary must be `0.1.0-beta.1` and must update the manifest, changelog,
`package.json`, and annotated plugin header together. Do not edit those
generated version changes or add a manual `Release-As` footer.

## Release gate

Before merging a release pull request:

1. Run `composer validate --strict --no-check-publish`, `pnpm install
   --frozen-lockfile`, `pnpm check`, and `composer check`.
2. Build twice from clean checkouts and confirm matching ZIP SHA-256 hashes.
3. Inspect the allowlisted ZIP: one
   `ran-booster-wp-pusher-migrator/` root; no tests, tools, workflows, scripts,
   dependency directory, or repository metadata.
4. Install the ZIP beside the exact released Booster generation exposing
   Portability API 2 and Admin Interaction API 2, with no add-on Logging API,
   in a disposable single-site WordPress
   install. For Bitbucket coverage, also install the compatible released RAN
   Booster Bitbucket Cloud add-on.
5. Exercise inactive and active WP Pusher, wrong version/schema, unsupported
   providers, public/private plugin and theme rows, public Bitbucket adoption,
   private Bitbucket adoption with an existing replacement Booster credential
   profile, Bitbucket with the add-on missing or incompatible, stale review/apply
   data, conflicting managed targets, partial exact source-row deletion
   recovery, and both plugin load orders. Confirm GitLab rows remain visible as
   unsupported with no adoption action.
6. Confirm every adopted target is freshly verified and Disabled before exact
   source-row deletion. Verify the completion advisory links to Installed
   Plugins and the WP Pusher dashboard, describes WP Pusher-owned uninstall,
   and leaves settings, the empty package table, plugin files, and remote
   webhooks untouched.

For the `0.1.0-beta.3` train, the exact compatible Booster release used by this
gate is `v1.0.0-beta.3` at
`f24eb3cc3775d1387e7eca956c2a8f78663aca36`. The bridge consumes Portability
API 2 and Admin Interaction API 2; this proof record is not a runtime Core pin.

Merging the reviewed Release Please pull request advances the reviewed
manifest. The same workflow resolves that manifest-changing commit, checks it
out detached, repeats the complete Composer and pnpm gates, and proves a second
deterministic build. It then creates or resumes a draft tied to that exact
commit, attaches the ZIP, portable checksum, and updater manifest, downloads
all three assets, and verifies that their bytes match the local artifacts.

Publication is fail-closed. First enable GitHub immutable releases for this
repository, then set the repository Actions variable
`RAN_IMMUTABLE_RELEASES_ENABLED=true`. The workflow publishes the verified
draft only when both conditions hold and the published API response reports
the release as immutable. Until then the verified release remains a draft; do
not publish it manually or hand its assets to a customer or deployment
workflow. A release is incomplete until all three verified assets are attached
and immutable publication has been read back successfully.

GitHub's generated source archives are not installable plugin packages. This
repository does not publish to WordPress.org, deploy a site, delete WP Pusher,
or contact a provider.

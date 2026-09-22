# Prerelease guide

Release Please owns the Beta version proposal, synchronized plugin-header and
`package.json` versions, `CHANGELOG.md`, tag identity, and GitHub Release
lifecycle. Ordinary changes use a Conventional Commit pull-request title; do not
edit generated release metadata or add a manual `Release-As` footer.

The `0.0.0` manifest value is the intentional boundary used when the current
prerelease line was rebooted. Release Please remains the sole versioning engine.

## Product release contract

RAN Booster WP Pusher Migrator is a Profile B consumer because the installable
ZIP is an authoritative built artifact rather than GitHub's generated source
archive.

The repository-local contract remains:

- build one deterministic ZIP from one explicit full source commit;
- verify the ZIP against the committed allowlist and plugin metadata;
- require the plugin header, package manifest, and Release Please manifest to
  agree on the version;
- verify the exact certified RAN Booster Core tag/commit and its Portability API
  2 and Admin Interaction API 2 surfaces;
- retain the repository-specific installed/manual migration evidence required
  by the product.

The current source/CI certification uses immutable Booster
`v1.0.0-beta.22`. The sole machine-readable tag/full-commit tuple is
`extra.ran-booster-core-certification` in `composer.json`. This source
certification is not a runtime Core pin and does not replace the installed-site
release gate.

Build and verify locally from one explicit commit:

```sh
bash scripts/build-release.sh "<full-source-commit>"
bash scripts/verify-release.sh   "dist/ran-booster-wp-pusher-migrator-<version>.zip"   "<same-full-source-commit>"
```

## Shared Profile B lifecycle

The repository's `Quality` workflow is read-only release evidence.

For pull requests, pushes to `main`, and trusted exact-head
`workflow_dispatch` qualification, Quality:

1. checks out the exact event SHA with persisted credentials disabled;
2. builds and verifies the runtime archive once from that exact SHA;
3. records the ZIP SHA-256 and product metadata;
4. verifies the exact certified Core contract;
5. uploads the run-bound artifact
   `ran-booster-wp-pusher-migrator-runtime-<run-id>-<attempt>`.

The run-bound artifact contains the ZIP, its portable checksum, the richer
repository-local release metadata used by Quality, and the small shared
`ran-profile-b-promotion.json` manifest.

The promotion manifest deliberately lists only the public GitHub Release assets:

- `ran-booster-wp-pusher-migrator-<version>.zip`;
- `ran-booster-wp-pusher-migrator-<version>.zip.sha256`.

The richer product JSON remains Quality evidence and is not part of the public
release asset contract.

After an exact successful `main` Quality run, the repository's thin release
caller invokes the pinned organization Profile B reusable workflow. The shared
workflow:

1. authenticates the exact successful `main` Quality revision;
2. runs Release Please without recomputing version semantics;
3. dispatches the existing inputless Quality workflow at an exact Release
   Please candidate head when GitHub's automatic token suppresses the normal
   pull-request event;
4. requires Release Please's exact draft/tag identity for a merged release;
5. downloads the exact triggering Quality artifact by run ID and attempt;
6. verifies the promotion manifest and SHA-256 of every declared public asset;
7. uploads only missing exact assets and fails closed on any conflict;
8. publishes only after the tested assets are attached;
9. requires exact tag target, asset digests, published state, and GitHub
   `immutable == true` readback.

GitHub immutable releases are an owner-managed organization production
constraint. The release workflow does not receive a standing administration
token merely to re-read that setting. If the platform control is changed, that
is an organization settings defect, not permission for this repository to fall
back to mutable release recovery.

The release workflow never rebuilds substitute bytes, uses `--clobber`,
replaces assets on an existing published release, or maintains a separate
Release Please label/candidate state machine.

## Recovery

Published production releases are immutable.

If a release artifact or build is wrong:

```text
fix source/build
→ fresh qualification
→ new version/tag
→ new immutable release
```

Do not rebuild or replace bytes under an existing published version.

GitHub's generated source archives are not installable plugin packages. This
repository does not publish to WordPress.org, deploy a site, delete WP Pusher,
or contact a provider.

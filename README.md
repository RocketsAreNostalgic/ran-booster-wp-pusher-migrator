# RAN Booster WP Pusher Migrator

RAN Booster WP Pusher Migrator is a free, temporary bridge for moving supported
plugin and theme records from an inactive WP Pusher 3.0.13 installation into
RAN Booster.

This is Beta software for an administrator completing a one-time migration. It
is not another package manager and is intended to be removed after the retained
WP Pusher records have been dealt with.

## What it does

- Finds retained GitHub and Bitbucket Cloud package rows from the exact
  supported WP Pusher schema.
- Reviews each candidate through RAN Booster before adoption.
- Adopts packages with deployment Disabled.
- Removes only the exact WP Pusher source row after a fresh, verified adoption.
- Leaves unsupported GitLab rows visible for manual migration.

The bridge does not import credentials, install package files, enable
deployments, contact WP Pusher, delete WP Pusher, run its uninstaller, or remove
provider webhooks.

## Requirements

- WordPress 7.0 or newer and PHP 8.2 or newer.
- A single-site WordPress installation.
- WP Pusher 3.0.13 installed but inactive.
- A compatible RAN Booster release exposing Portability API 2 and Admin Interaction API 2.
- For Bitbucket Cloud packages, the compatible RAN Booster Bitbucket Cloud
  add-on installed and active.
- An existing replacement Booster credential profile for each private
  repository, including private Bitbucket repositories. The bridge never copies
  WP Pusher credentials.

The current source and CI certification use the immutable RAN Booster
`v1.0.0-beta.22` release. The exact tag and full commit are owned by
`extra.ran-booster-core-certification` in `composer.json`. The loaded API checks
remain authoritative: `Requires Plugins` and matching version numbers cannot
make an incompatible Core release compatible.

## Install

This plugin is not distributed through WordPress.org. Install the release ZIP
attached to an immutable release in this repository; GitHub's generated
**Source code** archives are not installable plugin packages.

1. Install and activate the compatible RAN Booster release first.
2. Open this repository's [Releases](https://github.com/RocketsAreNostalgic/ran-booster-wp-pusher-migrator/releases)
   page and select the intended immutable Beta release.
3. Download both
   `ran-booster-wp-pusher-migrator-<version>.zip` and its matching
   `.zip.sha256` file.
4. Verify the ZIP from the directory containing both downloads:

   ```sh
   shasum -a 256 -c ran-booster-wp-pusher-migrator-<version>.zip.sha256
   ```

5. In WordPress, open **Plugins > Add New Plugin > Upload Plugin**, choose the
   verified ZIP, and activate it.

The current Beta does not register an automatic update provider. To update,
repeat the release and checksum verification above, then upload the newer ZIP
through WordPress and confirm the replacement when prompted. The extension's
canonical `Update URI` prevents an unrelated WordPress.org package from being
offered under the same slug; it is not an update feed.

## Migrate from WP Pusher

1. Back up the site and deactivate WP Pusher.
2. Install and activate this bridge beside a compatible RAN Booster release.
3. Open Booster's Transporter screen, choose **Migrate from WP Pusher**, and
   review one retained package at a time. Confirm the Bitbucket add-on is active
   before reviewing any Bitbucket rows.
4. Apply only candidates whose installed package and repository identity match.
5. Confirm the adopted Booster package is Disabled before removing its exact
   retained WP Pusher row.
6. After all rows are migrated, delete WP Pusher through WordPress. WP Pusher's
   uninstaller removes its local data and attempts to revoke the site's license
   activation.
7. Confirm the site activation is gone in the WP Pusher dashboard and review
   any provider-side deployment webhooks separately.
8. Verify the migrated packages, then remove this bridge.

The bridge leaves WP Pusher's settings and empty package table for WP Pusher's
own uninstaller.

## Provider support

- GitHub and Bitbucket Cloud package rows can be adopted when their matching
  Booster provider is available. Public Bitbucket repositories do not need a
  credential profile.
- Private Bitbucket repositories require the compatible Bitbucket Cloud add-on
  and an existing Bitbucket credential profile in Booster.
- GitLab package rows are retained and shown as **Cannot adopt**. Migrate those
  packages manually before removing WP Pusher.

## Help and security

Read [SUPPORT.md](SUPPORT.md) before opening a public issue. Report security
problems through the confidential route in [SECURITY.md](SECURITY.md), never in
a public issue.

Contributions use the repository workflow described in
[CONTRIBUTING.md](CONTRIBUTING.md). Release construction, verification, and
publication policy is authoritative in [RELEASE.md](RELEASE.md).

## Development

Composer and pnpm install development tools only; the release ZIP contains no
dependency directory.

```sh
composer install --no-interaction --prefer-dist
pnpm install --frozen-lockfile
pnpm check
composer check
```

### Repeatable local migration fixtures

The development-only fixture set provides eight harmless installed plugins:
six repeatable GitHub adoption fixtures, one public Bitbucket provider/error
fixture, and one unsupported GitLab fixture. Run the reseed script with the
target WordPress public directory and its Local MySQL socket:

```sh
bash scripts/reseed-local-wp-pusher-fixtures.sh \
  "/path/to/site/app/public" \
  "/path/to/Local/run/site-id/mysql/mysqld.sock" \
  "http://localhost:10023"
```

The script verifies the expected site URL and exact WP Pusher table schema,
refuses to replace existing plugin paths, and inserts only missing WP Pusher
rows. It never deletes Booster management records. The first cycle tests
adoption; later reseeded cycles test the already-managed review and exact
source-row removal path. The fake Bitbucket repository is deliberately
unresolvable so **Check** exercises provider failure handling; the GitLab row
renders **Cannot adopt** without offering an action.

Historical source, archive, installed-candidate, and load-order evidence remains
under [`docs/`](docs/) for maintainers. It does not replace the release gate in
`RELEASE.md` for a new candidate.

The current [WordPress.org suitability decision](docs/wordpress-org-suitability.md)
keeps distribution on verified repository release assets.

## License

GPL-2.0-or-later. See [license.txt](license.txt).

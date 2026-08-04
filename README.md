# RAN Booster WP Pusher Migrator

This temporary, standalone WordPress plugin adopts supported package ownership
from an inactive WP Pusher 3.0.13 installation into RAN Booster.

The bridge reads only the exact supported WP Pusher package schema. It does not
import legacy credentials, install package files, enable deployments, contact
WP Pusher, or run WP Pusher's uninstall routine. Every candidate is freshly
reviewed and applied through Booster Portability API 2. Adopted packages start
with deployment Disabled.

## Requirements

- WordPress 7.0 or newer and PHP 8.2 or newer.
- A compatible RAN Booster release exposing Portability API 2 and Admin Interaction API 2.
- A single-site WordPress installation.
- WP Pusher 3.0.13 installed but inactive.
- For Bitbucket Cloud packages, the compatible RAN Booster Bitbucket Cloud
  add-on installed and active.
- An existing replacement Booster credential profile for each private
  repository, including private Bitbucket repositories. The bridge never copies
  WP Pusher credentials.

The current tested release train is RAN Booster `v1.0.0-beta.5` at commit
`c992d612a827bef2bc6dea6993e25045087b6d52`, exposing Portability API 2 and
Admin Interaction API 2. This records the release used for compatibility proof;
the bridge remains coupled to those public API generations rather than to a
particular Core implementation commit.

## Provider support

- GitHub and Bitbucket Cloud package rows can be adopted when their matching
  Booster provider is available. Public Bitbucket repositories do not need a
  credential profile.
- Private Bitbucket repositories require the compatible Bitbucket Cloud add-on
  and an existing Bitbucket credential profile in Booster.
- GitLab package rows are retained and shown as **Cannot adopt**. GitLab is not
  supported by this bridge; migrate those packages manually before removing WP
  Pusher.

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
own uninstaller. It does not delete WP Pusher, contact WP Pusher, or remove
provider webhooks.

## Development

Composer and pnpm install development tools only; the release ZIP contains
neither dependency directory.

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

See [RELEASE.md](RELEASE.md) for the authoritative release procedure.

## License

GPL-2.0-or-later. See [license.txt](license.txt).

# RAN Booster WP Pusher Migrator

This temporary, standalone WordPress plugin adopts supported package ownership
from an inactive WP Pusher 3.0.13 installation into RAN Booster.

The bridge reads only the exact supported WP Pusher package schema. It does not
import legacy credentials, install package files, enable deployments, contact
WP Pusher, or run WP Pusher's uninstall routine. Every candidate is freshly
reviewed and applied through Booster Portability API 1. Adopted packages start
with deployment Disabled.

## Requirements

- WordPress 7.0 or newer and PHP 8.2 or newer.
- A compatible RAN Booster release exposing Portability API 1 and Logging API 1.
- A single-site WordPress installation.
- WP Pusher 3.0.13 installed but inactive.
- An existing Booster credential profile for each private repository.

## Migrate from WP Pusher

1. Back up the site and deactivate WP Pusher.
2. Install and activate this bridge beside a compatible RAN Booster release.
3. Open Booster's Transporter screen, choose **Migrate from WP Pusher**, and
   review one retained package at a time.
4. Apply only candidates whose installed package and repository identity match.
5. Confirm the adopted Booster package is Disabled before removing its exact
   retained WP Pusher row.
6. After all rows are migrated, optionally remove only the known unused options
   and freshly verified empty package table offered by the bridge.
7. Verify the migrated packages, remove any provider-side deployment webhooks,
   then remove this bridge.

The bridge preserves the WP Pusher license key and unknown options. WordPress
plugin deletion can invoke WP Pusher's own uninstall behavior, so review that
separately.

## Development

Composer installs development tools only; the release ZIP contains no `vendor/`
directory.

```sh
composer install --no-interaction --prefer-dist
composer check
```

See [RELEASE.md](RELEASE.md) for the authoritative release procedure.

## License

GPL-2.0-or-later. See [license.txt](license.txt).

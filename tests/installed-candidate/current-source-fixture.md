# Current API 4 source fixture

`current-source-fixture.sh` is an additive setup/readback helper for Blueprint's
real HTTP migration tests. It does not run those tests or supersede the historical
beta.7 proof. Its consumer is the integration controller's marked disposable,
single-site WordPress installation with current Core already active. The consumer
owns network blocking, authenticated browser/HTTP requests, target plugin files,
per-request evidence, and disposal of the whole dedicated site/database.

Required inputs for every invocation:

```sh
export RAN_MIGRATOR_PROOF_DISPOSABLE=1
export RAN_MIGRATOR_WORDPRESS_PATH=/absolute/canonical/disposable-wordpress
export RAN_MIGRATOR_EXPECTED_SITE_URL=http://localhost:8080
export RAN_MIGRATOR_WP_USER=1 # existing disposable-site administrator
export RAN_MIGRATOR_ARCHIVE=/absolute/current-migrator.zip
export RAN_MIGRATOR_WP_PUSHER_ARCHIVE=/absolute/retained-wppusher.zip
# Optional: RAN_MIGRATOR_WP_CLI_BIN=/absolute/wp
bash tests/installed-candidate/current-source-fixture.sh setup
bash tests/installed-candidate/current-source-fixture.sh seed
bash tests/installed-candidate/current-source-fixture.sh readback
bash tests/installed-candidate/current-source-fixture.sh cleanup
```

The root must contain `.ran-booster-disposable-test-site` with exactly
`RAN Booster disposable test site`. The helper rejects non-localhost URLs,
multisite, users without manage_options and activate_plugins, relocated/linked content and plugin paths, and existing installation
destinations. Before installation, a WordPress preflight verifies the actual runtime
ABSPATH, WP_CONTENT_DIR and WP_PLUGIN_DIR. Setup installs WP Pusher without activating it, installs Migrator,
activates Migrator through WordPress and checks composition in a fresh process.
It never activates WP Pusher or runs its uninstall routine. No fixture creates
fake WP Pusher plugin headers or substitutes metadata.

The caller supplies the official WP Pusher 3.0.18 ZIP with SHA-256
`245b029e3ad0758ac817be0a8af715efd03c143fc66aadb1527abdec0daa681c`.
On 16 October 2026 the official download page <https://wppusher.com/download/>
linked <https://dashboard.wppusher.com/download/latest>. That URL is mutable:
verify the digest and `wppusher/wppusher.php` header version before using it.
The wrapper checks both before installation. Historical 3.0.13 provenance and
proofs remain unchanged in the separate historical lane.
The Migrator ZIP must pass the repository archive verifier against compatibility
source `ef225f13134bbca33bf910fd1ed5941f42ec9c46` (PR 80), replacing the old
PR 75 archive. Its rebuilt ZIP SHA-256 is
`005df2efc9a999b2e99b69d5c63eb3f62a40fed61db0477442894d8bb2dd6163`
(22,842 bytes); two builds from the exact source produced identical bytes.
This is a source-candidate artifact, not a release or immutable-host certification.
Both installed trees must
match the verified input archives on every invocation. The wrapper is mandatory;
direct `eval-file` of the PHP helper is not archive qualification.

`seed` creates the exact source schema only when its table is absent. An existing
schema must be supported and empty. It inserts only source ID `734001`:

- package: `ran-migrator-fixture/plugin.php` (consumer installs the inert target);
- repository: `fixtures/ran-migrator-fixture`, branch: `main`, host: `gh`;
- type: `1`, status: `1`, ptd/private: `0`, subdirectory: `null`.

The final stdout line is JSON with `phase`, `source_id`, `present`,
`source_fingerprint`, `row`, and `route_composed`. The fingerprint is produced by
the actual installed `WpPusherSource` / `WpPusherPackage`, never manufactured by
the harness. Readback reports a changed row's actual fingerprint, or null after
successful consumer cleanup. It does not mint nonces: the authenticated consumer
must retrieve actual review/apply form values and perform its own fresh requests.
For the public fixture the HTTP request uses `credential_id=''` (an empty string),
which the real controller maps to `null`. If a consumer test needs a profile, it
must explicitly create/select one through Core's existing credential setup and
submit that ID itself. This helper never reads or imports legacy credentials,
creates secrets, or selects a credential automatically.

The helper checks the real `admin_post_ran_booster_wp_pusher_migrator_package`
hook is registered against Core Portability API 4.

`cleanup` requires the fixture ownership option and deletes only an unchanged
exact fixture row through the source reader's `delete_exact`. A row already
removed by the real successful Apply is accepted. Changed rows are preserved
and cause refusal. No phase drops a table, removes other rows, changes retained
WP Pusher options, deletes plugin files or alters Core management state. The
empty table remains for the owning driver's whole-site disposal.

Source/static checks do not establish installed acceptance. The outstanding
proof is the connected real HTTP review, provider A-to-B switch, stale Apply
refusal before credentials/provider I/O, unchanged retained source row and
unchanged Core management/sidecar state, followed by successful cleanup only
after a freshly verified Disabled target. PR 75's immutable Core certification
and Migrator manual acceptance hold remain independent requirements.

## What the current evidence establishes

Fresh official 3.0.18 archive inspection found the same ten columns/types in
`Pusher/Storage/Database.php`, plugin file/type 1 and theme stylesheet/type 2 in
the storage repositories, empty branch to `master` in `Pusher/Git/Repository.php`,
and the same ten option names in `uninstall.php`. Its static database version
is still `1.0`; no persisted schema-version option was found. The reader checks
the actual schema and each record independently of that declaration or plugin
version. Seeded unit tests exercise the supported retained-data subset across
3.0.12, 3.0.13, 3.0.18 and a different major version; these are synthetic metadata
cases, not qualification of those upstream releases.

`seed` deliberately creates retained rows rather than running WP Pusher's own
activation/installation path. Thus archive/source inspection, seeded-row tests
and guarded helper execution must be reported separately from actual upstream
installation and connected HTTP migration. The latter belong to integration's
final tuple and remain outstanding until executed.

Existing conservative limits remain: integer display widths other than the
reader's supported spelling may reject a database schema; a noncanonical
subdirectory with a trailing slash may fail exact unchanged-row cleanup after
normalization. Neither is permission to broaden schema admission or deletion.

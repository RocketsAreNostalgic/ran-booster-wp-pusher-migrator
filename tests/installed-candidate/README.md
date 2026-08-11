# Installed-candidate proof

This operator-only lane is not part of the plugin ZIP and must run only against
an isolated disposable WordPress site. It verifies a caller-selected exact
future beta.6 source commit and retained archive, immutable Core beta.15, and
the exact WP Pusher 3.0.13 fixture. It snapshots the full database, exact sparse
`active_plugins` value, and all three physical plugin directories before any
mutation; uncertain cleanup retains the private recovery directory.

The caller supplies canonical paths, the expected site URL and user, local
MySQL socket/database, full beta.6 commit, and retained beta.6 ZIP digest. The
driver refuses credential-bearing environment variables, executable pre-MU
drop-ins, existing top-level MU plugins, noncanonical paths, and linked plugin
trees. It never activates WP Pusher or performs a successful migration/provider
operation.

```sh
RAN_MIGRATOR_PROOF_DISPOSABLE=1 \
RAN_MIGRATOR_WORDPRESS_PATH='<canonical ABSPATH>' \
RAN_MIGRATOR_EXPECTED_SITE_URL='<exact disposable URL>' \
RAN_MIGRATOR_WP_USER='<disposable administrator>' \
RAN_MIGRATOR_CORE_ARCHIVE='<Core beta.15 ZIP>' \
RAN_MIGRATOR_ARCHIVE='<retained Migrator beta.6 ZIP>' \
RAN_MIGRATOR_SHA256='<retained Migrator ZIP SHA-256>' \
RAN_MIGRATOR_SOURCE_PATH='<clean exact beta.6 checkout>' \
RAN_MIGRATOR_SOURCE_COMMIT='<full exact beta.6 commit>' \
RAN_MIGRATOR_WP_PUSHER_ARCHIVE='<WP Pusher 3.0.13 ZIP>' \
RAN_MIGRATOR_MYSQL_SOCKET='<local disposable MySQL socket>' \
RAN_MIGRATOR_MYSQL_DATABASE='<disposable database>' \
bash tests/installed-candidate/migrator-installed-proof.sh
```

The source suite remains authoritative for fake-facade conflict, review/apply,
refusal, and partial-cleanup recovery branches. The active and wrong-version WP
Pusher refusals here are injected source-boundary cases; WP Pusher itself stays
physically inactive. This partial installed lane proves native Core composition
and the installed source boundary, but deliberately stops before installing the
Bitbucket add-on or performing a successful Portability apply, source-row
deletion, completion advisory, or WP Pusher activation. Those require separately
recoverable provider/add-on fixtures and replacement credentials. Publication
immutability remains a separate post-release readback, so this lane alone does
not close release checklist items 4-6.

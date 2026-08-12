# Installed candidate and load-order proof

This record qualifies one exact, unpublished Migrator source candidate in a
disposable WordPress runtime. It does not publish, tag or deploy the candidate;
it does not exercise package adoption; and it does not authorize provider,
WP Pusher or production-site mutation.

## Exact inputs

The proof used these immutable inputs:

- Migrator source commit
  `3db2ff9341a4795f605b80c8f0da960b83604edc`;
- candidate `ran-booster-wp-pusher-migrator-0.1.0-beta.5.zip`, 20,145 bytes,
  SHA-256
  `d6c18e21ff44ebd79a36bcf4fe85072fe0a7ec37de33dc446723d800cf21d97b`;
- RAN Booster `v1.0.0-beta.14` asset, 2,531,212 bytes, SHA-256
  `ee91446d5255a495646dd4663fbc798b79b6752bba17b22fe07b29bae3c56a62`;
- WP Pusher 3.0.13 fixture ZIP, SHA-256
  `4f1533b9b946afdf9d699ea54279ea236b7e25f3d3fc9182bb53cec295a52208`;
- WordPress 7.0.3; and
- PHP 8.2.29.

The candidate remains distinct from the existing immutable published Migrator
`v0.1.0-beta.5` ZIP described in the Phase 0 source record. Its identity is the
source commit and digest above, not its reused prerelease version string.

The driver verified all three input hashes and ZIP integrity before creating
WordPress state. After installation, recursive byte comparisons between each
installed plugin directory and its corresponding extracted ZIP root were
empty. Executed runtime readback, rather than cache or path names, reported
WordPress 7.0.3 and PHP 8.2.29. WP Pusher remained inactive.

## Disposable boundary

An owning driver created a random temporary WordPress root and a dedicated,
uniquely named database. A trap was installed before the first database or
filesystem mutation. The proof recorder was installed as a must-use plugin
before the first normal plugin installation so it covered installation,
activation and both load-order requests.

The recorder intercepted every HTTP attempt through `pre_http_request`,
appended its method and URL to an evidence log outside the WordPress root, and
returned `WP_Error`. Seventeen WordPress update-check or loopback-cron attempts
were recorded and blocked. No URL for GitHub, Bitbucket or GitLab was recorded.
No provider request completed.

One first execution stopped before lifecycle evidence because WP-CLI's eval
wrapper cannot accept a file-level `strict_types` declaration. Its trap removed
the root and candidate and dropped the database; the zero-byte eval output was
not accepted as evidence. The declaration was removed from the private probe,
the immutable inputs were restored and reverified, and the complete proof was
rerun from a fresh root and database.

After the successful run, readback proved:

- the temporary WordPress root was absent;
- the retained input-copy directory was absent; and
- the dedicated database count was zero.

No local production WordPress installation or database was used.

## Missing and wrong facade delivery

The candidate was loaded in isolation and received structurally wrong
`stdClass` deliveries on both ready actions. Reflection readback showed all
three composition properties remained `null`: migration, WP Pusher source and
Admin Interaction facade.

At this exact pre-M1 candidate, the proof also confirmed that migration-mode
and migration-flow hooks were already registered before either exact facade had
been captured. This was characterization, not an accepted compatibility
contract. The later
[M1 lifecycle correction](m1-lifecycle-and-request-order.md) defers every
feature surface until both first-valid facades have been frozen.

## Both physical plugin load orders

The driver wrote the `active_plugins` option directly, once per requested
order, then ran a fresh WordPress request. In each request the recorder captured
the real `plugin_loaded` sequence and the probe required exact equality with
the requested order.

### Core first

The observed order was:

1. `ran-booster/ran-booster.php`;
2. `ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php`.

### Migrator first

The observed order was:

1. `ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php`;
2. `ran-booster/ran-booster.php`.

In both orders:

- priority-zero wrong deliveries preceded Core's valid default-priority
  deliveries and left composition unset;
- the exact captured facade classes were
  `RAN\AddOn\Portability\NativePortabilityFacade` and
  `RAN\Admin\Interaction\CoreAdminInteractionFacade`;
- each of the two ready actions and five feature surfaces had exactly one
  callback after valid composition;
- two further deliveries of the same exact real facade objects did not add a
  callback; and
- the exact retained package fingerprint was
  `v1:777b7945e61b9d9902c3ba42daf46f37eb2e2fbf92daa91768be8e6fe1eb609c`.

The final duplicate-delivery probe truthfully recorded the second pre-M1
shortcoming: a later valid delivery replaced the request-local composed
migration object even though hook counts remained stable. The later M1 source
correction makes the first exact valid delivery win.

## Non-mutation readback

The proof seeded one exact `wppusher_packages` row and two credential sentinels,
then captured the complete record sets before either load-order request. After
both requests:

- the source-row serialization and SHA-256 were byte-identical;
- the all-ten-name legacy-option projection was byte-identical, with the same
  two records present and the same eight names absent;
- no additional record appeared under any of the ten known WP Pusher option
  names; and
- the source row and credential sentinels retained their original values.

The same package fingerprint and the same absence of provider attempts were
observed in both orders. No package adoption, managed-package file installation,
deployment, credential import, source deletion, WP Pusher uninstall or remote
write occurred.

## Gate result

The exact source candidate passes the installed WordPress, inactive WP Pusher,
wrong-delivery, both-physical-load-orders, duplicate-delivery, non-mutation and
cleanup gate. This closes installed qualification only. It does not change the
candidate's unpublished status and does not claim the existing published
beta.5 asset contains these bytes.

The subsequent M1 source gate corrected the two explicitly characterized
lifecycle defects with both delivery orders, first-valid wins, exactly-once
feature registration and no public or persistent-state expansion. This proof
remains the installed evidence for its exact pre-M1 candidate and does not claim
the later M1 archive was installed.

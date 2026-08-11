# M1 lifecycle and request-order correction

This record binds the independently reviewed M1 source correction to one exact
commit and archive. M1 changes request-local hook composition and submitted
request ordering only. It does not adopt a package, install managed-package
files, contact a provider, import credentials, add persistent state, publish an
artifact or mutate a production site.

## Exact source and budget

The immutable M1 source commit is
`57f46cc6e0091b62c1b33d8c9e3c30287344fe3f`.

The frozen Phase 0 shape was 1,375 backend PHP lines, 64 passive PHP lines and
six runtime types. M1 lands at:

- 1,371 backend PHP lines, four fewer than Phase 0;
- 64 passive PHP lines;
- six runtime types; and
- `Plugin.php` at 525 physical lines and 25 methods.

There is no new runtime type, schema, option, table, dependency, custom public
API, custom hook name, facade API or provider authority. M1 adds the public
WordPress callback `renderCompatibilityNotice()` on the existing WordPress
`admin_notices` hook; it exposes no component API. The one new boolean is
request-local static composition state and is reset by a new PHP request; it is
not persisted.

## First-valid lifecycle

Initial registration now installs only:

- the Portability-ready listener;
- the Admin-Interaction-ready listener; and
- one compatibility notice callback.

The notice reads no legacy inventory and calls no Core facade. It renders only
for a user with `activate_plugins`, only while compatible composition is
incomplete.

Each ready listener validates the exact published API generation and exact
facade contract. A wrong, null or scalar payload changes no state. The first
exact valid Portability facade creates the one request-local source and
migration service; the first exact valid Admin Interaction facade is retained.
Later duplicate or conflicting exact objects cannot replace either frozen
identity.

Only after both first-valid facades exist does the plugin register these five
feature surfaces, exactly once:

- migration-mode rendering;
- migration-flow rendering;
- overview migration prompt;
- enhanced package admin-post handling; and
- admin asset loading.

Both facade delivery orders are outcome-tested. Missing either facade leaves
all five surfaces absent. Repeated correct, conflicting correct and wrong
deliveries leave the frozen identities and hook counts unchanged.

## Authority before inventory

Native full-page and enhanced fragment submissions now share one semantic
review/apply outcome. Their response mechanics remain appropriate to their
WordPress transports.

Both boundaries enforce this exact order:

1. recognize the Migrator submission and allowlist `review|apply`;
2. require `manage_options`;
3. validate the operation-specific outer nonce;
4. only then read the WP Pusher inventory or call Core;
5. run one shared review/apply outcome; and
6. project that outcome into full-page or transporter-row output.

A malformed native submission cannot fall through to ordinary GET rendering.
Invalid operations perform no capability, inventory or Core work. Valid but
unauthorized operations stop at capability. Invalid review and apply nonces
stop before inventory. The refusal matrix asserts zero review calls, zero apply
calls and an exactly unchanged source-row set.

Ordinary authorized GET rendering remains read-only and may acquire the bounded
inventory. Successful review, stale source, verified apply, unverified apply,
cleanup-pending, completion-readback failure, missing source and unexpected
failure outcomes remain bounded. Native cleanup-pending and unverified applies
retain the typed result notice and source row. Enhanced missing-source behavior
retains HTTP 409. `MigrationService` remains the owner of fresh source
reacquisition, Core review/apply, exact cleanup and verified readback.

## Verification

The frozen source passed:

- focused lifecycle and request-boundary tests: 25 tests / 152 assertions;
- full PHPUnit on PHP 8.5: 92 tests / 994 assertions;
- full PHPUnit on PHP 8.2.29: 92 tests / 994 assertions;
- PHPCS across all four configured source groups;
- PHP 8.2 syntax checking of the changed runtime source;
- CSS Stylelint and Prettier checks; and
- Git diff whitespace checks.

Test source now totals 2,973 PHP lines and 71 test methods. Test growth is
reported separately and does not offset the four-line physical backend
reduction.

Two builds from the exact M1 commit were byte-identical. The retained M1
candidate is:

- `ran-booster-wp-pusher-migrator-0.1.0-beta.5.zip`;
- 20,454 bytes;
- SHA-256
  `5aa3c11ab8c67337193761a05eccff0a5f51b4044aac5bb7e79ec86ee995cf75`;
- 19 archive members; and
- schema-1 metadata bound to the exact M1 commit, size and digest.

The in-memory hostile verifier passed the archive again at that exact commit.
This same-version archive is an unpublished M1 candidate. It is neither the
previous Phase 0 candidate nor the existing immutable published beta.5 asset.
No installed-runtime or publication claim is made for the M1 archive.

## Next gate

The objective M2 exception does not pass: `Plugin.php` is 525 lines / 25
methods, above the 350-line / 18-method exception boundary, and still owns
lifecycle, request transport and presentation composition. M2 therefore remains
the required bounded cohesion gate. It must preserve this lifecycle and
authority ordering and must not treat line count alone as permission to distort
correctness or add a migration framework.

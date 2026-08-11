# Phase 0 source certification

This record freezes the source-only gate before Migrator lifecycle or request
behaviour changes. It does not install, activate, publish or deploy anything;
it does not read or change WP Pusher data or credentials.

## Frozen baseline

- Reviewed source: `47cedf05310c4bbf49cc57d76aa9974ff5165f25`.
- Shipped PHP: 1,439 lines across 13 files.
- Entrypoint plus `src/`: 1,194 lines; stateful/actionable views: 181 lines;
  passive presentation: 64 lines. The Phase 0 backend ceiling is therefore
  1,375 lines.
- Named runtime declarations: six.
- Test PHP: 2,049 lines before Phase 0 certification tests.
- Asset CSS: 112 lines.

The current `source-card.php` and `source-row.php` views still derive
actionability and call the interaction facade, so their 181 lines remain in the
backend counter. Moving code into them would not be physical backend deletion.
Tests, passive presentation, assets, release metadata, documentation and
tooling are reported separately and cannot offset backend growth.

### Reproducible counters

Counters were rerun with Git 2.39.5 (Apple Git-154), awk 20200816 and PHP
8.5.0. The immutable baseline object is the reviewed source above. Physical
lines include a final non-newline-terminated line. The exact counter is:

```sh
baseline=47cedf05310c4bbf49cc57d76aa9974ff5165f25
count_object_lines() {
  total=0
  for path in "$@"; do
    lines="$(git show "${baseline}:${path}" | awk 'END { print NR }')"
    total=$((total + lines))
  done
  printf '%s\n' "$total"
}

count_object_lines \
  index.php ran-booster-wp-pusher-migrator.php \
  $(git ls-tree -r --name-only "$baseline" -- src) \
  views/source-card.php views/source-row.php
count_object_lines \
  views/migration-complete.php views/migration-mode.php \
  views/overview-prompt.php
count_object_lines \
  $(git ls-tree -r --name-only "$baseline" -- tests | awk '/\.php$/')
git grep -E '^[[:space:]]*public function test' "$baseline" -- tests | wc -l
git grep -E '^((abstract|final|readonly)[[:space:]]+)*(class|interface|trait|enum)[[:space:]]' \
  "$baseline" -- src | wc -l
count_object_lines assets/wp-pusher-migrator.css
count_object_lines $(git ls-tree -r --name-only "$baseline" -- scripts)
count_object_lines $(git ls-tree -r --name-only "$baseline" | awk '/\.md$/')
```

This yields 1,375 backend PHP lines, 64 passive PHP lines, six runtime types,
2,049 test PHP lines/48 `test*` methods, 112 CSS lines, 353 development-script
lines and 372 Markdown evidence/documentation lines at the baseline object.

For the Phase 0 source slice, the same explicit current-path sets are 1,375
backend, 64 passive, six runtime types, 2,685 test PHP lines/63 `test*` methods,
112 CSS lines, 767 development-script lines and 619 Markdown lines. Within the
script counter, exact release build/verification tooling is 232 baseline lines
(`build-release.sh` plus `verify-release.sh`) and 646 Phase 0 lines (those files
plus `core-certification.php` and `verify-release.php`). Production remains
unchanged; all growth is test, public evidence or supply-chain tooling tied to
the named invariants below.

Release metadata is an exact field inventory rather than a fungible whole-file
line bucket. Its owners are the 12 WordPress plugin-header fields and two
Release Please header markers; the synchronized `package.json` version and
`.release-please-manifest.json` value; the Release Please identity/versioning
policy; the runtime allowlist; the 14-field schema-1 release manifest; and the
six-field schema-1 CI provenance manifest. Phase 0 adds only the two-key Core
certification tuple (`tag`, `commit`) to that inventory and does not change the
two existing manifest schemas.

## Stable source contract

The external contract remains:

- entrypoint `ran-booster-wp-pusher-migrator.php`;
- exact WP Pusher source plugin `wppusher/wppusher.php` at version `3.0.13`;
- exact retained `wppusher_packages` row identity and fingerprint;
- Portability API 2 and Admin Interaction API 2;
- ready actions `ran_booster_portability_ready` and
  `ran_booster_admin_interaction_ready`;
- migration mode, flow and overview prompt rendering actions;
- native `admin.php` review/apply requests and enhanced
  `admin_post_ran_booster_wp_pusher_migrator_package` transport, using outer
  nonce actions `ran-booster-wp-pusher-migrator-review-v1` and
  `ran-booster-wp-pusher-migrator-apply-v1` and field
  `ran_booster_wp_pusher_migrator_action=review|apply`;
- Portability review actions `adopt|managed|protected|blocked`, Apply statuses
  `adopted|unchanged|blocked|failed`, and `targetVerified=true` only for
  `adopted|unchanged`;
- Portability reason vocabulary `none|already_managed|management_conflict|`
  `stale_management|malformed_management|credential_required|`
  `local_secret_store_unavailable|repository_access_failed|`
  `repository_identity_mismatch|destination_conflict|provider_unavailable|`
  `provider_temporarily_unavailable|forbidden|review_changed|`
  `unexpected_failure|unsupported_runtime`;
- interaction outcomes `success|validation_failure|unexpected_failure`;
- exact retained row identity fields, in order,
  `id|package|repository|branch|type|status|ptd|host|private|subdirectory`; the
  fingerprint is exactly `v1:` plus the lowercase SHA-256 of that ordered
  ten-field JSON object encoded with `JSON_UNESCAPED_SLASHES` (and throwing on
  encoding error); and
- no Core Logging facade, legacy credential import, provider contact,
  activation/deactivation/uninstall hook or new persistent state.

Public method visibility, controller construction and reflection-based tests do
not make internal methods a supported external API.

## Exact Core source certification

`composer.json` is the sole machine-readable owner of the exact
`extra.ran-booster-core-certification` tag/full-commit tuple. The selected
immutable release is RAN Booster `v1.0.0-beta.14`, whose source exposes both
required API 2 constants and ready actions without the removed Logging facade.

The compatible Core release evidence is:

- GitHub release ID `367443490`;
- sole asset ID `507380746`;
- asset `ran-booster-1.0.0-beta.14.zip`;
- asset size `2,531,212` bytes; and
- asset digest
  `sha256:ee91446d5255a495646dd4663fbc798b79b6752bba17b22fe07b29bae3c56a62`.

The strict reader rejects invalid JSON, a missing/non-object tuple, missing or
extra keys, a non-semantic or non-`v` tag, uppercase/short/non-string commits,
and a checked-out Core whose HEAD or tag resolution contradicts the tuple.
Quality checks out the certified commit and proves that its exact tag resolves
to that commit before checking the two real facade constants and ready actions.

This is source/CI certification only. A sibling authority must retain the exact
Migrator candidate ZIP and install it beside this exact Core artifact and an
inactive WP Pusher 3.0.13 fixture before the installed and both-load-orders gate
can pass.

## Current lifecycle characterization

Source-level characterization deliberately records the pre-M1 shortcomings
without changing runtime behaviour:

- registration currently installs the two ready listeners and all five feature
  surfaces before either facade has been captured;
- a missing facade leaves its composition state absent, but the premature
  feature hooks remain registered;
- wrong facade deliveries are inert;
- both valid delivery orders capture both facades;
- repeated valid deliveries currently replace the captured composition; and
- facade delivery itself adds no further feature-hook registrations.

These observations are not desired compatibility guarantees. M1 remains
responsible for making the first exact valid facade win and registering feature
behaviour exactly once only after both exact facades are frozen. Physical
plugin load-order proof remains part of the separate installed-runtime gate.

## Explicit-commit archive boundary

The builder and verifier require the same existing 40-character source commit.
Header version and compatibility metadata, Core certification, allowlist,
runtime bytes, checksum and release manifest are all derived from that commit;
dirty tracked or untracked worktree files cannot enter the ZIP.

Before reading any member as runtime content, the verifier rejects:

- missing, extra, duplicate, case-colliding or normalized-colliding members;
- traversal, backslash, empty or dot path segments and files outside the one
  canonical plugin root;
- symlink, executable, non-regular, encrypted or unsupported member types;
- unsafe per-member, aggregate compressed/uncompressed or compression-ratio
  bounds;
- source allowlist overlap and non-regular or executable committed modes;
- checksum, release-manifest, source-commit or member-byte disagreement;
- development-only paths, removed Logging facade text or changed API/source
  version boundaries; and
- PHP syntax failure in the validated in-memory member set.

The verifier never extracts the hostile archive. Syntax checking writes only
the already validated PHP bytes to a verifier-owned `0700` temporary directory.

The current source version is still `0.1.0-beta.5`. Any newly retained
same-version archive is an unpublished candidate identified by its explicit
source commit and SHA-256, not the already published immutable
`v0.1.0-beta.5` release. That existing release is release `364564640`, targets
commit `04e148185d57b263b7c8a8d5c0e95a8b8d96e576`, and its ZIP asset `500721822`
is 19,763 bytes with digest
`sha256:627a69fce11865d17fb8dd3ae594fd59b3eebb04952a490ffa1e5dfa74d0800f`.
The version-derived manifest `tag` field alone is not publication or tag-target
proof.

## Retained source candidate

The immutable Phase 0 source commit is
`3db2ff9341a4795f605b80c8f0da960b83604edc`. Two independent builds from that
exact commit produced the same candidate bytes:

- archive `ran-booster-wp-pusher-migrator-0.1.0-beta.5.zip`;
- size `20,145` bytes;
- SHA-256
  `d6c18e21ff44ebd79a36bcf4fe85072fe0a7ec37de33dc446723d800cf21d97b`;
- checksum sidecar with that exact digest and basename; and
- schema-1 manifest bound to that exact source commit, size and digest.

The retained archive has exactly 19 members: the canonical root; three
directory members (`assets/`, `src/`, `views/`); one CSS file; `index.php`,
`license.txt` and the main plugin file; six `src/` PHP files; and five `views/`
PHP files. The exact-commit verifier passed again after the candidate and both
sidecars were copied into the privately retained proof directory.

This candidate is not the existing published beta.5 artifact described above.
It remains source-qualified and unpublished until a later, separately
authorized release process gives a new immutable release identity.

## Remaining authority

Phase 0 source certification does not authorize installed WordPress mutation,
remote writes, a version bump, a Release Please merge, a tag or publication.
The next gate is the separate disposable installed runtime proof with both
physical plugin load orders; M1 must not start before that gate completes.

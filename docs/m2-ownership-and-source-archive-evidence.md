# M2 ownership and source/archive evidence

This record binds the M2 cohesion correction to one exact source commit and
archive. M2 changes internal request-local ownership only. It does not install
or activate a plugin, read or mutate an installed WP Pusher table, import or
change credentials, contact a provider, publish an artifact or touch a
production site.

## Exact source and counters

The immutable M2 source commit is
`b2173e8f3bf8552e374b64001bda5731a4b9b476`.

At that object, the frozen Phase 0 counter paths contain:

- 1,362 backend PHP lines, 13 below the 1,375-line ceiling;
- 64 passive PHP lines;
- eight runtime types, exactly two more than M1;
- `Plugin.php` at 83 physical lines / 5 methods;
- `MigrationRequestController.php` at 270 physical lines / 14 methods;
- `MigrationPresenter.php` at 204 physical lines / 12 methods; and
- 3,021 test PHP lines / 71 test methods.

The backend reduction is physical: M1 contained 1,371 backend lines, so M2
removes nine backend lines while splitting the two approved internal final
types. Test source remains a separate evidence counter and does not offset
production growth.

The counters are reproducible from the immutable object:

```sh
commit=b2173e8f3bf8552e374b64001bda5731a4b9b476
count_object_lines() {
  total=0
  for file_path in "$@"; do
    lines="$(git show "${commit}:${file_path}" | awk 'END { print NR }')"
    total=$((total + lines))
  done
  printf '%s\n' "$total"
}

count_object_lines \
  index.php ran-booster-wp-pusher-migrator.php \
  $(git ls-tree -r --name-only "$commit" -- src) \
  views/source-card.php views/source-row.php
count_object_lines \
  views/migration-complete.php views/migration-mode.php \
  views/overview-prompt.php
count_object_lines \
  $(git ls-tree -r --name-only "$commit" -- tests | awk '/\.php$/')
git grep -E '^[[:space:]]*public function test' "$commit" -- tests | wc -l
git grep -E '^((abstract|final|readonly)[[:space:]]+)*(class|interface|trait|enum)[[:space:]]' \
  "$commit" -- src | wc -l
git show "${commit}:src/Plugin.php" | awk 'END { print NR }'
git grep -E '^[[:space:]]*(public|protected|private) function ' \
  "$commit" -- src/Plugin.php | wc -l
```

The same final two commands, with `MigrationRequestController.php` or
`MigrationPresenter.php`, reproduce their named line/method pairs.

## Internal ownership

`Plugin` is now the small request-local lifecycle and composition root. One
instance registers the two exact facade-ready listeners and the compatibility
notice. WordPress retains that instance through its registered callbacks. The
first exact valid Portability facade and first exact valid Admin Interaction
facade win; wrong, missing, repeated and conflicting deliveries are inert.

Only after both facades are frozen does `Plugin` create one `CandidateFactory`.
That same object is injected into the existing `MigrationService` and the new
presenter. The plugin then creates one request controller, which directly owns
the five feature-hook callbacks. There are no Plugin forwarding callbacks.

The resulting responsibility boundaries are:

| Owner                                | Exact responsibility                                                                                                                                                                                  |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Plugin`                             | Facade lifecycle, first-valid composition and compatibility notice                                                                                                                                    |
| `MigrationRequestController`         | Five feature-hook boundaries, operation recognition, capability and purpose-nonce ordering, WP Pusher inventory, shared review/apply outcome, native page transport and enhanced admin-post transport |
| `MigrationService`                   | Fresh source reacquisition, Core nonce action, review/apply, exact cleanup and verified readback                                                                                                      |
| `MigrationPresenter`                 | Complete passive card/row display models and migration-owned rendering                                                                                                                                |
| `source-card.php` / `source-row.php` | Passive markup, with only the exact lazy Admin Interaction form-attribute rendering seam retained at the emitted `<form>`                                                                             |

The controller still enforces operation, capability and purpose nonce before
inventory or Core work on both transports. Native cleanup-pending and
unverified results retain their typed notice and source row; enhanced missing
source remains HTTP 409. `MigrationService` retains the M1 reacquisition,
apply, cleanup and completion-readback semantics.

Both new types are final and explicitly `@internal`. M2 adds no custom public
API, custom hook, option, table, dependency, provider authority or stored
state. Reflection-based Plugin tests were removed; supported registered hooks
and controller/presenter instances now carry the evidence. The registered-flow
fixture proves the first facade objects receive review and form presentation
after conflicting exact objects are delivered, while the later objects receive
zero calls.

## Verification

The exact M2 source passed:

- focused lifecycle, request-controller and presenter tests: 36 tests / 214 assertions;
- full PHPUnit on PHP 8.5: 92 tests / 981 assertions;
- full PHPUnit on PHP 8.2.29: 92 tests / 981 assertions;
- hostile archive fixtures: 18 tests / 547 assertions;
- strict Composer manifest validation, PHP syntax checking and PHPCS;
- CSS Stylelint and Prettier checks; and
- Git diff whitespace checks.

The form-attribute seam is lazy and outcome-tested: a check row renders exactly
one check request, an actionable row exactly one import request, and imported
or unsupported rows make zero unused form-attribute calls.

## Exact archive candidate

Two builds from the exact M2 commit were byte-identical, and the result was
independently verified against the commit and release allowlist. The candidate
is:

- `ran-booster-wp-pusher-migrator-0.1.0-beta.5.zip`;
- 21,885 bytes;
- SHA-256
  `6fb4d8da06f40d532a00f7f4f554689544e5c0e4199fad2d4e747459d4039e9f`;
- 21 members; and
- schema-1 release metadata bound to the exact M2 commit, size and digest.

The exact member inventory is:

```text
ran-booster-wp-pusher-migrator/
ran-booster-wp-pusher-migrator/assets/
ran-booster-wp-pusher-migrator/assets/wp-pusher-migrator.css
ran-booster-wp-pusher-migrator/index.php
ran-booster-wp-pusher-migrator/license.txt
ran-booster-wp-pusher-migrator/ran-booster-wp-pusher-migrator.php
ran-booster-wp-pusher-migrator/src/
ran-booster-wp-pusher-migrator/src/Autoloader.php
ran-booster-wp-pusher-migrator/src/CandidateFactory.php
ran-booster-wp-pusher-migrator/src/MigrationPresenter.php
ran-booster-wp-pusher-migrator/src/MigrationRequestController.php
ran-booster-wp-pusher-migrator/src/MigrationService.php
ran-booster-wp-pusher-migrator/src/Plugin.php
ran-booster-wp-pusher-migrator/src/WpPusherPackage.php
ran-booster-wp-pusher-migrator/src/WpPusherSource.php
ran-booster-wp-pusher-migrator/views/
ran-booster-wp-pusher-migrator/views/migration-complete.php
ran-booster-wp-pusher-migrator/views/migration-mode.php
ran-booster-wp-pusher-migrator/views/overview-prompt.php
ran-booster-wp-pusher-migrator/views/source-card.php
ran-booster-wp-pusher-migrator/views/source-row.php
```

This is an unpublished, same-version M2 candidate identified by its exact
commit and digest. It is not the immutable published beta.5 release and does
not replace that release. This record qualifies source and archive only. The
earlier disposable installed-site proof covered the pre-M1 Phase 0 candidate,
not M2; no installed-runtime or publication claim is made here.

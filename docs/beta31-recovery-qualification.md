# Beta.31 consumer recovery — 2 October 2026

The original published recovery branch is preserved. This receiver completes exact
boundary guards and consumer fixtures for Provider 14 / Add-on 17 / Admin Interaction 3.
Core keeps Portability 3 and its existing hash domain. Prospective Release 8 remains
Core-owned; these satellite runtime guards do not claim that capability.

## Historical recovery checkpoint — 2 October 2026, before source CI separation

This checkpoint used published Core
`ae4de158e3ae02d99162b9b8d0babdc9269a36da` (Core draft PR #224),
whose tree `f5ad8aa484fbe63b7d966eeef96ae9afd5d78a94` exactly matches
local checkpoint `fa5265731f50dad658d1c1bda63ad3cca7168a42`.
On PHP 8.3.6, Provider has 432 passing tests / 3,354 assertions and PHPStan;
Bitbucket has 202 passing tests / 2,371 assertions and level 8 PHPStan;
Migrator has 128 passing tests / 1,462 assertions, level 6 exact-source analysis,
and behavior output identical to the previous source checkpoint, including
fingerprints, nonce strings and cleanup outcomes. This is candidate-source
evidence only; the final published pair remains subject to hosted matrix/review.

The historical released-Core certification tuples remain unchanged. Neither these
source proofs nor this recovery grant installed-site acceptance, release certification,
merge approval, or beta.31 publication. Matching immutable Core release certification,
PHP 8.2/8.5 hosted matrix, and existing installed acceptance remain outstanding.

Canonical checks after retrying existing fixtures outside the sandbox:

- Provider `composer check`: PASS, including all 33 Node release-control tests.
- Bitbucket `composer check`: PASS, including syntax/coverage/naming negatives.
- Migrator `composer check`: PHP syntax and PHPCS pass; historical Core fetching and
  exact certification verification pass, then PHPStan correctly reports 13 errors
  because the historical API 2 declarations do not provide the new snake_case
  Portability 3 / Admin Interaction 3 surface. Its source-candidate level 6 proof
  passes. The canonical released-host gate remains held until matching immutable
  Core recertification; no fallback, baseline, or suppression was introduced.

## Final reviewed source qualification — 2 October 2026

Migrator source head `83e484338f340ae7dcecc2008cfdedf4a0095d42`, tree
`c436e2d1572f5ea509ce15609355762cf69defb4`, uses the exact Core commit/tree
recorded above. With exact locked dependencies, complete `composer check` passes:
129 tests / 1,478 assertions, level 6 PHPStan, real Core behavior, analysis negative
contracts and direct shipped-file/archive coverage. `pnpm check` also passes.
Native Quality run `37003784566` passes every job, including the required `quality`
aggregate, on that reviewed source head. These results supersede the earlier
128-test checkpoint and its canonical-source failure description. Later
documentation-only commits do not change that source qualification tuple; their
native checks are recorded separately on the pull request.

## Canonical source qualification and release separation

Source Quality now analyzes against `extra.ran-booster-core-source`, the exact
Core commit/tree above, and exercises real facade/DTO behavior in `composer check`.
The previously observed thirteen historical-host errors remain an intentional
`composer analyze:certified` failure, not the canonical source analysis target.
No diagnostic exclusions or runtime fallbacks were added. Release-candidate
Quality and the shared Release Please caller require the separate immutable-host
analysis; the latter checks the exact successful same-repository push/main Quality
head before invoking the existing shared lifecycle. Historical certification and
installed acceptance remain unchanged and do not become true through source CI.

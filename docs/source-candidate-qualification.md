# Source-candidate naming qualification

The additional source-only lane checks an exact committed Core/Migrator pair.
It does **not** change the released-host certification tuple in `composer.json`,
replace `composer check`, satisfy installed-site acceptance, or authorize a
release. The historical beta.22 certification remains historical evidence.
Renamed API 3 consumers require a matching Core candidate for source analysis;
passing that analysis does not certify compatibility with beta.22.

## Run against exact sources

Install each checkout's locked Composer dependencies without changing its lock.
Use canonical absolute checkout paths and full 40-character commits. Commit
runtime changes before running: declaration/source bytes are compared with Git
blobs independently of Git's cached status and assume-unchanged flags.

From the Migrator checkout containing the proof:

```sh
bash tests/source-candidate-analysis.sh \
  /absolute/core/source <core-full-commit> <migrator-full-commit>

RAN_MIGRATOR_SOURCE_CORE=/absolute/core/source \
RAN_MIGRATOR_SOURCE_CORE_SHA=<core-full-commit> \
php tests/source-candidate-behaviour.php \
  /absolute/migrator/source candidate <migrator-full-commit> \
  > /tmp/migrator-candidate-behaviour.json
```

The analysis command derives a temporary configuration from the canonical
PHPStan configuration. It preserves level, PHP target, production paths,
extensions, dynamic constants and counted exceptions. It replaces only the
scanned Core directory, verification bootstrap and cache directory. Unexpected
configuration shape fails closed. The temporary configuration is removed on exit.
The existing certified-Core bootstrap and certification tuple are not altered.

The behaviour command loads actual Core DTO/facade declarations and Migrator
runtime classes. Its concrete test facade supplies outcomes and its existing
in-memory database fixture records source cleanup. This is a boundary
composition test, **not** native provider/adoption execution or WordPress
installed-site evidence. Core's native Portability tests remain necessary.

For paired evidence, run the same behaviour command in another PHP process
against untouched baseline checkouts, with `baseline` instead of `candidate`
and their exact commits. Compare the two JSON outputs with `cmp`. The mode
explicitly requires API 2 or API 3 respectively; it does not silently fall back
between method spellings.

## Measured first candidate

| Source | Baseline commit | Candidate commit |
| --- | --- | --- |
| Core | `8956a6a81d696d01be7b6651ba9b660474881d9a` | `2d9da38177192715e1de3868bb0ba685e90746f7` |
| Migrator | `4e707dc3f05233fbde4b06b933f1ac85ee03a494` | `5c42c91862d98cfaf7bf129bc9559d40de30f954` |

The paired behaviour output was byte-for-byte equal for candidate wire fields,
source and review fingerprints, real Core review/apply nonce strings, apply
status/reason/message and the cleanup outcome. Assertions also proved stale
source refusal before facade access, unverified-target cleanup refusal and exact
source-row deletion only after a verified result. API numbering changes do not
change these preserved hash payload domains.

On PHP 8.3.6 the baseline's complete locked `composer check` passed, including
102 tests / 1,098 assertions, blocking level-6 analysis, standards regression,
analysis negatives and shipped-file coverage controls. This local runtime does
not substitute for the PHP 8.2 CI matrix.

The additional exact-source validator was manually exercised in a separate
Core worktree: a clean exact checkout passed; a wrong full commit failed; a
changed declaration hidden with `git update-index --assume-unchanged` failed
its blob check. The modified file and index flag were restored. These are
recorded controls, not claims of a persistent automated negative-control suite.

Repeat qualification for the final reviewed heads; the first candidate pair
above is not certification of later compositions. Retain canonical formatting,
archive verification and direct shipped-file analysis coverage as separate
gates. Published-host certification requires a real published matching Core
release and truthful reviewed certification metadata. Owner-verified
interactive installed acceptance remains deferred and separate from automated
source proofs and the historical installed-candidate driver.

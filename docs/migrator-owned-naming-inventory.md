# Migrator owned naming cohort

This source-candidate cohort starts at Migrator
`4e707dc3f05233fbde4b06b933f1ac85ee03a494`. It requires the matching Core
Portability API 3 and Admin Interaction API 3 candidate. It is not
certification against a published Core release and does not complete #43's
owner-verified installed acceptance.

## Ownership and mapping

Migrator owns declarations and connected callers below. Each listed camelCase
method becomes its ordinary snake_case equivalent. Class names, namespace,
PSR-4 paths and magic methods remain unchanged.

| Runtime path | Renamed methods |
| --- | --- |
| `src/Plugin.php` | `captureAdminInteraction`, `renderCompatibilityNotice` |
| `src/CandidateFactory.php` | `pluginName`, `themeName` |
| `src/MigrationService.php` | `nonceAction`, `unchangedSource`, `coreCandidate` |
| `src/MigrationPresenter.php` | `renderMode`, `renderPanel`, `renderOverviewPrompt`, `importedRow`, `renderSourceRow`, `interactionRequest`, `errorRegionId`, `migrationUrl`, `failureMessage` |
| `src/MigrationRequestController.php` | `renderMode`, `renderPanel`, `renderOverviewPrompt`, `enqueueAssets`, `handleAdminPost`, `operationOutcome`, `migrationComplete`, `requestOperation`, `requestValue`, `isSubmittedRequest`, `invalidOperation` |
| `src/WpPusherPackage.php` | `fromRow`, `toArray`, `positiveInteger`, `oneOfIntegers`, `booleanInteger`, `oneOfStrings`, `boundedString` |
| `src/WpPusherSource.php` | `optionPresence`, `supportedPackageTablePresent`, `assertPackageTableSchema`, `deleteExact`, `assertSupported`, `assertSchema`, `packageTableExists` |

`Autoloader` already uses compliant identifiers. All runtime classes are final;
there is no Migrator override or implementation chain. Runtime parameters and
local variables also become snake_case, including variables passed into views.
The complete connected property changes are `Plugin::$adminInteraction` and
`$requestController`, the promoted `$adminInteraction` in presenter/controller,
and `WpPusherSource::$activePlugins` and `$networkActivePlugins`. Other owned
promoted properties are already compliant.

All existing audited calls were positional. New regression checks exercise named
arguments on owned factory and package APIs and the Portability DTO constructor
shape. Reflection checks protect methods, parameters and properties of all eight
owned runtime classes. Unit doubles do not establish real-Core qualification.

Connected unit consumers are `CandidateFactoryTest`, `MigrationServiceTest`,
`SourceCardViewTest`, `PluginAdminPostTest`, `WpPusherSourceTest` and
`PluginLifecycleTest`. WordPress callback method strings are renamed with their
receivers; action names, priorities and accepted argument counts stay unchanged.
The original runtime cohort excluded test/helper naming; the follow-up below
completes that separately tracked scope.

## Core boundary

Core owns the Portability declarations and native implementation. Migrator owns
only their callers and its PHPUnit doubles in `tests/fixtures/PortabilityApi.php`.
The matching Core cohort renames `nonceAction` to `nonce_action`, candidate
promotions `displayName`, `providerCode`, `credentialId` to `display_name`,
`provider_code`, `credential_id`, and apply-result promotion `targetVerified` to
`target_verified`. Facade parameter `expectedFingerprint` becomes
`expected_fingerprint` in corresponding test overrides. Review-result properties
are already compliant. These are breaking PHP contracts: the runtime checks
require exact Portability API 3 and exact Admin Interaction API 3 checks.

Other Core consumers and Core-side declarations/callers belong to the connected
Core handoff and must be reviewed there before integration. This Migrator change
does not authorize broader Core ownership.

## Preserved foreign and data contracts

Core owns the Admin Interaction API 3 declarations; Migrator owns their connected
callers and local test implementations. Both use the matching snake_case names:
`transporter_migration_source_row`, `target_element_id`, `canonical_url`,
`error_region_id`, `validation_failure`, `unexpected_failure`,
`render_form_attributes`, `is_enhanced_request`,
`respond_with_transporter_row_fragment`. The presenter-owned `error_region_id`
and the Core DTO accessor now follow the same naming contract. Native `Closure::fromCallable`, `Throwable::getMessage`, wpdb
methods, theme `exists`/`get`, and PHPUnit signatures are preserved.

No serialized key, WP Pusher column or option, source fingerprint JSON key/order,
request field, nonce text, status/reason value, row-model field, DOM attribute,
URL, CSS selector or translated output changes. Candidate arrays retain
`display_name`, `credential_id` and `provider`; the PHP DTO property name changes
are separate from those keys. Migration admission, read-only detection,
capability checks, cleanup refusal, failure messages and rendered output retain
their existing behavior.

## Separate evidence and remaining gates

The historical installed proof remains pinned to its original beta.22/API 2
composition, including its original callback expectations. It must not be run
against this candidate and described as new certification. Its identities and expectations are retained
for held #43 acceptance; development-local identifiers may be renamed without
representing a new installed proof; candidate source and installed qualification
need separately identified evidence. No existing certified-host tuple is widened.

The coordinator owns certification/version decisions, shared rules/guidance,
dependency composition and landing order. Blocking PHPStan level 6, shipped-file
archive coverage, canonical formatting, release archives and immutable-host
checks remain required. New source-candidate evidence must name exact matching
Core and Migrator commits/trees; published-host certification and owner interactive
acceptance remain distinct subsequent gates.

## Test/helper follow-up — organisation #121 and narrow #122 adoption

Base `024d8d5cbf35c46b196df06e053d08dbc542513b` already contains the runtime
migration. This follow-up renames owned PHPUnit tests/providers/helpers, local
variables, helper properties and callers, including callback/data-provider strings.
Test discovery retains the `test_` prefix. No production PHP or Core API changes.
CLI certification/archive helpers and the installed probe's local variables use
snake_case; installed release identities and legacy callback expectations remain
historical and unchanged.

The released `ran/coding-standards` v1.0.0 at
`6af816a02b7d1108ad5c990e9d0fda0af0a13de7` supplies `RANOwnedMethods` to close
WPCS's inherited-class method exemption. The manifest uses `^1.0`; the lock pins
that exact release. No other locked package changes. Test/script naming directory
exclusions are removed. Seven exact PHPUnit `setUp`/`tearDown` overrides preserve
the framework contract; native `ZipArchive::$numFiles` reads remain unchanged.

Four blanket PHPCS annotations (archive verifier, analysis-coverage CLI, archive
test and installed hostile probe) become specific diagnostic exceptions for CLI
filesystem/process APIs, proof-owned globals/SQL and hostile serialized input.
Formatting is normalized; naming rules stay active. A token-aware PHPUnit guard
rejects blanket disable/ignore comments. Actual canonical-command negatives prove
an inherited helper method and a CLI variable cannot escape naming enforcement.

Source qualification still uses the unchanged exact Core source tuple. PHPStan
remains level 6. Released-host certification, installed/manual acceptance and
release PR43 remain separate held work; this naming follow-up establishes none
of those acceptances.

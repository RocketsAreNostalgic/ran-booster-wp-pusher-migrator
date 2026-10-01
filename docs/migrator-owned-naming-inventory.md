# Migrator owned naming cohort

This source-candidate cohort starts at Migrator
`4e707dc3f05233fbde4b06b933f1ac85ee03a494`. It requires the matching Core
Portability API 3 candidate; Admin Interaction remains API 2. It is not
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
Test helper method names outside the completed runtime scope remain excluded debt.

## Core boundary

Core owns the Portability declarations and native implementation. Migrator owns
only their callers and its PHPUnit doubles in `tests/fixtures/PortabilityApi.php`.
The matching Core cohort renames `nonceAction` to `nonce_action`, candidate
promotions `displayName`, `providerCode`, `credentialId` to `display_name`,
`provider_code`, `credential_id`, and apply-result promotion `targetVerified` to
`target_verified`. Facade parameter `expectedFingerprint` becomes
`expected_fingerprint` in corresponding test overrides. Review-result properties
are already compliant. These are breaking PHP contracts: the runtime checks
require exact Portability API 3, retaining exact Admin Interaction API 2 checks.

Other Core consumers and Core-side declarations/callers belong to the connected
Core handoff and must be reviewed there before integration. This Migrator change
does not authorize broader Core ownership.

## Preserved foreign and data contracts

Admin Interaction is an excluded shared boundary. Its DTO factories/accessors,
facade methods and local test implementations retain their exact foreign names:
`transporterMigrationSourceRow`, `targetElementId`, `canonicalUrl`,
`errorRegionId`, `validationFailure`, `unexpectedFailure`,
`renderFormAttributes`, `isEnhancedRequest`, `respondWithTransporterRowFragment`.
In particular, the presenter-owned `errorRegionId` changes while the Core DTO
method does not. Native `Closure::fromCallable`, `Throwable::getMessage`, wpdb
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
against this candidate and described as new certification. Its files are retained
unchanged for held #43 acceptance; candidate source and installed qualification
need separately identified evidence. No existing certified-host tuple is widened.

The coordinator owns certification/version decisions, shared rules/guidance,
dependency composition and landing order. Blocking PHPStan level 6, shipped-file
archive coverage, canonical formatting, release archives and immutable-host
checks remain required. New source-candidate evidence must name exact matching
Core and Migrator commits/trees; published-host certification and owner interactive
acceptance remain distinct subsequent gates.

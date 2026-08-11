<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionOutcome;
use RAN\Admin\Interaction\AdminInteractionRequest;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use RAN\BoosterWpPusherMigrator\CandidateFactory;
use RAN\BoosterWpPusherMigrator\MigrationService;
use RAN\BoosterWpPusherMigrator\MigrationPresenter;
use RAN\BoosterWpPusherMigrator\MigrationRequestController;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use RuntimeException;

final class PluginAdminPostTest extends TestCase {
	private MigrationRequestController $controller;

	protected function setUp(): void {
		parent::setUp();
		$_POST = array();
		$GLOBALS['ran_booster_wp_pusher_test_can_manage']   = true;
		$GLOBALS['ran_booster_wp_pusher_test_capabilities'] = array();
		$GLOBALS['ran_booster_wp_pusher_test_events']       = array();
		$GLOBALS['ran_booster_wp_pusher_test_plugins']      = array(
			'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ),
		);
	}

	protected function tearDown(): void {
		$_POST = array();
		$GLOBALS['ran_booster_wp_pusher_test_capabilities'] = array();
		parent::tearDown();
	}

	public function testOperationCapabilityAndPurposeNoncePrecedeSourceInventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );

		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = false;
		$_POST = array( 'ran_booster_wp_pusher_migrator_action' => 'wrong' );
		try {
			$this->controller->handleAdminPost();
			self::fail( 'Invalid operation did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 400, $failure->args['response'] );
		}
		self::assertSame( array(), $GLOBALS['ran_booster_wp_pusher_test_events'] );

		$_POST = $this->reviewRequest( $source );
		try {
			$this->controller->handleAdminPost();
			self::fail( 'Unauthorized request did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame( array( 'capability:manage_options' ), $GLOBALS['ran_booster_wp_pusher_test_events'] );

		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = true;
		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$_POST = $this->reviewRequest( $source, 'wrong-nonce' );
		try {
			$this->controller->handleAdminPost();
			self::fail( 'Invalid nonce did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-review-v1',
			),
			$GLOBALS['ran_booster_wp_pusher_test_events']
		);

		$GLOBALS['ran_booster_wp_pusher_test_events'] = array();
		$_POST                                        = $this->applyRequest( $source, 'v1:' . str_repeat( 'a', 64 ) );
		$_POST['_wpnonce']                            = 'wrong-nonce';
		try {
			$this->controller->handleAdminPost();
			self::fail( 'Invalid Apply nonce did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-apply-v1',
			),
			$GLOBALS['ran_booster_wp_pusher_test_events']
		);
		self::assertSame( 0, $portability->reviewCalls );
		self::assertSame( 0, $portability->applyCalls );
		self::assertSame( array( AdminPostDatabase::fixtureRow() ), $database->rows );
	}

	public function testNativeMalformedSubmissionCannotFallThroughToGetInventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = false;
		$_POST = array( 'action' => 'ran_booster_wp_pusher_migrator_package' );

		try {
			$this->controller->renderPanel();
			self::fail( 'Malformed native submission did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 400, $failure->args['response'] );
		}

		self::assertSame( array(), $GLOBALS['ran_booster_wp_pusher_test_events'] );
		self::assertSame( 0, $portability->reviewCalls );
		self::assertSame( 0, $portability->applyCalls );
		self::assertSame( array( AdminPostDatabase::fixtureRow() ), $database->rows );
	}

	public function testNativeCapabilityAndPurposeNoncePrecedeInventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );

		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = false;
		$_POST = $this->reviewRequest( $source );
		ob_start();
		$this->controller->renderPanel();
		self::assertSame( '', (string) ob_get_clean() );
		self::assertSame( array( 'capability:manage_options' ), $GLOBALS['ran_booster_wp_pusher_test_events'] );

		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = true;
		foreach ( array( 'review', 'apply' ) as $operation ) {
			$GLOBALS['ran_booster_wp_pusher_test_events'] = array();
			$_POST                                        = 'review' === $operation
				? $this->reviewRequest( $source, 'wrong-nonce' )
				: $this->applyRequest( $source, 'v1:' . str_repeat( 'a', 64 ) );
			$_POST['_wpnonce']                            = 'wrong-nonce';
			try {
				$this->controller->renderPanel();
				self::fail( 'Invalid native nonce did not stop.' );
			} catch ( \WpDieException $failure ) {
				self::assertSame( 403, $failure->args['response'] );
			}
			self::assertSame(
				array(
					'capability:manage_options',
					'nonce:ran-booster-wp-pusher-migrator-' . $operation . '-v1',
				),
				$GLOBALS['ran_booster_wp_pusher_test_events']
			);
		}
		self::assertSame( 0, $portability->reviewCalls );
		self::assertSame( 0, $portability->applyCalls );
		self::assertSame( array( AdminPostDatabase::fixtureRow() ), $database->rows );
	}

	public function testAuthorizedNativeGetMayReadAndRenderInventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$GLOBALS['ran_booster_wp_pusher_test_events'] = array();
		$_POST                                        = array();

		ob_start();
		$this->controller->renderPanel();
		$output = (string) ob_get_clean();

		self::assertSame( 'capability:manage_options', $GLOBALS['ran_booster_wp_pusher_test_events'][0] ?? null );
		self::assertContains( 'database', $GLOBALS['ran_booster_wp_pusher_test_events'] );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
		self::assertSame( 0, $portability->reviewCalls );
	}

	public function testNativeReviewUsesTheSharedSemanticOutcomeAfterAuthorization(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                                       = $this->sourcePackage( $database );
		$GLOBALS['ran_booster_wp_pusher_test_events'] = array();
		$_POST                                        = $this->reviewRequest( $source );

		ob_start();
		$this->controller->renderPanel();
		$output = (string) ob_get_clean();

		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-review-v1',
				'database',
			),
			array_slice( $GLOBALS['ran_booster_wp_pusher_test_events'], 0, 3 )
		);
		self::assertSame( 1, $portability->reviewCalls );
		self::assertStringContainsString( '<strong>Ready to adopt</strong>', $output );
		self::assertStringContainsString( '>Adopt</button>', $output );
	}

	public function testNativeAndAdminPostShareStaleSourceValidationOutcome(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                      = $this->sourcePackage( $database );
		$_POST                       = $this->reviewRequest( $source );
		$_POST['source_fingerprint'] = 'v1:' . str_repeat( 'b', 64 );

		ob_start();
		$this->controller->renderPanel();
		$native = (string) ob_get_clean();
		$this->runHandler();

		self::assertStringContainsString( 'The WP Pusher package changed. Review it again.', $native );
		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame( 'The WP Pusher package changed. Review it again.', $interaction->outcome?->message() );
		self::assertSame( 0, $portability->reviewCalls );
	}

	public function testNativeCleanupPendingRetainsTypedApplyOutcomeAndSourceRow(): void {
		$database               = new AdminPostDatabase();
		$database->deleteResult = 0;
		$portability            = new AdminPostPortabilityFacade();
		$interaction            = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'a', 64 ) );

		ob_start();
		$this->controller->renderPanel();
		$output = (string) ob_get_clean();

		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'Imported.', $output );
		self::assertStringContainsString( 'its old WP Pusher record remains', $output );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
	}

	public function testNativeUnverifiedApplyRetainsTypedFailureAndSourceRow(): void {
		$database                    = new AdminPostDatabase();
		$portability                 = new AdminPostPortabilityFacade();
		$portability->applyStatus    = 'blocked';
		$portability->targetVerified = false;
		$interaction                 = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'a', 64 ) );

		ob_start();
		$this->controller->renderPanel();
		$output = (string) ob_get_clean();

		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'Target changed.', $output );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
	}

	public function testReviewReplacesOnlyExactRowWithCheckedImportAction(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->reviewRequest( $source );

		$this->runHandler();

		$identityHash = substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );
		$targetId     = 'ran-booster-transporter-migration-source-'
			. substr( hash( 'sha256', 'wp-pusher:package-' . $identityHash ), 0, 32 );
		self::assertSame( 'success', $interaction->outcome?->kind() );
		self::assertSame( 'wp-pusher:check-package', $interaction->outcome?->request()->operation() );
		self::assertSame( $targetId, $interaction->outcome?->request()->targetElementId() );
		self::assertStringStartsWith( '<tr id="' . $targetId . '">', trim( $interaction->fragment ) );
		self::assertStringContainsString( '<strong>Ready to adopt</strong>', $interaction->fragment );
		self::assertStringContainsString( '>Adopt</button>', $interaction->fragment );
		self::assertStringNotContainsString( '>Check</button>', $interaction->fragment );
		self::assertStringNotContainsString( '<table', $interaction->fragment );
		self::assertSame( $source->fingerprint(), $portability->reviewedSourceFingerprint );
	}

	public function testPrivateBitbucketProviderAndReplacementCredentialReachReviewAndApply(): void {
		$database                        = new AdminPostDatabase();
		$database->rows[0]['host']       = 'bb';
		$database->rows[0]['private']    = '1';
		$database->rows[0]['repository'] = 'fixture-workspace/private-plugin';
		$portability                     = new AdminPostPortabilityFacade();
		$interaction                     = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                 = $this->sourcePackage( $database );
		$_POST                  = $this->reviewRequest( $source );
		$_POST['credential_id'] = 'bitbucket_profile';

		$this->runHandler();

		self::assertSame( 'bb', $portability->candidate?->providerCode );
		self::assertSame( 'fixture-workspace/private-plugin', $portability->candidate?->repository );
		self::assertSame( 'bitbucket_profile', $portability->candidate?->credentialId );
		self::assertStringContainsString( 'name="credential_id" value="bitbucket_profile"', $interaction->fragment );

		$_POST = $this->applyRequest(
			$source,
			'v1:' . str_repeat( 'a', 64 ),
			'bitbucket_profile'
		);

		$this->runHandler();

		self::assertSame( 'bb', $portability->candidate?->providerCode );
		self::assertSame( 'bitbucket_profile', $portability->candidate?->credentialId );
	}

	public function testStaleSourceFingerprintFailsLocallyWithoutApplying(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                      = $this->sourcePackage( $database );
		$_POST                       = $this->reviewRequest( $source );
		$_POST['source_fingerprint'] = 'v1:' . str_repeat( 'b', 64 );

		$this->runHandler();

		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame( 'The WP Pusher package changed. Review it again.', $interaction->outcome?->message() );
		self::assertSame( 0, $portability->reviewCalls );
		self::assertCount( 1, $database->rows );
	}

	public function testVerifiedApplyDeletesExactSourceAndReturnsImportedManageRow(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source            = $this->sourcePackage( $database );
		$reviewFingerprint = 'v1:' . str_repeat( 'c', 64 );
		$_POST             = $this->applyRequest( $source, $reviewFingerprint );

		$this->runHandler();

		self::assertSame( 'success', $interaction->outcome?->kind() );
		self::assertSame( 'wp-pusher:import-package', $interaction->outcome?->request()->operation() );
		self::assertSame( $reviewFingerprint, $portability->expectedReviewFingerprint );
		self::assertSame( array(), $database->rows );
		self::assertStringContainsString( 'data-ran-booster-wp-pusher-migration-complete="true"', $interaction->fragment );
		self::assertStringContainsString( '<strong>Adopted</strong>', $interaction->fragment );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
		self::assertStringContainsString(
			'href="https://example.test/wp-admin/admin.php?page=ran-booster-plugins&amp;package=fixture%2Ffixture.php"',
			$interaction->fragment
		);
		self::assertStringNotContainsString( 'deployment remains off', strtolower( $interaction->fragment ) );
	}

	public function testApplyDoesNotMarkCompletionWhileAnotherSourceRemains(): void {
		$database             = new AdminPostDatabase();
		$remaining            = AdminPostDatabase::fixtureRow();
		$remaining['id']      = '2';
		$remaining['package'] = 'other/other.php';
		$database->rows[]     = $remaining;
		$portability          = new AdminPostPortabilityFacade();
		$interaction          = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'f', 64 ) );

		$this->runHandler();

		self::assertCount( 1, $database->rows );
		self::assertSame( 'other/other.php', $database->rows[0]['package'] );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
		self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
	}

	public function testCompletionReadbackFailureDoesNotClaimCompletion(): void {
		$database                               = new AdminPostDatabase();
		$database->failInventoryReadAfterDelete = true;
		$portability                            = new AdminPostPortabilityFacade();
		$interaction                            = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( '9', 64 ) );

		$this->runHandler();

		self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
	}

	public function testAlreadyManagedApplyReturnsOneLineVerifiedStatus(): void {
		$database                 = new AdminPostDatabase();
		$portability              = new AdminPostPortabilityFacade();
		$portability->applyStatus = 'unchanged';
		$interaction              = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'e', 64 ) );

		$this->runHandler();

		self::assertStringContainsString( '<strong>Adoption verified</strong>', $interaction->fragment );
		self::assertStringNotContainsString( 'Managed by Booster.', $interaction->fragment );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
	}

	public function testCleanupPendingUsesBoundedLocalFailureAndKeepsSource(): void {
		$database               = new AdminPostDatabase();
		$database->deleteResult = 0;
		$portability            = new AdminPostPortabilityFacade();
		$interaction            = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'd', 64 ) );

		$this->runHandler();

		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame(
			'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.',
			$interaction->outcome?->message()
		);
		self::assertLessThanOrEqual( 255, strlen( (string) $interaction->outcome?->message() ) );
		self::assertCount( 1, $database->rows );
	}

	public function testMissingSourceReturnsConflictWithoutReplacingTheRow(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source             = $this->sourcePackage( $database );
		$_POST              = $this->reviewRequest( $source );
		$_POST['source_id'] = '999';

		try {
			$this->controller->handleAdminPost();
			self::fail( 'Missing source did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 409, $failure->args['response'] );
		}

		self::assertNull( $interaction->outcome );
		self::assertSame( '', $interaction->fragment );
		self::assertSame( 0, $portability->reviewCalls );
	}

	public function testUnexpectedSourceFailureReturnsGenericLocalFailure(): void {
		$database                   = new AdminPostDatabase();
		$portability                = new AdminPostPortabilityFacade();
		$portability->reviewFailure = new RuntimeException( 'SECRET-CANARY /private/source.php' );
		$interaction                = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->reviewRequest( $source );

		$this->runHandler();

		self::assertSame( 'unexpected_failure', $interaction->outcome?->kind() );
		self::assertSame( 'We could not complete that request. Please try again.', $interaction->outcome?->message() );
		self::assertStringNotContainsString( 'SECRET-CANARY', (string) $interaction->outcome?->message() );
	}

	private function runHandler(): void {
		try {
			$this->controller->handleAdminPost();
			self::fail( 'The administration interaction did not terminate the response.' );
		} catch ( AdminPostResponse $response ) {
			self::assertSame( '', $response->getMessage() );
		}
	}

	private function connect(
		AdminPostDatabase $database,
		AdminPostPortabilityFacade $portability,
		AdminPostInteractionSpy $interaction
	): void {
		$source           = new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.13' ) ),
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);
		$factory          = new CandidateFactory(
			static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) )
		);
		$service          = new MigrationService( $source, $factory, $portability );
		$presenter        = new MigrationPresenter( $factory, $interaction );
		$this->controller = new MigrationRequestController( $service, $source, $presenter, $interaction );
	}

	private function sourcePackage( AdminPostDatabase $database ): WpPusherPackage {
		$source = new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.13' ) ),
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);

		return $source->packages()[0];
	}

	/** @return array<string, string> */
	private function reviewRequest( WpPusherPackage $source, string $nonce = 'ran-booster-wp-pusher-migrator-review-v1' ): array {
		return array(
			'action'                                => 'ran_booster_wp_pusher_migrator_package',
			'ran_booster_wp_pusher_migrator_action' => 'review',
			'source_id'                             => (string) $source->id,
			'source_fingerprint'                    => $source->fingerprint(),
			'_wpnonce'                              => $nonce,
		);
	}

	/** @return array<string, string> */
	private function applyRequest(
		WpPusherPackage $source,
		string $reviewFingerprint,
		string $credentialId = ''
	): array {
		return array(
			'action'                                => 'ran_booster_wp_pusher_migrator_package',
			'ran_booster_wp_pusher_migrator_action' => 'apply',
			'source_id'                             => (string) $source->id,
			'source_fingerprint'                    => $source->fingerprint(),
			'review_fingerprint'                    => $reviewFingerprint,
			'credential_id'                         => $credentialId,
			'_wpnonce'                              => 'ran-booster-wp-pusher-migrator-apply-v1',
		);
	}
}

final class AdminPostResponse extends RuntimeException {
}

final class AdminPostInteractionSpy implements AdminInteractionFacade, TransporterRowAdminInteractionFacade {

	public ?AdminInteractionOutcome $outcome = null;
	public string $fragment                  = '';

	public function renderFormAttributes( AdminInteractionRequest $request ): void {
		echo ' data-test-operation="' . esc_attr( $request->operation() ) . '"';
	}

	public function isEnhancedRequest( AdminInteractionRequest $request ): bool {
		unset( $request );

		return true;
	}

	public function respond( AdminInteractionOutcome $outcome ): never {
		$this->outcome = $outcome;
		throw new AdminPostResponse();
	}

	public function respondWithTransporterRowFragment(
		AdminInteractionOutcome $outcome,
		callable $renderFragment
	): never {
		$this->outcome = $outcome;
		ob_start();
		$renderFragment( $outcome->request()->targetElementId() );
		$this->fragment = (string) ob_get_clean();
		throw new AdminPostResponse();
	}
}

final class AdminPostPortabilityFacade extends PortabilityFacade {

	public int $reviewCalls                  = 0;
	public int $applyCalls                   = 0;
	public string $reviewedSourceFingerprint = '';
	public string $expectedReviewFingerprint = '';
	public string $applyStatus               = 'adopted';
	public bool $targetVerified              = true;
	public ?RuntimeException $reviewFailure  = null;
	public ?PortabilityCandidate $candidate  = null;

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		unset( $nonce );
		++$this->reviewCalls;
		if ( $this->reviewFailure instanceof RuntimeException ) {
			throw $this->reviewFailure;
		}
		$this->candidate                 = $candidate;
		$this->reviewedSourceFingerprint = WpPusherPackage::fromRow( AdminPostDatabase::fixtureRow() )->fingerprint();

		return new PortabilityReviewResult(
			$candidate,
			'adopt',
			'ready',
			'Ready to import.',
			'v1:' . str_repeat( 'a', 64 )
		);
	}

	public function apply(
		PortabilityCandidate $candidate,
		string $expectedFingerprint,
		string $nonce
	): PortabilityApplyResult {
		unset( $nonce );
		++$this->applyCalls;
		$this->candidate                 = $candidate;
		$this->expectedReviewFingerprint = $expectedFingerprint;

		return new PortabilityApplyResult(
			$this->applyStatus,
			$this->applyStatus,
			$this->targetVerified ? 'Imported.' : 'Target changed.',
			$this->targetVerified
		);
	}
}

final class AdminPostDatabase {

	public string $prefix  = 'wp_';
	public string $options = 'wp_options';

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $deleteResult                  = 1;
	public bool $failInventoryReadAfterDelete = false;
	/** @var list<mixed> */
	private array $preparedValues = array();
	private bool $deleted         = false;

	public function __construct() {
		$this->rows = array( self::fixtureRow() );
	}

	/** @return array<string, mixed> */
	public static function fixtureRow(): array {
		return array(
			'id'           => '1',
			'package'      => 'fixture/fixture.php',
			'repository'   => 'RocketsAreNostalgic/booster-fixture-plugin',
			'branch'       => 'main',
			'type'         => '1',
			'status'       => '1',
			'ptd'          => '0',
			'host'         => 'gh',
			'private'      => '0',
			'subdirectory' => null,
		);
	}

	public function get_var( string $query ): string|int|null {
		$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'database';
		if ( str_starts_with( $query, 'SHOW TABLES' ) ) {
			return 'wp_wppusher_packages';
		}

		return count( $this->rows );
	}

	/** @return list<array<string, mixed>> */
	public function get_results( string $query, string $output ): array {
		unset( $output );
		$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'database';
		if ( $this->failInventoryReadAfterDelete
			&& $this->deleted
			&& str_starts_with( $query, 'SELECT `' ) ) {
			throw new RuntimeException( 'Inventory readback failed.' );
		}
		if ( str_starts_with( $query, 'SHOW COLUMNS' ) ) {
			return array(
				array(
					'Field' => 'id',
					'Type'  => 'mediumint(9)',
				),
				array(
					'Field' => 'package',
					'Type'  => 'varchar(255)',
				),
				array(
					'Field' => 'repository',
					'Type'  => 'varchar(255)',
				),
				array(
					'Field' => 'branch',
					'Type'  => 'varchar(255)',
				),
				array(
					'Field' => 'type',
					'Type'  => 'int',
				),
				array(
					'Field' => 'status',
					'Type'  => 'int',
				),
				array(
					'Field' => 'ptd',
					'Type'  => 'int',
				),
				array(
					'Field' => 'host',
					'Type'  => 'varchar(10)',
				),
				array(
					'Field' => 'private',
					'Type'  => 'int',
				),
				array(
					'Field' => 'subdirectory',
					'Type'  => 'varchar(255)',
				),
			);
		}

		return $this->rows;
	}

	public function prepare( string $query, mixed ...$values ): string {
		$this->preparedValues = $values;

		return $query;
	}

	/** @return list<string> */
	public function get_col( string $query ): array {
		unset( $query );
		$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'database';

		return array();
	}

	public function query( string $query ): int|false {
		$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'database';
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' )
			&& 1 === $this->deleteResult ) {
			$sourceId      = (string) ( $this->preparedValues[0] ?? '' );
			$this->rows    = array_values(
				array_filter(
					$this->rows,
					static fn ( array $row ): bool => $sourceId !== (string) $row['id']
				)
			);
			$this->deleted = true;
		}

		return $this->deleteResult;
	}
}

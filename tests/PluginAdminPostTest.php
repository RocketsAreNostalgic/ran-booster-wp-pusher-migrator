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
			$this->controller->handle_admin_post();
			self::fail( 'Invalid operation did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 400, $failure->args['response'] );
		}
		self::assertSame( array(), $GLOBALS['ran_booster_wp_pusher_test_events'] );

		$_POST = $this->reviewRequest( $source );
		try {
			$this->controller->handle_admin_post();
			self::fail( 'Unauthorized request did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame( array( 'capability:manage_options' ), $GLOBALS['ran_booster_wp_pusher_test_events'] );

		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = true;
		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$_POST = $this->reviewRequest( $source, 'wrong-nonce' );
		try {
			$this->controller->handle_admin_post();
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
			$this->controller->handle_admin_post();
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
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( 0, $portability->apply_calls );
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
			$this->controller->render_panel();
			self::fail( 'Malformed native submission did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 400, $failure->args['response'] );
		}

		self::assertSame( array(), $GLOBALS['ran_booster_wp_pusher_test_events'] );
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( 0, $portability->apply_calls );
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
		$this->controller->render_panel();
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
				$this->controller->render_panel();
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
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( 0, $portability->apply_calls );
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
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertSame( 'capability:manage_options', $GLOBALS['ran_booster_wp_pusher_test_events'][0] ?? null );
		self::assertContains( 'database', $GLOBALS['ran_booster_wp_pusher_test_events'] );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
		self::assertSame( 0, $portability->review_calls );
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
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-review-v1',
				'database',
			),
			array_slice( $GLOBALS['ran_booster_wp_pusher_test_events'], 0, 3 )
		);
		self::assertSame( 1, $portability->review_calls );
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
		$this->controller->render_panel();
		$native = (string) ob_get_clean();
		$this->runHandler();

		self::assertStringContainsString( 'The WP Pusher package changed. Review it again.', $native );
		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame( 'The WP Pusher package changed. Review it again.', $interaction->outcome?->message() );
		self::assertSame( 0, $portability->review_calls );
	}

	public function testNativeCleanupPendingRetainsTypedApplyOutcomeAndSourceRow(): void {
		$database                = new AdminPostDatabase();
		$database->delete_result = 0;
		$portability             = new AdminPostPortabilityFacade();
		$interaction             = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'a', 64 ) );

		ob_start();
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'Imported.', $output );
		self::assertStringContainsString( 'its old WP Pusher record remains', $output );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
	}

	public function testNativeUnverifiedApplyRetainsTypedFailureAndSourceRow(): void {
		$database                     = new AdminPostDatabase();
		$portability                  = new AdminPostPortabilityFacade();
		$portability->apply_status    = 'blocked';
		$portability->target_verified = false;
		$interaction                  = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'a', 64 ) );

		ob_start();
		$this->controller->render_panel();
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

		$identity_hash = substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );
		$target_id     = 'ran-booster-transporter-migration-source-'
			. substr( hash( 'sha256', 'wp-pusher:package-' . $identity_hash ), 0, 32 );
		self::assertSame( 'success', $interaction->outcome?->kind() );
		self::assertSame( 'wp-pusher:check-package', $interaction->outcome?->request()->operation() );
		self::assertSame( $target_id, $interaction->outcome?->request()->target_element_id() );
		self::assertStringStartsWith( '<tr id="' . $target_id . '">', trim( $interaction->fragment ) );
		self::assertStringContainsString( '<strong>Ready to adopt</strong>', $interaction->fragment );
		self::assertStringContainsString( '>Adopt</button>', $interaction->fragment );
		self::assertStringNotContainsString( '>Check</button>', $interaction->fragment );
		self::assertStringNotContainsString( '<table', $interaction->fragment );
		self::assertSame( $source->fingerprint(), $portability->reviewed_source_fingerprint );
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

		self::assertSame( 'bb', $portability->candidate?->provider_code );
		self::assertSame( 'fixture-workspace/private-plugin', $portability->candidate?->repository );
		self::assertSame( 'bitbucket_profile', $portability->candidate?->credential_id );
		self::assertStringContainsString( 'name="credential_id" value="bitbucket_profile"', $interaction->fragment );

		$_POST = $this->applyRequest(
			$source,
			'v1:' . str_repeat( 'a', 64 ),
			'bitbucket_profile'
		);

		$this->runHandler();

		self::assertSame( 'bb', $portability->candidate?->provider_code );
		self::assertSame( 'bitbucket_profile', $portability->candidate?->credential_id );
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
		self::assertSame( 0, $portability->review_calls );
		self::assertCount( 1, $database->rows );
	}

	public function testVerifiedApplyDeletesExactSourceAndReturnsImportedManageRow(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source             = $this->sourcePackage( $database );
		$review_fingerprint = 'v1:' . str_repeat( 'c', 64 );
		$_POST              = $this->applyRequest( $source, $review_fingerprint );

		$this->runHandler();

		self::assertSame( 'success', $interaction->outcome?->kind() );
		self::assertSame( 'wp-pusher:import-package', $interaction->outcome?->request()->operation() );
		self::assertSame( $review_fingerprint, $portability->expected_review_fingerprint );
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
		$database                                   = new AdminPostDatabase();
		$database->fail_inventory_read_after_delete = true;
		$portability                                = new AdminPostPortabilityFacade();
		$interaction                                = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( '9', 64 ) );

		$this->runHandler();

		self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
	}

	public function testAlreadyManagedApplyReturnsOneLineVerifiedStatus(): void {
		$database                  = new AdminPostDatabase();
		$portability               = new AdminPostPortabilityFacade();
		$portability->apply_status = 'unchanged';
		$interaction               = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->applyRequest( $source, 'v1:' . str_repeat( 'e', 64 ) );

		$this->runHandler();

		self::assertStringContainsString( '<strong>Adoption verified</strong>', $interaction->fragment );
		self::assertStringNotContainsString( 'Managed by Booster.', $interaction->fragment );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
	}

	public function testCleanupPendingUsesBoundedLocalFailureAndKeepsSource(): void {
		$database                = new AdminPostDatabase();
		$database->delete_result = 0;
		$portability             = new AdminPostPortabilityFacade();
		$interaction             = new AdminPostInteractionSpy();
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
			$this->controller->handle_admin_post();
			self::fail( 'Missing source did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 409, $failure->args['response'] );
		}

		self::assertNull( $interaction->outcome );
		self::assertSame( '', $interaction->fragment );
		self::assertSame( 0, $portability->review_calls );
	}

	public function testUnexpectedSourceFailureReturnsGenericLocalFailure(): void {
		$database                    = new AdminPostDatabase();
		$portability                 = new AdminPostPortabilityFacade();
		$portability->review_failure = new RuntimeException( 'SECRET-CANARY /private/source.php' );
		$interaction                 = new AdminPostInteractionSpy();
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
			$this->controller->handle_admin_post();
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
		string $review_fingerprint,
		string $credential_id = ''
	): array {
		return array(
			'action'                                => 'ran_booster_wp_pusher_migrator_package',
			'ran_booster_wp_pusher_migrator_action' => 'apply',
			'source_id'                             => (string) $source->id,
			'source_fingerprint'                    => $source->fingerprint(),
			'review_fingerprint'                    => $review_fingerprint,
			'credential_id'                         => $credential_id,
			'_wpnonce'                              => 'ran-booster-wp-pusher-migrator-apply-v1',
		);
	}
}

final class AdminPostResponse extends RuntimeException {
}

final class AdminPostInteractionSpy implements AdminInteractionFacade, TransporterRowAdminInteractionFacade {

	public ?AdminInteractionOutcome $outcome = null;
	public string $fragment                  = '';

	public function render_form_attributes( AdminInteractionRequest $request ): void {
		echo ' data-test-operation="' . esc_attr( $request->operation() ) . '"';
	}

	public function is_enhanced_request( AdminInteractionRequest $request ): bool {
		unset( $request );

		return true;
	}

	public function respond( AdminInteractionOutcome $outcome ): never {
		$this->outcome = $outcome;
		throw new AdminPostResponse();
	}

	public function respond_with_transporter_row_fragment(
		AdminInteractionOutcome $outcome,
		callable $render_fragment
	): never {
		$this->outcome = $outcome;
		ob_start();
		$render_fragment( $outcome->request()->target_element_id() );
		$this->fragment = (string) ob_get_clean();
		throw new AdminPostResponse();
	}
}

final class AdminPostPortabilityFacade extends PortabilityFacade {

	public int $review_calls                   = 0;
	public int $apply_calls                    = 0;
	public string $reviewed_source_fingerprint = '';
	public string $expected_review_fingerprint = '';
	public string $apply_status                = 'adopted';
	public bool $target_verified               = true;
	public ?RuntimeException $review_failure   = null;
	public ?PortabilityCandidate $candidate    = null;

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		unset( $nonce );
		++$this->review_calls;
		if ( $this->review_failure instanceof RuntimeException ) {
			throw $this->review_failure;
		}
		$this->candidate                   = $candidate;
		$this->reviewed_source_fingerprint = WpPusherPackage::from_row( AdminPostDatabase::fixtureRow() )->fingerprint();

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
		string $expected_fingerprint,
		string $nonce
	): PortabilityApplyResult {
		unset( $nonce );
		++$this->apply_calls;
		$this->candidate                   = $candidate;
		$this->expected_review_fingerprint = $expected_fingerprint;

		return new PortabilityApplyResult(
			$this->apply_status,
			$this->apply_status,
			$this->target_verified ? 'Imported.' : 'Target changed.',
			$this->target_verified
		);
	}
}

final class AdminPostDatabase {

	public string $prefix  = 'wp_';
	public string $options = 'wp_options';

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $delete_result                     = 1;
	public bool $fail_inventory_read_after_delete = false;
	/** @var list<mixed> */
	private array $prepared_values = array();
	private bool $deleted          = false;

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
		if ( $this->fail_inventory_read_after_delete
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
		$this->prepared_values = $values;

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
			&& 1 === $this->delete_result ) {
			$source_id     = (string) ( $this->prepared_values[0] ?? '' );
			$this->rows    = array_values(
				array_filter(
					$this->rows,
					static fn ( array $row ): bool => $source_id !== (string) $row['id']
				)
			);
			$this->deleted = true;
		}

		return $this->delete_result;
	}
}

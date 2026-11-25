<?php


declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
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

	// phpcs:ignore RANOwnedMethods.NamingConventions.ValidMethodName.NotSnakeCase -- Exact PHPUnit lifecycle override.
	protected function setUp(): void {
		parent::setUp();
		$_GET  = array();
		$_POST = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage']      = true;
		$GLOBALS['ran_booster_wp_pusher_migrator_test_capabilities']    = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events']          = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_enqueued_styles'] = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_translations']    = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_plugins']         = array(
			'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ),
		);
	}

	// phpcs:ignore RANOwnedMethods.NamingConventions.ValidMethodName.NotSnakeCase -- Exact PHPUnit lifecycle override.
	protected function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_capabilities']    = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_enqueued_styles'] = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_translations']    = array();
		parent::tearDown();
	}

	public function test_malformed_expectation_transport_never_reaches_apply_or_cleanup(): void {
		foreach ( array( null, array(), '', '[]', '"gh"', '{', str_repeat( ' ', 1025 ) ) as $value ) {
			$database    = new AdminPostDatabase();
			$portability = new AdminPostPortabilityFacade();
			$this->connect( $database, $portability, new AdminPostInteractionSpy() );
			$source                        = $this->source_package( $database );
			$_POST                         = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
			$_POST['provider_expectation'] = $value;
			ob_start();
			$this->controller->render_panel();
			$output = (string) ob_get_clean();
			self::assertStringContainsString( 'Refresh the WP Pusher migration review.', $output );
			self::assertSame( 0, $portability->apply_calls );
			self::assertCount( 1, $database->rows );
		}
	}

	public function test_exact_expectation_transport_is_forwarded_without_selection_lookup(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$this->connect( $database, $portability, new AdminPostInteractionSpy() );
		$source                        = $this->source_package( $database );
		$expectation                   = array(
			'provider_code'              => 'gh',
			'selection_generation'       => 42,
			'implementation_fingerprint' => str_repeat( 'b', 64 ),
		);
		$_POST                         = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
		$_POST['provider_expectation'] = wp_json_encode( $expectation );
		ob_start();
		$this->controller->render_panel();
		ob_end_clean();
		self::assertSame( $expectation, $portability->provider_expectation );
	}

	public function test_operation_capability_and_purpose_nonce_precede_source_inventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );

		$GLOBALS['ran_booster_wp_pusher_migrator_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage'] = false;
		$_POST = array( 'ran_booster_wp_pusher_migrator_action' => 'wrong' );
		try {
			$this->controller->handle_admin_post();
			self::fail( 'Invalid operation did not stop.' );
		} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
			self::assertSame( 400, $failure->args['response'] );
		}
		self::assertSame( array(), $GLOBALS['ran_booster_wp_pusher_migrator_test_events'] );

		$_POST = $this->review_request( $source );
		try {
			$this->controller->handle_admin_post();
			self::fail( 'Unauthorized request did not stop.' );
		} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame( array( 'capability:manage_options' ), $GLOBALS['ran_booster_wp_pusher_migrator_test_events'] );

		$GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage'] = true;
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events']     = array();
		$_POST = $this->review_request( $source, 'wrong-nonce' );
		try {
			$this->controller->handle_admin_post();
			self::fail( 'Invalid nonce did not stop.' );
		} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-review-v1',
			),
			$GLOBALS['ran_booster_wp_pusher_migrator_test_events']
		);

		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'] = array();
		$_POST             = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
		$_POST['_wpnonce'] = 'wrong-nonce';
		try {
			$this->controller->handle_admin_post();
			self::fail( 'Invalid Apply nonce did not stop.' );
		} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-apply-v1',
			),
			$GLOBALS['ran_booster_wp_pusher_migrator_test_events']
		);
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( 0, $portability->apply_calls );
		self::assertSame( array( AdminPostDatabase::fixture_row() ), $database->rows );
	}

	public function test_native_malformed_submission_cannot_fall_through_to_get_inventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage'] = false;
		$_POST = array( 'action' => 'ran_booster_wp_pusher_migrator_package' );

		try {
			$this->controller->render_panel();
			self::fail( 'Malformed native submission did not stop.' );
		} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
			self::assertSame( 400, $failure->args['response'] );
		}

		self::assertSame( array(), $GLOBALS['ran_booster_wp_pusher_migrator_test_events'] );
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( 0, $portability->apply_calls );
		self::assertSame( array( AdminPostDatabase::fixture_row() ), $database->rows );
	}

	public function test_native_capability_and_purpose_nonce_precede_inventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );

		$GLOBALS['ran_booster_wp_pusher_migrator_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage'] = false;
		$_POST = $this->review_request( $source );
		ob_start();
		$this->controller->render_panel();
		self::assertSame( '', (string) ob_get_clean() );
		self::assertSame( array( 'capability:manage_options' ), $GLOBALS['ran_booster_wp_pusher_migrator_test_events'] );

		$GLOBALS['ran_booster_wp_pusher_migrator_test_can_manage'] = true;
		foreach ( array( 'review', 'apply' ) as $operation ) {
			$GLOBALS['ran_booster_wp_pusher_migrator_test_events'] = array();
			$_POST             = 'review' === $operation
				? $this->review_request( $source, 'wrong-nonce' )
				: $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
			$_POST['_wpnonce'] = 'wrong-nonce';
			try {
				$this->controller->render_panel();
				self::fail( 'Invalid native nonce did not stop.' );
			} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
				self::assertSame( 403, $failure->args['response'] );
			}
			self::assertSame(
				array(
					'capability:manage_options',
					'nonce:ran-booster-wp-pusher-migrator-' . $operation . '-v1',
				),
				$GLOBALS['ran_booster_wp_pusher_migrator_test_events']
			);
		}
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( 0, $portability->apply_calls );
		self::assertSame( array( AdminPostDatabase::fixture_row() ), $database->rows );
	}

	public function test_authorized_native_get_may_read_and_render_inventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'] = array();
		$_POST = array();

		ob_start();
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertSame( 'capability:manage_options', $GLOBALS['ran_booster_wp_pusher_migrator_test_events'][0] ?? null );
		self::assertContains( 'database', $GLOBALS['ran_booster_wp_pusher_migrator_test_events'] );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
		self::assertSame( 0, $portability->review_calls );
	}

	public function test_native_review_uses_the_shared_semantic_outcome_after_authorization(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'] = array();
		$_POST = $this->review_request( $source );

		ob_start();
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertSame(
			array(
				'capability:manage_options',
				'nonce:ran-booster-wp-pusher-migrator-review-v1',
				'database',
			),
			array_slice( $GLOBALS['ran_booster_wp_pusher_migrator_test_events'], 0, 3 )
		);
		self::assertSame( 1, $portability->review_calls );
		self::assertStringContainsString( '<strong>Ready to adopt</strong>', $output );
		self::assertStringContainsString( '>Adopt</button>', $output );
	}

	public function test_native_and_admin_post_share_stale_source_validation_outcome(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                      = $this->source_package( $database );
		$_POST                       = $this->review_request( $source );
		$_POST['source_fingerprint'] = 'v1:' . str_repeat( 'b', 64 );

		ob_start();
		$this->controller->render_panel();
		$native = (string) ob_get_clean();
		$this->run_handler();

		self::assertStringContainsString( 'The WP Pusher package changed. Review it again.', $native );
		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame( 'The WP Pusher package changed. Review it again.', $interaction->outcome->message() );
		self::assertSame( 0, $portability->review_calls );
	}

	public function test_native_cleanup_pending_retains_typed_apply_outcome_and_source_row(): void {
		$database                = new AdminPostDatabase();
		$database->delete_result = 0;
		$portability             = new AdminPostPortabilityFacade();
		$interaction             = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		ob_start();
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'Imported.', $output );
		self::assertStringContainsString( 'its old WP Pusher record remains', $output );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
	}

	public function test_native_unverified_apply_retains_typed_failure_and_source_row(): void {
		$database                     = new AdminPostDatabase();
		$portability                  = new AdminPostPortabilityFacade();
		$portability->apply_status    = 'blocked';
		$portability->target_verified = false;
		$interaction                  = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		ob_start();
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'Target changed.', $output );
		self::assertStringContainsString( 'fixture/fixture.php', $output );
	}

	public function test_review_replaces_only_exact_row_with_checked_import_action(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->review_request( $source );

		$this->run_handler();

		$identity_hash = substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );
		$target_id     = 'ran-booster-transporter-migration-source-'
			. substr( hash( 'sha256', 'wp-pusher:package-' . $identity_hash ), 0, 32 );
		self::assertSame( 'success', $interaction->outcome?->kind() );
		self::assertSame( 'wp-pusher:check-package', $interaction->outcome->request()->operation() );
		self::assertSame( $target_id, $interaction->outcome->request()->target_element_id() );
		self::assertStringStartsWith( '<tr id="' . $target_id . '">', trim( $interaction->fragment ) );
		self::assertStringContainsString( '<strong>Ready to adopt</strong>', $interaction->fragment );
		self::assertStringContainsString( '>Adopt</button>', $interaction->fragment );
		self::assertStringNotContainsString( '>Check</button>', $interaction->fragment );
		self::assertStringNotContainsString( '<table', $interaction->fragment );
		self::assertSame( $source->fingerprint(), $portability->reviewed_source_fingerprint );
	}

	public function test_private_bitbucket_provider_and_replacement_credential_reach_review_and_apply(): void {
		$database                        = new AdminPostDatabase();
		$database->rows[0]['host']       = 'bb';
		$database->rows[0]['private']    = '1';
		$database->rows[0]['repository'] = 'fixture-workspace/private-plugin';
		$portability                     = new AdminPostPortabilityFacade();
		$interaction                     = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                 = $this->source_package( $database );
		$_POST                  = $this->review_request( $source );
		$_POST['credential_id'] = 'bitbucket_profile';

		$this->run_handler();

		self::assertSame( 'bb', $portability->candidate?->provider_code );
		self::assertSame( 'fixture-workspace/private-plugin', $portability->candidate->repository );
		self::assertSame( 'bitbucket_profile', $portability->candidate->credential_id );
		self::assertStringContainsString( 'name="credential_id" value="bitbucket_profile"', $interaction->fragment );

		$_POST = $this->apply_request(
			$source,
			'v1:' . str_repeat( 'a', 64 ),
			'bitbucket_profile'
		);

		$this->run_handler();

		self::assertSame( 'bb', $portability->candidate->provider_code );
		self::assertSame( 'bitbucket_profile', $portability->candidate->credential_id );
	}

	public function test_stale_source_fingerprint_fails_locally_without_applying(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source                      = $this->source_package( $database );
		$_POST                       = $this->review_request( $source );
		$_POST['source_fingerprint'] = 'v1:' . str_repeat( 'b', 64 );

		$this->run_handler();

		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame( 'The WP Pusher package changed. Review it again.', $interaction->outcome->message() );
		self::assertSame( 0, $portability->review_calls );
		self::assertCount( 1, $database->rows );
	}

	public function test_verified_apply_deletes_exact_source_and_returns_imported_manage_row(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source             = $this->source_package( $database );
		$review_fingerprint = 'v1:' . str_repeat( 'c', 64 );
		$_POST              = $this->apply_request( $source, $review_fingerprint );

		$this->run_handler();

		self::assertSame( 'success', $interaction->outcome?->kind() );
		self::assertSame( 'wp-pusher:import-package', $interaction->outcome->request()->operation() );
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

	public function test_apply_does_not_mark_completion_while_another_source_remains(): void {
		$database             = new AdminPostDatabase();
		$remaining            = AdminPostDatabase::fixture_row();
		$remaining['id']      = '2';
		$remaining['package'] = 'other/other.php';
		$database->rows[]     = $remaining;
		$portability          = new AdminPostPortabilityFacade();
		$interaction          = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'f', 64 ) );

		$this->run_handler();

		self::assertCount( 1, $database->rows );
		self::assertSame( 'other/other.php', $database->rows[0]['package'] );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
		self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
	}

	public function test_completion_readback_failure_does_not_claim_completion(): void {
		$database                                   = new AdminPostDatabase();
		$database->fail_inventory_read_after_delete = true;
		$portability                                = new AdminPostPortabilityFacade();
		$interaction                                = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( '9', 64 ) );

		$this->run_handler();

		self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
	}

	public function test_already_managed_apply_returns_one_line_verified_status(): void {
		$database                  = new AdminPostDatabase();
		$portability               = new AdminPostPortabilityFacade();
		$portability->apply_status = 'unchanged';
		$interaction               = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'e', 64 ) );

		$this->run_handler();

		self::assertStringContainsString( '<strong>Adoption verified</strong>', $interaction->fragment );
		self::assertStringNotContainsString( 'Managed by Booster.', $interaction->fragment );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
	}

	public function test_cleanup_pending_uses_bounded_local_failure_and_keeps_source(): void {
		$database                = new AdminPostDatabase();
		$database->delete_result = 0;
		$portability             = new AdminPostPortabilityFacade();
		$interaction             = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'd', 64 ) );

		$this->run_handler();

		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		self::assertSame(
			'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.',
			$interaction->outcome->message()
		);
		self::assertLessThanOrEqual( 255, strlen( (string) $interaction->outcome->message() ) );
		self::assertCount( 1, $database->rows );
	}

	public function test_missing_source_returns_conflict_without_replacing_the_row(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source             = $this->source_package( $database );
		$_POST              = $this->review_request( $source );
		$_POST['source_id'] = '999';

		try {
			$this->controller->handle_admin_post();
			self::fail( 'Missing source did not stop.' );
		} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
			self::assertSame( 409, $failure->args['response'] );
		}

		self::assertNull( $interaction->outcome );
		self::assertSame( '', $interaction->fragment );
		self::assertSame( 0, $portability->review_calls );
	}

	public function test_unexpected_source_failure_returns_generic_local_failure(): void {
		$database                    = new AdminPostDatabase();
		$portability                 = new AdminPostPortabilityFacade();
		$portability->review_failure = new RuntimeException( 'SECRET-CANARY /private/source.php' );
		$interaction                 = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->review_request( $source );

		$this->run_handler();

		self::assertSame( 'unexpected_failure', $interaction->outcome?->kind() );
		self::assertSame( 'We could not complete that request. Please try again.', $interaction->outcome->message() );
		self::assertStringNotContainsString( 'SECRET-CANARY', (string) $interaction->outcome->message() );
	}

	/** @return iterable<string, array{mixed, array<string, mixed>, bool}> */
	public static function enqueue_assets_cases(): iterable {
		yield 'standalone transporter page without tab' => array(
			'ran-booster_page_ran-booster-transporter',
			array(),
			true,
		);
		yield 'standalone transporter page with unrelated tab' => array(
			'ran-booster_page_ran-booster-transporter',
			array( 'tab' => 'settings' ),
			true,
		);
		yield 'toplevel page without tab defaults to overview' => array(
			'toplevel_page_ran-booster',
			array(),
			true,
		);
		yield 'toplevel page overview tab' => array(
			'toplevel_page_ran-booster',
			array( 'tab' => 'overview' ),
			true,
		);
		yield 'toplevel page portability tab' => array(
			'toplevel_page_ran-booster',
			array( 'tab' => 'portability' ),
			true,
		);
		yield 'toplevel page settings tab does not enqueue' => array(
			'toplevel_page_ran-booster',
			array( 'tab' => 'settings' ),
			false,
		);
		yield 'unrelated admin hook does not enqueue' => array(
			'plugins.php',
			array( 'tab' => 'overview' ),
			false,
		);
		yield 'unrelated booster submenu does not enqueue' => array(
			'ran-booster_page_ran-booster-plugins',
			array(),
			false,
		);
		yield 'null hook does not enqueue' => array(
			null,
			array(),
			false,
		);
		yield 'integer hook does not enqueue' => array(
			123,
			array(),
			false,
		);
	}

	/** @param array<string, mixed> $query */
	#[DataProvider( 'enqueue_assets_cases' )]
	public function test_enqueue_assets_loads_only_on_supported_admin_screens( mixed $hook, array $query, bool $expected_enqueued ): void {
		$this->connect( new AdminPostDatabase(), new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
		$_GET = $query;

		$this->controller->enqueue_assets( $hook );

		$styles = $GLOBALS['ran_booster_wp_pusher_migrator_test_enqueued_styles'];
		if ( ! $expected_enqueued ) {
			self::assertSame( array(), $styles );
			return;
		}

		$asset_path = dirname( __DIR__ ) . '/assets/wp-pusher-migrator.css';
		self::assertCount( 1, $styles );
		self::assertSame( 'ran-booster-wp-pusher-migrator', $styles[0]['handle'] );
		self::assertSame(
			'https://example.test/wp-content/plugins/ran-booster-wp-pusher-migrator/assets/wp-pusher-migrator.css',
			$styles[0]['src']
		);
		self::assertSame( array( 'ran-booster-onboarding' ), $styles[0]['deps'] );
		self::assertSame( (string) filemtime( $asset_path ), $styles[0]['ver'] );
		self::assertSame( 'all', $styles[0]['media'] );
	}

	public function test_render_panel_shows_informational_notice_when_wp_pusher_is_absent(): void {
		$database = new AdminPostDatabase();
		$this->connect_custom( $database, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy(), array() );
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'] = array();

		ob_start();
		$this->controller->render_panel();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'notice-info', $output );
		self::assertStringContainsString( 'No WP Pusher packages were detected. WP Pusher is not installed on this site.', $output );
		self::assertStringNotContainsString( 'notice-error', $output );
		self::assertStringNotContainsString( 'completion-panel--visible', $output );
		self::assertNotContains( 'database', $GLOBALS['ran_booster_wp_pusher_migrator_test_events'] );
	}

	public function test_render_panel_shows_informational_notice_when_inactive_with_no_packages(): void {
		foreach ( array( false, true ) as $table_exists ) {
			$database               = new AdminPostDatabase();
			$database->table_exists = $table_exists;
			$database->rows         = array();
			$this->connect( $database, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );

			ob_start();
			$this->controller->render_panel();
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'notice-info', $output );
			self::assertStringContainsString( 'No WP Pusher packages were detected.', $output );
			self::assertStringNotContainsString( 'notice-error', $output );
			self::assertStringNotContainsString( 'completion-panel--visible', $output );
		}
	}

	public function test_render_panel_maps_each_distinct_read_failure_to_its_own_fixed_message(): void {
		// Active plugin.
		$this->connect_custom(
			new AdminPostDatabase(),
			new AdminPostPortabilityFacade(),
			new AdminPostInteractionSpy(),
			array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.18' ) ),
			array( WpPusherSource::PLUGIN )
		);
		$active_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $active_output );
		self::assertStringContainsString( 'WP Pusher is still active. Deactivate WP Pusher before migrating retained packages.', $active_output );
		self::assertStringNotContainsString( '(code:', $active_output );

		// Multisite.
		$this->connect_custom(
			new AdminPostDatabase(),
			new AdminPostPortabilityFacade(),
			new AdminPostInteractionSpy(),
			array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.18' ) ),
			array(),
			array(),
			true
		);
		$multisite_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $multisite_output );
		self::assertStringContainsString( 'WP Pusher migration supports single-site WordPress only.', $multisite_output );
		self::assertStringNotContainsString( '(code:', $multisite_output );

		// Invalid plugin version metadata.
		$this->connect_custom(
			new AdminPostDatabase(),
			new AdminPostPortabilityFacade(),
			new AdminPostInteractionSpy(),
			array( WpPusherSource::PLUGIN => array( 'Version' => '' ) )
		);
		$metadata_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $metadata_output );
		self::assertStringContainsString( 'WP Pusher is installed, but its version metadata is missing or invalid.', $metadata_output );
		self::assertStringNotContainsString( '(code:', $metadata_output );

		// Unsupported schema.
		$schema_database                     = new AdminPostDatabase();
		$schema_database->unsupported_schema = true;
		$this->connect( $schema_database, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
		$schema_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $schema_output );
		self::assertStringContainsString( 'The retained WP Pusher package table schema is not supported.', $schema_output );
		self::assertStringNotContainsString( '(code:', $schema_output );

		// Duplicate retained rows.
		$dup_database       = new AdminPostDatabase();
		$dup_database->rows = array(
			AdminPostDatabase::fixture_row(),
			array_merge( AdminPostDatabase::fixture_row(), array( 'id' => '2' ) ),
		);
		$this->connect( $dup_database, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
		$dup_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $dup_output );
		self::assertStringContainsString( 'The retained WP Pusher package table contains duplicate package records.', $dup_output );
		self::assertStringNotContainsString( '(code:', $dup_output );

		// Database reader unavailable (closed reason + code).
		$no_reader = new class() {
			public string $prefix = 'wp_';
		};
		$this->connect_custom( $no_reader, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
		$db_reader_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $db_reader_output );
		self::assertStringContainsString( '(code: db_table_reader_unavailable)', $db_reader_output );

		// Option reader unavailable (closed reason + code).
		$no_options = new class() extends AdminPostDatabase {
			public function __construct() {
				parent::__construct();
				unset( $this->options );
			}
		};
		$this->connect_custom( $no_options, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
		$option_reader_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $option_reader_output );
		self::assertStringContainsString( '(code: db_option_reader_unavailable)', $option_reader_output );

		// Database query failure: closed reason + code, never SQL text.
		$sql_error_database             = new AdminPostDatabase();
		$sql_error_database->last_error = "SQLSTATE[42S02]: Table 'secret_db.wp_wppusher_packages' doesn't exist in SELECT `id` FROM `wp_wppusher_packages`";
		$this->connect( $sql_error_database, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
		$sql_output = $this->capture_panel();
		self::assertStringContainsString( 'notice-error', $sql_output );
		self::assertStringContainsString( '(code: db_query_failed)', $sql_output );
		self::assertStringNotContainsString( 'SQLSTATE', $sql_output );
		self::assertStringNotContainsString( 'SELECT', $sql_output );
		self::assertStringNotContainsString( 'secret_db', $sql_output );
	}

	public function test_native_apply_of_last_package_shows_completion_panel_instead_of_empty_info(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		$output = $this->capture_panel();

		self::assertSame( array(), $database->rows );
		self::assertStringContainsString( 'completion-panel--visible', $output );
		self::assertStringContainsString( 'Migration complete!', $output );
		self::assertStringNotContainsString( 'notice-info', $output );
	}

	public function test_panel_lists_valid_and_rejected_subdirectory_rows_and_keeps_completion_off_while_rejected_remains(): void {
		$database                     = new AdminPostDatabase();
		$valid_row                    = AdminPostDatabase::fixture_row();
		$valid_row['subdirectory']    = 'plugins/foo/';
		$rejected_row                 = AdminPostDatabase::fixture_row();
		$rejected_row['id']           = '2';
		$rejected_row['package']      = 'bad/bad.php';
		$rejected_row['subdirectory'] = '../<script>alert(1)</script>';
		$database->rows               = array( $valid_row, $rejected_row );
		$portability                  = new AdminPostPortabilityFacade();
		$interaction                  = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );

		$panel = $this->capture_panel();
		self::assertStringContainsString( '<code>fixture/fixture.php</code>', $panel );
		self::assertStringContainsString( '>Check</button>', $panel );
		self::assertStringContainsString( '<code>bad/bad.php</code>', $panel );
		self::assertStringContainsString(
			'Can&#039;t migrate: subdirectory &quot;../&lt;script&gt;alert(1)&lt;/script&gt;&quot; isn&#039;t a plain relative path.',
			$panel
		);
		self::assertStringNotContainsString( '<script', $panel );
		self::assertStringNotContainsString( 'notice-error', $panel );

		// Adopting the valid trailing-slash row cleans up only the valid row and keeps completion off.
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
		$this->run_handler();

		self::assertSame( 'plugins/foo', $portability->candidate?->subdirectory );
		self::assertCount( 1, $database->rows );
		self::assertSame( 'bad/bad.php', $database->rows[0]['package'] );
		self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
		self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
	}

	public function test_fragment_apply_preserves_verified_adoption_and_diagnostic_when_cleanup_throws_db_error(): void {
		$database                       = new AdminPostDatabase();
		$database->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
		$portability                    = new AdminPostPortabilityFacade();
		$interaction                    = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		$this->run_handler();

		self::assertSame( 1, $portability->apply_calls );
		self::assertCount( 1, $database->rows );
		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		$message = (string) $interaction->outcome->message();
		self::assertStringContainsString(
			'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.',
			$message
		);
		self::assertStringContainsString(
			'Could not access WP Pusher data: a database query failed (code: db_query_failed).',
			$message
		);
		self::assertLessThanOrEqual( 255, strlen( $message ) );
		self::assertStringNotContainsString( 'SQLSTATE', $message );
		self::assertStringNotContainsString( 'DELETE FROM', $message );
		self::assertStringNotContainsString( 'secret_col', $message );
	}

	public function test_native_apply_preserves_verified_adoption_and_diagnostic_when_cleanup_throws_db_error(): void {
		// Case 1: delete_exact() fails with $wpdb->last_error, then subsequent inventory() read succeeds.
		$database                       = new AdminPostDatabase();
		$database->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
		$portability                    = new AdminPostPortabilityFacade();
		$interaction                    = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		$output = $this->capture_panel();

		self::assertSame( 1, $portability->apply_calls );
		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'class="notice notice-success inline"', $output );
		self::assertStringContainsString( 'Imported.', $output );
		self::assertStringContainsString( 'its old WP Pusher record remains', $output );
		self::assertStringContainsString(
			'Could not access WP Pusher data: a database query failed (code: db_query_failed).',
			$output
		);
		self::assertStringContainsString( 'fixture/fixture.php', $output );
		self::assertStringNotContainsString( 'SQLSTATE', $output );
		self::assertStringNotContainsString( 'DELETE FROM', $output );
		self::assertStringNotContainsString( 'secret_col', $output );

		// Case 2: delete_exact() fails with $wpdb->last_error AND subsequent inventory() read also throws.
		$database                       = new AdminPostDatabase();
		$database->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
		$database->persist_delete_error = true;
		$portability                    = new AdminPostPortabilityFacade();
		$interaction                    = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		$output_with_read_error = $this->capture_panel();

		self::assertSame( 1, $portability->apply_calls );
		self::assertCount( 1, $database->rows );
		self::assertStringContainsString( 'class="notice notice-success inline"', $output_with_read_error );
		self::assertStringContainsString( 'Imported.', $output_with_read_error );
		self::assertStringContainsString( 'its old WP Pusher record remains', $output_with_read_error );
		self::assertStringContainsString( 'class="notice notice-error inline"', $output_with_read_error );
		self::assertStringContainsString(
			'Could not access WP Pusher data: a database query failed (code: db_query_failed).',
			$output_with_read_error
		);
		self::assertStringNotContainsString( 'SQLSTATE', $output_with_read_error );
		self::assertStringNotContainsString( 'DELETE FROM', $output_with_read_error );
		self::assertStringNotContainsString( 'secret_col', $output_with_read_error );
	}

	public function test_fragment_cleanup_message_uses_compact_multibyte_translation_when_combined_exceeds_255_bytes(): void {
		$long_cleanup_translation  = 'Booster は採用済みパッケージを検証しましたが、WP Pusher のソースレコードを削除できませんでした。WP Pusher を無効のままにして再試行してください。';
		$long_db_error_translation = 'WP Pusher データにアクセスできませんでした: データベースクエリが失敗しました (code: db_query_failed)。';
		$compact_translation       = 'Booster は採用を検証済みですが、WP Pusher レコードが残っています';
		$GLOBALS['ran_booster_wp_pusher_migrator_test_translations'] = array(
			'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.' => $long_cleanup_translation,
			'Could not access WP Pusher data: a database query failed'                                                                                  => $long_db_error_translation,
			'Booster verified the adopted package, but its WP Pusher record remains'                                                                  => $compact_translation,
		);
		self::assertGreaterThan( 255, strlen( $long_cleanup_translation . ' ' . $long_db_error_translation ) );

		$database                       = new AdminPostDatabase();
		$database->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
		$portability                    = new AdminPostPortabilityFacade();
		$interaction                    = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		$this->run_handler();

		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		$message = (string) $interaction->outcome->message();
		self::assertSame( $compact_translation . ' (code: db_query_failed).', $message );
		self::assertLessThanOrEqual( 255, strlen( $message ) );
		self::assertTrue( 1 === preg_match( '//u', $message ) );
		self::assertStringContainsString( '(code: db_query_failed)', $message );
		self::assertStringNotContainsString( 'SQLSTATE', $message );
		self::assertStringNotContainsString( 'secret_col', $message );
	}

	public function test_fragment_cleanup_message_falls_back_to_bounded_ascii_retaining_code_when_all_translations_exceed_255_bytes(): void {
		$oversized_multibyte = str_repeat( '検証済み・未削除・Éé ', 16 );
		$GLOBALS['ran_booster_wp_pusher_migrator_test_translations'] = array(
			'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.' => $oversized_multibyte,
			'Could not access WP Pusher data: a database query failed'                                                                                  => $oversized_multibyte,
			'Booster verified the adopted package, but its WP Pusher record remains'                                                                  => $oversized_multibyte,
		);
		self::assertGreaterThan( 255, strlen( $oversized_multibyte ) );

		$database                       = new AdminPostDatabase();
		$database->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
		$portability                    = new AdminPostPortabilityFacade();
		$interaction                    = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->source_package( $database );
		$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

		$this->run_handler();

		self::assertSame( 'validation_failure', $interaction->outcome?->kind() );
		$message = (string) $interaction->outcome->message();
		self::assertSame(
			'Booster verified the adopted package, but its WP Pusher record remains (code: db_query_failed).',
			$message
		);
		self::assertLessThanOrEqual( 255, strlen( $message ) );
		self::assertTrue( 1 === preg_match( '//u', $message ) );
		self::assertStringContainsString( 'verified the adopted package', $message );
		self::assertStringContainsString( 'WP Pusher record remains', $message );
		self::assertStringContainsString( '(code: db_query_failed)', $message );
		self::assertStringNotContainsString( 'SQLSTATE', $message );
		self::assertStringNotContainsString( 'secret_col', $message );
	}

	public function test_fragment_review_and_apply_bound_long_translated_second_read_db_error_and_retain_code(): void {
		$long_db_translation = str_repeat( 'データベース照会エラー・', 14 ) . ' (code: db_query_failed)';
		$compact_translation = 'WP Pusher データにアクセスできませんでした';
		self::assertGreaterThan( 450, strlen( $long_db_translation ) );
		self::assertLessThanOrEqual( 255, strlen( $compact_translation . ' (code: db_query_failed).' ) );

		$borderline_compact = str_repeat( 'x', 240 );
		self::assertLessThanOrEqual( 255, strlen( $borderline_compact ) );
		self::assertGreaterThan( 255, strlen( $borderline_compact . ' (code: db_query_failed).' ) );

		$oversized_compact = str_repeat( 'データベース接続失敗・Éé ', 16 );
		self::assertGreaterThan( 255, strlen( $oversized_compact ) );

		$scenarios = array(
			'compact_multibyte'        => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => $long_db_translation,
					'Could not access WP Pusher data' => $compact_translation,
				),
				'expected'     => $compact_translation . ' (code: db_query_failed).',
			),
			'short_full_omitting_code' => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => 'WP Pusher エラー。',
					'Could not access WP Pusher data' => $compact_translation,
				),
				'expected'     => 'WP Pusher エラー。 (code: db_query_failed).',
			),
			'compact_prose_fits_alone_but_overflows_with_suffix' => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => $long_db_translation,
					'Could not access WP Pusher data' => $borderline_compact,
				),
				'expected'     => 'Could not access WP Pusher data (code: db_query_failed).',
			),
			'ascii_fallback'           => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => $long_db_translation,
					'Could not access WP Pusher data' => $oversized_compact,
				),
				'expected'     => 'Could not access WP Pusher data (code: db_query_failed).',
			),
		);

		foreach ( array( 'review', 'apply' ) as $operation ) {
			foreach ( $scenarios as $name => $scenario ) {
				$GLOBALS['ran_booster_wp_pusher_migrator_test_translations'] = $scenario['translations'];

				$database    = new AdminPostDatabase();
				$portability = new AdminPostPortabilityFacade();
				$interaction = new AdminPostInteractionSpy();
				$this->connect( $database, $portability, $interaction );
				$source                               = $this->source_package( $database );
				$database->fail_on_package_read       = 2;
				$database->package_read_error_message = 'SQLSTATE[42000]: SELECT secret_table';

				$_POST = 'review' === $operation
					? $this->review_request( $source )
					: $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

				$this->run_handler();

				self::assertSame( 'validation_failure', $interaction->outcome?->kind(), $operation . ':' . $name );
				$message = (string) $interaction->outcome->message();
				self::assertSame( $scenario['expected'], $message, $operation . ':' . $name );
				self::assertLessThanOrEqual( 255, strlen( $message ), $operation . ':' . $name );
				self::assertSame( 1, preg_match( '//u', $message ), $operation . ':' . $name );
				self::assertStringContainsString( '(code: db_query_failed)', $message, $operation . ':' . $name );
				self::assertStringNotContainsString( 'SQLSTATE', $message, $operation . ':' . $name );
				self::assertStringNotContainsString( 'secret_table', $message, $operation . ':' . $name );
				self::assertSame( 0, $portability->review_calls, $operation . ':' . $name );
				self::assertSame( 0, $portability->apply_calls, $operation . ':' . $name );
			}
		}
	}

	public function test_apply_does_not_report_completion_when_installation_disappears_before_post_apply_readback(): void {
		foreach ( array( 'native', 'fragment' ) as $transport ) {
			$second_row            = AdminPostDatabase::fixture_row();
			$second_row['id']      = '2';
			$second_row['package'] = 'other/other.php';
			$database              = new AdminPostDatabase();
			$database->rows        = array( AdminPostDatabase::fixture_row(), $second_row );
			$portability           = new AdminPostPortabilityFacade();
			$interaction           = new AdminPostInteractionSpy();
			$plugins_provider      = static function () use ( $database ): array {
				return 1 === $database->row_count()
					? array()
					: array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.18' ) );
			};
			$this->connect_custom( $database, $portability, $interaction, null, array(), array(), false, $plugins_provider );
			$source = $this->source_package( $database );
			$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

			if ( 'native' === $transport ) {
				$output = $this->capture_panel();
				self::assertCount( 1, $database->rows );
				self::assertSame( 'other/other.php', $database->rows[0]['package'] );
				self::assertStringContainsString( 'Imported.', $output );
				self::assertStringContainsString( 'No WP Pusher packages were detected. WP Pusher is not installed on this site.', $output );
				self::assertStringNotContainsString( 'completion-panel--visible', $output );
				self::assertStringNotContainsString( 'Migration complete!', $output );
				continue;
			}

			$this->run_handler();
			self::assertCount( 1, $database->rows );
			self::assertSame( 'other/other.php', $database->rows[0]['package'] );
			self::assertSame( 'success', $interaction->outcome?->kind() );
			self::assertStringContainsString( '>Settings</a>', $interaction->fragment );
			self::assertStringNotContainsString( 'data-ran-booster-wp-pusher-migration-complete', $interaction->fragment );
			self::assertStringNotContainsString( 'Migration complete!', $interaction->fragment );
		}
	}

	public function test_all_response_paths_retain_closed_db_code_with_short_or_empty_translations(): void {
		$variants = array(
			'short_omitting_code' => 'WP Pusher エラー。',
			'empty_translation'   => '',
		);

		foreach ( $variants as $variant => $translated ) {
			$GLOBALS['ran_booster_wp_pusher_migrator_test_translations'] = array(
				'Could not access WP Pusher data: a database query failed' => $translated,
			);

			// Native inventory read failure.
			$inventory_db             = new AdminPostDatabase();
			$inventory_db->last_error = "SQLSTATE[42S02]: SELECT * FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
			$this->connect( $inventory_db, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
			$_POST            = array();
			$inventory_output = $this->capture_panel();
			self::assertStringContainsString( '(code: db_query_failed)', $inventory_output, 'native_inventory:' . $variant );
			self::assertStringNotContainsString( 'SQLSTATE', $inventory_output, 'native_inventory:' . $variant );
			self::assertStringNotContainsString( 'secret_col', $inventory_output, 'native_inventory:' . $variant );

			// Native review and apply second-read database failures.
			foreach ( array( 'review', 'apply' ) as $operation ) {
				$read_db    = new AdminPostDatabase();
				$read_proxy = new AdminPostPortabilityFacade();
				$this->connect( $read_db, $read_proxy, new AdminPostInteractionSpy() );
				$source                              = $this->source_package( $read_db );
				$read_db->fail_on_package_read       = 2;
				$read_db->package_read_error_message = 'SQLSTATE[42000]: SELECT secret_table';
				$_POST                               = 'review' === $operation
					? $this->review_request( $source )
					: $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
				$native_output                       = $this->capture_panel();
				self::assertStringContainsString( '(code: db_query_failed)', $native_output, 'native_' . $operation . ':' . $variant );
				self::assertStringNotContainsString( 'SQLSTATE', $native_output, 'native_' . $operation . ':' . $variant );
				self::assertStringNotContainsString( 'secret_table', $native_output, 'native_' . $operation . ':' . $variant );
			}

			// Native cleanup database failure.
			$cleanup_db                       = new AdminPostDatabase();
			$cleanup_db->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
			$this->connect( $cleanup_db, new AdminPostPortabilityFacade(), new AdminPostInteractionSpy() );
			$source         = $this->source_package( $cleanup_db );
			$_POST          = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
			$cleanup_output = $this->capture_panel();
			self::assertStringContainsString( '(code: db_query_failed)', $cleanup_output, 'native_cleanup:' . $variant );
			self::assertStringNotContainsString( 'SQLSTATE', $cleanup_output, 'native_cleanup:' . $variant );
			self::assertStringNotContainsString( 'secret_col', $cleanup_output, 'native_cleanup:' . $variant );

			// Fragment review and apply second-read database failures.
			foreach ( array( 'review', 'apply' ) as $operation ) {
				$frag_db  = new AdminPostDatabase();
				$frag_spy = new AdminPostInteractionSpy();
				$this->connect( $frag_db, new AdminPostPortabilityFacade(), $frag_spy );
				$source                              = $this->source_package( $frag_db );
				$frag_db->fail_on_package_read       = 2;
				$frag_db->package_read_error_message = 'SQLSTATE[42000]: SELECT secret_table';
				$_POST                               = 'review' === $operation
					? $this->review_request( $source )
					: $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
				$this->run_handler();
				self::assertSame( 'validation_failure', $frag_spy->outcome?->kind(), 'fragment_' . $operation . ':' . $variant );
				$message = (string) $frag_spy->outcome->message();
				self::assertLessThanOrEqual( 255, strlen( $message ), 'fragment_' . $operation . ':' . $variant );
				self::assertSame( 1, preg_match( '//u', $message ), 'fragment_' . $operation . ':' . $variant );
				self::assertStringContainsString( '(code: db_query_failed)', $message, 'fragment_' . $operation . ':' . $variant );
				self::assertStringNotContainsString( 'SQLSTATE', $message, 'fragment_' . $operation . ':' . $variant );
				self::assertStringNotContainsString( 'secret_table', $message, 'fragment_' . $operation . ':' . $variant );
			}

			// Fragment cleanup database failure.
			$frag_cleanup_db                       = new AdminPostDatabase();
			$frag_cleanup_db->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
			$frag_cleanup_spy                      = new AdminPostInteractionSpy();
			$this->connect( $frag_cleanup_db, new AdminPostPortabilityFacade(), $frag_cleanup_spy );
			$source = $this->source_package( $frag_cleanup_db );
			$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );
			$this->run_handler();
			self::assertSame( 'validation_failure', $frag_cleanup_spy->outcome?->kind(), 'fragment_cleanup:' . $variant );
			$cleanup_message = (string) $frag_cleanup_spy->outcome->message();
			self::assertLessThanOrEqual( 255, strlen( $cleanup_message ), 'fragment_cleanup:' . $variant );
			self::assertSame( 1, preg_match( '//u', $cleanup_message ), 'fragment_cleanup:' . $variant );
			self::assertStringContainsString( '(code: db_query_failed)', $cleanup_message, 'fragment_cleanup:' . $variant );
			self::assertStringNotContainsString( 'SQLSTATE', $cleanup_message, 'fragment_cleanup:' . $variant );
			self::assertStringNotContainsString( 'secret_col', $cleanup_message, 'fragment_cleanup:' . $variant );
		}
	}

	public function test_fragment_first_read_db_failure_returns_escaped_bounded_409_with_code(): void {
		$oversized = str_repeat( 'データベース接続失敗・Éé ', 16 );
		$variants  = array(
			'default'         => array(
				'translations' => array(),
				'expected_sub' => '(code: db_query_failed)',
			),
			'short_html'      => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => '<script>WP Pusher エラー</script>',
				),
				'expected_sub' => '&lt;script&gt;WP Pusher エラー&lt;/script&gt; (code: db_query_failed).',
			),
			'empty'           => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => '',
					'Could not access WP Pusher data' => '',
				),
				'expected_sub' => 'Could not access WP Pusher data: a database query failed (code: db_query_failed).',
			),
			'oversized_ascii' => array(
				'translations' => array(
					'Could not access WP Pusher data: a database query failed' => $oversized,
					'Could not access WP Pusher data' => $oversized,
				),
				'expected_sub' => 'Could not access WP Pusher data (code: db_query_failed).',
			),
		);

		foreach ( array( 'review', 'apply' ) as $operation ) {
			foreach ( $variants as $name => $variant ) {
				$GLOBALS['ran_booster_wp_pusher_migrator_test_translations'] = $variant['translations'];

				$database    = new AdminPostDatabase();
				$portability = new AdminPostPortabilityFacade();
				$interaction = new AdminPostInteractionSpy();
				$this->connect( $database, $portability, $interaction );
				$source                               = $this->source_package( $database );
				$database->fail_on_package_read       = 1;
				$database->package_read_error_message = 'SQLSTATE[42000]: SELECT secret_table';
				$_POST                                = 'review' === $operation
					? $this->review_request( $source )
					: $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

				try {
					$this->controller->handle_admin_post();
					self::fail( 'First-read database failure did not stop.' );
				} catch ( \Ran_Booster_Wp_Pusher_Migrator_Wp_Die_Exception $failure ) {
					self::assertSame( 409, $failure->args['response'], $operation . ':' . $name );
					$escaped = $failure->getMessage();
					$decoded = htmlspecialchars_decode( $escaped, ENT_QUOTES );
					self::assertStringContainsString( $variant['expected_sub'], $escaped, $operation . ':' . $name );
					self::assertStringContainsString( '(code: db_query_failed)', $escaped, $operation . ':' . $name );
					self::assertStringNotContainsString( '<script', $escaped, $operation . ':' . $name );
					self::assertStringNotContainsString( 'SQLSTATE', $escaped, $operation . ':' . $name );
					self::assertStringNotContainsString( 'secret_table', $escaped, $operation . ':' . $name );
					self::assertLessThanOrEqual( 255, strlen( $decoded ), $operation . ':' . $name );
					self::assertSame( 1, preg_match( '//u', $decoded ), $operation . ':' . $name );
				}

				self::assertCount( 1, $database->rows, $operation . ':' . $name );
				self::assertNull( $interaction->outcome, $operation . ':' . $name );
				self::assertSame( '', $interaction->fragment, $operation . ':' . $name );
				self::assertSame( 0, $portability->review_calls, $operation . ':' . $name );
				self::assertSame( 0, $portability->apply_calls, $operation . ':' . $name );
			}
		}
	}

	public function test_fragment_cleanup_retains_verified_adoption_remaining_record_and_code_with_empty_translations(): void {
		$cases = array(
			'empty_all'               => array(
				'translations'     => array(
					'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.' => '',
					'Booster verified the adopted package, but its WP Pusher record remains' => '',
					'Could not access WP Pusher data: a database query failed' => '',
					'Could not access WP Pusher data' => '',
				),
				'record_substring' => 'source record could not be removed',
			),
			'empty_with_short_db'     => array(
				'translations'     => array(
					'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.' => '',
					'Booster verified the adopted package, but its WP Pusher record remains'                                                                  => '',
					'Could not access WP Pusher data: a database query failed'                                                                                  => 'WP Pusher エラー。',
				),
				'record_substring' => 'source record could not be removed',
			),
			'empty_with_oversized_db' => array(
				'translations'     => array(
					'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.' => '',
					'Booster verified the adopted package, but its WP Pusher record remains'                                                                  => '',
					'Could not access WP Pusher data: a database query failed'                                                                                  => str_repeat( 'データベース照会エラー・', 14 ),
				),
				'record_substring' => 'WP Pusher record remains',
			),
		);

		foreach ( $cases as $name => $case ) {
			$GLOBALS['ran_booster_wp_pusher_migrator_test_translations'] = $case['translations'];

			$database                       = new AdminPostDatabase();
			$database->delete_error_message = "SQLSTATE[42000]: DELETE FROM `wp_wppusher_packages` WHERE secret_col = 'leak'";
			$portability                    = new AdminPostPortabilityFacade();
			$interaction                    = new AdminPostInteractionSpy();
			$this->connect( $database, $portability, $interaction );
			$source = $this->source_package( $database );
			$_POST  = $this->apply_request( $source, 'v1:' . str_repeat( 'a', 64 ) );

			$this->run_handler();

			self::assertSame( 1, $portability->apply_calls, $name );
			self::assertCount( 1, $database->rows, $name );
			self::assertSame( 'validation_failure', $interaction->outcome?->kind(), $name );
			$message = (string) $interaction->outcome->message();
			self::assertLessThanOrEqual( 255, strlen( $message ), $name );
			self::assertSame( 1, preg_match( '//u', $message ), $name );
			self::assertStringContainsString( 'Booster verified the adopted package', $message, $name );
			self::assertStringContainsString( $case['record_substring'], $message, $name );
			self::assertStringContainsString( '(code: db_query_failed)', $message, $name );
			self::assertStringNotContainsString( 'SQLSTATE', $message, $name );
			self::assertStringNotContainsString( 'secret_col', $message, $name );
		}
	}

	private function capture_panel(): string {
		ob_start();
		$this->controller->render_panel();

		return (string) ob_get_clean();
	}

	private function run_handler(): void {
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
		$this->connect_custom( $database, $portability, $interaction );
	}

	/**
	 * @param array<string, array<string, mixed>>|null               $plugins
	 * @param array<int, string>                                     $active_plugins
	 * @param array<string, mixed>                                   $network_active_plugins
	 * @param (callable():array<string, array<string, mixed>>)|null  $plugins_provider
	 */
	private function connect_custom(
		object $database,
		AdminPostPortabilityFacade $portability,
		AdminPostInteractionSpy $interaction,
		?array $plugins = null,
		array $active_plugins = array(),
		array $network_active_plugins = array(),
		bool $multisite = false,
		?callable $plugins_provider = null
	): void {
		$installed_plugins = $plugins ?? array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.18' ) );
		$source            = new WpPusherSource(
			$database,
			$plugins_provider ?? static fn (): array => $installed_plugins,
			static fn (): array => $active_plugins,
			static fn (): array => $network_active_plugins,
			static fn (): bool => $multisite
		);
		$factory           = new CandidateFactory(
			static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) )
		);
		$service           = new MigrationService( $source, $factory, $portability );
		$presenter         = new MigrationPresenter( $factory, $interaction );
		$this->controller  = new MigrationRequestController( $service, $source, $presenter, $interaction );
	}

	private function source_package( AdminPostDatabase $database ): WpPusherPackage {
		$source = new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.18' ) ),
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);

		return $source->packages()[0];
	}

	/** @return array<string, string> */
	private function review_request( WpPusherPackage $source, string $nonce = 'ran-booster-wp-pusher-migrator-review-v1' ): array {
		return array(
			'action'                                => 'ran_booster_wp_pusher_migrator_package',
			'ran_booster_wp_pusher_migrator_action' => 'review',
			'source_id'                             => (string) $source->id,
			'source_fingerprint'                    => $source->fingerprint(),
			'_wpnonce'                              => $nonce,
		);
	}

	/** @return array<string, string> */
	private function apply_request(
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
			'provider_expectation'                  => 'null',
			'credential_id'                         => $credential_id,
			'_wpnonce'                              => 'ran-booster-wp-pusher-migrator-apply-v1',
		);
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
final class AdminPostResponse extends RuntimeException {
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
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

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
final class AdminPostPortabilityFacade extends PortabilityFacade {

	/** @var array<string,mixed>|null */
	public ?array $provider_expectation = null;

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
		$this->reviewed_source_fingerprint = WpPusherPackage::from_row( AdminPostDatabase::fixture_row() )->fingerprint();

		return new PortabilityReviewResult(
			$candidate,
			'adopt',
			'ready',
			'Ready to import.',
			'v1:' . str_repeat( 'a', 64 )
		);
	}

	/** @param array<string,mixed>|null $provider_expectation Review context. */
	public function apply(
		PortabilityCandidate $candidate,
		string $expected_fingerprint,
		string $nonce,
		?array $provider_expectation
	): PortabilityApplyResult {
		unset( $nonce );
		++$this->apply_calls;
		$this->provider_expectation        = $provider_expectation;
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

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Colocated test double belongs to this fixture load unit; unrelated declarations remain checked.
class AdminPostDatabase {

	public string $prefix     = 'wp_';
	public string $options    = 'wp_options';
	public string $last_error = '';

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $delete_result                     = 1;
	public bool $fail_inventory_read_after_delete = false;
	public bool $table_exists                     = true;
	public bool $unsupported_schema               = false;
	public string $delete_error_message           = '';
	public bool $persist_delete_error             = false;
	public int $fail_on_package_read              = 0;
	public string $package_read_error_message     = '';
	private int $package_read_count               = 0;
	/** @var array<int|string, mixed> Variadic arguments retain named keys too. */
	private array $prepared_values = array();
	private bool $deleted          = false;

	public function __construct() {
		$this->rows = array( self::fixture_row() );
	}

	public function row_count(): int {
		return count( $this->rows );
	}

	/** @return array<string, mixed> */
	public static function fixture_row(): array {
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
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'][] = 'database';
		if ( ! $this->persist_delete_error && '' !== $this->delete_error_message ) {
			$this->last_error = '';
		}
		if ( str_starts_with( $query, 'SHOW TABLES' ) ) {
			return $this->table_exists ? 'wp_wppusher_packages' : null;
		}

		return count( $this->rows );
	}

	/** @return list<array<string, mixed>> */
	public function get_results( string $query, string $output ): array {
		unset( $output );
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'][] = 'database';
		if ( $this->fail_inventory_read_after_delete
			&& $this->deleted
			&& str_starts_with( $query, 'SELECT `' ) ) {
			throw new RuntimeException( 'Inventory readback failed.' );
		}
		if ( str_starts_with( $query, 'SELECT `' ) && $this->fail_on_package_read > 0 ) {
			++$this->package_read_count;
			if ( $this->package_read_count === $this->fail_on_package_read && '' !== $this->package_read_error_message ) {
				$this->last_error = $this->package_read_error_message;

				return array();
			}
		}
		if ( str_starts_with( $query, 'SHOW COLUMNS' ) ) {
			if ( $this->unsupported_schema ) {
				return array(
					array(
						'Field' => 'id',
						'Type'  => 'bigint',
					),
				);
			}

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
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'][] = 'database';

		return array();
	}

	public function query( string $query ): int {
		$GLOBALS['ran_booster_wp_pusher_migrator_test_events'][] = 'database';
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' )
			&& '' !== $this->delete_error_message ) {
			$this->last_error = $this->delete_error_message;

			return 0;
		}
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' )
			&& 1 === $this->delete_result ) {
			$matched   = 0;
			$remaining = array();
			foreach ( $this->rows as $row ) {
				if ( $this->row_matches_delete( $query, $row ) ) {
					++$matched;
					continue;
				}
				$remaining[] = $row;
			}
			$this->rows    = $remaining;
			$this->deleted = $matched > 0;

			return $matched;
		}

		return $this->delete_result;
	}

	/** @param array<string, mixed> $row */
	private function row_matches_delete( string $query, array $row ): bool {
		$values = $this->prepared_values;
		if ( (string) ( $row['id'] ?? '' ) !== (string) ( $values[0] ?? '' )
			|| (string) ( $row['package'] ?? '' ) !== (string) ( $values[1] ?? '' )
			|| (string) ( $row['repository'] ?? '' ) !== (string) ( $values[2] ?? '' )
			|| (string) ( $row['branch'] ?? '' ) !== (string) ( $values[3] ?? '' )
			|| (string) ( $row['type'] ?? '' ) !== (string) ( $values[4] ?? '' )
			|| (string) ( $row['status'] ?? '' ) !== (string) ( $values[5] ?? '' )
			|| (string) ( $row['ptd'] ?? '' ) !== (string) ( $values[6] ?? '' )
			|| (string) ( $row['host'] ?? '' ) !== (string) ( $values[7] ?? '' )
			|| (string) ( $row['private'] ?? '' ) !== (string) ( $values[8] ?? '' ) ) {
			return false;
		}
		if ( str_contains( $query, '`subdirectory` IS NULL' ) ) {
			return null === ( $row['subdirectory'] ?? null );
		}

		if ( array_key_exists( 9, $values ) && ( $row['subdirectory'] ?? null ) === $values[9] ) {
			return true;
		}

		return false;
	}
}

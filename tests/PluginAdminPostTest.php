<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RAN\AddOn\Logging\LoggingFacade;
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
use RAN\BoosterWpPusherMigrator\Plugin;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RAN\BoosterWpPusherMigrator\WpPusherSource;
use ReflectionProperty;
use RuntimeException;

final class PluginAdminPostTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$_POST = array();
		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = true;
		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_test_plugins']    = array(
			'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ),
		);
	}

	protected function tearDown(): void {
		$_POST = array();
		foreach ( array( 'migration', 'source', 'adminInteraction', 'logging' ) as $property ) {
			$this->setPluginProperty( $property, null );
		}
		parent::tearDown();
	}

	public function testCapabilityAndNonceAreCheckedBeforeSourceInventory(): void {
		$database    = new AdminPostDatabase();
		$portability = new AdminPostPortabilityFacade();
		$interaction = new AdminPostInteractionSpy();
		$this->connect( $database, $portability, $interaction );
		$source = $this->sourcePackage( $database );

		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = false;
		try {
			Plugin::handleAdminPost();
			self::fail( 'Unauthorized request did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 403, $failure->args['response'] );
		}
		self::assertSame( array( 'capability:manage_options' ), $GLOBALS['ran_booster_wp_pusher_test_events'] );

		$GLOBALS['ran_booster_wp_pusher_test_can_manage'] = true;
		$GLOBALS['ran_booster_wp_pusher_test_events']     = array();
		$_POST = $this->reviewRequest( $source, 'wrong-nonce' );
		try {
			Plugin::handleAdminPost();
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
		self::assertStringContainsString( '<strong>Checked</strong>', $interaction->fragment );
		self::assertStringContainsString( '>Import</button>', $interaction->fragment );
		self::assertStringNotContainsString( 'Check package', $interaction->fragment );
		self::assertStringNotContainsString( '<table', $interaction->fragment );
		self::assertSame( $source->fingerprint(), $portability->reviewedSourceFingerprint );
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
		self::assertStringContainsString( '<strong>Imported</strong>', $interaction->fragment );
		self::assertStringContainsString( 'Managed by Booster.', $interaction->fragment );
		self::assertStringContainsString( '>Plugin settings</a>', $interaction->fragment );
		self::assertStringContainsString(
			'href="https://example.test/wp-admin/admin.php?page=ran-booster-plugins&amp;package=fixture%2Ffixture.php"',
			$interaction->fragment
		);
		self::assertStringNotContainsString( 'deployment remains off', strtolower( $interaction->fragment ) );
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
			'Booster verified the imported package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.',
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
			Plugin::handleAdminPost();
			self::fail( 'Missing source did not stop.' );
		} catch ( \WpDieException $failure ) {
			self::assertSame( 409, $failure->args['response'] );
		}

		self::assertNull( $interaction->outcome );
		self::assertSame( '', $interaction->fragment );
		self::assertSame( 0, $portability->reviewCalls );
	}

	public function testUnexpectedSourceFailureIsLoggedAndReturnsGenericLocalFailure(): void {
		$database                   = new AdminPostDatabase();
		$portability                = new AdminPostPortabilityFacade();
		$portability->reviewFailure = new RuntimeException( 'SECRET-CANARY /private/source.php' );
		$interaction                = new AdminPostInteractionSpy();
		$logging                    = new AdminPostLoggingSpy();
		$this->connect( $database, $portability, $interaction );
		$this->setPluginProperty( 'logging', $logging );
		$source = $this->sourcePackage( $database );
		$_POST  = $this->reviewRequest( $source );

		$this->runHandler();

		self::assertSame( 'unexpected_failure', $interaction->outcome?->kind() );
		self::assertSame( 'We could not complete that request. Please try again.', $interaction->outcome?->message() );
		self::assertStringNotContainsString( 'SECRET-CANARY', (string) $interaction->outcome?->message() );
		self::assertSame( 'WP Pusher package migration interaction failed.', $logging->message );
		self::assertSame( $portability->reviewFailure, $logging->exception );
	}

	private function runHandler(): void {
		try {
			Plugin::handleAdminPost();
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
		$source  = new WpPusherSource(
			$database,
			static fn (): array => array( WpPusherSource::PLUGIN => array( 'Version' => '3.0.13' ) ),
			static fn (): array => array(),
			static fn (): array => array(),
			static fn (): bool => false
		);
		$factory = new CandidateFactory(
			static fn (): array => array( 'fixture/fixture.php' => array( 'Name' => 'Fixture Plugin' ) )
		);
		$service = new MigrationService( $source, $factory, $portability );

		$this->setPluginProperty( 'migration', $service );
		$this->setPluginProperty( 'source', $source );
		$this->setPluginProperty( 'adminInteraction', $interaction );
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
	private function applyRequest( WpPusherPackage $source, string $reviewFingerprint ): array {
		return array(
			'action'                                => 'ran_booster_wp_pusher_migrator_package',
			'ran_booster_wp_pusher_migrator_action' => 'apply',
			'source_id'                             => (string) $source->id,
			'source_fingerprint'                    => $source->fingerprint(),
			'review_fingerprint'                    => $reviewFingerprint,
			'credential_id'                         => '',
			'_wpnonce'                              => 'ran-booster-wp-pusher-migrator-apply-v1',
		);
	}

	private function setPluginProperty( string $property, mixed $value ): void {
		$reflection = new ReflectionProperty( Plugin::class, $property );
		$reflection->setValue( null, $value );
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
	public string $reviewedSourceFingerprint = '';
	public string $expectedReviewFingerprint = '';
	public ?RuntimeException $reviewFailure  = null;

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		unset( $nonce );
		++$this->reviewCalls;
		if ( $this->reviewFailure instanceof RuntimeException ) {
			throw $this->reviewFailure;
		}
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
		unset( $candidate, $nonce );
		$this->expectedReviewFingerprint = $expectedFingerprint;

		return new PortabilityApplyResult( 'adopted', 'adopted', 'Imported.', true );
	}
}

final class AdminPostLoggingSpy extends LoggingFacade {

	public string $message        = '';
	public ?\Throwable $exception = null;
	/** @var array<string, mixed> */
	public array $context = array();

	/** @param array<string, mixed> $context */
	public function logException( string $message, \Throwable $exception, array $context = array() ): void {
		$this->message   = $message;
		$this->exception = $exception;
		$this->context   = $context;
	}
}

final class AdminPostDatabase {

	public string $prefix = 'wp_';

	/** @var list<array<string, mixed>> */
	public array $rows;
	public int $deleteResult = 1;

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
		unset( $values );

		return $query;
	}

	public function query( string $query ): int|false {
		$GLOBALS['ran_booster_wp_pusher_test_events'][] = 'database';
		if ( str_starts_with( $query, 'DELETE FROM `wp_wppusher_packages`' )
			&& 1 === $this->deleteResult ) {
			$this->rows = array();
		}

		return $this->deleteResult;
	}
}

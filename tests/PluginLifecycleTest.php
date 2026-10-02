<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityCandidate;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionOutcome;
use RAN\Admin\Interaction\AdminInteractionRequest;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use RAN\BoosterWpPusherMigrator\Plugin;
use RAN\BoosterWpPusherMigrator\WpPusherPackage;
use RuntimeException;
use stdClass;

final class PluginLifecycleTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['ran_booster_wp_pusher_test_hooks']        = array();
		$GLOBALS['ran_booster_wp_pusher_test_events']       = array();
		$GLOBALS['ran_booster_wp_pusher_test_capabilities'] = array();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated lifecycle fixture supplies the constructor's WordPress database global.
		$GLOBALS['wpdb'] = new stdClass();
	}

	protected function tearDown(): void {
		$_POST                                        = array();
		$GLOBALS['ran_booster_wp_pusher_test_hooks']  = array();
		$GLOBALS['ran_booster_wp_pusher_test_events'] = array();
		$GLOBALS['ran_booster_wp_pusher_test_capabilities'] = array();
		unset( $GLOBALS['wpdb'] );
		unset( $GLOBALS['ran_booster_wp_pusher_test_plugins'] );
		parent::tearDown();
	}

	public function testInitialRegistrationContainsOnlyCompatibilityBoundaries(): void {
		$this->defineCompatibleApis();
		( new Plugin() )->register();

		self::assertSame(
			array(
				'ran_booster_portability_ready',
				'ran_booster_admin_interaction_ready',
				'admin_notices',
			),
			array_keys( $GLOBALS['ran_booster_wp_pusher_test_hooks'] )
		);
	}

	public function testWrongFacadeDeliveriesAreInertAndLeaveFeatureHooksAbsent(): void {
		$this->defineCompatibleApis();
		( new Plugin() )->register();
		$hooks = $GLOBALS['ran_booster_wp_pusher_test_hooks'];

		$this->deliver( 'ran_booster_portability_ready', new stdClass() );
		$this->deliver( 'ran_booster_admin_interaction_ready', new stdClass() );
		$this->deliver( 'ran_booster_portability_ready', null );
		$this->deliver( 'ran_booster_portability_ready', 'wrong' );
		$this->deliver( 'ran_booster_admin_interaction_ready', null );
		$this->deliver( 'ran_booster_admin_interaction_ready', 'wrong' );

		self::assertSame( $hooks, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	/** @return iterable<string,array{list<string>}> */
	public static function facadeDeliveryOrders(): iterable {
		yield 'portability then interaction' => array( array( 'portability', 'interaction' ) );
		yield 'interaction then portability' => array( array( 'interaction', 'portability' ) );
	}

	/** @param list<string> $order */
	#[DataProvider( 'facadeDeliveryOrders' )]
	public function testSecondExactFacadeRegistersEveryFeatureHookExactlyOnce( array $order ): void {
		$this->defineCompatibleApis();
		( new Plugin() )->register();
		$initial     = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$portability = new LifecyclePortabilityFacade();
		$interaction = new LifecycleInteractionFacade();

		$this->deliver(
			'portability' === $order[0] ? 'ran_booster_portability_ready' : 'ran_booster_admin_interaction_ready',
			'portability' === $order[0] ? $portability : $interaction
		);
		self::assertSame( $initial, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );

		$this->deliver(
			'portability' === $order[1] ? 'ran_booster_portability_ready' : 'ran_booster_admin_interaction_ready',
			'portability' === $order[1] ? $portability : $interaction
		);

		self::assertSame(
			array(
				'ran_booster_portability_ready',
				'ran_booster_admin_interaction_ready',
				'admin_notices',
				'ran_booster_portability_render_migration_modes',
				'ran_booster_portability_render_migration_flows',
				'ran_booster_overview_render_migration_prompt',
				'admin_post_ran_booster_wp_pusher_migrator_package',
				'admin_enqueue_scripts',
			),
			array_keys( $GLOBALS['ran_booster_wp_pusher_test_hooks'] )
		);
		foreach ( $GLOBALS['ran_booster_wp_pusher_test_hooks'] as $callbacks ) {
			self::assertCount( 1, $callbacks );
		}
	}

	public function testFirstExactFacadeInstancesWinAgainstDuplicateAndConflictingDeliveries(): void {
		$this->defineCompatibleApis();
		( new Plugin() )->register();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated registered-flow fixture.
		$GLOBALS['wpdb']                               = new LifecycleDatabase();
		$GLOBALS['ran_booster_wp_pusher_test_plugins'] = array(
			'wppusher/wppusher.php' => array( 'Version' => '3.0.13' ),
			'fixture/fixture.php'   => array( 'Name' => 'Fixture Plugin' ),
		);
		$first_portability                             = new LifecyclePortabilityFacade();
		$first_interaction                             = new LifecycleInteractionFacade();
		$later_portability                             = new LifecyclePortabilityFacade();
		$later_interaction                             = new LifecycleInteractionFacade();
		$this->deliver( 'ran_booster_portability_ready', $first_portability );
		$this->deliver( 'ran_booster_admin_interaction_ready', $first_interaction );
		$hooks              = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$feature_controller = $hooks['ran_booster_portability_render_migration_flows'][0]['callback'][0];

		$this->deliver( 'ran_booster_portability_ready', $first_portability );
		$this->deliver( 'ran_booster_portability_ready', $later_portability );
		$this->deliver( 'ran_booster_admin_interaction_ready', $first_interaction );
		$this->deliver( 'ran_booster_admin_interaction_ready', $later_interaction );
		$this->deliver( 'ran_booster_portability_ready', new stdClass() );
		$this->deliver( 'ran_booster_admin_interaction_ready', new stdClass() );

		self::assertSame( $hooks, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
		self::assertSame(
			$feature_controller,
			$GLOBALS['ran_booster_wp_pusher_test_hooks']['ran_booster_portability_render_migration_flows'][0]['callback'][0]
		);

		$source = WpPusherPackage::from_row( LifecycleDatabase::fixtureRow() );
		$_POST  = array(
			'action'                                => 'ran_booster_wp_pusher_migrator_package',
			'ran_booster_wp_pusher_migrator_action' => 'review',
			'source_id'                             => (string) $source->id,
			'source_fingerprint'                    => $source->fingerprint(),
			'_wpnonce'                              => 'ran-booster-wp-pusher-migrator-review-v1',
		);
		ob_start();
		$this->runHook( 'ran_booster_portability_render_migration_flows' );
		$output = (string) ob_get_clean();

		self::assertSame( 1, $first_portability->review_calls );
		self::assertSame( 0, $later_portability->review_calls );
		self::assertSame( array( 'wp-pusher:import-package' ), $first_interaction->rendered_operations );
		self::assertSame( array(), $later_interaction->rendered_operations );
		self::assertStringContainsString( '>Adopt</button>', $output );
	}

	public function testMissingEitherFacadeLeavesEveryFeatureHookAbsent(): void {
		$this->defineCompatibleApis();
		( new Plugin() )->register();
		$initial = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$this->deliver( 'ran_booster_portability_ready', new LifecyclePortabilityFacade() );
		self::assertSame( $initial, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );

		$GLOBALS['ran_booster_wp_pusher_test_hooks'] = array();
		( new Plugin() )->register();
		$initial     = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$interaction = new LifecycleInteractionFacade();
		$this->deliver( 'ran_booster_admin_interaction_ready', $interaction );
		self::assertSame( $initial, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	public function testCompatibilityNoticeIsCapabilityGatedAndSilentAfterComposition(): void {
		$this->defineCompatibleApis();
		( new Plugin() )->register();
		$GLOBALS['ran_booster_wp_pusher_test_capabilities']['activate_plugins'] = false;
		ob_start();
		$this->runHook( 'admin_notices' );
		self::assertSame( '', (string) ob_get_clean() );

		$GLOBALS['ran_booster_wp_pusher_test_capabilities']['activate_plugins'] = true;
		ob_start();
		$this->runHook( 'admin_notices' );
		$notice = (string) ob_get_clean();
		self::assertStringContainsString( 'needs a compatible RAN Booster release', $notice );

		$this->deliver( 'ran_booster_portability_ready', new LifecyclePortabilityFacade() );
		$this->deliver( 'ran_booster_admin_interaction_ready', new LifecycleInteractionFacade() );
		ob_start();
		$this->runHook( 'admin_notices' );
		self::assertSame( '', (string) ob_get_clean() );
	}

	/** @return iterable<string, array{?int, ?int, list<string>, bool}> */
	public static function incompatibleApiMarkers(): iterable {
		$markers = array(
			'old portability'     => array( 2, 2 ),
			'future portability'  => array( 4, 2 ),
			'missing portability' => array( null, 2 ),
			'old interaction'     => array( 3, 1 ),
			'future interaction'  => array( 3, 3 ),
			'missing interaction' => array( 3, null ),
		);
		foreach ( $markers as $label => $versions ) {
			foreach ( self::facadeDeliveryOrders() as $order_label => [ $order ] ) {
				foreach ( array( true, false ) as $before_registration ) {
					yield $label . ', ' . $order_label . ( $before_registration ? ', Core first' : ', Migrator first' ) => array( $versions[0], $versions[1], $order, $before_registration );
				}
			}
		}
	}

	/** @param list<string> $order */
	#[DataProvider( 'incompatibleApiMarkers' )]
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testIncompatibleMarkersNeverAdmitFacadesOrFeatureHooks( ?int $portability_version, ?int $interaction_version, array $order, bool $before_registration ): void {
		$publish_markers = static function () use ( $portability_version, $interaction_version ): void {
			if ( null !== $portability_version ) {
				define( 'RAN_BOOSTER_PORTABILITY_API_VERSION', $portability_version );
			}
			if ( null !== $interaction_version ) {
				define( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION', $interaction_version );
			}
		};
		if ( $before_registration ) {
			$publish_markers();
		}
		( new Plugin() )->register();
		$initial = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		if ( ! $before_registration ) {
			$publish_markers();
		}
		$portability = new LifecyclePortabilityFacade();
		$interaction = new LifecycleInteractionFacade();
		foreach ( array_merge( $order, $order ) as $boundary ) {
			$this->deliver(
				'portability' === $boundary ? 'ran_booster_portability_ready' : 'ran_booster_admin_interaction_ready',
				'portability' === $boundary ? $portability : $interaction
			);
			self::assertSame( $initial, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
		}
		self::assertSame( 0, $portability->review_calls );
		self::assertSame( array(), $interaction->rendered_operations );
		self::assertArrayNotHasKey( 'admin_post_ran_booster_wp_pusher_migrator_package', $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	private function defineCompatibleApis(): void {
		if ( ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' ) ) {
			define( 'RAN_BOOSTER_PORTABILITY_API_VERSION', 3 );
		}
		if ( ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' ) ) {
			define( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION', 2 );
		}
	}

	private function deliver( string $hook, mixed $facade ): void {
		foreach ( $GLOBALS['ran_booster_wp_pusher_test_hooks'][ $hook ] ?? array() as $registered ) {
			( $registered['callback'] )( $facade );
		}
	}

	private function runHook( string $hook ): void {
		foreach ( $GLOBALS['ran_booster_wp_pusher_test_hooks'][ $hook ] ?? array() as $registered ) {
			( $registered['callback'] )();
		}
	}
}

final class LifecyclePortabilityFacade extends PortabilityFacade {
	public int $review_calls = 0;

	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		unset( $nonce );
		++$this->review_calls;

		return new PortabilityReviewResult(
			$candidate,
			'adopt',
			'ready',
			'Ready to adopt.',
			'v1:' . str_repeat( 'a', 64 )
		);
	}

	public function apply(
		PortabilityCandidate $candidate,
		string $expected_fingerprint,
		string $nonce
	): PortabilityApplyResult {
		unset( $candidate, $expected_fingerprint, $nonce );
		throw new RuntimeException( 'Lifecycle characterization does not execute migration.' );
	}
}

final class LifecycleInteractionFacade implements AdminInteractionFacade, TransporterRowAdminInteractionFacade {
	/** @var list<string> */
	public array $rendered_operations = array();

	public function render_form_attributes( AdminInteractionRequest $request ): void {
		$this->rendered_operations[] = $request->operation();
	}

	public function is_enhanced_request( AdminInteractionRequest $request ): bool {
		unset( $request );

		return false;
	}

	public function respond( AdminInteractionOutcome $outcome ): never {
		unset( $outcome );
		throw new RuntimeException( 'Lifecycle characterization does not execute transport.' );
	}

	public function respond_with_transporter_row_fragment(
		AdminInteractionOutcome $outcome,
		callable $render_fragment
	): never {
		unset( $outcome, $render_fragment );
		throw new RuntimeException( 'Lifecycle characterization does not execute transport.' );
	}
}

final class LifecycleDatabase {
	public string $prefix  = 'wp_';
	public string $options = 'wp_options';

	/** @return array<string, mixed> */
	public static function fixtureRow(): array {
		return array(
			'id'           => '1',
			'package'      => 'fixture/fixture.php',
			'repository'   => 'fixture/repository',
			'branch'       => 'main',
			'type'         => '1',
			'status'       => '1',
			'ptd'          => '0',
			'host'         => 'gh',
			'private'      => '0',
			'subdirectory' => null,
		);
	}

	public function prepare( string $query, mixed ...$values ): string {
		unset( $values );

		return $query;
	}

	public function get_var( string $query ): string {
		unset( $query );

		return 'wp_wppusher_packages';
	}

	/** @return list<array<string, mixed>> */
	public function get_results( string $query, string $output ): array {
		unset( $output );
		if ( str_starts_with( $query, 'SHOW COLUMNS' ) ) {
			return array_map(
				static fn ( string $field, string $type ): array => array(
					'Field' => $field,
					'Type'  => $type,
				),
				array( 'id', 'package', 'repository', 'branch', 'type', 'status', 'ptd', 'host', 'private', 'subdirectory' ),
				array( 'mediumint(9)', 'varchar(255)', 'varchar(255)', 'varchar(255)', 'int', 'int', 'int', 'varchar(10)', 'int', 'varchar(255)' )
			);
		}

		return array( self::fixtureRow() );
	}

	/** @return list<string> */
	public function get_col( string $query ): array {
		unset( $query );

		return array();
	}
}

<?php

declare(strict_types=1);

namespace Tests;

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
use RAN\BoosterWpPusherMigrator\Plugin;
use ReflectionProperty;
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
		if ( ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' ) ) {
			define( 'RAN_BOOSTER_PORTABILITY_API_VERSION', 2 );
		}
		if ( ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' ) ) {
			define( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION', 2 );
		}
		$this->resetComposition();
	}

	protected function tearDown(): void {
		$this->resetComposition();
		$GLOBALS['ran_booster_wp_pusher_test_hooks']        = array();
		$GLOBALS['ran_booster_wp_pusher_test_events']       = array();
		$GLOBALS['ran_booster_wp_pusher_test_capabilities'] = array();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function testInitialRegistrationContainsOnlyCompatibilityBoundaries(): void {
		Plugin::register();

		self::assertSame(
			array(
				'ran_booster_portability_ready',
				'ran_booster_admin_interaction_ready',
				'admin_notices',
			),
			array_keys( $GLOBALS['ran_booster_wp_pusher_test_hooks'] )
		);
		self::assertNull( $this->pluginProperty( 'migration' ) );
		self::assertNull( $this->pluginProperty( 'source' ) );
		self::assertNull( $this->pluginProperty( 'adminInteraction' ) );
	}

	public function testWrongFacadeDeliveriesAreInertAndLeaveFeatureHooksAbsent(): void {
		Plugin::register();
		$hooks = $GLOBALS['ran_booster_wp_pusher_test_hooks'];

		$this->deliver( 'ran_booster_portability_ready', new stdClass() );
		$this->deliver( 'ran_booster_admin_interaction_ready', new stdClass() );
		$this->deliver( 'ran_booster_portability_ready', null );
		$this->deliver( 'ran_booster_portability_ready', 'wrong' );
		$this->deliver( 'ran_booster_admin_interaction_ready', null );
		$this->deliver( 'ran_booster_admin_interaction_ready', 'wrong' );

		self::assertNull( $this->pluginProperty( 'migration' ) );
		self::assertNull( $this->pluginProperty( 'source' ) );
		self::assertNull( $this->pluginProperty( 'adminInteraction' ) );
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
		Plugin::register();
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

		self::assertNotNull( $this->pluginProperty( 'migration' ) );
		self::assertNotNull( $this->pluginProperty( 'source' ) );
		self::assertSame( $interaction, $this->pluginProperty( 'adminInteraction' ) );
		self::assertTrue( $this->pluginProperty( 'featuresRegistered' ) );
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
		Plugin::register();
		$firstPortability = new LifecyclePortabilityFacade();
		$firstInteraction = new LifecycleInteractionFacade();
		$this->deliver( 'ran_booster_portability_ready', $firstPortability );
		$this->deliver( 'ran_booster_admin_interaction_ready', $firstInteraction );
		$firstMigration = $this->pluginProperty( 'migration' );
		$firstSource    = $this->pluginProperty( 'source' );
		$hooks          = $GLOBALS['ran_booster_wp_pusher_test_hooks'];

		$this->deliver( 'ran_booster_portability_ready', $firstPortability );
		$this->deliver( 'ran_booster_portability_ready', new LifecyclePortabilityFacade() );
		$this->deliver( 'ran_booster_admin_interaction_ready', $firstInteraction );
		$this->deliver( 'ran_booster_admin_interaction_ready', new LifecycleInteractionFacade() );
		$this->deliver( 'ran_booster_portability_ready', new stdClass() );
		$this->deliver( 'ran_booster_admin_interaction_ready', new stdClass() );

		self::assertSame( $firstMigration, $this->pluginProperty( 'migration' ) );
		self::assertSame( $firstSource, $this->pluginProperty( 'source' ) );
		self::assertSame( $firstInteraction, $this->pluginProperty( 'adminInteraction' ) );
		self::assertSame( $hooks, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	public function testMissingEitherFacadeLeavesEveryFeatureHookAbsent(): void {
		Plugin::register();
		$initial = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$this->deliver( 'ran_booster_portability_ready', new LifecyclePortabilityFacade() );
		self::assertNotNull( $this->pluginProperty( 'migration' ) );
		self::assertNull( $this->pluginProperty( 'adminInteraction' ) );
		self::assertSame( $initial, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );

		$this->resetComposition();
		$GLOBALS['ran_booster_wp_pusher_test_hooks'] = array();
		Plugin::register();
		$initial     = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$interaction = new LifecycleInteractionFacade();
		$this->deliver( 'ran_booster_admin_interaction_ready', $interaction );
		self::assertNull( $this->pluginProperty( 'migration' ) );
		self::assertSame( $interaction, $this->pluginProperty( 'adminInteraction' ) );
		self::assertSame( $initial, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	public function testCompatibilityNoticeIsCapabilityGatedAndSilentAfterComposition(): void {
		Plugin::register();
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

	private function resetComposition(): void {
		foreach ( array( 'migration', 'source', 'adminInteraction' ) as $property ) {
			$reflection = new ReflectionProperty( Plugin::class, $property );
			$reflection->setValue( null, null );
		}
		$features = new ReflectionProperty( Plugin::class, 'featuresRegistered' );
		$features->setValue( null, false );
	}

	private function pluginProperty( string $property ): mixed {
		$reflection = new ReflectionProperty( Plugin::class, $property );

		return $reflection->getValue();
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
	public function review( PortabilityCandidate $candidate, string $nonce ): PortabilityReviewResult {
		unset( $candidate, $nonce );
		throw new RuntimeException( 'Lifecycle characterization does not execute migration.' );
	}

	public function apply(
		PortabilityCandidate $candidate,
		string $expectedFingerprint,
		string $nonce
	): PortabilityApplyResult {
		unset( $candidate, $expectedFingerprint, $nonce );
		throw new RuntimeException( 'Lifecycle characterization does not execute migration.' );
	}
}

final class LifecycleInteractionFacade implements AdminInteractionFacade, TransporterRowAdminInteractionFacade {
	public function renderFormAttributes( AdminInteractionRequest $request ): void {
		unset( $request );
	}

	public function isEnhancedRequest( AdminInteractionRequest $request ): bool {
		unset( $request );

		return false;
	}

	public function respond( AdminInteractionOutcome $outcome ): never {
		unset( $outcome );
		throw new RuntimeException( 'Lifecycle characterization does not execute transport.' );
	}

	public function respondWithTransporterRowFragment(
		AdminInteractionOutcome $outcome,
		callable $renderFragment
	): never {
		unset( $outcome, $renderFragment );
		throw new RuntimeException( 'Lifecycle characterization does not execute transport.' );
	}
}

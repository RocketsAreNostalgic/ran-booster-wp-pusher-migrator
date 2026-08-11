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
		$GLOBALS['ran_booster_wp_pusher_test_hooks'] = array();
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
		$GLOBALS['ran_booster_wp_pusher_test_hooks'] = array();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function testBaselineRegistersFeatureHooksBeforeEitherFacadeExists(): void {
		Plugin::register();

		self::assertSame(
			array(
				'ran_booster_portability_ready',
				'ran_booster_admin_interaction_ready',
				'ran_booster_portability_render_migration_modes',
				'ran_booster_portability_render_migration_flows',
				'ran_booster_overview_render_migration_prompt',
				'admin_post_ran_booster_wp_pusher_migrator_package',
				'admin_enqueue_scripts',
			),
			array_keys( $GLOBALS['ran_booster_wp_pusher_test_hooks'] )
		);
		self::assertNull( $this->pluginProperty( 'migration' ) );
		self::assertNull( $this->pluginProperty( 'source' ) );
		self::assertNull( $this->pluginProperty( 'adminInteraction' ) );
	}

	public function testWrongFacadeDeliveriesAreInertWhilePrematureFeatureHooksRemain(): void {
		Plugin::register();
		$hooks = $GLOBALS['ran_booster_wp_pusher_test_hooks'];

		Plugin::connect( new stdClass() );
		Plugin::captureAdminInteraction( new stdClass() );

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
	public function testBothSourceDeliveryOrdersCaptureTheTwoExactFacadesWithoutAddingHooks( array $order ): void {
		Plugin::register();
		$hooks       = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		$portability = new LifecyclePortabilityFacade();
		$interaction = new LifecycleInteractionFacade();

		foreach ( $order as $delivery ) {
			'portability' === $delivery
				? Plugin::connect( $portability )
				: Plugin::captureAdminInteraction( $interaction );
		}

		self::assertNotNull( $this->pluginProperty( 'migration' ) );
		self::assertNotNull( $this->pluginProperty( 'source' ) );
		self::assertSame( $interaction, $this->pluginProperty( 'adminInteraction' ) );
		self::assertSame( $hooks, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	public function testBaselineDuplicateValidDeliveriesReplaceCompositionButDoNotDuplicateHooks(): void {
		Plugin::register();
		$hooks = $GLOBALS['ran_booster_wp_pusher_test_hooks'];
		Plugin::connect( new LifecyclePortabilityFacade() );
		$firstMigration   = $this->pluginProperty( 'migration' );
		$firstInteraction = new LifecycleInteractionFacade();
		Plugin::captureAdminInteraction( $firstInteraction );

		$secondInteraction = new LifecycleInteractionFacade();
		Plugin::connect( new LifecyclePortabilityFacade() );
		Plugin::captureAdminInteraction( $secondInteraction );

		self::assertNotSame( $firstMigration, $this->pluginProperty( 'migration' ) );
		self::assertNotSame( $firstInteraction, $this->pluginProperty( 'adminInteraction' ) );
		self::assertSame( $secondInteraction, $this->pluginProperty( 'adminInteraction' ) );
		self::assertSame( $hooks, $GLOBALS['ran_booster_wp_pusher_test_hooks'] );
	}

	public function testMissingEitherFacadeLeavesItsCompositionStateAbsent(): void {
		Plugin::connect( new LifecyclePortabilityFacade() );
		self::assertNotNull( $this->pluginProperty( 'migration' ) );
		self::assertNull( $this->pluginProperty( 'adminInteraction' ) );

		$this->resetComposition();
		$interaction = new LifecycleInteractionFacade();
		Plugin::captureAdminInteraction( $interaction );
		self::assertNull( $this->pluginProperty( 'migration' ) );
		self::assertSame( $interaction, $this->pluginProperty( 'adminInteraction' ) );
	}

	private function resetComposition(): void {
		foreach ( array( 'migration', 'source', 'adminInteraction' ) as $property ) {
			$reflection = new ReflectionProperty( Plugin::class, $property );
			$reflection->setValue( null, null );
		}
	}

	private function pluginProperty( string $property ): mixed {
		$reflection = new ReflectionProperty( Plugin::class, $property );

		return $reflection->getValue();
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

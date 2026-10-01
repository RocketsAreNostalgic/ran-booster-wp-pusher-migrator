<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityFacade;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;

/** Request-local lifecycle and exact Core facade composition root. */
final class Plugin {
	private const REQUIRED_PORTABILITY_API_VERSION       = 3;
	private const REQUIRED_ADMIN_INTERACTION_API_VERSION = 2;

	private ?PortabilityFacade $portability                 = null;
	private ?AdminInteractionFacade $admin_interaction      = null;
	private ?MigrationRequestController $request_controller = null;

	public function register(): void {
		add_action( 'ran_booster_portability_ready', array( $this, 'connect' ), 10, 1 );
		add_action( 'ran_booster_admin_interaction_ready', array( $this, 'capture_admin_interaction' ), 10, 1 );
		add_action( 'admin_notices', array( $this, 'render_compatibility_notice' ) );
	}

	public function connect( mixed $portability ): void {
		if ( null !== $this->portability
			|| ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
			|| self::REQUIRED_PORTABILITY_API_VERSION !== RAN_BOOSTER_PORTABILITY_API_VERSION
			|| self::REQUIRED_PORTABILITY_API_VERSION !== PortabilityFacade::API_VERSION
			|| ! $portability instanceof PortabilityFacade ) {
			return;
		}

		$this->portability = $portability;
		$this->compose();
	}

	public function capture_admin_interaction( mixed $facade ): void {
		if ( null !== $this->admin_interaction
			|| ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' )
			|| self::REQUIRED_ADMIN_INTERACTION_API_VERSION !== constant( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' )
			|| self::REQUIRED_ADMIN_INTERACTION_API_VERSION !== AdminInteractionFacade::API_VERSION
			|| ! interface_exists( TransporterRowAdminInteractionFacade::class )
			|| ! $facade instanceof AdminInteractionFacade
			|| ! $facade instanceof TransporterRowAdminInteractionFacade ) {
			return;
		}

		$this->admin_interaction = $facade;
		$this->compose();
	}

	public function render_compatibility_notice(): void {
		if ( null !== $this->request_controller || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p><?php esc_html_e( 'RAN Booster WP Pusher Migrator needs a compatible RAN Booster release before migration features can load.', 'ran-booster-wp-pusher-migrator' ); ?></p></div>
		<?php
	}

	private function compose(): void {
		if ( null !== $this->request_controller
			|| null === $this->portability
			|| ! $this->admin_interaction instanceof TransporterRowAdminInteractionFacade ) {
			return;
		}

		$source     = new WpPusherSource();
		$candidates = new CandidateFactory();
		$migration  = new MigrationService( $source, $candidates, $this->portability );
		$presenter  = new MigrationPresenter( $candidates, $this->admin_interaction );

		$this->request_controller = new MigrationRequestController(
			$migration,
			$source,
			$presenter,
			$this->admin_interaction
		);
		$this->request_controller->register();
	}
}

<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionOutcome;
use RAN\Admin\Interaction\AdminInteractionRequest;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use Throwable;

/** WordPress hooks and request-local migration presentation. */
final class Plugin {
	private const REQUIRED_PORTABILITY_API_VERSION       = 2;
	private const REQUIRED_ADMIN_INTERACTION_API_VERSION = 2;

	private const FORM_ACTION       = 'ran-booster-wp-pusher-migrator-review-v1';
	private const APPLY_FORM_ACTION = 'ran-booster-wp-pusher-migrator-apply-v1';
	private const ADMIN_POST_ACTION = 'ran_booster_wp_pusher_migrator_package';

	private static ?MigrationService $migration              = null;
	private static ?WpPusherSource $source                   = null;
	private static ?AdminInteractionFacade $adminInteraction = null;
	private static bool $featuresRegistered                  = false;

	public static function register(): void {
		add_action( 'ran_booster_portability_ready', array( self::class, 'connect' ), 10, 1 );
		add_action( 'ran_booster_admin_interaction_ready', array( self::class, 'captureAdminInteraction' ), 10, 1 );
		add_action( 'admin_notices', array( self::class, 'renderCompatibilityNotice' ) );
	}

	public static function connect( mixed $portability ): void {
		if ( null !== self::$migration
			|| ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
			|| self::REQUIRED_PORTABILITY_API_VERSION !== RAN_BOOSTER_PORTABILITY_API_VERSION
			|| self::REQUIRED_PORTABILITY_API_VERSION !== PortabilityFacade::API_VERSION
			|| ! $portability instanceof PortabilityFacade ) {
			return;
		}

		self::$source    = new WpPusherSource();
		self::$migration = new MigrationService( self::$source, new CandidateFactory(), $portability );
		self::registerFeatures();
	}

	public static function captureAdminInteraction( mixed $facade ): void {
		if ( null !== self::$adminInteraction
			|| ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' )
			|| self::REQUIRED_ADMIN_INTERACTION_API_VERSION !== constant( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' )
			|| self::REQUIRED_ADMIN_INTERACTION_API_VERSION !== AdminInteractionFacade::API_VERSION
			|| ! interface_exists( TransporterRowAdminInteractionFacade::class )
			|| ! $facade instanceof AdminInteractionFacade
			|| ! $facade instanceof TransporterRowAdminInteractionFacade ) {
			return;
		}

		self::$adminInteraction = $facade;
		self::registerFeatures();
	}

	public static function renderCompatibilityNotice(): void {
		if ( self::$featuresRegistered || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p><?php esc_html_e( 'RAN Booster WP Pusher Migrator needs a compatible RAN Booster release before migration features can load.', 'ran-booster-wp-pusher-migrator' ); ?></p></div>
		<?php
	}

	private static function registerFeatures(): void {
		if ( self::$featuresRegistered
			|| null === self::$migration
			|| ! self::$adminInteraction instanceof TransporterRowAdminInteractionFacade ) {
			return;
		}

		self::$featuresRegistered = true;
		add_action( 'ran_booster_portability_render_migration_modes', array( self::class, 'renderMode' ), 20 );
		add_action( 'ran_booster_portability_render_migration_flows', array( self::class, 'renderPanel' ), 20 );
		add_action( 'ran_booster_overview_render_migration_prompt', array( self::class, 'renderOverviewPrompt' ), 20 );
		add_action( 'admin_post_' . self::ADMIN_POST_ACTION, array( self::class, 'handleAdminPost' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueueAssets' ), 20 );
	}

	public static function renderMode(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require dirname( __DIR__ ) . '/views/migration-mode.php';
	}

	public static function renderPanel(): void {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = self::requestOperation( $request );
		$submitted = self::isSubmittedRequest( $request );
		if ( $submitted && ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			wp_die(
				esc_html__( 'Choose a valid WP Pusher migration action.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Invalid migration request', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 400 )
			);
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $submitted ) {
			check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );
		}

		try {
			$operationOutcome = $submitted ? self::operationOutcome( $operation, $request ) : null;
			$error            = null !== $operationOutcome
				&& 'success' !== $operationOutcome['kind']
				&& null === $operationOutcome['apply']
				? ( 'unexpected_failure' === $operationOutcome['kind']
					? __( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' )
					: $operationOutcome['message'] )
				: '';
			$packages         = '' === $error ? self::$migration->packages() : array();
			$review           = $operationOutcome['review'] ?? null;
			$apply            = $operationOutcome['apply'] ?? null;
			$migrationUrl     = self::migrationUrl();
			$optionPresence   = '' === $error ? self::$source->optionPresence() : array();
			$rows             = self::rows( $packages, $review, $migrationUrl );
			$formAction       = self::FORM_ACTION;
			$applyFormAction  = self::APPLY_FORM_ACTION;
			$adminPostAction  = self::ADMIN_POST_ACTION;
			$adminInteraction = self::$adminInteraction;
			$pluginsUrl       = admin_url( 'plugins.php' );
			require dirname( __DIR__ ) . '/views/source-card.php';
		} catch ( Throwable ) {
			$error = __( 'Booster could not read this WP Pusher installation safely. Check that WP Pusher 3.0.13 is installed and inactive, then try again.', 'ran-booster-wp-pusher-migrator' );
			require dirname( __DIR__ ) . '/views/source-card.php';
		}
	}

	public static function renderOverviewPrompt(): void {
		if ( ! current_user_can( 'manage_options' )
			|| null === self::$source ) {
			return;
		}

		try {
			if ( ! self::$source->supportedPackageTablePresent() ) {
				return;
			}
		} catch ( Throwable ) {
			return;
		}

		$migrationUrl = admin_url( 'admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher' );
		require dirname( __DIR__ ) . '/views/overview-prompt.php';
	}

	public static function enqueueAssets( mixed $hook ): void {
		if ( 'toplevel_page_ran-booster' !== $hook ) {
			return;
		}

		// Read-only allowlisted navigation state; no action is performed from this value.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation state.
			? sanitize_key( wp_unslash( $_GET['tab'] ) )
			: 'overview';
		if ( ! in_array( $tab, array( 'overview', 'portability' ), true ) ) {
			return;
		}

		$path = dirname( __DIR__ ) . '/assets/wp-pusher-migrator.css';
		$url  = plugins_url( 'assets/wp-pusher-migrator.css', dirname( __DIR__ ) . '/ran-booster-wp-pusher-migrator.php' );
		wp_enqueue_style(
			'ran-booster-wp-pusher-migrator',
			$url,
			array( 'ran-booster-onboarding' ),
			file_exists( $path ) ? (string) filemtime( $path ) : null
		);
	}

	public static function handleAdminPost(): void {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = self::requestOperation( $request );
		if ( ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			wp_die(
				esc_html__( 'Choose a valid WP Pusher migration action.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Invalid migration request', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 400 )
			);
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to migrate WP Pusher packages.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration unavailable', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 403 )
			);
		}
		check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );
		if ( null === self::$migration
			|| ! self::$adminInteraction instanceof TransporterRowAdminInteractionFacade ) {
			wp_die(
				esc_html__( 'Update Booster before migrating. This migrator needs compatible administration interaction support.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration unavailable', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 503 )
			);
		}

		$operationOutcome = self::operationOutcome( $operation, $request );
		$source           = $operationOutcome['source'];
		if ( ! $source instanceof WpPusherPackage ) {
			wp_die(
				esc_html__( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration request failed', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 409 )
			);
		}

		$migrationUrl       = self::migrationUrl();
		$interactionRequest = self::interactionRequest(
			$source,
			'review' === $operation ? 'check-package' : 'import-package',
			$migrationUrl
		);
		$outcome            = match ( $operationOutcome['kind'] ) {
			'success' => AdminInteractionOutcome::success( $interactionRequest, $operationOutcome['message'] ),
			'validation_failure' => AdminInteractionOutcome::validationFailure( $interactionRequest, $operationOutcome['message'] ),
			default => AdminInteractionOutcome::unexpectedFailure( $interactionRequest ),
		};
		$fragmentRow = null;
		if ( 'success' === $operationOutcome['kind'] ) {
			$fragmentRow = 'review' === $operation
				? self::row( $source, $operationOutcome['review'], $migrationUrl )
				: self::importedRow(
					$source,
					$migrationUrl,
					$operationOutcome['apply']['result'],
					$operationOutcome['migration_complete']
				);
		}

		self::$adminInteraction->respondWithTransporterRowFragment(
			$outcome,
			static function ( string $targetElementId ) use ( $fragmentRow ): void {
				if ( is_array( $fragmentRow ) ) {
					self::renderSourceRow( $fragmentRow, $targetElementId );
				}
			}
		);
	}

	/**
	 * @param array<string, mixed> $request Authorized request values.
	 * @return array{kind:'success'|'validation_failure'|'unexpected_failure',message:string,source:WpPusherPackage|null,review:PortabilityReviewResult|null,apply:array{result:PortabilityApplyResult,cleanup_pending:bool}|null,migration_complete:bool}
	 */
	private static function operationOutcome( string $operation, array $request ): array {
		$outcome = array(
			'kind'               => 'success',
			'message'            => '',
			'source'             => null,
			'review'             => null,
			'apply'              => null,
			'migration_complete' => false,
		);
		try {
			$packages          = self::$migration->packages();
			$source            = self::package( $packages, isset( $request['source_id'] ) ? absint( $request['source_id'] ) : 0 );
			$outcome['source'] = $source;
			if ( 'review' === $operation ) {
				$outcome['review']  = self::review( $source, $request );
				$outcome['message'] = $outcome['review']->message;

				return $outcome;
			}

			$apply                         = self::apply( $source, $request );
			$outcome['apply']              = $apply;
			$outcome['migration_complete'] = ! $apply['cleanup_pending']
				&& $apply['result']->targetVerified
				&& self::migrationComplete();
			$outcome['message']            = $apply['cleanup_pending']
				? __( 'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.', 'ran-booster-wp-pusher-migrator' )
				: $apply['result']->message;
			$outcome['kind']               = $apply['result']->targetVerified && ! $apply['cleanup_pending'] ? 'success' : 'validation_failure';
		} catch ( Throwable $failure ) {
			$outcome['message'] = self::expectedFailureMessage( $failure ) ?? '';
			$outcome['kind']    = '' === $outcome['message'] ? 'unexpected_failure' : 'validation_failure';
		}

		return $outcome;
	}

	/**
	 * @param array<string, mixed> $request Authorized request values.
	 */
	private static function review( WpPusherPackage $source, array $request ): PortabilityReviewResult {
		$expected     = isset( $request['source_fingerprint'] ) && is_scalar( $request['source_fingerprint'] )
			? sanitize_text_field( (string) $request['source_fingerprint'] )
			: '';
		$credentialId = isset( $request['credential_id'] ) && is_scalar( $request['credential_id'] )
			? sanitize_text_field( (string) $request['credential_id'] )
			: '';
		$credentialId = '' === $credentialId ? null : $credentialId;

		$coreAction = self::$migration->nonceAction( 'review', $source, $credentialId );
		$coreNonce  = wp_create_nonce( $coreAction );

		return self::$migration->review( $source->id, $expected, $credentialId, $coreNonce );
	}

	/**
	 * @param array<string, mixed> $request Authorized request values.
	 * @return array{result:PortabilityApplyResult,cleanup_pending:bool}
	 */
	private static function apply( WpPusherPackage $source, array $request ): array {
		$expectedSource = isset( $request['source_fingerprint'] ) && is_scalar( $request['source_fingerprint'] )
			? sanitize_text_field( (string) $request['source_fingerprint'] )
			: '';
		$expectedReview = isset( $request['review_fingerprint'] ) && is_scalar( $request['review_fingerprint'] )
			? sanitize_text_field( (string) $request['review_fingerprint'] )
			: '';
		$credentialId   = isset( $request['credential_id'] ) && is_scalar( $request['credential_id'] )
			? sanitize_text_field( (string) $request['credential_id'] )
			: '';
		$credentialId   = '' === $credentialId ? null : $credentialId;
		$coreAction     = self::$migration->nonceAction( 'apply', $source, $credentialId, $expectedReview );
		$coreNonce      = wp_create_nonce( $coreAction );

		$result  = self::$migration->apply(
			$source->id,
			$expectedSource,
			$credentialId,
			$expectedReview,
			$coreNonce
		);
		$removed = $result->targetVerified
			&& self::$migration->cleanup( $source->id, $expectedSource, $result );

		return array(
			'result'          => $result,
			'cleanup_pending' => $result->targetVerified && ! $removed,
		);
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 */
	private static function package( array $packages, int $sourceId ): WpPusherPackage {
		foreach ( $packages as $package ) {
			if ( $sourceId === $package->id ) {
				return $package;
			}
		}

		throw new \RuntimeException( 'The WP Pusher package is no longer available.' );
	}

	/**
	 * @param list<WpPusherPackage>   $packages Current exact source rows.
	 * @return list<array<string, mixed>>
	 */
	private static function rows( array $packages, ?PortabilityReviewResult $review, string $migrationUrl ): array {
		$rows = array();
		foreach ( $packages as $source ) {
			$sourceReview = null !== $review && $review->candidate->identifier === $source->package ? $review : null;
			$rows[]       = self::row( $source, $sourceReview, $migrationUrl );
		}

		return $rows;
	}

	/** @return array<string, mixed> */
	private static function row(
		WpPusherPackage $source,
		?PortabilityReviewResult $review,
		string $migrationUrl
	): array {
		$candidate = null;
		$error     = '';
		try {
			$candidate = ( new CandidateFactory() )->candidate(
				$source,
				1 === $source->private ? 'credential_required' : null
			);
		} catch ( Throwable $failure ) {
			$error = self::expectedFailureMessage( $failure )
				?? __( 'This retained package is unsupported.', 'ran-booster-wp-pusher-migrator' );
		}

		$checkRequest  = null;
		$importRequest = null;
		if ( self::$adminInteraction instanceof TransporterRowAdminInteractionFacade ) {
			$checkRequest  = self::interactionRequest( $source, 'check-package', $migrationUrl );
			$importRequest = self::interactionRequest( $source, 'import-package', $migrationUrl );
		}

		return array(
			'source'             => $source,
			'candidate'          => $candidate,
			'error'              => $error,
			'review'             => $review,
			'imported'           => false,
			'migration_complete' => false,
			'status_label'       => '',
			'manage_url'         => '',
			'manage_label'       => '',
			'check_request'      => $checkRequest,
			'import_request'     => $importRequest,
			'error_region_id'    => self::errorRegionId( $source ),
		);
	}

	/** @return array<string, mixed> */
	private static function importedRow(
		WpPusherPackage $source,
		string $migrationUrl,
		PortabilityApplyResult $result,
		bool $migrationComplete
	): array {
		$row                       = self::row( $source, null, $migrationUrl );
		$row['imported']           = true;
		$row['migration_complete'] = $migrationComplete;
		$row['status_label']       = 'adopted' === $result->status
			? __( 'Adopted', 'ran-booster-wp-pusher-migrator' )
			: __( 'Adoption verified', 'ran-booster-wp-pusher-migrator' );
		$row['manage_url']         = admin_url(
			'admin.php?page=' . ( 1 === $source->type ? 'ran-booster-plugins' : 'ran-booster-themes' )
			. '&package=' . rawurlencode( $source->package )
		);
		$row['manage_label']       = __( 'Settings', 'ran-booster-wp-pusher-migrator' );

		return $row;
	}

	private static function migrationComplete(): bool {
		try {
			return null !== self::$migration && array() === self::$migration->packages();
		} catch ( Throwable ) {
			return false;
		}
	}

	/** @param array<string, mixed> $row */
	private static function renderSourceRow( array $row, string $targetElementId ): void {
		$migrationUrl       = self::migrationUrl();
		$formAction         = self::FORM_ACTION;
		$applyFormAction    = self::APPLY_FORM_ACTION;
		$adminPostAction    = self::ADMIN_POST_ACTION;
		$adminInteraction   = self::$adminInteraction;
		$rowTargetElementId = $targetElementId;
		require dirname( __DIR__ ) . '/views/source-row.php';
	}

	private static function interactionRequest(
		WpPusherPackage $source,
		string $operation,
		string $migrationUrl
	): AdminInteractionRequest {
		$identityHash = substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );

		return AdminInteractionRequest::transporterMigrationSourceRow(
			'wp-pusher:' . $operation,
			'wp-pusher:package-' . $identityHash,
			$migrationUrl,
			self::errorRegionId( $source )
		);
	}

	private static function errorRegionId( WpPusherPackage $source ): string {
		return 'ran-booster-wp-pusher-row-error-'
			. substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 32 );
	}

	/** @param array<string, mixed> $request */
	private static function requestOperation( array $request ): string {
		return isset( $request['ran_booster_wp_pusher_migrator_action'] )
			&& is_scalar( $request['ran_booster_wp_pusher_migrator_action'] )
				? sanitize_key( (string) $request['ran_booster_wp_pusher_migrator_action'] )
				: '';
	}

	/** @param array<string, mixed> $request */
	private static function isSubmittedRequest( array $request ): bool {
		return array_key_exists( 'ran_booster_wp_pusher_migrator_action', $request )
			|| ( isset( $request['action'] )
				&& is_scalar( $request['action'] )
				&& self::ADMIN_POST_ACTION === sanitize_key( (string) $request['action'] ) );
	}

	private static function migrationUrl(): string {
		return admin_url( 'admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher' );
	}

	private static function expectedFailureMessage( Throwable $failure ): ?string {
		if ( ! $failure instanceof \RuntimeException ) {
			return null;
		}

		$message = $failure->getMessage();
		$allowed = array(
			'The WP Pusher package could not be checked.',
			'The WP Pusher package could not be adopted.',
			'The WP Pusher package is no longer available.',
			'Refresh the WP Pusher migration review.',
			'The WP Pusher package changed. Review it again.',
			'GitLab WP Pusher packages are not supported.',
			'Choose an existing Booster credential profile for this private repository.',
			'The Booster credential profile identifier is invalid.',
			'The WP Pusher plugin is not installed.',
			'The WP Pusher theme is not installed.',
			'The retained WP Pusher package inventory is unsupported.',
			'The retained WP Pusher package inventory contains duplicates.',
			'WP Pusher migration supports single-site WordPress only.',
			'Only retained WP Pusher 3.0.13 data is supported.',
			'Deactivate WP Pusher before assessing retained packages.',
			'The retained WP Pusher package schema is unsupported.',
			'The WordPress database prefix is invalid.',
		);

		return in_array( $message, $allowed, true ) ? $message : null;
	}
}

<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Logging\LoggingFacade;
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

	private const FORM_ACTION       = 'ran-booster-wp-pusher-migrator-review-v1';
	private const APPLY_FORM_ACTION = 'ran-booster-wp-pusher-migrator-apply-v1';
	private const OPTIONS_ACTION    = 'ran-booster-wp-pusher-migrator-delete-options-v1';
	private const TABLE_ACTION      = 'ran-booster-wp-pusher-migrator-drop-table-v1';
	private const ADMIN_POST_ACTION = 'ran_booster_wp_pusher_migrator_package';

	private static ?MigrationService $migration              = null;
	private static ?WpPusherSource $source                   = null;
	private static ?AdminInteractionFacade $adminInteraction = null;
	private static ?LoggingFacade $logging                   = null;

	public static function register(): void {
		add_action( 'ran_booster_portability_ready', array( self::class, 'connect' ), 10, 2 );
		add_action( 'ran_booster_admin_interaction_ready', array( self::class, 'captureAdminInteraction' ), 10, 2 );
		add_action( 'ran_booster_portability_render_migration_modes', array( self::class, 'renderMode' ), 20 );
		add_action( 'ran_booster_portability_render_migration_flows', array( self::class, 'renderPanel' ), 20 );
		add_action( 'ran_booster_overview_render_migration_prompt', array( self::class, 'renderOverviewPrompt' ), 20 );
		add_action( 'admin_post_' . self::ADMIN_POST_ACTION, array( self::class, 'handleAdminPost' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueueAssets' ), 20 );
	}

	public static function connect( object $portability, object $logging ): void {
		if ( ! defined( 'RAN_BOOSTER_PORTABILITY_API_VERSION' )
			|| 1 !== RAN_BOOSTER_PORTABILITY_API_VERSION
			|| ! defined( 'RAN_BOOSTER_LOGGING_API_VERSION' )
			|| 1 !== RAN_BOOSTER_LOGGING_API_VERSION
			|| ! $portability instanceof PortabilityFacade
			|| ! $logging instanceof LoggingFacade ) {
			return;
		}

		self::$source    = new WpPusherSource();
		self::$migration = new MigrationService( self::$source, new CandidateFactory(), $portability );
		self::$logging   = $logging;
	}

	public static function captureAdminInteraction( mixed $facade, mixed $logging ): void {
		unset( $logging );
		if ( ! defined( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' )
			|| AdminInteractionFacade::API_VERSION !== constant( 'RAN_BOOSTER_ADMIN_INTERACTION_API_VERSION' )
			|| ! interface_exists( TransporterRowAdminInteractionFacade::class )
			|| ! $facade instanceof AdminInteractionFacade
			|| ! $facade instanceof TransporterRowAdminInteractionFacade ) {
			return;
		}

		self::$adminInteraction = $facade;
	}

	public static function renderMode(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require dirname( __DIR__ ) . '/views/migration-mode.php';
	}

	public static function renderPanel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$migrationUrl = admin_url( 'admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher' );
		if ( null === self::$migration ) {
			$error = __( 'Update Booster before migrating. This migrator needs a compatible version of Booster.', 'ran-booster-wp-pusher-migrator' );
			require dirname( __DIR__ ) . '/views/source-card.php';
			return;
		}

		try {
			$error    = '';
			$packages = self::$migration->packages();
			$review   = self::submittedReview( $packages );
			$apply    = self::submittedApply( $packages );
			if ( null !== $apply ) {
				$packages = self::$migration->packages();
			}
			$cleanup          = self::submittedCleanup( $packages );
			$optionPresence   = self::$source->optionPresence();
			$tablePresent     = self::$source->packageTablePresent();
			$rows             = self::rows( $packages, $review, $migrationUrl );
			$formAction       = self::FORM_ACTION;
			$applyFormAction  = self::APPLY_FORM_ACTION;
			$optionsAction    = self::OPTIONS_ACTION;
			$tableAction      = self::TABLE_ACTION;
			$adminPostAction  = self::ADMIN_POST_ACTION;
			$adminInteraction = self::$adminInteraction;
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to migrate WP Pusher packages.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration unavailable', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 403 )
			);
		}
		if ( null === self::$migration
			|| null === self::$adminInteraction
			|| ! self::$adminInteraction instanceof TransporterRowAdminInteractionFacade ) {
			wp_die(
				esc_html__( 'Update Booster before migrating. This migrator needs compatible administration interaction support.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration unavailable', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 503 )
			);
		}

		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = self::requestOperation( $request );
		if ( ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			wp_die(
				esc_html__( 'Choose a valid WP Pusher migration action.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Invalid migration request', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 400 )
			);
		}

		check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );

		$source             = null;
		$interactionRequest = null;
		$outcome            = null;
		$fragmentRow        = null;
		try {
			$packages           = self::$migration->packages();
			$sourceId           = isset( $request['source_id'] ) ? absint( $request['source_id'] ) : 0;
			$source             = self::package( $packages, $sourceId );
			$migrationUrl       = self::migrationUrl();
			$interactionRequest = self::interactionRequest(
				$source,
				'review' === $operation ? 'check-package' : 'import-package',
				$migrationUrl
			);

			if ( 'review' === $operation ) {
				$review = self::review( $packages, $request );
				if ( null === $review ) {
					throw new \RuntimeException( 'The WP Pusher package could not be checked.' );
				}
				$row         = self::row( $source, $review, $migrationUrl );
				$outcome     = AdminInteractionOutcome::success( $interactionRequest, $review->message );
				$fragmentRow = $row;
			} else {
				$apply = self::apply( $packages, $request );
				if ( null === $apply ) {
					throw new \RuntimeException( 'The WP Pusher package could not be adopted.' );
				}
				$result = $apply['result'];
				if ( ! $result->targetVerified || $apply['cleanup_pending'] ) {
					$message = $apply['cleanup_pending']
						? __( 'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.', 'ran-booster-wp-pusher-migrator' )
						: $result->message;
					$outcome = AdminInteractionOutcome::validationFailure( $interactionRequest, $message );
				} else {
					$fragmentRow = self::importedRow(
						$source,
						$migrationUrl,
						$result,
						self::migrationComplete()
					);
					$outcome     = AdminInteractionOutcome::success( $interactionRequest, $result->message );
				}
			}
		} catch ( Throwable $failure ) {
			if ( $source instanceof WpPusherPackage
				&& $interactionRequest instanceof AdminInteractionRequest ) {
				$message = self::expectedFailureMessage( $failure );
				if ( null === $message ) {
					self::$logging?->logException(
						'WP Pusher package migration interaction failed.',
						$failure,
						array(
							'operation'    => $operation,
							'package_type' => 1 === $source->type ? 'plugin' : 'theme',
						)
					);
				}
				self::$adminInteraction->respondWithTransporterRowFragment(
					null === $message
						? AdminInteractionOutcome::unexpectedFailure( $interactionRequest )
						: AdminInteractionOutcome::validationFailure( $interactionRequest, $message ),
					static function (): void {
					}
				);
			}

			self::$logging?->logException(
				'WP Pusher package migration request failed before a row response was available.',
				$failure,
				array( 'operation' => $operation )
			);
			wp_die(
				esc_html__( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration request failed', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 409 )
			);
		}

		if ( ! $outcome instanceof AdminInteractionOutcome ) {
			wp_die(
				esc_html__( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration request failed', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 400 )
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
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 * @return array{success:bool,message:string}|null
	 */
	private static function submittedCleanup( array $packages ): ?array {
		$operation = isset( $_POST['ran_booster_wp_pusher_migrator_action'] ) && is_scalar( $_POST['ran_booster_wp_pusher_migrator_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['ran_booster_wp_pusher_migrator_action'] ) )
			: '';
		if ( ! in_array( $operation, array( 'delete_options', 'drop_table' ), true ) ) {
			return null;
		}
		check_admin_referer( 'delete_options' === $operation ? self::OPTIONS_ACTION : self::TABLE_ACTION );
		if ( array() !== $packages ) {
			return array(
				'success' => false,
				'message' => __( 'Migrate every supported package row before cleaning up WP Pusher data.', 'ran-booster-wp-pusher-migrator' ),
			);
		}

		if ( 'delete_options' === $operation ) {
			$success = self::$source->deleteUnusedOptions();
			$message = $success
				? __( 'Unused known WP Pusher options were removed. The license key and unknown options were preserved.', 'ran-booster-wp-pusher-migrator' )
				: __( 'WP Pusher options were not removed because the source state changed.', 'ran-booster-wp-pusher-migrator' );
		} else {
			$success = self::$source->dropEmptyPackageTable();
			$message = $success
				? __( 'The freshly verified empty WP Pusher package table was removed.', 'ran-booster-wp-pusher-migrator' )
				: __( 'The WP Pusher package table was not removed because it changed or is not empty.', 'ran-booster-wp-pusher-migrator' );
		}

		return compact( 'success', 'message' );
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 */
	private static function submittedReview( array $packages ): ?PortabilityReviewResult {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = self::requestOperation( $request );
		if ( 'review' !== $operation ) {
			return null;
		}
		check_admin_referer( self::FORM_ACTION );

		return self::review( $packages, $request );
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 * @param array<string, mixed>   $request  Authorized request values.
	 */
	private static function review( array $packages, array $request ): ?PortabilityReviewResult {
		if ( 'review' !== self::requestOperation( $request ) ) {
			return null;
		}

		$sourceId     = isset( $request['source_id'] ) ? absint( $request['source_id'] ) : 0;
		$source       = self::package( $packages, $sourceId );
		$expected     = isset( $request['source_fingerprint'] ) && is_scalar( $request['source_fingerprint'] )
			? sanitize_text_field( (string) $request['source_fingerprint'] )
			: '';
		$credentialId = isset( $request['credential_id'] ) && is_scalar( $request['credential_id'] )
			? sanitize_text_field( (string) $request['credential_id'] )
			: '';
		$credentialId = '' === $credentialId ? null : $credentialId;

		$coreAction = self::$migration->nonceAction( 'review', $source, $credentialId );
		$coreNonce  = wp_create_nonce( $coreAction );

		return self::$migration->review( $sourceId, $expected, $credentialId, $coreNonce );
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 */
	/** @return array{result:PortabilityApplyResult,cleanup_pending:bool}|null */
	private static function submittedApply( array $packages ): ?array {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = self::requestOperation( $request );
		if ( 'apply' !== $operation ) {
			return null;
		}
		check_admin_referer( self::APPLY_FORM_ACTION );

		return self::apply( $packages, $request );
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 * @param array<string, mixed>   $request  Authorized request values.
	 * @return array{result:PortabilityApplyResult,cleanup_pending:bool}|null
	 */
	private static function apply( array $packages, array $request ): ?array {
		if ( 'apply' !== self::requestOperation( $request ) ) {
			return null;
		}

		$sourceId       = isset( $request['source_id'] ) ? absint( $request['source_id'] ) : 0;
		$source         = self::package( $packages, $sourceId );
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
			$sourceId,
			$expectedSource,
			$credentialId,
			$expectedReview,
			$coreNonce
		);
		$removed = $result->targetVerified
			&& self::$migration->cleanup( $sourceId, $expectedSource, $result );

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
			$error = self::safeSourceError( $failure );
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
		} catch ( Throwable $failure ) {
			self::$logging?->logException(
				'WP Pusher migration completion could not be verified.',
				$failure
			);

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

	private static function migrationUrl(): string {
		return admin_url( 'admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher' );
	}

	private static function safeSourceError( Throwable $failure ): string {
		$message = self::expectedFailureMessage( $failure );

		return $message ?? __( 'This retained package is unsupported.', 'ran-booster-wp-pusher-migrator' );
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

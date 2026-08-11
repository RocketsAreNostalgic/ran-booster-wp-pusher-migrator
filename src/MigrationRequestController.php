<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionOutcome;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use RuntimeException;
use Throwable;

/**
 * Owns feature hooks, request authority, inventory, operations, and transports.
 *
 * @internal
 */
final readonly class MigrationRequestController {
	private const FORM_ACTION       = 'ran-booster-wp-pusher-migrator-review-v1';
	private const APPLY_FORM_ACTION = 'ran-booster-wp-pusher-migrator-apply-v1';
	private const ADMIN_POST_ACTION = 'ran_booster_wp_pusher_migrator_package';

	public function __construct(
		private MigrationService $migration,
		private WpPusherSource $source,
		private MigrationPresenter $presenter,
		private AdminInteractionFacade&TransporterRowAdminInteractionFacade $adminInteraction
	) {
	}

	public function register(): void {
		add_action( 'ran_booster_portability_render_migration_modes', array( $this, 'renderMode' ), 20 );
		add_action( 'ran_booster_portability_render_migration_flows', array( $this, 'renderPanel' ), 20 );
		add_action( 'ran_booster_overview_render_migration_prompt', array( $this, 'renderOverviewPrompt' ), 20 );
		add_action( 'admin_post_' . self::ADMIN_POST_ACTION, array( $this, 'handleAdminPost' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ), 20 );
	}

	public function renderMode(): void {
		if ( current_user_can( 'manage_options' ) ) {
			$this->presenter->renderMode();
		}
	}

	public function renderPanel(): void {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = $this->requestOperation( $request );
		$submitted = $this->isSubmittedRequest( $request );
		if ( $submitted && ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			$this->invalidOperation();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $submitted ) {
			check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );
		}

		try {
			$outcome  = $submitted ? $this->operationOutcome( $operation, $request ) : null;
			$error    = null !== $outcome && 'success' !== $outcome['kind'] && null === $outcome['apply']
				? ( 'unexpected_failure' === $outcome['kind']
					? __( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' )
					: $outcome['message'] )
				: '';
			$packages = '' === $error ? $this->migration->packages() : array();
			$this->presenter->renderPanel(
				$packages,
				$outcome['review'] ?? null,
				$outcome['apply'] ?? null,
				'' === $error ? $this->source->optionPresence() : array(),
				$error
			);
		} catch ( Throwable ) {
			$this->presenter->renderPanel(
				array(),
				null,
				null,
				array(),
				__( 'Booster could not read this WP Pusher installation safely. Check that WP Pusher 3.0.13 is installed and inactive, then try again.', 'ran-booster-wp-pusher-migrator' )
			);
		}
	}

	public function renderOverviewPrompt(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		try {
			if ( ! $this->source->supportedPackageTablePresent() ) {
				return;
			}
		} catch ( Throwable ) {
			return;
		}
		$this->presenter->renderOverviewPrompt();
	}

	public function enqueueAssets( mixed $hook ): void {
		if ( 'toplevel_page_ran-booster' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted navigation state.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted navigation state.
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

	public function handleAdminPost(): void {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = $this->requestOperation( $request );
		if ( ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			$this->invalidOperation();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to migrate WP Pusher packages.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration unavailable', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 403 )
			);
		}
		check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );

		$outcome = $this->operationOutcome( $operation, $request );
		$source  = $outcome['source'];
		if ( ! $source instanceof WpPusherPackage ) {
			wp_die(
				esc_html__( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration request failed', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 409 )
			);
		}

		$requestModel       = $this->presenter->interactionRequest(
			$source,
			'review' === $operation ? 'check-package' : 'import-package'
		);
		$interactionOutcome = match ( $outcome['kind'] ) {
			'success' => AdminInteractionOutcome::success( $requestModel, $outcome['message'] ),
			'validation_failure' => AdminInteractionOutcome::validationFailure( $requestModel, $outcome['message'] ),
			default => AdminInteractionOutcome::unexpectedFailure( $requestModel ),
		};
		$row = null;
		if ( 'success' === $outcome['kind'] ) {
			$row = 'review' === $operation
				? $this->presenter->row( $source, $outcome['review'] )
				: $this->presenter->importedRow(
					$source,
					$outcome['apply']['result'],
					$outcome['migration_complete']
				);
		}

		$this->adminInteraction->respondWithTransporterRowFragment(
			$interactionOutcome,
			fn ( string $targetElementId ): mixed => is_array( $row )
				? $this->presenter->renderSourceRow( $row, $targetElementId )
				: null
		);
	}

	/**
	 * @param array<string, mixed> $request Authorized request values.
	 * @return array{kind:'success'|'validation_failure'|'unexpected_failure',message:string,source:WpPusherPackage|null,review:PortabilityReviewResult|null,apply:array{result:PortabilityApplyResult,cleanup_pending:bool}|null,migration_complete:bool}
	 */
	private function operationOutcome( string $operation, array $request ): array {
		$outcome = array(
			'kind'               => 'success',
			'message'            => '',
			'source'             => null,
			'review'             => null,
			'apply'              => null,
			'migration_complete' => false,
		);
		try {
			$source            = $this->package( $this->migration->packages(), isset( $request['source_id'] ) ? absint( $request['source_id'] ) : 0 );
			$outcome['source'] = $source;
			$credentialId      = $this->requestValue( $request, 'credential_id' );
			$credentialId      = '' === $credentialId ? null : $credentialId;
			$sourceFingerprint = $this->requestValue( $request, 'source_fingerprint' );
			if ( 'review' === $operation ) {
				$nonce              = wp_create_nonce( $this->migration->nonceAction( 'review', $source, $credentialId ) );
				$outcome['review']  = $this->migration->review( $source->id, $sourceFingerprint, $credentialId, $nonce );
				$outcome['message'] = $outcome['review']->message;
				return $outcome;
			}

			$reviewFingerprint             = $this->requestValue( $request, 'review_fingerprint' );
			$nonce                         = wp_create_nonce( $this->migration->nonceAction( 'apply', $source, $credentialId, $reviewFingerprint ) );
			$result                        = $this->migration->apply( $source->id, $sourceFingerprint, $credentialId, $reviewFingerprint, $nonce );
			$removed                       = $result->targetVerified && $this->migration->cleanup( $source->id, $sourceFingerprint, $result );
			$cleanupPending                = $result->targetVerified && ! $removed;
			$outcome['apply']              = array(
				'result'          => $result,
				'cleanup_pending' => $cleanupPending,
			);
			$outcome['migration_complete'] = ! $cleanupPending && $result->targetVerified && $this->migrationComplete();
			$outcome['message']            = $cleanupPending
				? __( 'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.', 'ran-booster-wp-pusher-migrator' )
				: $result->message;
			$outcome['kind']               = $result->targetVerified && ! $cleanupPending ? 'success' : 'validation_failure';
		} catch ( Throwable $failure ) {
			$outcome['message'] = $this->presenter->failureMessage( $failure ) ?? '';
			$outcome['kind']    = '' === $outcome['message'] ? 'unexpected_failure' : 'validation_failure';
		}
		return $outcome;
	}

	/** @param list<WpPusherPackage> $packages */
	private function package( array $packages, int $sourceId ): WpPusherPackage {
		foreach ( $packages as $package ) {
			if ( $sourceId === $package->id ) {
				return $package;
			}
		}
		throw new RuntimeException( 'The WP Pusher package is no longer available.' );
	}

	private function migrationComplete(): bool {
		try {
			return array() === $this->migration->packages();
		} catch ( Throwable ) {
			return false;
		}
	}

	/** @param array<string, mixed> $request */
	private function requestOperation( array $request ): string {
		return $this->requestValue( $request, 'ran_booster_wp_pusher_migrator_action', true );
	}

	/** @param array<string, mixed> $request */
	private function requestValue( array $request, string $key, bool $sanitizeKey = false ): string {
		if ( ! isset( $request[ $key ] ) || ! is_scalar( $request[ $key ] ) ) {
			return '';
		}
		return $sanitizeKey ? sanitize_key( (string) $request[ $key ] ) : sanitize_text_field( (string) $request[ $key ] );
	}

	/** @param array<string, mixed> $request */
	private function isSubmittedRequest( array $request ): bool {
		return array_key_exists( 'ran_booster_wp_pusher_migrator_action', $request )
			|| ( isset( $request['action'] ) && is_scalar( $request['action'] )
				&& self::ADMIN_POST_ACTION === sanitize_key( (string) $request['action'] ) );
	}

	private function invalidOperation(): never {
		wp_die(
			esc_html__( 'Choose a valid WP Pusher migration action.', 'ran-booster-wp-pusher-migrator' ),
			esc_html__( 'Invalid migration request', 'ran-booster-wp-pusher-migrator' ),
			array( 'response' => 400 )
		);
	}
}

<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionOutcome;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Owns feature hooks, request authority, inventory, operations, and transports.
 *
 * @internal
 */
final readonly class MigrationRequestController {
	private const FORM_ACTION                   = 'ran-booster-wp-pusher-migrator-review-v1';
	private const APPLY_FORM_ACTION             = 'ran-booster-wp-pusher-migrator-apply-v1';
	private const ADMIN_POST_ACTION             = 'ran_booster_wp_pusher_migrator_package';
	private const MAX_INTERACTION_MESSAGE_BYTES = 255;

	public function __construct(
		private MigrationService $migration,
		private WpPusherSource $source,
		private MigrationPresenter $presenter,
		private AdminInteractionFacade&TransporterRowAdminInteractionFacade $admin_interaction
	) {
	}

	public function register(): void {
		add_action( 'ran_booster_portability_render_migration_modes', array( $this, 'render_mode' ), 20 );
		add_action( 'ran_booster_portability_render_migration_flows', array( $this, 'render_panel' ), 20 );
		add_action( 'ran_booster_overview_render_migration_prompt', array( $this, 'render_overview_prompt' ), 20 );
		add_action( 'admin_post_' . self::ADMIN_POST_ACTION, array( $this, 'handle_admin_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	public function render_mode(): void {
		if ( current_user_can( 'manage_options' ) ) {
			$this->presenter->render_mode();
		}
	}

	public function render_panel(): void {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = $this->request_operation( $request );
		$submitted = $this->is_submitted_request( $request );
		if ( $submitted && ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			$this->invalid_operation();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $submitted ) {
			check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );
		}

		$outcome = null;
		try {
			$outcome             = $submitted ? $this->operation_outcome( $operation, $request ) : null;
			$error               = null !== $outcome && 'success' !== $outcome['kind'] && null === $outcome['apply']
				? ( 'unexpected_failure' === $outcome['kind']
					? __( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' )
					: $outcome['message'] )
				: '';
			$inventory           = '' === $error ? $this->migration->inventory() : WpPusherInventory::empty_inventory();
			$option_presence     = '' === $error && $inventory->is_installed() ? $this->source->option_presence() : array();
			$apply               = $outcome['apply'] ?? null;
			$apply_completed_all = null !== $apply
				&& $apply['result']->target_verified
				&& ! $apply['cleanup_pending']
				&& WpPusherInventory::STATUS_EMPTY === $inventory->status;
			$info                = '' === $error && $inventory->is_empty() && ! $apply_completed_all
				? $this->presenter->empty_inventory_message( $inventory )
				: '';
			$this->presenter->render_panel(
				$inventory->entries,
				$outcome['review'] ?? null,
				$apply,
				$option_presence,
				$error,
				$info
			);
		} catch ( Throwable $failure ) {
			$this->presenter->render_panel(
				array(),
				null,
				$outcome['apply'] ?? null,
				array(),
				$this->presenter->source_error_message( $failure )
			);
		}
	}

	public function render_overview_prompt(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		try {
			if ( ! $this->source->supported_package_table_present() ) {
				return;
			}
		} catch ( Throwable ) {
			return;
		}
		$this->presenter->render_overview_prompt();
	}

	public function enqueue_assets( mixed $hook ): void {
		if ( 'toplevel_page_ran-booster' === $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted navigation state.
			$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] )
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted navigation state.
				? sanitize_key( wp_unslash( $_GET['tab'] ) )
				: 'overview';
			if ( ! in_array( $tab, array( 'overview', 'portability' ), true ) ) {
				return;
			}
		} elseif ( 'ran-booster_page_ran-booster-transporter' !== $hook ) {
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

	public function handle_admin_post(): void {
		$request   = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$operation = $this->request_operation( $request );
		if ( ! in_array( $operation, array( 'review', 'apply' ), true ) ) {
			$this->invalid_operation();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to migrate WP Pusher packages.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration unavailable', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 403 )
			);
		}
		check_admin_referer( 'review' === $operation ? self::FORM_ACTION : self::APPLY_FORM_ACTION );

		$outcome = $this->operation_outcome( $operation, $request );
		$source  = $outcome['source'];
		if ( ! $source instanceof WpPusherPackage ) {
			wp_die(
				esc_html__( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' ),
				esc_html__( 'Migration request failed', 'ran-booster-wp-pusher-migrator' ),
				array( 'response' => 409 )
			);
		}

		$request_model       = $this->presenter->interaction_request(
			$source,
			'review' === $operation ? 'check-package' : 'import-package'
		);
		$interaction_outcome = match ( $outcome['kind'] ) {
			'success' => AdminInteractionOutcome::success( $request_model, $outcome['message'] ),
			'validation_failure' => AdminInteractionOutcome::validation_failure(
				$request_model,
				$this->bounded_validation_failure_message( $outcome['message'], $outcome['failure'] )
			),
			default => AdminInteractionOutcome::unexpected_failure( $request_model ),
		};
		$row = null;
		if ( 'success' === $outcome['kind'] ) {
			if ( 'review' === $operation ) {
				$row = $this->presenter->row( $source, $outcome['review'] );
			} else {
				if ( null === $outcome['apply'] ) {
					throw new LogicException( 'A successful migration apply requires its result.' );
				}
				$row = $this->presenter->imported_row(
					$source,
					$outcome['apply']['result'],
					$outcome['migration_complete']
				);
			}
		}

		$this->admin_interaction->respond_with_transporter_row_fragment(
			$interaction_outcome,
			fn ( string $target_element_id ): mixed => is_array( $row )
				? $this->presenter->render_source_row( $row, $target_element_id )
				: null
		);
	}

	/**
	 * @param array<string, mixed> $request Authorized request values.
	 * @return array{kind:'success'|'validation_failure'|'unexpected_failure',message:string,failure:?Throwable,source:WpPusherPackage|null,review:PortabilityReviewResult|null,apply:array{result:PortabilityApplyResult,cleanup_pending:bool,cleanup_error:?string}|null,migration_complete:bool}
	 */
	private function operation_outcome( string $operation, array $request ): array {
		$outcome = array(
			'kind'               => 'success',
			'message'            => '',
			'failure'            => null,
			'source'             => null,
			'review'             => null,
			'apply'              => null,
			'migration_complete' => false,
		);
		try {
			$source             = $this->package( $this->migration->packages(), isset( $request['source_id'] ) ? absint( $request['source_id'] ) : 0 );
			$outcome['source']  = $source;
			$credential_id      = $this->request_value( $request, 'credential_id' );
			$credential_id      = '' === $credential_id ? null : $credential_id;
			$source_fingerprint = $this->request_value( $request, 'source_fingerprint' );
			if ( 'review' === $operation ) {
				$nonce              = wp_create_nonce( $this->migration->nonce_action( 'review', $source, $credential_id ) );
				$outcome['review']  = $this->migration->review( $source->id, $source_fingerprint, $credential_id, $nonce );
				$outcome['message'] = $outcome['review']->message;
				return $outcome;
			}

			$review_fingerprint   = $this->request_value( $request, 'review_fingerprint' );
			$provider_expectation = $this->provider_expectation( $request );
			$nonce                = wp_create_nonce( $this->migration->nonce_action( 'apply', $source, $credential_id, $review_fingerprint, $provider_expectation ) );
			$result               = $this->migration->apply( $source->id, $source_fingerprint, $credential_id, $review_fingerprint, $nonce, $provider_expectation );
			$removed              = false;
			$cleanup_error        = null;
			$cleanup_failure      = null;
			if ( $result->target_verified ) {
				try {
					$removed = $this->migration->cleanup( $source->id, $source_fingerprint, $result );
				} catch ( Throwable $failure ) {
					$removed         = false;
					$cleanup_failure = $failure;
					$cleanup_error   = $this->presenter->failure_message( $failure );
				}
			}
			$cleanup_pending               = $result->target_verified && ! $removed;
			$outcome['apply']              = array(
				'result'          => $result,
				'cleanup_pending' => $cleanup_pending,
				'cleanup_error'   => $cleanup_error,
			);
			$outcome['migration_complete'] = ! $cleanup_pending && $result->target_verified && $this->migration_complete();
			$outcome['message']            = $cleanup_pending
				? $this->cleanup_pending_message( $cleanup_error, $cleanup_failure )
				: $result->message;
			$outcome['kind']               = $result->target_verified && ! $cleanup_pending ? 'success' : 'validation_failure';
		} catch ( Throwable $failure ) {
			$failure_message    = $this->presenter->failure_message( $failure );
			$outcome['failure'] = $failure;
			$outcome['message'] = $failure_message ?? '';
			$outcome['kind']    = null !== $failure_message || $failure instanceof WpPusherSourceException
				? 'validation_failure'
				: 'unexpected_failure';
		}
		return $outcome;
	}

	private function bounded_validation_failure_message( string $message, ?Throwable $failure ): string {
		$database_code = $failure instanceof WpPusherSourceException && $failure->is_database_error
			? $failure->reason
			: null;
		if ( null !== $database_code ) {
			$code_suffix = '(code: ' . $database_code . ')';
			if ( $this->is_bounded_interaction_message( $message ) && str_contains( $message, $code_suffix ) ) {
				return $message;
			}

			$compact_prose = rtrim( trim( __( 'Could not access WP Pusher data', 'ran-booster-wp-pusher-migrator' ) ), ". \t\n\r\0\x0B" );
			$compact       = '' === $compact_prose
				? ''
				: $compact_prose . ' ' . $code_suffix . '.';
			if ( $this->is_bounded_interaction_message( $compact ) ) {
				return $compact;
			}

			return 'Could not access WP Pusher data ' . $code_suffix . '.';
		}

		if ( $this->is_bounded_interaction_message( $message ) ) {
			return $message;
		}

		if ( $failure instanceof WpPusherSourceException ) {
			$fallback = $failure->getMessage();
			if ( $this->is_bounded_interaction_message( $fallback ) ) {
				return $fallback;
			}
		}

		$compact = __( 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' );
		if ( $this->is_bounded_interaction_message( $compact ) ) {
			return $compact;
		}

		return 'Booster could not safely process this WP Pusher package. Reload Transporter and try again.';
	}

	private function cleanup_pending_message( ?string $cleanup_error, ?Throwable $cleanup_failure ): string {
		$cleanup_message = __( 'Booster verified the adopted package, but its exact WP Pusher source record could not be removed. Keep WP Pusher inactive and try again.', 'ran-booster-wp-pusher-migrator' );
		$combined        = null !== $cleanup_error && '' !== $cleanup_error
			? $cleanup_message . ' ' . $cleanup_error
			: $cleanup_message;
		$database_code   = $cleanup_failure instanceof WpPusherSourceException && $cleanup_failure->is_database_error
			? $cleanup_failure->reason
			: null;
		if ( null !== $database_code ) {
			$code_suffix = '(code: ' . $database_code . ')';
			if ( $this->is_bounded_interaction_message( $combined ) && str_contains( $combined, $code_suffix ) ) {
				return $combined;
			}

			$compact_prose = rtrim( trim( __( 'Booster verified the adopted package, but its WP Pusher record remains', 'ran-booster-wp-pusher-migrator' ) ), ". \t\n\r\0\x0B" );
			$compact       = '' === $compact_prose
				? ''
				: $compact_prose . ' ' . $code_suffix . '.';
			if ( $this->is_bounded_interaction_message( $compact ) ) {
				return $compact;
			}

			return 'Booster verified the adopted package, but its WP Pusher record remains ' . $code_suffix . '.';
		}

		if ( $this->is_bounded_interaction_message( $combined ) ) {
			return $combined;
		}

		$compact = __( 'Booster verified the adopted package, but its WP Pusher record remains.', 'ran-booster-wp-pusher-migrator' );
		if ( $this->is_bounded_interaction_message( $compact ) ) {
			return $compact;
		}

		return 'Booster verified the adopted package, but its WP Pusher record remains.';
	}

	private function is_bounded_interaction_message( string $message ): bool {
		return '' !== trim( $message )
			&& strlen( $message ) <= self::MAX_INTERACTION_MESSAGE_BYTES
			&& 1 === preg_match( '//u', $message )
			&& 1 !== preg_match( '/[\x00-\x1F\x7F]/', $message );
	}

	/**
	 * Decode only the bounded transport; Core validates the selection contract.
	 *
	 * @param array<string,mixed> $request Submitted fields.
	 * @return array<string,mixed>|null
	 */
	private function provider_expectation( array $request ): ?array {
		$value = $request['provider_expectation'] ?? null;
		if ( ! is_string( $value ) || strlen( $value ) > 1024 ) {
			throw new RuntimeException( 'Refresh the WP Pusher migration review.' );
		}
		$decoded = json_decode( $value, false, 4 );
		if ( JSON_ERROR_NONE !== json_last_error() || ( null !== $decoded && ! $decoded instanceof \stdClass ) ) {
			throw new RuntimeException( 'Refresh the WP Pusher migration review.' );
		}
		return null === $decoded ? null : get_object_vars( $decoded );
	}

	/** @param list<WpPusherPackage> $packages */
	private function package( array $packages, int $source_id ): WpPusherPackage {
		foreach ( $packages as $package ) {
			if ( $source_id === $package->id ) {
				return $package;
			}
		}
		throw new RuntimeException( 'The WP Pusher package is no longer available.' );
	}

	private function migration_complete(): bool {
		try {
			return WpPusherInventory::STATUS_EMPTY === $this->migration->inventory()->status;
		} catch ( Throwable ) {
			return false;
		}
	}

	/** @param array<string, mixed> $request */
	private function request_operation( array $request ): string {
		return $this->request_value( $request, 'ran_booster_wp_pusher_migrator_action', true );
	}

	/** @param array<string, mixed> $request */
	private function request_value( array $request, string $key, bool $sanitize_key = false ): string {
		if ( ! isset( $request[ $key ] ) || ! is_scalar( $request[ $key ] ) ) {
			return '';
		}
		return $sanitize_key ? sanitize_key( (string) $request[ $key ] ) : sanitize_text_field( (string) $request[ $key ] );
	}

	/** @param array<string, mixed> $request */
	private function is_submitted_request( array $request ): bool {
		return array_key_exists( 'ran_booster_wp_pusher_migrator_action', $request )
			|| ( isset( $request['action'] ) && is_scalar( $request['action'] )
				&& self::ADMIN_POST_ACTION === sanitize_key( (string) $request['action'] ) );
	}

	private function invalid_operation(): never {
		wp_die(
			esc_html__( 'Choose a valid WP Pusher migration action.', 'ran-booster-wp-pusher-migrator' ),
			esc_html__( 'Invalid migration request', 'ran-booster-wp-pusher-migrator' ),
			array( 'response' => 400 )
		);
	}
}

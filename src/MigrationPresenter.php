<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityReviewResult;
use RAN\Admin\Interaction\AdminInteractionFacade;
use RAN\Admin\Interaction\AdminInteractionRequest;
use RAN\Admin\Interaction\TransporterRowAdminInteractionFacade;
use RuntimeException;
use Throwable;

/**
 * Builds passive view models and renders migration-owned presentation.
 *
 * @internal
 */
final readonly class MigrationPresenter {
	private const FORM_ACTION       = 'ran-booster-wp-pusher-migrator-review-v1';
	private const APPLY_FORM_ACTION = 'ran-booster-wp-pusher-migrator-apply-v1';
	private const ADMIN_POST_ACTION = 'ran_booster_wp_pusher_migrator_package';

	public function __construct(
		private CandidateFactory $candidates,
		private AdminInteractionFacade&TransporterRowAdminInteractionFacade $adminInteraction
	) {
	}

	public function renderMode(): void {
		require dirname( __DIR__ ) . '/views/migration-mode.php';
	}

	/**
	 * @param list<WpPusherPackage> $packages
	 * @param array{result:PortabilityApplyResult,cleanup_pending:bool}|null $apply
	 * @param array<string, bool> $optionPresence
	 */
	public function renderPanel(
		array $packages,
		?PortabilityReviewResult $review,
		?array $apply,
		array $optionPresence,
		string $error
	): void {
		$rows              = $this->rows( $packages, $review );
		$hasError          = '' !== $error;
		$legacyDataPresent = ! $hasError && in_array( true, $optionPresence, true );
		$applyVisible      = null !== $apply;
		$applyClass        = $applyVisible && $apply['result']->targetVerified ? 'notice-success' : 'notice-error';
		$applyMessage      = $apply['result']->message ?? '';
		$cleanupPending    = $apply['cleanup_pending'] ?? false;
		$completionVisible = array() === $rows;
		$adminInteraction  = $this->adminInteraction;
		$pluginsUrl        = admin_url( 'plugins.php' );
		require dirname( __DIR__ ) . '/views/source-card.php';
	}

	public function renderOverviewPrompt(): void {
		$migrationUrl = $this->migrationUrl();
		require dirname( __DIR__ ) . '/views/overview-prompt.php';
	}

	/**
	 * @param list<WpPusherPackage> $packages
	 * @return list<array<string, mixed>>
	 */
	private function rows( array $packages, ?PortabilityReviewResult $review ): array {
		$rows = array();
		foreach ( $packages as $source ) {
			$rows[] = $this->row( $source, null !== $review && $review->candidate->identifier === $source->package ? $review : null );
		}
		return $rows;
	}

	/** @return array<string, mixed> */
	public function row( WpPusherPackage $source, ?PortabilityReviewResult $review ): array {
		$candidate = null;
		$error     = '';
		try {
			$candidate = $this->candidates->candidate( $source, 1 === $source->private ? 'credential_required' : null );
		} catch ( Throwable $failure ) {
			$error = $this->failureMessage( $failure ) ?? __( 'This retained package is unsupported.', 'ran-booster-wp-pusher-migrator' );
		}
		$actionable = null !== $review && in_array( $review->action, array( 'adopt', 'managed' ), true );
		$managed    = null !== $review && 'managed' === $review->action;
		$check      = $this->interactionRequest( $source, 'check-package' );
		$import     = $this->interactionRequest( $source, 'import-package' );

		$statusHeading = '';
		$statusMessage = '';
		$statusStrong  = true;
		if ( '' !== $error ) {
			$statusHeading = __( 'Cannot adopt', 'ran-booster-wp-pusher-migrator' );
			$statusMessage = $error;
		} elseif ( $managed ) {
			$statusHeading = __( 'Adoption incomplete', 'ran-booster-wp-pusher-migrator' );
			$statusMessage = __( 'Booster manages this package; a WP Pusher record remains.', 'ran-booster-wp-pusher-migrator' );
		} elseif ( $actionable ) {
			$statusHeading = __( 'Ready to adopt', 'ran-booster-wp-pusher-migrator' );
		} elseif ( null !== $review ) {
			$statusHeading = __( 'Cannot adopt', 'ran-booster-wp-pusher-migrator' );
			$statusMessage = $review->message;
		} else {
			$statusHeading = __( 'Ready to check', 'ran-booster-wp-pusher-migrator' );
			$statusStrong  = false;
		}

		$action = null === $candidate ? 'none' : ( $actionable ? 'apply' : 'check' );
		return array(
			'package'            => $source->package,
			'repository'         => $source->repository,
			'migration_url'      => $this->migrationUrl(),
			'form_action'        => self::FORM_ACTION,
			'apply_form_action'  => self::APPLY_FORM_ACTION,
			'admin_post_action'  => self::ADMIN_POST_ACTION,
			'source_id'          => $source->id,
			'source_fingerprint' => $source->fingerprint(),
			'private'            => 1 === $source->private,
			'review_fingerprint' => $review->fingerprint ?? '',
			'credential_id'      => $review->candidate->credentialId ?? '',
			'migration_complete' => false,
			'status_heading'     => $statusHeading,
			'status_message'     => $statusMessage,
			'status_strong'      => $statusStrong,
			'action'             => $action,
			'action_label'       => $managed ? __( 'Finish', 'ran-booster-wp-pusher-migrator' ) : __( 'Adopt', 'ran-booster-wp-pusher-migrator' ),
			'manage_url'         => '',
			'manage_label'       => '',
			'form_request'       => 'check' === $action ? $check : ( 'apply' === $action ? $import : null ),
			'target_element_id'  => $check->targetElementId(),
			'error_region_id'    => $this->errorRegionId( $source ),
		);
	}

	/** @return array<string, mixed> */
	public function importedRow( WpPusherPackage $source, PortabilityApplyResult $result, bool $migrationComplete ): array {
		$row                       = $this->row( $source, null );
		$row['migration_complete'] = $migrationComplete;
		$row['status_heading']     = 'adopted' === $result->status ? __( 'Adopted', 'ran-booster-wp-pusher-migrator' ) : __( 'Adoption verified', 'ran-booster-wp-pusher-migrator' );
		$row['status_message']     = '';
		$row['status_strong']      = true;
		$row['action']             = 'manage';
		$row['form_request']       = null;
		$row['manage_url']         = admin_url( 'admin.php?page=' . ( 1 === $source->type ? 'ran-booster-plugins' : 'ran-booster-themes' ) . '&package=' . rawurlencode( $source->package ) );
		$row['manage_label']       = __( 'Settings', 'ran-booster-wp-pusher-migrator' );
		return $row;
	}

	/** @param array<string, mixed> $row */
	public function renderSourceRow( array $row, string $targetElementId ): void {
		$row['target_element_id'] = $targetElementId;
		$adminInteraction         = $this->adminInteraction;
		require dirname( __DIR__ ) . '/views/source-row.php';
	}

	public function interactionRequest( WpPusherPackage $source, string $operation ): AdminInteractionRequest {
		$identityHash = substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );
		return AdminInteractionRequest::transporterMigrationSourceRow(
			'wp-pusher:' . $operation,
			'wp-pusher:package-' . $identityHash,
			$this->migrationUrl(),
			$this->errorRegionId( $source )
		);
	}

	private function errorRegionId( WpPusherPackage $source ): string {
		return 'ran-booster-wp-pusher-row-error-' . substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 32 );
	}

	private function migrationUrl(): string {
		return admin_url( 'admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher' );
	}

	public function failureMessage( Throwable $failure ): ?string {
		if ( ! $failure instanceof RuntimeException ) {
			return null;
		}
		return in_array(
			$failure->getMessage(),
			array(
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
			),
			true
		) ? $failure->getMessage() : null;
	}
}

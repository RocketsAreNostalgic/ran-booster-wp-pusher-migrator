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
		private AdminInteractionFacade&TransporterRowAdminInteractionFacade $admin_interaction
	) {
	}

	public function render_mode(): void {
		require dirname( __DIR__ ) . '/views/migration-mode.php';
	}

	/**
	 * @param list<WpPusherPackage|WpPusherRejectedPackage>                 $entries
	 * @param array{result:PortabilityApplyResult,cleanup_pending:bool}|null $apply
	 * @param array<string, bool>                                           $option_presence
	 */
	public function render_panel(
		array $entries,
		?PortabilityReviewResult $review,
		?array $apply,
		array $option_presence,
		string $error,
		string $info = ''
	): void {
		$rows                = $this->rows( $entries, $review );
		$has_error           = '' !== $error;
		$has_info            = ! $has_error && '' !== $info;
		$legacy_data_present = ! $has_error && ! $has_info && in_array( true, $option_presence, true );
		$apply_visible       = null !== $apply;
		$apply_class         = $apply_visible && $apply['result']->target_verified ? 'notice-success' : 'notice-error';
		$apply_message       = $apply['result']->message ?? '';
		$cleanup_pending     = $apply['cleanup_pending'] ?? false;
		$completion_visible  = ! $has_error && ! $has_info && array() === $rows;
		$admin_interaction   = $this->admin_interaction;
		$plugins_url         = admin_url( 'plugins.php' );
		require dirname( __DIR__ ) . '/views/source-card.php';
	}

	public function render_overview_prompt(): void {
		$migration_url = $this->migration_url();
		require dirname( __DIR__ ) . '/views/overview-prompt.php';
	}

	public function empty_inventory_message( WpPusherInventory $inventory ): string {
		if ( WpPusherInventory::STATUS_NOT_INSTALLED === $inventory->status ) {
			return __( 'No WP Pusher packages were detected. WP Pusher is not installed on this site.', 'ran-booster-wp-pusher-migrator' );
		}

		return __( 'No WP Pusher packages were detected.', 'ran-booster-wp-pusher-migrator' );
	}

	public function source_error_message( Throwable $failure ): string {
		if ( ! $failure instanceof WpPusherSourceException ) {
			return __( 'Booster could not read this WP Pusher installation safely. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' );
		}

		return match ( $failure->reason ) {
			WpPusherSourceException::REASON_MULTISITE => __( 'WP Pusher migration supports single-site WordPress only.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_INVALID_PLUGIN_METADATA => __( 'WP Pusher is installed, but its version metadata is missing or invalid.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_PLUGIN_ACTIVE => __( 'WP Pusher is still active. Deactivate WP Pusher before migrating retained packages.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_UNSUPPORTED_SCHEMA => __( 'The retained WP Pusher package table schema is not supported.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_INVENTORY_LIMIT_EXCEEDED => __( 'The retained WP Pusher package table contains more than 128 packages, which is not supported.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DUPLICATE_PACKAGES => __( 'The retained WP Pusher package table contains duplicate package records.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_MALFORMED_PACKAGE_ROW => __( 'The retained WP Pusher package table contains a malformed package record.', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_PREFIX_INVALID => __( 'Could not read WP Pusher data: the WordPress database prefix is unavailable or invalid (code: db_prefix_invalid).', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_TABLE_READER_UNAVAILABLE => __( 'Could not read WP Pusher data: the database table reader is unavailable (code: db_table_reader_unavailable).', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_SCHEMA_READER_UNAVAILABLE => __( 'Could not read WP Pusher data: the database schema reader is unavailable (code: db_schema_reader_unavailable).', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_READER_UNAVAILABLE => __( 'Could not read WP Pusher data: the database package reader is unavailable (code: db_reader_unavailable).', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_OPTION_READER_UNAVAILABLE => __( 'Could not read WP Pusher settings: the database option reader is unavailable (code: db_option_reader_unavailable).', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_CLEANUP_UNAVAILABLE => __( 'Could not clean up the WP Pusher record: the database writer is unavailable (code: db_cleanup_unavailable).', 'ran-booster-wp-pusher-migrator' ),
			WpPusherSourceException::REASON_DB_QUERY_FAILED => __( 'Could not access WP Pusher data: a database query failed (code: db_query_failed).', 'ran-booster-wp-pusher-migrator' ),
			default => __( 'Booster could not read this WP Pusher installation safely. Reload Transporter and try again.', 'ran-booster-wp-pusher-migrator' ),
		};
	}

	/**
	 * @param list<WpPusherPackage|WpPusherRejectedPackage> $entries
	 * @return list<array<string, mixed>>
	 */
	private function rows( array $entries, ?PortabilityReviewResult $review ): array {
		$rows = array();
		foreach ( $entries as $entry ) {
			if ( $entry instanceof WpPusherRejectedPackage ) {
				$rows[] = $this->rejected_row( $entry );
				continue;
			}
			$rows[] = $this->row( $entry, null !== $review && $review->candidate->identifier === $entry->package ? $review : null );
		}
		return $rows;
	}

	/** @return array<string, mixed> */
	public function rejected_row( WpPusherRejectedPackage $rejected ): array {
		$status_message = match ( $rejected->reason ) {
			WpPusherRejectedPackage::REASON_INVALID_SUBDIRECTORY => sprintf(
				/* translators: %s: stored WP Pusher subdirectory value. */
				__( "Can't migrate: subdirectory \"%s\" isn't a plain relative path.", 'ran-booster-wp-pusher-migrator' ),
				$rejected->display_subdirectory()
			),
			default => __( 'This retained package is unsupported.', 'ran-booster-wp-pusher-migrator' ),
		};

		return array(
			'package'              => $rejected->package,
			'repository'           => $rejected->repository,
			'migration_url'        => $this->migration_url(),
			'form_action'          => self::FORM_ACTION,
			'apply_form_action'    => self::APPLY_FORM_ACTION,
			'admin_post_action'    => self::ADMIN_POST_ACTION,
			'source_id'            => $rejected->id,
			'source_fingerprint'   => '',
			'private'              => false,
			'review_fingerprint'   => '',
			'provider_expectation' => 'null',
			'credential_id'        => '',
			'migration_complete'   => false,
			'status_heading'       => __( 'Cannot adopt', 'ran-booster-wp-pusher-migrator' ),
			'status_message'       => $status_message,
			'status_strong'        => true,
			'action'               => 'none',
			'action_label'         => '',
			'manage_url'           => '',
			'manage_label'         => '',
			'form_request'         => null,
			'target_element_id'    => '',
			'error_region_id'      => $this->error_region_id_for_identity( $rejected->type, $rejected->package ),
		);
	}

	/** @return array<string, mixed> */
	public function row( WpPusherPackage $source, ?PortabilityReviewResult $review ): array {
		$candidate = null;
		$error     = '';
		try {
			$candidate = $this->candidates->candidate( $source, 1 === $source->is_private ? 'credential_required' : null );
		} catch ( Throwable $failure ) {
			$error = $this->failure_message( $failure ) ?? __( 'This retained package is unsupported.', 'ran-booster-wp-pusher-migrator' );
		}
		$actionable = null !== $review && in_array( $review->action, array( 'adopt', 'managed' ), true );
		$managed    = null !== $review && 'managed' === $review->action;
		$check      = $this->interaction_request( $source, 'check-package' );
		$import     = $this->interaction_request( $source, 'import-package' );

		$status_heading = '';
		$status_message = '';
		$status_strong  = true;
		if ( '' !== $error ) {
			$status_heading = __( 'Cannot adopt', 'ran-booster-wp-pusher-migrator' );
			$status_message = $error;
		} elseif ( $managed ) {
			$status_heading = __( 'Adoption incomplete', 'ran-booster-wp-pusher-migrator' );
			$status_message = __( 'Booster manages this package; a WP Pusher record remains.', 'ran-booster-wp-pusher-migrator' );
		} elseif ( $actionable ) {
			$status_heading = __( 'Ready to adopt', 'ran-booster-wp-pusher-migrator' );
		} elseif ( null !== $review ) {
			$status_heading = __( 'Cannot adopt', 'ran-booster-wp-pusher-migrator' );
			$status_message = $review->message;
		} else {
			$status_heading = __( 'Ready to check', 'ran-booster-wp-pusher-migrator' );
			$status_strong  = false;
		}

		$action = null === $candidate ? 'none' : ( $actionable ? 'apply' : 'check' );
		return array(
			'package'              => $source->package,
			'repository'           => $source->repository,
			'migration_url'        => $this->migration_url(),
			'form_action'          => self::FORM_ACTION,
			'apply_form_action'    => self::APPLY_FORM_ACTION,
			'admin_post_action'    => self::ADMIN_POST_ACTION,
			'source_id'            => $source->id,
			'source_fingerprint'   => $source->fingerprint(),
			'private'              => 1 === $source->is_private,
			'review_fingerprint'   => $review->fingerprint ?? '',
			'provider_expectation' => wp_json_encode( $review->provider_expectation ?? null ),
			'credential_id'        => $review->candidate->credential_id ?? '',
			'migration_complete'   => false,
			'status_heading'       => $status_heading,
			'status_message'       => $status_message,
			'status_strong'        => $status_strong,
			'action'               => $action,
			'action_label'         => $managed ? __( 'Finish', 'ran-booster-wp-pusher-migrator' ) : __( 'Adopt', 'ran-booster-wp-pusher-migrator' ),
			'manage_url'           => '',
			'manage_label'         => '',
			'form_request'         => 'check' === $action ? $check : ( 'apply' === $action ? $import : null ),
			'target_element_id'    => $check->target_element_id(),
			'error_region_id'      => $this->error_region_id( $source ),
		);
	}

	/** @return array<string, mixed> */
	public function imported_row( WpPusherPackage $source, PortabilityApplyResult $result, bool $migration_complete ): array {
		$row                       = $this->row( $source, null );
		$row['migration_complete'] = $migration_complete;
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
	public function render_source_row( array $row, string $target_element_id ): void {
		$row['target_element_id'] = $target_element_id;
		$admin_interaction        = $this->admin_interaction;
		require dirname( __DIR__ ) . '/views/source-row.php';
	}

	public function interaction_request( WpPusherPackage $source, string $operation ): AdminInteractionRequest {
		$identity_hash = substr( hash( 'sha256', $source->type . ':' . $source->package ), 0, 40 );
		return AdminInteractionRequest::transporter_migration_source_row(
			'wp-pusher:' . $operation,
			'wp-pusher:package-' . $identity_hash,
			$this->migration_url(),
			$this->error_region_id( $source )
		);
	}

	private function error_region_id( WpPusherPackage $source ): string {
		return $this->error_region_id_for_identity( $source->type, $source->package );
	}

	private function error_region_id_for_identity( int $type, string $package ): string {
		return 'ran-booster-wp-pusher-row-error-' . substr( hash( 'sha256', $type . ':' . $package ), 0, 32 );
	}

	private function migration_url(): string {
		return admin_url( 'admin.php?page=ran-booster&tab=portability#ran-booster-portability-wp-pusher' );
	}

	public function failure_message( Throwable $failure ): ?string {
		if ( $failure instanceof WpPusherSourceException ) {
			return $this->source_error_message( $failure );
		}
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
			),
			true
		) ? $failure->getMessage() : null;
	}
}

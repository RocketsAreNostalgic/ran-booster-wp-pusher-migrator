<?php

declare(strict_types=1);

namespace RAN\BoosterWpPusherMigrator;

use RAN\AddOn\Logging\LoggingFacade;
use RAN\AddOn\Portability\PortabilityApplyResult;
use RAN\AddOn\Portability\PortabilityFacade;
use RAN\AddOn\Portability\PortabilityReviewResult;
use Throwable;

/** WordPress hooks and request-local migration presentation. */
final class Plugin {

	private const FORM_ACTION       = 'ran-booster-wp-pusher-migrator-review-v1';
	private const APPLY_FORM_ACTION = 'ran-booster-wp-pusher-migrator-apply-v1';

	private static ?MigrationService $migration = null;

	public static function register(): void {
		add_action( 'ran_booster_portability_ready', array( self::class, 'connect' ), 10, 2 );
		add_action( 'ran_booster_portability_render_guidance', array( self::class, 'render' ), 20 );
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

		self::$migration = new MigrationService(
			new WpPusherSource(),
			new CandidateFactory(),
			$portability
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( null === self::$migration ) {
			self::notice( __( 'The WP Pusher migrator requires compatible RAN Booster Portability API 1 and Logging API 1.', 'ran-booster-wp-pusher-migrator' ), 'error' );
			return;
		}

		try {
			$packages        = self::$migration->packages();
			$optionPresence  = ( new WpPusherSource() )->optionPresence();
			$review          = self::submittedReview( $packages );
			$apply           = self::submittedApply( $packages );
			$rows            = self::rows( $packages, $review );
			$formAction      = self::FORM_ACTION;
			$applyFormAction = self::APPLY_FORM_ACTION;
			require dirname( __DIR__ ) . '/views/source-card.php';
		} catch ( Throwable ) {
			self::notice(
				__( 'The retained WP Pusher installation could not be assessed safely. Confirm exact version 3.0.13, deactivate it, and review its package table before retrying.', 'ran-booster-wp-pusher-migrator' ),
				'error'
			);
		}
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 */
	private static function submittedReview( array $packages ): ?PortabilityReviewResult {
		$operation = isset( $_POST['ran_booster_wp_pusher_migrator_action'] ) && is_scalar( $_POST['ran_booster_wp_pusher_migrator_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['ran_booster_wp_pusher_migrator_action'] ) )
			: '';
		if ( 'review' !== $operation ) {
			return null;
		}
		check_admin_referer( self::FORM_ACTION );

		$sourceId     = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$source       = self::package( $packages, $sourceId );
		$expected     = isset( $_POST['source_fingerprint'] ) && is_scalar( $_POST['source_fingerprint'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['source_fingerprint'] ) )
			: '';
		$credentialId = isset( $_POST['credential_id'] ) && is_scalar( $_POST['credential_id'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['credential_id'] ) )
			: '';
		$credentialId = '' === $credentialId ? null : $credentialId;

		$coreAction = self::$migration->nonceAction( 'review', $source, $credentialId );
		$coreNonce  = wp_create_nonce( $coreAction );

		return self::$migration->review( $sourceId, $expected, $credentialId, $coreNonce );
	}

	/**
	 * @param list<WpPusherPackage> $packages Current exact source rows.
	 */
	private static function submittedApply( array $packages ): ?PortabilityApplyResult {
		$operation = isset( $_POST['ran_booster_wp_pusher_migrator_action'] ) && is_scalar( $_POST['ran_booster_wp_pusher_migrator_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['ran_booster_wp_pusher_migrator_action'] ) )
			: '';
		if ( 'apply' !== $operation ) {
			return null;
		}
		check_admin_referer( self::APPLY_FORM_ACTION );

		$sourceId       = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$source         = self::package( $packages, $sourceId );
		$expectedSource = isset( $_POST['source_fingerprint'] ) && is_scalar( $_POST['source_fingerprint'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['source_fingerprint'] ) )
			: '';
		$expectedReview = isset( $_POST['review_fingerprint'] ) && is_scalar( $_POST['review_fingerprint'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['review_fingerprint'] ) )
			: '';
		$credentialId   = isset( $_POST['credential_id'] ) && is_scalar( $_POST['credential_id'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['credential_id'] ) )
			: '';
		$credentialId   = '' === $credentialId ? null : $credentialId;
		$coreAction     = self::$migration->nonceAction( 'apply', $source, $credentialId, $expectedReview );
		$coreNonce      = wp_create_nonce( $coreAction );

		return self::$migration->apply(
			$sourceId,
			$expectedSource,
			$credentialId,
			$expectedReview,
			$coreNonce
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
	 * @return list<array{source:WpPusherPackage,candidate:array<string,mixed>|null,error:string,review:PortabilityReviewResult|null}>
	 */
	private static function rows( array $packages, ?PortabilityReviewResult $review ): array {
		$rows = array();
		foreach ( $packages as $source ) {
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
			$rows[] = array(
				'source'    => $source,
				'candidate' => $candidate,
				'error'     => $error,
				'review'    => null !== $review && $review->candidate->identifier === $source->package ? $review : null,
			);
		}

		return $rows;
	}

	private static function safeSourceError( Throwable $failure ): string {
		$message = $failure->getMessage();
		if ( '' !== $message
			&& strlen( $message ) <= 255
			&& 1 === preg_match( '//u', $message )
			&& 0 === preg_match( '/[\x00-\x1F\x7F]/', $message ) ) {
			return $message;
		}

		return __( 'This retained package is unsupported.', 'ran-booster-wp-pusher-migrator' );
	}

	private static function notice( string $message, string $type ): void {
		printf(
			'<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
